<?php

declare(strict_types=1);

namespace Fawaz\Database;

interface MintRepository
{
    public function callGlobalWins(): array;

    public function getTimeSorted();

    public function getTimeSortedMatch(string $day = 'D0'): array;

    public function callUserMove(string $userId): array;

    /**
     * Check if a mint was performed for a given day action (e.g., D0..D7).
     * Returns true if at least one mint transaction exists for that day.
     */
    public function mintWasPerformedForDay(string $dayAction): bool;
}
