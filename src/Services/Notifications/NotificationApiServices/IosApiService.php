<?php

class IosApiService implements ApiService
{
    public static function sendNotification(NotificationPayload $payload, $receiver): bool
    {
        $payload = (new IosPayloadStructureHelper())->payload($payload);

        return true;
    }
}