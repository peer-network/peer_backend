<?php

declare(strict_types=1);

namespace Fawaz\config\constants;

class ConstantsModeration
{
    public static function contentFiltering()
    {
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
        'post' => 'Post',
        'comment' => 'Comment',
        'user' => 'User',
    ];

    /**
     * Moderation Tickets Status
     *
     * @var string
     */
    public const MODERATION_TICKETS_STATUS_OPEN = 'open';
    public const MODERATION_TICKETS_STATUS_CLOSED = 'closed';

    /**
     * Post Statuses
     */
    /**
     * Post Status: published
     * Applies same as in ConstantsConfig::post()['STATUS']['PUBLISHED']
     */
    public const POST_STATUS_PUBLISHED = 0;

    /**
     * Post Status: advertised
     * Applies same as in ConstantsConfig::post()['STATUS']['ADVERTISED']
     */
    public const POST_STATUS_ADVERTISED = 1;

    /**
     * Post Status: illegal
     * Applies same as in ConstantsConfig::post()['STATUS']['ILLEGAL']
     */
    public const POST_STATUS_ILLEGAL = 2;


    /**
     * Entity VisibilityStatuses
     */
    public const VISIBILITY_STATUS = [
        'normal',
        'hidden', // Hidden logic of entity (post/user/comment) is not Handled/Defined through this Status flag, 
        'illegal' // It will apply the `illegal` status of the entity (post/user/comment) through Moderation.
    ];
}
