<?php

declare(strict_types=1);

namespace Fawaz\Database;

use Fawaz\App\DTO\UncollectedGemsResult;

interface GemsRepository
{
    /**
     * Aggregated stats for uncollected gems across common time windows.
     */
    public function fetchUncollectedGemsStats(): array;

    /**
     * Aggregated per-user gems for a given day filter.
     * Supported filters: D0..D5, W0, M0, Y0
     */
    public function fetchAllGemsForDay(string $day = 'D0'): array;

    public function fetchUncollectedGemsForMint(string $day = 'D0'): array;
    
    public function fetchUncollectedGemsForMintResult(string $day = 'D0'): UncollectedGemsResult;

    public function setGemsAsCollected(UncollectedGemsResult $uncollectedGems);
}

