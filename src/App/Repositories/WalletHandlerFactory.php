<?php

declare(strict_types=1);

namespace Fawaz\App\Repositories;


use Fawaz\App\Models\MintAccount;
use Fawaz\Database\WalletMapper;
use Fawaz\Services\ContentFiltering\Capabilities\HasWalletId;

/**
 * Factory for resolving the appropriate WalletRepository implementation
 * from a HasUserId domain object.
 *
 * - If the object is a MintAccount, returns MintAccountRepository
 * - Otherwise, returns WalletMapper
 */
class WalletHandlerFactory {
    public function __construct(
        private WalletMapper $walletMapper,
        private MintAccountRepository $mintAccountRepository,
    ) {
    }

    public function for(HasWalletId $owner): WalletCreditable | WalletDebitable
    {
        if ($owner instanceof MintAccount) {
            return $this->mintAccountRepository;
        }
        return $this->walletMapper;
    }
}

