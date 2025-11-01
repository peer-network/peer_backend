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

final class DeletedUserSpec implements Specification {

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
            match ($showingContent) {
                ContentType::user => new SpecificationSQLData(
                [
                    "EXISTS (
                        SELECT 1
                            FROM users DeletedUserSpec_users
                            WHERE DeletedUserSpec_users.uid = u.uid
                            AND DeletedUserSpec_users.status IN (0)
                    )" ],[]),
                ContentType::post => new SpecificationSQLData(
                [
                    "EXISTS (
                        SELECT 1
                            FROM users DeletedUserSpec_users
                            WHERE DeletedUserSpec_users.uid = p.userid
                            AND DeletedUserSpec_users.status IN (0)
                    )" ],[]),
                ContentType::comment => new SpecificationSQLData(
                [
                    "EXISTS (
                        SELECT 1
                            FROM users DeletedUserSpec_users
                            WHERE DeletedUserSpec_users.uid = c.userid
                            AND DeletedUserSpec_users.status IN (0)
                    )" ],[]),
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
