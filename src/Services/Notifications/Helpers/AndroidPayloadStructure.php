<?php
namespace Fawaz\Services\Notifications\Helpers;

use Fawaz\Services\Notifications\Interface\NotificationPayload;
use Fawaz\Services\Notifications\Interface\PayloadStructure;

class AndroidPayloadStructure implements PayloadStructure
{
    public function payload(NotificationPayload $contentType): array
    {
        $payload = [
                'message' => [
                    'token' => 'FCM_DEVICE_TOKEN',

                    // System-handled notification (title + body)
                    'notification' => [
                        'title' => $contentType->getTitle(),
                        'body'  => $contentType->getBodyContent(),
                    ],

                    // Android-specific options
                    'android' => [
                        'priority' => 'HIGH',
                        'notification' => [
                            'channel_id' => 'peer_activity',
                            'icon'       => 'ic_stat_peer', // drawable resource name
                            'color'      => '#3a6bf0',
                        ],
                    ],

                    // Custom app data
                    'data' => [
                        'contentid'        => $contentType->getContentId(),
                        'contenttype'      => $contentType->getContentType(),
                        'description'      => $contentType->getBodyContent(),
                        'profile_username' => $contentType->getInitiatorObj()->getName(),
                        'profile_avatar'   => $contentType->getInitiatorObj()->getImg(),
                        'small_icon'       => 'ic_stat_peer',
                    ],
                ],
            ];

        return $payload;
    }
}