<?php

namespace Fawaz\Services\Notifications\Interface;

use Fawaz\App\Models\UserDeviceToken;
use Fawaz\Services\Notifications\Interface\NotificationPayload;

interface NotificationSenderStrategy
{
    public function supports(UserDeviceToken $receiver): bool;

    public function send(NotificationPayload $payload, UserDeviceToken $receiver): bool;
}