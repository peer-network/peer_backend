<?php
namespace Fawaz\Services\Notifications\Interface;

use Fawaz\App\Models\UserDeviceToken;
use Fawaz\Services\Notifications\Interface\NotificationPayload;

interface ApiService
{
    public static function sendNotification(NotificationPayload $payload,  UserDeviceToken $receiver): bool;
}