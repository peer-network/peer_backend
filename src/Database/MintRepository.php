<?php

declare(strict_types=1);

namespace Fawaz\Database;

interface MintRepository
{
    /**
     * Check if a mint was performed for a given day action (e.g., D0..D7).
     * Returns true if at least one mint transaction exists for that day.
     */
    public function mintWasPerformedForDay(string $dayAction): bool;

    /**
     * Insert a mint log entry into mint_info.
     */
    public function insertMintLog(\Fawaz\App\DTO\MintLogItem $item): void;

    /**
     * Insert multiple mint log entries into mint_info.
     * Accepts an array of MintLogItem instances.
     */
    public function insertMintLogs(array $items): void;
}
