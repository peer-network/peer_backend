<?php

namespace Fawaz\Services\Notifications\Helpers;

use Fawaz\Services\Notifications\Interface\NotificationPayload;
use Fawaz\Services\Notifications\Interface\PayloadStructure;

class IosPayloadStructureHelper implements PayloadStructure
{
    /**
     * iOS payload sturcture
     * 
     */
    public function payload(NotificationPayload $contentType): array
    {

        $payload = [
                'aps' => [
                    'alert' => [
                        'title' => $contentType->getTitle(),
                        'body'  => $contentType->getBodyContent(),
                    ],
                    'sound' => 'default',
                    'mutable-content' => 1,
                ],
                'contentid' => $contentType->getContentId(),
                'contenttype' => $contentType->getContentType(),
                'profile' => [
                    'username' => $contentType->getInitiatorObj()->getName(),
                    'avatar'   => $contentType->getInitiatorObj()->getImg(),
                ],
                'icon' => 'https://example.com/icon.png',
            ];

        return $payload;
    }
}