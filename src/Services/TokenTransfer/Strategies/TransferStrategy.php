<?php

declare(strict_types=1);

namespace Fawaz\Services\TokenTransfer\Strategies;

use Fawaz\Services\TokenTransfer\Fees\FeePolicyMode;

interface TransferStrategy
{
    /**
     * Transaction type for the recipient credit.
     */
    public function getRecipientTransactionType(): string;
    
    /**
     * Transaction type for inviter fee.
     */
    public function getInviterFeeTransactionType(): string;
    
    /**
     * Transaction type for pool fee.
     */
    public function getPoolFeeTransactionType(): string;
    
    /**
     * Transaction type for peer fee.
     */
    public function getPeerFeeTransactionType(): string;
    
    /**
     * Transaction type for burn fee.
     */
    public function getBurnFeeTransactionType(): string;

    /**
     * Get unique transaction ID.
     */
    public function getTransactionId(): string;
    
    /**
     * Get unique operation ID.
     */
    public function getOperationId(): string;

    /**
     * Fee policy mode for this transfer.
     * ADDED: price is net, fees added on top.
     * INCLUDED: price is gross, recipient gets net.
     */
    public function getFeePolicyMode(): FeePolicyMode;
}
