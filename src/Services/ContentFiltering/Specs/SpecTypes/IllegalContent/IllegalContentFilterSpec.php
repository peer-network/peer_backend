<?php

namespace Fawaz\Services\ContentFiltering\Specs\SpecTypes\IllegalContent;

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

final class IllegalContentFilterSpec implements Specification {

    private ContentFilterServiceImpl $contentFilterService;
    private ContentFilteringStrategy $contentFilterStrategy;

    public function __construct(
        ContentFilteringCases $case,
        ContentType $targetContent
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
                ContentType::user => new SpecificationSQLData([
                    "EXISTS (\n                        SELECT 1\n                            FROM users IllegalContentFilterSpec_users\n                            WHERE IllegalContentFilterSpec_users.uid = u.uid\n                            AND IllegalContentFilterSpec_users.visibility_status = 'illegal'\n                    )"
                ], []),
                ContentType::post => new SpecificationSQLData([
                    "EXISTS (\n                        SELECT 1\n                            FROM posts IllegalContentFilterSpec_posts\n                            WHERE IllegalContentFilterSpec_posts.postid = p.postid\n                            AND IllegalContentFilterSpec_posts.visibility_status = 'illegal'\n                    )"
                ], []),
                ContentType::comment => new SpecificationSQLData([
                    "EXISTS (\n                        SELECT 1\n                            FROM comments IllegalContentFilterSpec_comments\n                            WHERE IllegalContentFilterSpec_comments.commentid = c.commentid\n                            AND IllegalContentFilterSpec_comments.visibility_status = 'illegal'\n                    )"
                ], []),
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
        ) === ContentFilteringAction::replaceWithPlaceholder) {
            if ($subject->visibilityStatus() === 'illegal') {
                return ContentReplacementPattern::illegal;
            }
        }
        return null;
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
