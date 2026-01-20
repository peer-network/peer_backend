<?php

declare(strict_types=1);

namespace Fawaz\Database\Interfaces;

use Fawaz\Services\Notifications\Interface\NotificationStrategy;

interface NotificationsMapper
{
    public function notify(NotificationStrategy $notification, string $targetContentId): bool;
}
