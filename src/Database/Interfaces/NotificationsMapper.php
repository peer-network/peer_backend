<?php

declare(strict_types=1);

namespace Fawaz\Database\Interfaces;

use Fawaz\Services\Notifications\Enums\NotificationAction;
use Fawaz\Services\Notifications\Interface\NotificationInitiator;
use Fawaz\Services\Notifications\Interface\NotificationReceiver;
use Fawaz\Services\Notifications\Interface\NotificationStrategy;

interface NotificationsMapper
{
    public function notifyByType(NotificationAction $action, array $payload, NotificationInitiator $notificationInititor, NotificationReceiver $notificationReceiver): bool;
}
