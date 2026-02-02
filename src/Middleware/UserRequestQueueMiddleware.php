<?php

declare(strict_types=1);

namespace Fawaz\Middleware;

use Fawaz\Services\JWTService;
use Fawaz\Services\UserRequests\UserRequestQueue;
use Fawaz\Utils\PeerLoggerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Psr7\Response;

class UserRequestQueueMiddleware implements MiddlewareInterface
{
    public function __construct(
        private UserRequestQueue $queue,
        private JWTService $tokenService,
        private PeerLoggerInterface $logger
    ) {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if (!$this->isEnabled()) {
            return $handler->handle($request);
        }

        if (!$this->shouldQueueRequest($request)) {
            return $handler->handle($request);
        }

        $bearerToken = $this->extractBearerToken($request);
        if ($bearerToken === null) {
            return $handler->handle($request);
        }

        $userId = $this->resolveUserId($bearerToken);
        if ($userId === null) {
            return $handler->handle($request);
        }

        $operation = $this->resolveOperationName($request);
        $queuePayload = [
            'path' => $request->getUri()->getPath(),
            'method' => $request->getMethod(),
            'operation' => $operation,
        ];

        $messageId = $this->queue->enqueue($userId, $operation, $queuePayload);
        if ($messageId === null) {
            $this->logger->warning('User request queue unavailable, bypassing');
            return $handler->handle($request);
        }

        $this->logger->info('User request queued', [
            'user_id' => $userId,
            'operation' => $operation,
            'message_id' => $messageId,
        ]);

        $stream = $this->queue->streamForUserRequest($userId);
        $startedAt = microtime(true);
        if (!$this->waitForTurn($stream, $messageId, $userId)) {
            $this->queue->remove($stream, $messageId);
            return $this->queueTimeoutResponse();
        }

        $this->logger->info('User request dequeued', [
            'user_id' => $userId,
            'operation' => $operation,
            'message_id' => $messageId,
            'wait_ms' => (int) round((microtime(true) - $startedAt) * 1000),
        ]);

        try {
            return $handler->handle($request);
        } finally {
            $this->queue->remove($stream, $messageId);
            $this->queue->releaseLock($userId);
        }
    }

    private function shouldQueueRequest(ServerRequestInterface $request): bool
    {
        $path = $request->getUri()->getPath();
        if ($path !== '/graphql') {
            return false;
        }

        return strtoupper($request->getMethod()) === 'POST';
    }

    private function extractBearerToken(ServerRequestInterface $request): ?string
    {
        $authorizationHeader = $request->getHeader('Authorization');
        if (empty($authorizationHeader)) {
            return null;
        }

        $parts = explode(' ', $authorizationHeader[0]);
        if (count($parts) !== 2 || strtolower($parts[0]) !== 'bearer') {
            return null;
        }

        $token = trim($parts[1]);
        return $token !== '' ? $token : null;
    }

    private function resolveUserId(string $bearerToken): ?string
    {
        try {
            $decodedToken = $this->tokenService->validateToken($bearerToken);
            $userId = $decodedToken->uid ?? null;
            return is_string($userId) && $userId !== '' ? $userId : null;
        } catch (\Throwable $exception) {
            $this->logger->warning('User request queue token invalid', ['error' => $exception->getMessage()]);
            return null;
        }
    }

    private function resolveOperationName(ServerRequestInterface $request): string
    {
        $rawBody = (string) $request->getBody();
        $request->getBody()->rewind();

        if ($rawBody === '') {
            return 'unknown';
        }

        $decodedBody = json_decode($rawBody, true);
        if (!is_array($decodedBody)) {
            return 'unknown';
        }

        $operationName = $decodedBody['operationName'] ?? null;
        if (is_string($operationName) && $operationName !== '') {
            return $operationName;
        }

        $query = $decodedBody['query'] ?? null;
        if (!is_string($query) || trim($query) === '') {
            return 'unknown';
        }

        return 'graphql:' . substr(md5($query), 0, 12);
    }

    private function waitForTurn(string $stream, string $messageId, string $userId): bool
    {
        $timeoutMs = (int) ($_ENV['USER_REQUEST_QUEUE_TIMEOUT_MS'] ?? 15000);
        $pollMs = (int) ($_ENV['USER_REQUEST_QUEUE_POLL_MS'] ?? 200);
        $deadline = microtime(true) + ($timeoutMs / 1000);

        while (microtime(true) <= $deadline) {
            $head = $this->queue->peekHead($stream);
            if ($head !== null && $head['id'] === $messageId) {
                if ($this->queue->tryLock($userId)) {
                    return true;
                }
            }

            usleep($pollMs * 1000);
        }

        $this->logger->warning('User request queue timeout', [
            'stream' => $stream,
            'message_id' => $messageId,
            'user_id' => $userId,
        ]);

        return false;
    }

    private function queueTimeoutResponse(): ResponseInterface
    {
        $response = new Response(429);
        $response->getBody()->write(json_encode([
            'errors' => 'Request queue timeout',
            'message' => 'Please retry your request.',
        ]));

        return $response->withHeader('Content-Type', 'application/json');
    }

    private function isEnabled(): bool
    {
        $flag = $_ENV['USER_REQUEST_QUEUE_ENABLED'] ?? 'true';
        return filter_var($flag, FILTER_VALIDATE_BOOLEAN);
    }
}
