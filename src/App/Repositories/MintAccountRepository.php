<?php

declare(strict_types=1);

namespace Fawaz\App\Repositories;

use Fawaz\App\Models\MintAccount;

interface MintAccountRepository extends WalletDebitable
{
    public function getDefaultAccount(): ?MintAccount;
}
