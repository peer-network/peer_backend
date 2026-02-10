<?php

namespace Fawaz\Services\Notifications\NotificationApiServices;

use Fawaz\App\Models\UserDeviceToken;
use Fawaz\Services\Notifications\Interface\NotificationPayload;
use Fawaz\Services\Notifications\Interface\NotificationSenderStrategy;

final class IosNotificationSender implements NotificationSenderStrategy
{
    public function __construct(private IosApiService $iosApi)
    {
    }

    public function supports(UserDeviceToken $receiver): bool
    {
        return strtoupper((string)$receiver->getPlatform()) === 'IOS';
    }

    public function send(NotificationPayload $payload, UserDeviceToken $receiver): bool
    {
        return $this->iosApi->sendNotification($payload, $receiver);
    }
}
