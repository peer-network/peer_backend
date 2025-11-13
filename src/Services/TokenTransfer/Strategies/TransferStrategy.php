<?php

declare(strict_types=1);

namespace Fawaz\Services\TokenTransfer\Strategies;

use Fawaz\Services\TokenTransfer\Fees\FeePolicyMode;

interface TransferStrategy
{
    
    public function getRecipientTransactionType(): string;
    

    public function getInviterFeeTransactionType(): string;
    
    public function getPoolFeeTransactionType(): string;
    
    public function getPeerFeeTransactionType(): string;
    
    public function getBurnFeeTransactionType(): string;

    public function getTransactionId(): string;
    
    public function getOperationId(): string;

    public function getFeePolicyMode(): FeePolicyMode;
}
