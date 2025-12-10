<?php

namespace Fawaz\Services\ContentFiltering\Specs\SpecTypes\HiddenContent;

use Fawaz\config\ContentReplacementPattern;
use Fawaz\Services\ContentFiltering\HiddenContentFilterServiceImpl;
use Fawaz\Services\ContentFiltering\Replaceables\CommentReplaceable;
use Fawaz\Services\ContentFiltering\Replaceables\PostReplaceable;
use Fawaz\Services\ContentFiltering\Replaceables\ProfileReplaceable;
use Fawaz\Services\ContentFiltering\Specs\Specification;
use Fawaz\Services\ContentFiltering\Specs\SpecificationSQLData;
use Fawaz\Services\ContentFiltering\Strategies\ContentFilteringStrategy;
use Fawaz\Services\ContentFiltering\Strategies\Implementations\DoNothingContentFilteringStrategy;
use Fawaz\Services\ContentFiltering\Strategies\Implementations\HidePostsElsePlaceholder;
use Fawaz\Services\ContentFiltering\Strategies\Implementations\PlaceholderEverythingContentFilteringStrategy;
use Fawaz\Services\ContentFiltering\Strategies\Implementations\StrictlyHideEverythingContentFilteringStrategy;
use Fawaz\Services\ContentFiltering\Types\ContentFilteringAction;
use Fawaz\Services\ContentFiltering\Types\ContentFilteringCases;
use Fawaz\Services\ContentFiltering\Types\ContentType;

final class HiddenContentFilterSpec implements Specification
{
    private HiddenContentFilterServiceImpl $contentFilterService;
    private ContentFilteringStrategy $contentFilterStrategy;

    public function __construct(
        ContentFilteringCases $case,
        ?string $contentFilterBy,
        ContentType $contentTarget,
        private string $currentUserId,
    ) {
        $this->contentFilterService = new HiddenContentFilterServiceImpl(
            $contentTarget,
            $contentFilterBy
        );
        $this->contentFilterStrategy = self::createStrategy(
            $case
        );
    }

    public function toSql(ContentType $showingContent): ?SpecificationSQLData
    {
        $action = $this->contentFilterService->getContentFilterAction(
            $showingContent,
            $this->contentFilterStrategy
        );

        if (ContentFilteringAction::hideContent === $action) {
            return match ($showingContent) {
                ContentType::user => new SpecificationSQLData([
                    "NOT EXISTS (
                        SELECT 1
                        FROM users HiddenContentFiltering_users
                        LEFT JOIN users_info HiddenContentFiltering_users_info ON HiddenContentFiltering_users_info.userid = HiddenContentFiltering_users.uid
                        WHERE HiddenContentFiltering_users.uid = u.uid
                        AND (
                            (HiddenContentFiltering_users_info.reports >= :user_report_amount_to_hide AND HiddenContentFiltering_users.visibility_status = 'normal')
                            OR 
                            HiddenContentFiltering_users.visibility_status = 'hidden'
                        )
                        AND 
                        HiddenContentFiltering_users.userid != :HiddenContentFiltering_currentUserId
                    )",
                ], [
                    'user_report_amount_to_hide'           => $this->contentFilterService->getReportsAmountToHideContent(ContentType::user),
                    'HiddenContentFiltering_currentUserId' => $this->currentUserId,
                ]),
                ContentType::post => new SpecificationSQLData([
                    "NOT EXISTS (
                        SELECT 1
                        FROM posts HiddenContentFiltering_posts
                        LEFT JOIN post_info HiddenContentFiltering_post_info ON HiddenContentFiltering_post_info.postid = HiddenContentFiltering_posts.postid
                        WHERE HiddenContentFiltering_posts.postid = p.postid
                        AND (
                            (HiddenContentFiltering_post_info.reports >= :post_report_amount_to_hide AND HiddenContentFiltering_posts.visibility_status = 'normal')
                            OR 
                            HiddenContentFiltering_posts.visibility_status = 'hidden'
                        )
                        AND 
                        HiddenContentFiltering_posts.userid != :HiddenContentFiltering_currentUserId
                    )",
                ], [
                    'post_report_amount_to_hide'           => $this->contentFilterService->getReportsAmountToHideContent(ContentType::post),
                    'HiddenContentFiltering_currentUserId' => $this->currentUserId,
                ]),
                ContentType::comment => new SpecificationSQLData([
                    "NOT EXISTS (
                        SELECT 1
                        FROM comments HiddenContentFiltering_comments 
                        LEFT JOIN comment_info HiddenContentFiltering_comment_info ON HiddenContentFiltering_comment_info.commentid = HiddenContentFiltering_comments.commentid
                        WHERE HiddenContentFiltering_comments.commentid = c.commentid
                        AND (
                            (HiddenContentFiltering_comment_info.reports >= :comment_report_amount_to_hide AND HiddenContentFiltering_comments.visibility_status = 'normal')
                            OR 
                            HiddenContentFiltering_comments.visibility_status = 'hidden'
                        )
                        AND 
                        HiddenContentFiltering_comments.userid != :HiddenContentFiltering_currentUserId
                    )",
                ], [
                    'comment_report_amount_to_hide'        => $this->contentFilterService->getReportsAmountToHideContent(ContentType::comment),
                    'HiddenContentFiltering_currentUserId' => $this->currentUserId,
                ]),
            };
        }

        return null;
    }

    public function toReplacer(ProfileReplaceable|PostReplaceable|CommentReplaceable $subject): ?ContentReplacementPattern
    {
        if ($subject instanceof ProfileReplaceable) {
            $showingContent = ContentType::user;
        } elseif ($subject instanceof CommentReplaceable) {
            $showingContent = ContentType::comment;
        } else {
            $showingContent = ContentType::post;
        }
        $action = $this->contentFilterService->getContentFilterAction(
            $showingContent,
            $this->contentFilterStrategy,
            $subject->getActiveReports(),
            $subject->visibilityStatus()
        );

        if ($subject->getUserId() === $this->currentUserId) {
            return ContentReplacementPattern::normal;
        }

        if (ContentFilteringAction::replaceWithPlaceholder === $action) {
            return ContentReplacementPattern::hidden;
        }

        return null;
    }

    private static function createStrategy(
        ContentFilteringCases $strategy,
    ): ContentFilteringStrategy {
        return match ($strategy) {
            ContentFilteringCases::myprofile    => new DoNothingContentFilteringStrategy(),
            ContentFilteringCases::searchById   => new PlaceholderEverythingContentFilteringStrategy(),
            ContentFilteringCases::searchByMeta => new PlaceholderEverythingContentFilteringStrategy(),
            ContentFilteringCases::postFeed     => new HidePostsElsePlaceholder(),
            ContentFilteringCases::hideAll      => new StrictlyHideEverythingContentFilteringStrategy(),
        };
    }

    public function forbidInteractions(string $targetContentId): ?SpecificationSQLData
    {
        return null;
    }
}
