<?php
declare(strict_types=1);

namespace Fawaz\App\Repositories\Interfaces;

interface WalletBalanceRepositoryInterface
{
    /**
     * Get the current wallet balance for a user in tokens (decimal).
     */
    public function getBalance(string $userId): float;

    /**
     * Set the absolute wallet balance for a user (upsert semantics allowed).
     * Returns true on success. Intended for administrative or reconciliation flows.
     */
    public function setBalance(string $userId, float $liquidity): void;

    /**
     * Atomically add a delta to the user's wallet balance and return the new balance.
     *
     * Semantics:
     * - Interprets `delta` as a signed amount to add (negative to deduct).
     * - Uses row-level locking (SELECT ... FOR UPDATE) and should be called within
     *   an active transaction to ensure consistency under concurrency.
     * - Persists both human-readable liquidity and its Q64.96 representation.
     */
    public function addToBalance(string $userId, float $delta): float;
}
