<?php

use Fawaz\App\Models\UserDeviceToken;

interface ApiService
{
    public static function sendNotification(NotificationPayload $payload,  UserDeviceToken $receiver): bool;
}