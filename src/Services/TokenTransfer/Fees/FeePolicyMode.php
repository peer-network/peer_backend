<?php

declare(strict_types=1);

namespace Fawaz\Services\TokenTransfer\Fees;

enum FeePolicyMode: string
{
    case INCLUDED = 'INCLUDED'; // Price is gross (includes fees); recipient gets net
    case ADDED    = 'ADDED';       // Price is net; fees added on top (current default)
    case NO_FEES  = 'NO_FEES';
}
