<?php

declare(strict_types=1);

namespace Fawaz\Database\Interfaces;

use Fawaz\Services\Notifications\Interface\NotificationInitiator;
use Fawaz\Services\Notifications\Interface\NotificationReceiver;
use Fawaz\Services\Notifications\Interface\NotificationStrategy;

interface NotificationsMapper
{
    public function notify(NotificationStrategy $notificationStrategy, NotificationInitiator $notificationInititor,  NotificationReceiver $notificationReceiver): bool;
}
    