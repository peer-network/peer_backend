<?php

declare(strict_types=1);

namespace Fawaz\App\Repositories;

interface WalletCreditable {
    public function lockWalletBalance(string $walletId): void;

    public function credit(string $userId, string $amount): ?string;
}
