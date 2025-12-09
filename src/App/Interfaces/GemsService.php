<?php

namespace Fawaz\App\Interfaces;

interface GemsService
{
    public function setCurrentUserId(string $userId): void;

    /**
     * Stats for uncollected gems over common windows.
     */
    public function gemsStats(): array;

    /**
     * Aggregated per-user gems for a given day filter.
     * Supported filters: D0..D5, W0, M0, Y0
     */
    public function allGemsForDay(string $day = 'D0'): array;
    
    public function generateGemsFromActions(): array;
}

