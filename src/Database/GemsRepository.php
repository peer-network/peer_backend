<?php

declare(strict_types=1);

namespace Fawaz\Database;

use Fawaz\App\DTO\Gems;

interface GemsRepository
{
    /**
     * Aggregated stats for uncollected gems across common time windows.
     *
     * @return array Associative array with keys like d0..d7, w0, m0, y0
     */
    public function fetchUncollectedGemsStats(): array;

    /**
     * Aggregated per-user gems for a given day filter.
     *
     * Supported filters: D0..D5, W0, M0, Y0
     *
     * @param string $day Day filter (e.g., 'D0', 'W0', 'M0', 'Y0')
     * @return array List of per-user aggregates and totals
     */
    public function fetchAllGemsForDay(string $day = 'D0'): array;
    
    /**
     * Immutable DTO result for uncollected gems over a time window.
     *
     * @param string $day Day filter (e.g., 'D0', 'D1', 'W0', 'M0', 'Y0')
     * @return ?Gems DTO containing rows
     */
    public function fetchUncollectedGemsForMintResult(string $day = 'D0'): ?Gems;
    
    /**
     * Apply mint metadata and mark corresponding gems as collected.
     *
     * @param string                 $mintId           The mint operation identifier
     * @param Gems  $uncollectedGems  The result set to apply
     * @param array<string,array<string, mixed>>    $mintLogItems     Map of userId => log item (contains transactionId)
     * @return void
     */
    public function applyMintInfo(string $mintId, Gems $uncollectedGems, array $mintLogItems);

    /**
     * Insert global win entries into gems and mark source rows as collected.
     *
     * @param string $tableName Source table name (e.g., 'likes', 'views')
     * @param int    $winType   Win type code to store in whereby
     * @param float  $factor    Gems amount factor to insert
     * @return array Result payload including insertCount
     */
    public function setGlobalWins(string $tableName, int $winType, float $factor);
}
