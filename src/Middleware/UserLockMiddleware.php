<?php

declare(strict_types=1);

namespace Fawaz\Middleware;

use Fawaz\Services\JWTService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

class UserLockMiddleware implements MiddlewareInterface
{
    private JWTService $jwtService;
    private string $lockDir = __DIR__ . '/../../runtime-data/locks';
    private int $timeoutSeconds = 30; // adjust if needed

    /**
     * Only lock these mutation field names.
     * Extend this list if more write operations need serialization.
     */
    private array $lockedMutations = [
        'resolveTransferV2',
        'createPost',
        'createComment',
    ];

    public function __construct(JWTService $jwtService)
    {
        $this->jwtService = $jwtService;
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        // Apply locking only for GraphQL endpoint
        $path = $request->getUri()->getPath();
        if ($path !== '/graphql') {
            return $handler->handle($request);
        }
        // Lock only for selected mutation operations
        $queryString = '';
        $parsedBody = $request->getParsedBody();
        if (is_array($parsedBody) && isset($parsedBody['query']) && is_string($parsedBody['query'])) {
            $queryString = $parsedBody['query'];
        } else {
            // Fallbacks when the body parser doesn't populate parsed body
            // 1) Try query param
            $queryParams = $request->getQueryParams();
            if (isset($queryParams['query']) && is_string($queryParams['query'])) {
                $queryString = $queryParams['query'];
            } else {
                // 2) Try raw body (JSON single/batch or application/graphql)
                $raw = '';
                try {
                    $raw = (string)$request->getBody();
                } catch (\Throwable $e) {
                    $raw = '';
                }

                $decoded = null;
                if ($raw !== '') {
                    $decoded = json_decode($raw, true);
                }

                if (is_array($decoded)) {
                    if (isset($decoded['query']) && is_string($decoded['query'])) {
                        $queryString = $decoded['query'];
                    } else {
                        $collected = [];
                        foreach ($decoded as $item) {
                            if (is_array($item) && isset($item['query']) && is_string($item['query'])) {
                                $collected[] = $item['query'];
                            }
                        }
                        if (!empty($collected)) {
                            $queryString = implode("\n\n", $collected);
                        }
                    }
                }

                if ($queryString === '' && !empty($raw) && $raw !== '') {
                    $contentType = $request->getHeaderLine('Content-Type');
                    if (stripos($contentType, 'application/graphql') !== false || stripos($raw, 'mutation') !== false) {
                        $queryString = $raw;
                    }
                }

                // Rewind body for downstream consumers
                try {
                    $request->getBody()->rewind();
                } catch (\Throwable $e) {
                    // ignore
                }
            }
        }


        $shouldLock = false;
        if (!empty($queryString) && $queryString !== '') {
            // Check if it's a mutation and contains one of the locked field names
            if (stripos($queryString, 'mutation') !== false) {
                $escaped = array_map(static fn($s) => preg_quote($s, '/'), $this->lockedMutations);
                $pattern = '/\\b(' . implode('|', $escaped) . ')\\b/i';
                if (preg_match($pattern, $queryString) === 1) {
                    $shouldLock = true;
                }
            }
        }

        if ($shouldLock === false) {
            return $handler->handle($request);
        }

        // Read Authorization header line (keeps compatibility as requested)
        $authorizationLine = $request->getHeaderLine('Authorization');
        $bearerToken = null;
        if ($authorizationLine !== '') {
            $parts = explode(' ', $authorizationLine, 2);
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
            }
        }

        // If we still do not have a key, proceed without locking
        if ($lockKey === null) {
            return $handler->handle($request);
        }

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
            flock($fp, LOCK_EX); // Block until lock acquired
        }


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
        }
    }

}
