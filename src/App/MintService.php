<?php

declare(strict_types=1);

namespace Fawaz\App;

interface MintService
{
    public function setCurrentUserId(string $userId): void;
    
    public function listTodaysInteractions(): ?array;

    public function distributeTokensFromGems(string $day = 'D0'): array;

    /**
     * Returns true if a mint was performed for the given day action.
     * Exceptions are handled here (service layer), repository allowed to throw.
     */
    public function mintWasPerformedForDay(string $dayAction = 'D0'): bool;

    /**
     * Get the single Mint Account row.
     */
    public function getMintAccount(): array;
}
