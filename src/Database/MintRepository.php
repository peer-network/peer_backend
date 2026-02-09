<?php

declare(strict_types=1);

namespace Fawaz\Database;

interface MintRepository
{
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
}
