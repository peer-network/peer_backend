<?php

declare(strict_types=1);

namespace Fawaz\GraphQL\Loaders;

use Fawaz\Database\UserMapper;

/**
 * Lightweight per-request user loader with simple caching API.
 *
 * Note: This is not a full batching DataLoader implementation; it
 * provides a consistent interface and local cache. It can be extended
 * later to batch queries if a bulk fetch method is available.
 */
class UserLoader
{
    /** @var array<string, array|null> */
    private array $cache = [];

    public function __construct(private readonly UserMapper $userMapper)
    {
    }

    /** Load a single user by id with per-request caching. */
    public function load(string $userId): ?array
    {
        if (array_key_exists($userId, $this->cache)) {
            return $this->cache[$userId] ?? null;
        }

        // Fallback to existing mapper single fetch. Adjust if bulk is added.
        $user = $this->userMapper->loadByIdMAin($userId, 0)->getArrayCopy() ?: null;
        $this->cache[$userId] = $user ?: null;
        return $user ?: null;
    }

    /**
     * Load many users by ids; preserves input order. Uses cache and falls back
     * to single loads where bulk is unavailable.
     *
     * @param string[] $userIds
     * @return array<int, array|null>
     */
    public function loadMany(array $userIds): array
    {
        $results = [];
        foreach ($userIds as $id) {
            $results[] = $this->load((string) $id);
        }
        return $results;
    }

    /** Prime the cache with a known value. */
    public function prime(string $userId, ?array $value): void
    {
        $this->cache[$userId] = $value;
    }

    /** Clear one cached entry. */
    public function clear(string $userId): void
    {
        unset($this->cache[$userId]);
    }

    /** Clear entire cache. */
    public function clearAll(): void
    {
        $this->cache = [];
    }
}

