<?php

declare(strict_types=1);

namespace Fawaz\Services\TokenTransfer\Strategies;

use Fawaz\Utils\ResponseHelper;
use Fawaz\Services\TokenTransfer\Fees\FeePolicyMode;

class AdsTransferStrategy implements TransferStrategy
{
    use ResponseHelper;

    public string $operationId;
    public string $transactionId;
    private static FeePolicyMode $mode;

    public function __construct() {   
        $this::$mode = FeePolicyMode::INCLUDED;
        $this->operationId = self::generateUUID();
        $this->transactionId = self::generateUUID();
    }
    public function getRecipientTransactionType(): string
    {
        return 'transferForAds';
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

    public function setTransactionId(string $transactionId): void
    {
        $this->transactionId = $transactionId;
    }

    public function setOperationId(string $operationId): void
    {
        $this->operationId = $operationId;
    }
        
    public function getTransactionId(): string
    {
        return $this->transactionId;
    }

    public function getOperationId(): string
    {
        return $this->operationId;
    }

    public function getFeePolicyMode(): FeePolicyMode
    {
        return $this::$mode;
    }
}
