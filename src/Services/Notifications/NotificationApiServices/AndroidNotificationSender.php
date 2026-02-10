<?php

namespace Fawaz\Services\Notifications\NotificationApiServices;

use Fawaz\App\Models\UserDeviceToken;
use Fawaz\Services\Notifications\Interface\NotificationPayload;
use Fawaz\Services\Notifications\Interface\NotificationSenderStrategy;

final class AndroidNotificationSender implements NotificationSenderStrategy
{
    public function __construct(private AndroidApiService $androidApi)
    {
    }

    public function supports(UserDeviceToken $receiver): bool
    {
        return strtoupper((string)$receiver->getPlatform()) === 'ANDROID';
    }

    public function send(NotificationPayload $payload, UserDeviceToken $receiver): bool
    {
        return $this->androidApi->sendNotification($payload, $receiver);
    }
}
