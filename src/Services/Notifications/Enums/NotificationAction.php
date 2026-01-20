<?php

declare(strict_types=1);

namespace Fawaz\Services\Notifications\Enums;

enum NotificationAction: string
{
    case POST_LIKE = 'POST_LIKE';

    case POST_DISLIKE = 'POST_DISLIKE';

    case COMMENT = 'COMMENT';

    case COMMENT_REPLY = 'COMMENT_REPLY';

    case P2P_TRANSFER = 'P2P_TRANSFER';

    case DAILY_MINT = 'DAILY_MINT';

    case RELEASE_UPDATE = 'RELEASE_UPDATE';

    case ACTIVITY_REMINDER = 'ACTIVITY_REMINDER';

    case FOLLOWER = 'FOLLOWER';


}

