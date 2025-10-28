<?php

declare(strict_types=1);

namespace Fawaz\Middleware;

use Fawaz\Services\JWTService;
use Fawaz\Utils\PeerLoggerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

class UserLockMiddleware implements MiddlewareInterface
{
    private JWTService $jwtService;
    private PeerLoggerInterface $logger;
    private string $lockDir;
    private int $timeoutSeconds;

    public function __construct(JWTService $jwtService, PeerLoggerInterface $logger, string $lockDir, int $timeoutSeconds = 30)
    {
        $this->jwtService = $jwtService;
        $this->logger = $logger;
        $this->lockDir = rtrim($lockDir, '/\\');
        $this->timeoutSeconds = $timeoutSeconds;
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        // Apply locking only for GraphQL endpoint
        $path = $request->getUri()->getPath();
        if ($path !== '/graphql') {
            return $handler->handle($request);
        }

        $authorizationHeader = $request->getHeader('Authorization');
        $bearerToken = null;
        if (!empty($authorizationHeader)) {
            $parts = explode(' ', $authorizationHeader[0]);
            if (count($parts) === 2 && strtolower($parts[0]) === 'bearer') {
                $bearerToken = $parts[1];
            }
        }

        // If no bearer token, skip locking (guest schema is allowed concurrently)
        if ($bearerToken === null || $bearerToken === '') {
            return $handler->handle($request);
        }

        // Decode token to get user id. If invalid, skip locking and let downstream handle error
        try {
            $decoded = $this->jwtService->validateToken($bearerToken);
            $userId = isset($decoded->uid) ? (string)$decoded->uid : null;
        } catch (\Throwable $e) {
            $this->logger->debug('UserLockMiddleware: token invalid, skipping lock');
            $userId = null;
        }

        if ($userId === null || $userId === '') {
            return $handler->handle($request);
        }

        // Ensure lock directory exists
        if (!is_dir($this->lockDir)) {
            @mkdir($this->lockDir, 0775, true);
        }

        // Sanitize and derive lock file path
        $safeUserId = preg_replace('/[^A-Za-z0-9_-]/', '_', $userId);
        $lockFile = $this->lockDir . DIRECTORY_SEPARATOR . 'user_' . $safeUserId . '.lock';

        $fp = @fopen($lockFile, 'c');
        if ($fp === false) {
            // If lock file cannot be opened, log and proceed without locking
            $this->logger->error('UserLockMiddleware: cannot open lock file', ['file' => $lockFile]);
            return $handler->handle($request);
        }

        $acquired = false;
        $start = time();
        // Try to acquire exclusive lock with timeout by retrying non-blocking
        do {
            $acquired = flock($fp, LOCK_EX | LOCK_NB);
            if ($acquired) {
                break;
            }
            usleep(100_000); // 100ms
        } while ((time() - $start) < $this->timeoutSeconds);

        if (!$acquired) {
            // Optional: block indefinitely as last resort
            $this->logger->warning('UserLockMiddleware: lock timeout reached, blocking until available', ['userId' => $safeUserId]);
            flock($fp, LOCK_EX); // Block until lock acquired
        }

        $this->logger->debug('UserLockMiddleware: acquired lock', ['userId' => $safeUserId]);

        try {
            return $handler->handle($request);
        } finally {
            // Always release the lock
            try {
                flock($fp, LOCK_UN);
            } catch (\Throwable $e) {
                // ignore
            }
            fclose($fp);
            $this->logger->debug('UserLockMiddleware: released lock', ['userId' => $safeUserId]);
        }
    }
}

