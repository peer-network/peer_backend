<?php

declare(strict_types=1);

namespace Fawaz\App\Repositories;

/**
 * Lightweight value object that pairs a wallet/account ID
 * with the repository that should handle its balance operations.
 */
class WalletHandle
{
    public function __construct(
        private string $walletId,
        private WalletCreditable|WalletDebitable|WalletRepository $handler,
    ) {
    }

    public function handler(): WalletCreditable|WalletDebitable|WalletRepository
    {
        return $this->handler;
    }
    public function walletId(): string
    {
        return $this->walletId;
    }
}
