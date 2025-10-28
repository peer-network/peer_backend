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

        // Determine lock key: prefer authenticated user id, otherwise fall back to client IP
        $lockKey = null;
        $lockKeyType = null;

        if ($bearerToken !== null && $bearerToken !== '') {
            try {
                $decoded = $this->jwtService->validateToken($bearerToken);
                if (isset($decoded->uid) && $decoded->uid !== '') {
                    $lockKey = (string)$decoded->uid;
                    $lockKeyType = 'user';
                }
            } catch (\Throwable $e) {
                // ignore, will try IP below
                $this->logger->debug('UserLockMiddleware: token invalid, will use IP lock');
            }
        }

        if ($lockKey === null) {
            $ip = $this->getClientIp($request);
            if ($ip !== null) {
                $lockKey = $ip;
                $lockKeyType = 'ip';
            }
        }

        // If we still do not have a key, proceed without locking
        // if ($lockKey === null) {
        //     return $handler->handle($request);
        // }

        // Ensure lock directory exists
        if (!is_dir($this->lockDir)) {
            @mkdir($this->lockDir, 0775, true);
        }

        // Sanitize and derive lock file path
        $safeKey = preg_replace('/[^A-Za-z0-9_\-\.]/', '_', $lockKey);
        $prefix = $lockKeyType === 'user' ? 'user_' : 'guest_';
        $lockFile = $this->lockDir . DIRECTORY_SEPARATOR . $prefix . $safeKey . '.lock';

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
            $this->logger->warning('UserLockMiddleware: lock timeout reached, blocking until available', ['key' => $safeKey, 'type' => $lockKeyType]);
            flock($fp, LOCK_EX); // Block until lock acquired
        }

        $this->logger->debug('UserLockMiddleware: acquired lock', ['key' => $safeKey, 'type' => $lockKeyType]);

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
            // Best-effort cleanup of the lock file to avoid stale files
            try {
                @unlink($lockFile);
            } catch (\Throwable $e) {
                // ignore
            }
            $this->logger->debug('UserLockMiddleware: released lock', ['key' => $safeKey, 'type' => $lockKeyType]);
        }
    }

    private function getClientIp(ServerRequestInterface $request): ?string
    {
        // Prefer X-Forwarded-For (first IP), then X-Real-IP, then REMOTE_ADDR
        $headers = $request->getHeaders();
        $ip = null;

        if (!empty($headers['X-Forwarded-For'][0])) {
            $forwarded = explode(',', $headers['X-Forwarded-For'][0]);
            $candidate = trim($forwarded[0]);
            if (filter_var($candidate, FILTER_VALIDATE_IP)) {
                $ip = $candidate;
            }
        }

        if ($ip === null && !empty($headers['X-Real-IP'][0]) && filter_var($headers['X-Real-IP'][0], FILTER_VALIDATE_IP)) {
            $ip = $headers['X-Real-IP'][0];
        }

        if ($ip === null) {
            $serverParams = $request->getServerParams();
            $candidate = $serverParams['REMOTE_ADDR'] ?? null;
            if (is_string($candidate) && filter_var($candidate, FILTER_VALIDATE_IP)) {
                $ip = $candidate;
            }
        }

        return $ip;
    }
}
