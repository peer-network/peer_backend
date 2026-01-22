<?php

namespace Fawaz\App\Services\Notifications\NotificationApiServices;

use Fawaz\App\Services\Notifications\Interface\ApiService;
use Fawaz\Services\Notifications\Helpers\IosPayloadStructureHelper;
use Fawaz\Services\Notifications\Interface\NotificationPayload;

class IosApiService implements ApiService
{
    public static function sendNotification(NotificationPayload $payload, $receiver): bool
    {
        $payload = (new IosPayloadStructureHelper())->payload($payload);

        return true;
    }
}