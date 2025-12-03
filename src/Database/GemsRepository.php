<?php

declare(strict_types=1);

namespace Fawaz\Database;

interface GemsRepository
{
    public function callGlobalWins(): array;

    public function getTimeSorted();

    public function getTimeSortedMatch(string $day = 'D0'): array;

    public function callUserMove(string $userId): array;
}

