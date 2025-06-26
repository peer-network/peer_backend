<?php

namespace Fawaz\config\constants;

class ConstantsModeration
{
    public static function moderation_content() {
        return self::MODERATION_CONTENT;
    }

    private const MODERATION_CONTENT = [
        'CONTENT_LEVELS' => [
            'MY_GRANDMA_LIKES'
        ],
    ];
}