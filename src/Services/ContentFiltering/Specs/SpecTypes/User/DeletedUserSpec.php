<?php

namespace Fawaz\Services\ContentFiltering\Specs\SpecTypes\User;

use Fawaz\Services\ContentFiltering\ContentFilterServiceImpl;
use Fawaz\Services\ContentFiltering\Specs\Specification;
use Fawaz\Services\ContentFiltering\Specs\SpecificationSQLData;
use Fawaz\App\Status;
use Fawaz\config\ContentReplacementPattern;
use Fawaz\Services\ContentFiltering\Replaceables\ProfileReplaceable;
use Fawaz\Services\ContentFiltering\Replaceables\PostReplaceable;
use Fawaz\Services\ContentFiltering\Replaceables\CommentReplaceable;
use Fawaz\Services\ContentFiltering\Strategies\ContentFilteringStrategy;
use Fawaz\Services\ContentFiltering\Strategies\Implementations\HideEverythingContentFilteringStrategy;
use Fawaz\Services\ContentFiltering\Strategies\Implementations\HidePostsElsePlaceholder;
use Fawaz\Services\ContentFiltering\Strategies\Implementations\PlaceholderEverythingContentFilteringStrategy;
use Fawaz\Services\ContentFiltering\Strategies\Implementations\StrictlyHideEverythingContentFilteringStrategy;
use Fawaz\Services\ContentFiltering\Types\ContentFilteringAction;
use Fawaz\Services\ContentFiltering\Types\ContentFilteringCases;
use Fawaz\Services\ContentFiltering\Types\ContentType;

final class DeletedUserSpec implements Specification
{
    private ContentFilterServiceImpl $contentFilterService;
    private ContentFilteringStrategy $contentFilterStrategy;

    public function __construct(
        ContentFilteringCases $case,
        private ContentType $targetContent
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
        if ($this->contentFilterService->getContentFilterAction(
            $showingContent,
            $this->contentFilterStrategy
        ) === ContentFilteringAction::hideContent) {
            return match ($showingContent) {
                ContentType::user => new SpecificationSQLData(
                    [
                    "EXISTS (
                        SELECT 1
                            FROM users DeletedUserSpec_users
                            WHERE DeletedUserSpec_users.uid = u.uid
                            AND DeletedUserSpec_users.status IN (0)
                    )" ],
                    []
                ),
                ContentType::post => new SpecificationSQLData(
                    [
                    "EXISTS (
                        SELECT 1
                            FROM users DeletedUserSpec_users
                            WHERE DeletedUserSpec_users.uid = p.userid
                            AND DeletedUserSpec_users.status IN (0)
                    )" ],
                    []
                ),
                ContentType::comment => new SpecificationSQLData(
                    [
                    "EXISTS (
                        SELECT 1
                            FROM users DeletedUserSpec_users
                            WHERE DeletedUserSpec_users.uid = c.userid
                            AND DeletedUserSpec_users.status IN (0)
                    )" ],
                    []
                ),
            };
        }
        return null;
    }

    public function toReplacer(
        ProfileReplaceable|PostReplaceable|CommentReplaceable $subject
    ): ?ContentReplacementPattern {
        if ($subject instanceof ProfileReplaceable) {
            $showingContent = ContentType::user;
        } elseif ($subject instanceof CommentReplaceable) {
            $showingContent = ContentType::comment;
        } else {
            $showingContent = ContentType::post;
        }


        if ($this->contentFilterService->getContentFilterAction(
            $showingContent,
            $this->contentFilterStrategy
        )  === ContentFilteringAction::replaceWithPlaceholder) {
            if ($subject instanceof ProfileReplaceable) {
                if ($subject->getStatus() !== Status::NORMAL) {
                    return ContentReplacementPattern::deleted;
                }
            }
            // posts, comments of Deleted User are not changed
        }
        return null;
    }

    public function forbidInteractions(string $targetContentId): ?SpecificationSQLData
    {
        return match ($this->targetContent) {
            ContentType::user => new SpecificationSQLData([
                "EXISTS (
                    SELECT 1
                        FROM users DeletedUserSpec_users
                        WHERE DeletedUserSpec_users.uid = :DeletedUserSpec_userid
                        AND DeletedUserSpec_users.status IN (0)
                )"
            ], [
                    "DeletedUserSpec_userid" => $targetContentId
            ]),
            ContentType::post => null,
            ContentType::comment => null
            // ContentType::post => new SpecificationSQLData([
            //     "EXISTS (
            //         SELECT 1
            //             FROM posts DeletedUserSpec_posts
            //             LEFT JOIN users DeletedUserSpec_users ON DeletedUserSpec_users.uid = DeletedUserSpec_posts.userid
            //             WHERE DeletedUserSpec_posts.postid = :DeletedUserSpec_postid
            //             AND DeletedUserSpec_users.status IN (0)
            //     )"
            // ], [
            //         "DeletedUserSpec_postid" => $targetContentId
            // ]),
            // ContentType::comment => new SpecificationSQLData([
            //     "EXISTS (
            //         SELECT 1
            //             FROM comments DeletedUserSpec_comments
            //             LEFT JOIN users DeletedUserSpec_users ON DeletedUserSpec_users.uid = DeletedUserSpec_comments.userid
            //             WHERE DeletedUserSpec_comments.commentid = :DeletedUserSpec_commentid
            //             AND DeletedUserSpec_users.status IN (0)
            //     )"
            // ], [
            //         "DeletedUserSpec_commentid" => $targetContentId
            // ]),
        };
    }

    private static function createStrategy(
        ContentFilteringCases $strategy
    ): ContentFilteringStrategy {
        return match ($strategy) {
            ContentFilteringCases::myprofile => new PlaceholderEverythingContentFilteringStrategy(),
            ContentFilteringCases::searchById => new PlaceholderEverythingContentFilteringStrategy(),
            ContentFilteringCases::searchByMeta => new HideEverythingContentFilteringStrategy(),
            ContentFilteringCases::postFeed => new HidePostsElsePlaceholder(),
            ContentFilteringCases::hideAll => new StrictlyHideEverythingContentFilteringStrategy()
        };
    }
}
