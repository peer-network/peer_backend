<?php

namespace Fawaz\Services\ContentFiltering\Specs\SpecTypes\User;

use Fawaz\Services\ContentFiltering\ContentFilterServiceImpl;
use Fawaz\Services\ContentFiltering\Specs\Specification;
use Fawaz\Services\ContentFiltering\Specs\SpecificationSQLData;
use Fawaz\config\ContentReplacementPattern;
use Fawaz\Services\ContentFiltering\Replaceables\ProfileReplaceable;
use Fawaz\Services\ContentFiltering\Replaceables\PostReplaceable;
use Fawaz\Services\ContentFiltering\Replaceables\CommentReplaceable;
use Fawaz\Services\ContentFiltering\Strategies\ContentFilteringStrategy;
use Fawaz\Services\ContentFiltering\Strategies\Implementations\HideEverythingContentFilteringStrategy;
use Fawaz\Services\ContentFiltering\Types\ContentFilteringAction;
use Fawaz\Services\ContentFiltering\Types\ContentFilteringCases;
use Fawaz\Services\ContentFiltering\Types\ContentType;

final readonly class SystemUserSpec implements Specification
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
                        FROM users SystemUserSpec_users
                        WHERE SystemUserSpec_users.uid = u.uid AND
                        SystemUserSpec_users.roles_mask IN (0,2,16,256) AND
                        SystemUserSpec_users.verified = 1
                    )" ],
                    []
                ),
                ContentType::post => new SpecificationSQLData(
                    [
                    "EXISTS (
                        SELECT 1
                        FROM users SystemUserSpec_users
                        WHERE SystemUserSpec_users.uid = p.userid AND
                        SystemUserSpec_users.roles_mask IN (0,2,16,256) AND
                        SystemUserSpec_users.verified = 1
                    )" ],
                    []
                ),
                ContentType::comment => new SpecificationSQLData(
                    [
                    "EXISTS (
                        SELECT 1
                        FROM users SystemUserSpec_users
                        WHERE SystemUserSpec_users.uid = c.userid AND
                        SystemUserSpec_users.roles_mask IN (0,2,16,256) AND
                        SystemUserSpec_users.verified = 1
                    )" ],
                    []
                ),
            };
        }
        return null;
    }

    public function toReplacer(ProfileReplaceable|PostReplaceable|CommentReplaceable $subject): ?ContentReplacementPattern
    {
        return null;
    }


    private static function createStrategy(
        ContentFilteringCases $strategy
    ): ContentFilteringStrategy {
        return new HideEverythingContentFilteringStrategy();
    }

    public function forbidInteractions(string $targetContentId): SpecificationSQLData
    {
        return match ($this->targetContent) {
            ContentType::user => new SpecificationSQLData([
                "EXISTS (
                    SELECT 1
                    FROM users SystemUserSpec_users
                    WHERE SystemUserSpec_users.uid = :SystemUserSpec_userid
                    AND SystemUserSpec_users.roles_mask IN (0,2,16,256)
                    AND SystemUserSpec_users.verified = 1
                )"
            ], [
                "SystemUserSpec_userid" => $targetContentId
            ]),
            ContentType::post => new SpecificationSQLData([
                "EXISTS (
                    SELECT 1
                    FROM posts SystemUserSpec_posts
                    LEFT JOIN users SystemUserSpec_users ON SystemUserSpec_users.uid = SystemUserSpec_posts.userid
                    WHERE SystemUserSpec_posts.postid = :SystemUserSpec_postid
                    AND SystemUserSpec_users.roles_mask IN (0,2,16,256)
                    AND SystemUserSpec_users.verified = 1
                )"
            ], [
                "SystemUserSpec_postid" => $targetContentId
            ]),
            ContentType::comment => new SpecificationSQLData([
                "EXISTS (
                    SELECT 1
                    FROM comments SystemUserSpec_comments
                    LEFT JOIN users SystemUserSpec_users ON SystemUserSpec_users.uid = SystemUserSpec_comments.userid
                    WHERE SystemUserSpec_comments.commentid = :SystemUserSpec_commentid
                    AND SystemUserSpec_users.roles_mask IN (0,2,16,256)
                    AND SystemUserSpec_users.verified = 1
                )"
            ], [
                "SystemUserSpec_commentid" => $targetContentId
            ]),
        };
    }
}
