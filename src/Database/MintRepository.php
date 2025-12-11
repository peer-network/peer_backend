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
     * Insert a mint record into mints table.
     * - mintId: UUID for the mint row
     * - day: YYYY-MM-DD (date for which mint applies)
     * - gemsInTokenRatio: numeric as string to preserve precision
     */
    public function insertMint(string $mintId, string $day, string $gemsInTokenRatio): void;

    /**
     * Get a mint row for a concrete date (YYYY-MM-DD) if it exists.
     *
     * @param string $dateYYYYMMDD Date in ISO format (e.g., 2025-12-03)
     * @return array|null Associative row of mint data or null if none
     */
    public function getMintForDate(string $dateYYYYMMDD): ?array;

    /**
     * Resolve a day token (e.g., D0..D7) to a concrete date and fetch its mint.
     *
     * Supported tokens: D0..D7. Other tokens are not supported for a single-day mint lookup.
     *
     * @param string $day Day token (default 'D0')
     * @return array|null Associative row of mint data or null if none
     */
    public function getMintForDay(string $day = 'D0'): ?array;
}
