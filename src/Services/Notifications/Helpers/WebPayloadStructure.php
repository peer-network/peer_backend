<?php
namespace Fawaz\Services\Notifications\Helpers;

use Fawaz\Services\Notifications\Interface\NotificationPayload;
use Fawaz\Services\Notifications\Interface\PayloadStructure;

class WebPayloadStructure implements PayloadStructure
{
    public function payload(NotificationPayload $contentType): array
    {
        // Implement the payload method for iOS structure

        $payload = [
                'message' => [
                    'token' => 'FCM_WEB_DEVICE_TOKEN',

                    // Web Push specific block
                    'webpush' => [
                        'headers' => [
                            'TTL' => '300',
                        ],

                        // This becomes the browser Notification API payload
                        'notification' => [
                            'title' => $contentType->getTitle(),
                            'body'  => $contentType->getBodyContent(),
                            // 'icon'  => 'https://example.com/icon-192.png',
                            // 'badge' => 'https://example.com/badge-72.png',
                        ],

                        // Custom data accessible in service worker
                        'data' => [
                            'contentid' => $contentType->getContentId(),
                            'profile' => [
                                'username' => $contentType->getInitiatorObj()->getName(),
                                // 'avatar'  => 'https://example.com/avatar.jpg',
                            ],
                            // 'url' => 'https://peerapp.de/content/987654',
                        ],
                    ],
                ],
            ];


        return $payload;
    }
}