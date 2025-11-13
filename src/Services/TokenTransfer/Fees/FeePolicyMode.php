<?php

declare(strict_types=1);

namespace Fawaz\Services\TokenTransfer\Fees;

enum FeePolicyMode: string
{
    case INCLUDED = 'INCLUDED'; 
    case ADDED = 'ADDED';       
}
