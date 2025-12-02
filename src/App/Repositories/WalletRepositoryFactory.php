<?php

declare(strict_types=1);

namespace Fawaz\App\Repositories;


use Fawaz\App\Models\MintAccount;
use Fawaz\Database\WalletMapper;
use Fawaz\Services\ContentFiltering\Capabilities\HasUserId;

/**
 * Factory for resolving the appropriate WalletRepository implementation
 * from a HasUserId domain object.
 *
 * - If the object is a MintAccount, returns MintAccountRepository
 * - Otherwise, returns WalletMapper
 */
class WalletRepositoryFactory {
    public function __construct(
        private WalletMapper $walletMapper,
        private MintAccountRepository $mintAccountRepository,
    ) {
    }

    public function for(HasUserId $owner): WalletRepository
    {
        if ($owner instanceof MintAccount) {
            return $this->mintAccountRepository;
        }
        return $this->walletMapper;
    }
}

