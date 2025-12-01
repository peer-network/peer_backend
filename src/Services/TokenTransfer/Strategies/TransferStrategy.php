<?php

declare(strict_types=1);

namespace Fawaz\Services\TokenTransfer\Strategies;

use Fawaz\Services\TokenTransfer\Fees\FeePolicyMode;

interface TransferStrategy
{
    /**
     * Transaction type for the recipient credit.
     * If a fallback is provided, strategy may honor it.
     */
    public function getRecipientTransactionType(): string;
    public function getInviterFeeTransactionType(): string;
    public function getPoolFeeTransactionType(): string;
    public function getPeerFeeTransactionType(): string;
    public function getBurnFeeTransactionType(): string;

    public function getTransactionId(): string;
    public function getOperationId(): string;

    /**
     * Fee policy mode for this transfer.
     * ADDED: price is net, fees added on top.
     * INCLUDED: price is gross, recipient gets net.
     */
    public function getFeePolicyMode(): FeePolicyMode;
}
