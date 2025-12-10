<?php

namespace Fawaz\Services\ContentFiltering\Specs\SpecTypes\IllegalContent;

use Fawaz\config\ContentReplacementPattern;
use Fawaz\Services\ContentFiltering\ContentFilterServiceImpl;
use Fawaz\Services\ContentFiltering\Replaceables\CommentReplaceable;
use Fawaz\Services\ContentFiltering\Replaceables\PostReplaceable;
use Fawaz\Services\ContentFiltering\Replaceables\ProfileReplaceable;
use Fawaz\Services\ContentFiltering\Specs\Specification;
use Fawaz\Services\ContentFiltering\Specs\SpecificationSQLData;
use Fawaz\Services\ContentFiltering\Strategies\ContentFilteringStrategy;
use Fawaz\Services\ContentFiltering\Strategies\Implementations\HideEverythingContentFilteringStrategy;
use Fawaz\Services\ContentFiltering\Strategies\Implementations\HidePostsElsePlaceholder;
use Fawaz\Services\ContentFiltering\Strategies\Implementations\PlaceholderEverythingContentFilteringStrategy;
use Fawaz\Services\ContentFiltering\Strategies\Implementations\StrictlyHideEverythingContentFilteringStrategy;
use Fawaz\Services\ContentFiltering\Types\ContentFilteringAction;
use Fawaz\Services\ContentFiltering\Types\ContentFilteringCases;
use Fawaz\Services\ContentFiltering\Types\ContentType;

final class IllegalContentFilterSpec implements Specification
{
    private ContentFilterServiceImpl $contentFilterService;
    private ContentFilteringStrategy $contentFilterStrategy;

    public function __construct(
        ContentFilteringCases $case,
        private ContentType $targetContent,
    ) {
        $this->contentFilterService = new ContentFilterServiceImpl(
            $targetContent
        );
        $this->contentFilterStrategy = self::createStrategy(
            $case
        );
    }

    public function toSql(ContentType $showingContent): ?SpecificationSQLData
    {
        if (ContentFilteringAction::hideContent === $this->contentFilterService->getContentFilterAction(
            $showingContent,
            $this->contentFilterStrategy
        )) {
            return match ($showingContent) {
                ContentType::user => new SpecificationSQLData([
                    "NOT EXISTS (
                        SELECT 1
                            FROM users IllegalContentFilterSpec_users
                            WHERE IllegalContentFilterSpec_users.uid = u.uid
                            AND IllegalContentFilterSpec_users.visibility_status = 'illegal'
                        )",
                ], []),
                ContentType::post => new SpecificationSQLData([
                    "NOT EXISTS (
                        SELECT 1
                            FROM posts IllegalContentFilterSpec_posts
                            WHERE IllegalContentFilterSpec_posts.postid = p.postid
                            AND IllegalContentFilterSpec_posts.visibility_status = 'illegal'
                        )",
                ], []),
                ContentType::comment => new SpecificationSQLData([
                    "NOT EXISTS (
                        SELECT 1
                            FROM comments IllegalContentFilterSpec_comments
                            WHERE IllegalContentFilterSpec_comments.commentid = c.commentid
                            AND IllegalContentFilterSpec_comments.visibility_status = 'illegal'
                        )",
                ], []),
            };
        }

        return null;
    }

    public function toReplacer(
        ProfileReplaceable|PostReplaceable|CommentReplaceable $subject,
    ): ?ContentReplacementPattern {
        if ($subject instanceof ProfileReplaceable) {
            $showingContent = ContentType::user;
        } elseif ($subject instanceof CommentReplaceable) {
            $showingContent = ContentType::comment;
        } else {
            $showingContent = ContentType::post;
        }

        if (ContentFilteringAction::replaceWithPlaceholder === $this->contentFilterService->getContentFilterAction(
            $showingContent,
            $this->contentFilterStrategy
        )) {
            if ('illegal' === $subject->visibilityStatus()) {
                return ContentReplacementPattern::illegal;
            }
        }

        return null;
    }

    private static function createStrategy(
        ContentFilteringCases $strategy,
    ): ContentFilteringStrategy {
        return match ($strategy) {
            ContentFilteringCases::myprofile    => new PlaceholderEverythingContentFilteringStrategy(),
            ContentFilteringCases::searchById   => new PlaceholderEverythingContentFilteringStrategy(),
            ContentFilteringCases::searchByMeta => new HideEverythingContentFilteringStrategy(),
            ContentFilteringCases::postFeed     => new HidePostsElsePlaceholder(),
            ContentFilteringCases::hideAll      => new StrictlyHideEverythingContentFilteringStrategy(),
        };
    }

    // on input we have only targetId
    // we need to forbid interactions
    // with system/deleted accounts and
    // illegal targets
    // and illegal post comments

    // also resolve action post
    public function forbidInteractions(string $targetContentId): SpecificationSQLData
    {
        return match ($this->targetContent) {
            ContentType::user => new SpecificationSQLData([
                "NOT EXISTS (
                    SELECT 1
                    FROM 
                        users IllegalContentFilterSpec_users
                    WHERE 
                        IllegalContentFilterSpec_users.uid = :IllegalContentFilterSpec_userid AND
                        IllegalContentFilterSpec_users.visibility_status = 'illegal'
                )",
            ], [
                'IllegalContentFilterSpec_userid' => $targetContentId,
            ]),
            ContentType::post => new SpecificationSQLData([
                "NOT EXISTS (
                    SELECT 1
                    FROM 
                        posts IllegalContentFilterSpec_posts
                    WHERE
                        IllegalContentFilterSpec_posts.postid = :IllegalContentFilterSpec_postid AND
                        IllegalContentFilterSpec_posts.visibility_status = 'illegal'
                )",
            ], [
                'IllegalContentFilterSpec_postid' => $targetContentId,
            ]),
            ContentType::comment => new SpecificationSQLData(
                [
                    "NOT EXISTS (
                    SELECT 1
                        FROM 
                            comments IllegalContentFilterSpec_comments
                        LEFT JOIN 
                            posts IllegalContentFilterSpec_posts ON IllegalContentFilterSpec_posts.postid = IllegalContentFilterSpec_comments.postid
                        WHERE 
                            IllegalContentFilterSpec_comments.commentid = :IllegalContentFilterSpec_commentid AND
                            (IllegalContentFilterSpec_comments.visibility_status = 'illegal' OR
                            IllegalContentFilterSpec_posts.visibility_status = 'illegal')
                    )",
                ],
                [
                    'IllegalContentFilterSpec_commentid' => $targetContentId,
                ]
            ),
        };
    }
}
