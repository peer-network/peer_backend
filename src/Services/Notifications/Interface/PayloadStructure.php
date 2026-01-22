<?php

namespace Fawaz\Services\Notifications\Interface;

use Fawaz\Services\Notifications\Interface\NotificationPayload;

interface PayloadStructure
{
    /**
     * Structure should be compatible with iOS, Android and WEB notification services
     * 
     * @return array
     */
    public function payload(NotificationPayload $contentType): array;
}