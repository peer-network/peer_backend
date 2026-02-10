<?php

declare(strict_types=1);

namespace Fawaz\Services\Notifications\Enums;

/**
 * Refers to Content
 */
enum NotificationContent: string
{
    case POST = 'POST';

    case COMMENT = 'COMMENT';

    // Can be considered for User Follow
    case USER = 'USER';
}
