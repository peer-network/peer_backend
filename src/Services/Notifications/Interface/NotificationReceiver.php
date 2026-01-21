<?php

declare(strict_types=1);

namespace Fawaz\Services\Notifications\Interface;

use Fawaz\Services\Notifications\Enums\NotificationAction;
use Fawaz\Services\Notifications\Enums\NotificationPayload;

interface NotificationReceiver
{
    /**
     * Receiver refers to who will receive the notification
     * 
     * Can be delivered to multiple users
     * 
     * @return array
     */
    public function receiver(): array;
}