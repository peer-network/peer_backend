<?php
declare(strict_types=1);

namespace Fawaz\App\Repositories\Interfaces;

interface WalletBalanceRepositoryInterface
{
    /**
     * Returns the current wallet balance for a user.
     */
    public function getBalance(string $userId): float;

    /**
     * Sets the absolute wallet balance for a user (upsert semantics allowed).
     */
    public function setBalance(string $userId, float $liquidity): bool;

    /**
     * Atomically upserts a wallet entry and returns the resulting balance.
     */
    public function upsertAndReturn(string $userId, float $liquidity): float;
}

