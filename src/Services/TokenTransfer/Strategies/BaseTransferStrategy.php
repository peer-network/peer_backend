<?php

declare(strict_types=1);

namespace Fawaz\Services\TokenTransfer\Strategies;

abstract class BaseTransferStrategy
{
    public function getInviterFeeTransactionType(): string
    {
        return 'transferSenderToInviter';
    }

    public function getPoolFeeTransactionType(): string
    {
        return 'transferSenderToPoolWallet';
    }

    public function getPeerFeeTransactionType(): string
    {
        return 'transferSenderToPeerWallet';
    }

    public function getBurnFeeTransactionType(): string
    {
        return 'transferSenderToBurnWallet';
    }
}
