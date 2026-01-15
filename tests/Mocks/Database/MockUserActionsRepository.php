<?php

declare(strict_types=1);

namespace Tests\Mocks\Database;

use Fawaz\Database\UserActionsRepository;

final class MockUserActionsRepository implements UserActionsRepository
{
    private array $responses = [];

    public function listTodaysInteractions(string $userId): array
    {
        return $this->responses[$userId] ?? [
            'status' => 'success',
            'ResponseCode' => 21204,
            'affectedRows' => [
                'totalInteractions' => 0,
                'totalScore' => 0,
                'totalDetails' => [],
            ],
        ];
    }

    public function seedResponse(string $userId, array $payload): void
    {
        $this->responses[$userId] = $payload;
    }
}
