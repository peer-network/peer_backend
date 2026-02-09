<?php

namespace Fawaz\App\Interfaces;

interface GemsService
{
    public function setCurrentUserId(string $userId): void;

    /**
     * Stats for uncollected gems over common windows.
     */
    public function gemsStats(): array;

    public function allGemsForDay(string $day = 'D0'): array;

    public function generateGemsFromActions(): array;
}
