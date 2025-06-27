<?php

namespace Fawaz\config\constants;

class ConstantsModeration
{
    public static function moderation_content() {
        return self::MODERATION_CONTENT;
    }

    private const MODERATION_CONTENT = [
        'CONTENT_SEVERITY_LEVELS' => [
            "0" => 'MYGRANDMALIKES',
            "10" => 'MYGRANDMAHATES'
        ],
        'REPORTS_COUNT_TO_HIDE_FROM_IOS' => [
            'POST' => 1,
            'COMMENT' => 1,
            'USER' => 1,
        ],
    ];
}