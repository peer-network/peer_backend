<?php

declare(strict_types=1);

namespace Fawaz\App;

interface MintService
{
    public function setCurrentUserId(string $userId): void;
    
    public function listTodaysInteractions(): ?array;

    public function distributeTokensFromGems(string $day = 'D0'): array;

    /**
     * Get the single Mint Account row.
     */
    public function getMintAccount(): array;
}
