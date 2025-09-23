<?php

namespace Fawaz\config\constants;

class ConstantsModeration
{
    public static function contentFiltering() {
        return self::CONTENT_FILTERING;
    }

    private const CONTENT_FILTERING = [
        'CONTENT_SEVERITY_LEVELS' => [
            0 => 'MYGRANDMALIKES',
            10 => 'MYGRANDMAHATES'
        ],
        'REPORTS_COUNT_TO_HIDE_FROM_IOS' => [
            'POST' => 1,
            'COMMENT' => 1,
            'USER' => 1,
        ],
        'DISMISSING_MODERATION_COUNT_TO_RESTORE_TO_IOS' => [
            'POST' => 1,
            'COMMENT' => 1,
            'USER' => 1,
        ],
    ];
}