<?php

declare(strict_types=1);

namespace Fawaz\Services\TokenTransfer\Strategies;

use Fawaz\Utils\ResponseHelper;

class SwapToPoolTransferStrategy implements TransferStrategy
{
    use ResponseHelper;
    public string $operationId;
    public string $transactionId;

    public function __construct()
    {   
        $this->operationId = self::generateUUID();
        $this->transactionId = self::generateUUID();
    }

    public function getRecipientTransactionType(): string
    {
        return 'btcSwapToPool';
    }

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

    public function getTransactionId(): string
    {
        return $this->transactionId;
    }

    public function getOperationId(): string
    {
        return $this->operationId;
    }
}

