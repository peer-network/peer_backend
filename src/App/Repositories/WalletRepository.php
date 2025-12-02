<?php

declare(strict_types=1);

namespace Fawaz\App\Repositories;

interface WalletRepository {
    public function lockWalletBalance(string $walletId): void;

    public function debitIfSufficient(string $userId, string $amount): ?string;
}
