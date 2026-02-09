<?php

declare(strict_types=1);

namespace Fawaz\App;

use Fawaz\Utils\ErrorResponse;

interface MintService
{
    public function setCurrentUserId(string $userId): void;
    
    public function listTodaysInteractions(): ?array;

    public function distributeTokensFromGems(string $date): array | ErrorResponse;

    public function distributeTokensFromGemsWithoutBalanceUpdate(string $date): array | ErrorResponse;

    /**
     * Get the single Mint Account row.
     */
    public function getMintAccount(): array;
}
