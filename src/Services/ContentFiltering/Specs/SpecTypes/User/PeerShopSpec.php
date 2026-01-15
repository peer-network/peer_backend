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
use Fawaz\Services\ContentFiltering\Strategies\Implementations\HidePostsElsePlaceholder;
use Fawaz\Services\ContentFiltering\Strategies\Implementations\PlaceholderEverythingContentFilteringStrategy;
use Fawaz\Services\ContentFiltering\Strategies\Implementations\StrictlyHideEverythingContentFilteringStrategy;
use Fawaz\Services\ContentFiltering\Types\ContentFilteringAction;
use Fawaz\Services\ContentFiltering\Types\ContentFilteringCases;
use Fawaz\Services\ContentFiltering\Types\ContentType;

final class PeerShopSpec implements Specification
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
                            FROM users PeerShopSpec_users
                            WHERE PeerShopSpec_users.uid = u.uid
                            AND PeerShopSpec_users.roles_mask != 32
                    )" ],
                    []
                ),
                ContentType::post => new SpecificationSQLData(
                    [
                    "EXISTS (
                        SELECT 1
                            FROM users PeerShopSpec_users
                            WHERE PeerShopSpec_users.uid = p.userid
                            AND PeerShopSpec_users.roles_mask != 32
                    )" ],
                    []
                ),
                ContentType::comment => null
                // ContentType::comment => new SpecificationSQLData(
                //     [
                //     "EXISTS (
                //         SELECT 1
                //             FROM users PeerShopSpec_users
                //             WHERE PeerShopSpec_users.uid = c.userid
                //             AND PeerShopSpec_users.roles_mask != 32
                //     )" ],
                //     []
                // ),
            };
        }
        return null;
    }

    public function toReplacer(
        ProfileReplaceable|PostReplaceable|CommentReplaceable $subject
    ): ?ContentReplacementPattern {
        return null;
    }

    public function forbidInteractions(string $targetContentId): ?SpecificationSQLData
    {
        return match ($this->targetContent) {
            ContentType::user => new SpecificationSQLData([
                "EXISTS (
                    SELECT 1
                        FROM users PeerShopSpec_users
                        WHERE PeerShopSpec_users.uid = :PeerShopSpec_userid
                        AND PeerShopSpec_users.roles_mask != 32
                )"
            ], [
                    "PeerShopSpec_userid" => $targetContentId
            ]),
            ContentType::post => new SpecificationSQLData([
                "EXISTS (
                    SELECT 1
                        FROM posts PeerShopSpec_posts
                        LEFT JOIN users PeerShopSpec_users ON PeerShopSpec_users.uid = PeerShopSpec_posts.userid
                        WHERE PeerShopSpec_posts.postid = :DeletedUserSpec_postid
                        AND PeerShopSpec_users.roles_mask  != 32
                )"
            ], [
                    "DeletedUserSpec_postid" => $targetContentId
            ]),
            ContentType::comment => null,
            // ContentType::comment => new SpecificationSQLData([
            //     "EXISTS (
            //         SELECT 1
            //             FROM comments PeerShopSpec_comments
            //             LEFT JOIN users PeerShopSpec_users ON PeerShopSpec_users.uid = PeerShopSpec_comments.userid
            //             WHERE PeerShopSpec_comments.commentid = :PeerShopSpec_commentid
            //             AND PeerShopSpec_users.roles_mask != 32
            //     )"
            // ], [
            //         "PeerShopSpec_commentid" => $targetContentId
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
