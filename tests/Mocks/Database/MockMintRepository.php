<?php

declare(strict_types=1);

namespace Tests\Mocks\Database;

use Fawaz\Database\MintRepository;

final class MockMintRepository implements MintRepository
{
    /** @var array<string, array> */
    private array $mintsByDayToken = [];

    /** @var array<string, array> */
    private array $mintsByDate = [];

    /** @var array<int, array{mintId: string, day: string, ratio: string}> */
    public array $insertCalls = [];

    public function __construct(array $preExisting = [])
    {
        $this->mintsByDayToken = $preExisting;
    }

    public function mintWasPerformedForDay(string $dayAction): bool
    {
        return isset($this->mintsByDayToken[$dayAction]);
    }

    public function insertMint(string $mintId, string $day, string $gemsInTokenRatio): void
    {
        $record = [
            'mintId' => $mintId,
            'day' => $day,
            'ratio' => $gemsInTokenRatio,
        ];
        $this->insertCalls[] = $record;
        $this->mintsByDate[$day] = $record;
    }

    public function getMintForDate(string $dateYYYYMMDD): ?array
    {
        return $this->mintsByDate[$dateYYYYMMDD] ?? null;
    }

    public function getMintForDay(string $day = 'D0'): ?array
    {
        return $this->mintsByDayToken[$day] ?? null;
    }
}
