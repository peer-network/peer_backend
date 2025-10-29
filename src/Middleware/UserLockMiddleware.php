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
    private const LOCK_DIR = __DIR__ . '/../../runtime-data/locks';
    private const TIMEOUT_SECONDS = 30;

    /**
     * Only lock these mutation field names.
     */
    private array $lockedMutations = [
        'resolveTransferV2',
        'createPost',
        'createComment',
    ];

    public function __construct(private readonly JWTService $jwtService)
    {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if ($request->getUri()->getPath() !== '/graphql') {
            return $handler->handle($request);
        }

        $queryString = $this->extractGraphQLQuery($request);

        if (!$this->shouldLock($queryString)) {
            return $handler->handle($request);
        }

        $lockKey = $this->determineLockKey($request);
        if ($lockKey === null) {
            return $handler->handle($request);
        }

        $lockFile = $this->createLockFilePath($lockKey);
        $fp = @fopen($lockFile, 'c');

        if ($fp === false) {
            // Cannot create/open lock file → proceed without locking
            return $handler->handle($request);
        }

        $this->acquireLock($fp);

        try {
            return $handler->handle($request);
        } finally {
            $this->releaseLock($fp, $lockFile);
        }
    }

    // ---------------------------------------------------------
    // 🔒 Lock Handling
    // ---------------------------------------------------------

    private function acquireLock($fp): void
    {
        $start = time();

        while (!flock($fp, LOCK_EX | LOCK_NB)) {
            if ((time() - $start) >= self::TIMEOUT_SECONDS) {
                // As a last resort, block until available
                flock($fp, LOCK_EX);
                break;
            }
            usleep(100_000); // Retry every 100ms
        }
    }

    private function releaseLock($fp, string $lockFile): void
    {
        try {
            flock($fp, LOCK_UN);
        } catch (\Throwable) {
            // ignore
        } finally {
            fclose($fp);
        }

        // Cleanup (best effort)
        @unlink($lockFile);
    }

    private function createLockFilePath(string $lockKey): string
    {
        if (!is_dir(self::LOCK_DIR)) {
            @mkdir(self::LOCK_DIR, 0775, true);
        }

        $safeKey = preg_replace('/[^A-Za-z0-9_\-\.]/', '_', $lockKey);
        return self::LOCK_DIR . DIRECTORY_SEPARATOR . "user_{$safeKey}.lock";
    }

    // ---------------------------------------------------------
    // 🧩 Query Parsing
    // ---------------------------------------------------------

    private function extractGraphQLQuery(ServerRequestInterface $request): string
    {
        // Try parsed body
        $parsedBody = $request->getParsedBody();
        if (is_array($parsedBody) && isset($parsedBody['query'])) {
            return (string)$parsedBody['query'];
        }

        // Try query param
        $queryParams = $request->getQueryParams();
        if (isset($queryParams['query'])) {
            return (string)$queryParams['query'];
        }

        // Try raw body
        $raw = $this->getRawBody($request);
        if ($raw === '') {
            return '';
        }

        $decoded = json_decode($raw, true);
        if (is_array($decoded)) {
            if (isset($decoded['query'])) {
                return (string)$decoded['query'];
            }

            // Handle batch queries
            $queries = array_column(array_filter($decoded, fn($i) => isset($i['query'])), 'query');
            if ($queries) {
                return implode("\n\n", $queries);
            }
        }

        $contentType = $request->getHeaderLine('Content-Type');
        if (stripos($contentType, 'application/graphql') !== false || stripos($raw, 'mutation') !== false) {
            return $raw;
        }

        return '';
    }

    private function getRawBody(ServerRequestInterface $request): string
    {
        try {
            $raw = (string)$request->getBody();
            $request->getBody()->rewind();
            return $raw;
        } catch (\Throwable) {
            return '';
        }
    }

    // ---------------------------------------------------------
    // 🔐 Token / User Key Handling
    // ---------------------------------------------------------

    private function determineLockKey(ServerRequestInterface $request): ?string
    {
        $token = $this->extractBearerToken($request);
        if (!$token) {
            return null;
        }

        try {
            $decoded = $this->jwtService->validateToken($token);
            if (isset($decoded->uid) && $decoded->uid !== '') {
                return (string)$decoded->uid;
            }
        } catch (\Throwable) {
            // Invalid or expired token
        }

        return null;
    }

    private function extractBearerToken(ServerRequestInterface $request): ?string
    {
        $header = $request->getHeaderLine('Authorization');
        if (!$header) {
            return null;
        }

        $parts = explode(' ', $header, 2);
        return (count($parts) === 2 && strtolower($parts[0]) === 'bearer') ? $parts[1] : null;
    }

    // ---------------------------------------------------------
    // ⚙️ Logic
    // ---------------------------------------------------------

    private function shouldLock(string $queryString): bool
    {
        if ($queryString === '' || stripos($queryString, 'mutation') === false) {
            return false;
        }

        $escaped = array_map(static fn($s) => preg_quote($s, '/'), $this->lockedMutations);
        $pattern = '/\\b(' . implode('|', $escaped) . ')\\b/i';

        return (bool)preg_match($pattern, $queryString);
    }
}
