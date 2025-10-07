<?php

namespace Fawaz\config\constants;

class ConstantsModeration
{
    public static function contentFiltering() {
        return self::CONTENT_FILTERING;
    }

    /**
     * Get content moderation status
     *
     * @return array
     */
    public static function contentModerationStatus(): array
    {
        return self::CONTENT_MODERATION_STATUS;
    }

    private const CONTENT_FILTERING = [
        'CONTENT_SEVERITY_LEVELS' => [
            0 => 'MYGRANDMALIKES',
            10 => 'MYGRANDMAHATES'
        ],
        'REPORTS_COUNT_TO_HIDE_FROM_IOS' => [
            'POST' => 5,
            'COMMENT' => 5,
            'USER' => 5,
        ],
        'DISMISSING_MODERATION_COUNT_TO_RESTORE_TO_IOS' => [
            'POST' => 1,
            'COMMENT' => 1,
            'USER' => 1,
        ],
    ];

    /**
     * Content moderation status
     *
     * @var array
     */
    private const CONTENT_MODERATION_STATUS = [
        'waiting_for_review' => 'Waiting for review',
        'hidden' => 'Hidden',
        'restored' => 'Restored',
        'illegal' => 'Illegal',
    ];

    /**
     * Content Target Types
     *
     * @var array
     */
    public const CONTENT_MODERATION_TARGETS = [
        'POST' => 'Post',
        'COMMENT' => 'Comment',
        'USER' => 'User',
    ];
    
    /**
     * Moderation Tickets Status
     * 
     * @var string
     */
    public const MODERATION_TICKETS_STATUS_OPEN = 'open';
    public const MODERATION_TICKETS_STATUS_CLOSED = 'closed';

}