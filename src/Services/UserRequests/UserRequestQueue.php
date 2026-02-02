<?php

declare(strict_types=1);

namespace Fawaz\Services\UserRequests;

use Fawaz\Utils\PeerLoggerInterface;
use InvalidArgumentException;
use Predis\Client;

final class UserRequestQueue
{
    private const STRATEGY_SINGLE = 'single';
    private const STRATEGY_PER_USER = 'per_user';
    private const STRATEGY_SHARDED = 'sharded';

    public function __construct(private PeerLoggerInterface $logger)
    {
    }

    public function enqueue(string $userId, string $operation, array $payload): ?string
    {
        $client = $this->createClient();
        if ($client === null) {
            return null;
        }

        $stream = $this->resolveStreamForEnqueue($userId);
        $encodedPayload = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($encodedPayload === false) {
            return null;
        }

        $fields = [
            'user_id', $userId,
            'operation', $operation,
            'payload', $encodedPayload,
            'enqueued_at', (string) time(),
        ];

        try {
            $response = $client->executeRaw(array_merge(['XADD', $stream, '*'], $fields));
            return is_string($response) ? $response : null;
        } catch (\Exception $exception) {
            $this->logger->error('Failed to enqueue user request', ['exception' => $exception->getMessage()]);
            return null;
        }
    }

    public function streamForUserRequest(string $userId): string
    {
        return $this->resolveStreamForEnqueue($userId);
    }

    public function peekHead(string $stream): ?array
    {
        $client = $this->createClient();
        if ($client === null) {
            return null;
        }

        $response = $client->executeRaw(['XRANGE', $stream, '-', '+', 'COUNT', '1']);
        if (!is_array($response) || count($response) === 0) {
            return null;
        }

        $entry = $response[0] ?? null;
        if (!is_array($entry) || count($entry) < 2) {
            return null;
        }

        $messageId = $entry[0] ?? null;
        $fields = $entry[1] ?? null;
        if (!is_string($messageId) || !is_array($fields)) {
            return null;
        }

        return [
            'stream' => $stream,
            'id' => $messageId,
            'fields' => $this->normalizeFields($fields),
        ];
    }

    public function remove(string $stream, string $messageId): void
    {
        $client = $this->createClient();
        if ($client === null) {
            return;
        }

        $client->xdel($stream, [$messageId]);
    }

    public function tryLock(string $userId): bool
    {
        $client = $this->createClient();
        if ($client === null) {
            return false;
        }

        return $this->acquireLock($client, $userId);
    }

    public function readNext(
        string $consumer,
        int $count = 1,
        int $blockSeconds = 5,
        ?string $userId = null,
        ?int $shardId = null
    ): array {
        $client = $this->createClient();
        if ($client === null) {
            return [];
        }

        $stream = $this->resolveStreamForRead($userId, $shardId);
        $group = $this->groupName();
        $this->ensureGroup($client, $stream, $group);

        $pending = $this->readGroup($client, $group, $consumer, $stream, '0', $count, null);
        if (count($pending) === 0) {
            $blockMs = $blockSeconds * 1000;
            $pending = $this->readGroup($client, $group, $consumer, $stream, '>', $count, $blockMs);
        }

        if (count($pending) === 0) {
            return [];
        }

        $messages = $this->normalizeMessages($pending);
        $lockedMessages = [];

        foreach ($messages as $message) {
            $messageUserId = $message['user_id'] ?? '';
            if (!is_string($messageUserId) || $messageUserId === '') {
                continue;
            }

            if (!$this->acquireLock($client, $messageUserId)) {
                continue;
            }

            $lockedMessages[] = $message;
        }

        return $lockedMessages;
    }

    public function ack(string $stream, string $messageId): void
    {
        $client = $this->createClient();
        if ($client === null) {
            return;
        }

        $group = $this->groupName();
        $client->xack($stream, $group, [$messageId]);
    }

    public function releaseLock(string $userId): void
    {
        $client = $this->createClient();
        if ($client === null) {
            return;
        }

        $client->del([$this->lockKey($userId)]);
    }

    public function streamForUser(string $userId): string
    {
        return $this->streamPrefix() . ':' . $userId;
    }

    public function streamForShard(int $shardId): string
    {
        return $this->streamPrefix() . ':shard:' . $shardId;
    }

    public function shardForUser(string $userId): int
    {
        $shardCount = $this->shardCount();
        if ($shardCount <= 0) {
            return 0;
        }

        $hash = crc32($userId);
        return $hash % $shardCount;
    }

    private function resolveStreamForEnqueue(string $userId): string
    {
        return match ($this->strategy()) {
            self::STRATEGY_PER_USER => $this->streamForUser($userId),
            self::STRATEGY_SHARDED => $this->streamForShard($this->shardForUser($userId)),
            default => $this->streamPrefix(),
        };
    }

    private function resolveStreamForRead(?string $userId, ?int $shardId): string
    {
        $strategy = $this->strategy();
        if ($strategy === self::STRATEGY_SINGLE) {
            return $this->streamPrefix();
        }

        if ($strategy === self::STRATEGY_PER_USER) {
            if ($userId === null || $userId === '') {
                throw new InvalidArgumentException('User id is required for per-user streams.');
            }

            return $this->streamForUser($userId);
        }

        if ($shardId !== null) {
            return $this->streamForShard($shardId);
        }

        if ($userId === null || $userId === '') {
            throw new InvalidArgumentException('User id or shard id is required for sharded streams.');
        }

        return $this->streamForShard($this->shardForUser($userId));
    }

    private function normalizeMessages(array $rawStreams): array
    {
        $messages = [];

        foreach ($rawStreams as $streamEntry) {
            if (!is_array($streamEntry) || count($streamEntry) < 2) {
                continue;
            }

            $streamName = $streamEntry[0] ?? null;
            $streamMessages = $streamEntry[1] ?? null;
            if (!is_string($streamName) || !is_array($streamMessages)) {
                continue;
            }

            foreach ($streamMessages as $message) {
                if (!is_array($message) || count($message) < 2) {
                    continue;
                }

                $messageId = $message[0] ?? null;
                $fields = $message[1] ?? [];
                if (!is_string($messageId)) {
                    continue;
                }

                $normalizedFields = $this->normalizeFields($fields);
                $payload = $normalizedFields['payload'] ?? null;
                $decodedPayload = [];

                if (is_string($payload)) {
                    $decoded = json_decode($payload, true);
                    $decodedPayload = is_array($decoded) ? $decoded : [];
                }

                $messages[] = [
                    'stream' => $streamName,
                    'id' => $messageId,
                    'user_id' => $normalizedFields['user_id'] ?? null,
                    'operation' => $normalizedFields['operation'] ?? null,
                    'payload' => $decodedPayload,
                    'raw_payload' => $payload,
                ];
            }
        }

        return $messages;
    }

    private function normalizeFields(array $fields): array
    {
        $isList = array_keys($fields) === range(0, count($fields) - 1);
        if (!$isList) {
            return $fields;
        }

        $normalized = [];
        $count = count($fields);
        for ($index = 0; $index < $count; $index += 2) {
            $key = $fields[$index] ?? null;
            $value = $fields[$index + 1] ?? null;
            if ($key !== null) {
                $normalized[(string) $key] = $value;
            }
        }

        return $normalized;
    }

    private function readGroup(
        Client $client,
        string $group,
        string $consumer,
        string $stream,
        string $streamId,
        int $count,
        ?int $blockMs
    ): array {
        $args = ['XREADGROUP', 'GROUP', $group, $consumer, 'COUNT', (string) $count];
        if ($blockMs !== null) {
            $args[] = 'BLOCK';
            $args[] = (string) $blockMs;
        }

        $args[] = 'STREAMS';
        $args[] = $stream;
        $args[] = $streamId;

        $response = $client->executeRaw($args);
        return is_array($response) ? $response : [];
    }

    private function acquireLock(Client $client, string $userId): bool
    {
        $lockKey = $this->lockKey($userId);
        $ttlMs = $this->lockTtlMs();
        $response = $client->set($lockKey, '1', 'PX', $ttlMs, 'NX');
        return $response === 'OK';
    }

    private function lockKey(string $userId): string
    {
        $prefix = $_ENV['USER_REQUEST_LOCK_PREFIX'] ?? 'user_requests:lock';
        return $prefix . ':' . $userId;
    }

    private function lockTtlMs(): int
    {
        return (int) ($_ENV['USER_REQUEST_LOCK_TTL_MS'] ?? 10000);
    }

    private function groupName(): string
    {
        return $_ENV['USER_REQUEST_GROUP'] ?? 'user_requests_group';
    }

    private function streamPrefix(): string
    {
        return $_ENV['USER_REQUEST_STREAM_PREFIX'] ?? 'user_requests';
    }

    private function strategy(): string
    {
        return $_ENV['USER_REQUEST_STREAM_STRATEGY'] ?? self::STRATEGY_SINGLE;
    }

    private function shardCount(): int
    {
        return (int) ($_ENV['USER_REQUEST_SHARD_COUNT'] ?? 8);
    }

    private function ensureGroup(Client $client, string $stream, string $group): void
    {
        try {
            $client->executeRaw(['XGROUP', 'CREATE', $stream, $group, '$', 'MKSTREAM']);
        } catch (\Exception $exception) {
            if (!str_contains($exception->getMessage(), 'BUSYGROUP')) {
                $this->logger->warning('Failed to create user request group', [
                    'stream' => $stream,
                    'group' => $group,
                    'error' => $exception->getMessage(),
                ]);
            }
        }
    }

    private function createClient(): ?Client
    {
        $host = $_ENV['REDIS_HOST'] ?? '';
        if ($host === '') {
            $this->logger->warning('Redis host not configured for user request queue');
            return null;
        }

        $config = [
            'scheme' => $_ENV['REDIS_SCHEME'] ?? 'tcp',
            'host' => $host,
            'port' => (int) ($_ENV['REDIS_PORT'] ?? 6379),
            'database' => (int) ($_ENV['REDIS_DB'] ?? 0),
        ];

        $password = $_ENV['REDIS_PASSWORD'] ?? null;
        if ($password !== null && $password !== '') {
            $config['password'] = $password;
        }

        return new Client($config);
    }
}
