<?php

declare(strict_types=1);

namespace Fawaz\Services\TokenTransfer\Strategies;

use Fawaz\App\Models\TransactionCategory;
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
    public function getTransactionCategory(): TransactionCategory;

    public function getTransactionId(): string;
    public function getOperationId(): string;

    public function getFeePolicyMode(): FeePolicyMode;
}
