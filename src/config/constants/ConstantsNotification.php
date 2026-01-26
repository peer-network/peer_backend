<?php

declare(strict_types=1);

namespace Fawaz\config\constants;

class ConstantsNotification
{
    /**
    * @return array{
    *     POST_LIKE: array<string, array{TITLE: string, BODY: string}>
    * }
    */
    public static function notifications()
    {
        return self::NOTIFICATIONS;
    }

    /**
     * NOTIFICATIONS
     * 
     * Each notification must contain title and body.
     * 
     * If you want to some text, to be dynamic please wrap it like {{variableName}}
     * 
     * Currently it replces:
     *  {{initiator.username}} -> it will replace with username of sender
     *  {{receiver.username}} -> it will replace with username of receiver
     */
    public const NOTIFICATIONS = [
        'POST_LIKE' => [
            'TITLE' => 'Post Like',
            'BODY' => '{{initiator.username}} liked your post!'
        ]
    ];
}
