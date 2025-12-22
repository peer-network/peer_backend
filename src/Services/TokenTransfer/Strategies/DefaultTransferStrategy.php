<?php

declare(strict_types=1);

namespace Fawaz\Services\TokenTransfer\Strategies;

use Fawaz\Services\TokenTransfer\Fees\FeePolicyMode;
use Fawaz\Utils\ResponseHelper;

class DefaultTransferStrategy extends BaseTransferStrategy implements TransferStrategy
{
    use ResponseHelper;
    public string $operationId;
    public string $transactionId;

    private static FeePolicyMode $mode;

    public function __construct()
    {
        $this::$mode = FeePolicyMode::ADDED;
        $this->operationId = self::generateUUID();
        $this->transactionId = self::generateUUID();
    }

    public function getRecipientTransactionType(): string
    {
        return 'transferSenderToRecipient';
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
