<?php

declare(strict_types=1);

namespace Fawaz\App\Models;

enum TransactionCategory: string
{
    case P2P_TRANSFER = 'P2P_TRANSFER';
    case INVITER_FEE_EARN = 'INVITER_FEE_EARN';
    case AD_PINNED = 'AD_PINNED';
    case POST_CREATE = 'POST_CREATE';
    case LIKE = 'LIKE';
    case DISLIKE = 'DISLIKE';
    case COMMENT = 'COMMENT';
    case TOKEN_MINT = 'TOKEN_MINT';
    case SHOP_PURCHASE = 'SHOP_PURCHASE';
    case FEE = 'FEE';
}
