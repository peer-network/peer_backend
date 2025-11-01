<?php

namespace Fawaz\Services\ContentFiltering\Specs\SpecTypes\HiddenContent;

use Fawaz\Services\ContentFiltering\HiddenContentFilterServiceImpl;
use Fawaz\Services\ContentFiltering\Specs\Specification;
use Fawaz\Services\ContentFiltering\Specs\SpecificationSQLData;
use Fawaz\Services\ContentFiltering\Types\ContentFilteringAction;
use Fawaz\Services\ContentFiltering\Types\ContentFilteringCases;
use Fawaz\Services\ContentFiltering\Types\ContentType;
use Fawaz\config\ContentReplacementPattern;
use Fawaz\Services\ContentFiltering\Replaceables\ProfileReplaceable;
use Fawaz\Services\ContentFiltering\Replaceables\PostReplaceable;
use Fawaz\Services\ContentFiltering\Replaceables\CommentReplaceable;


final class HiddenContentFilterSpec implements Specification
{
    private HiddenContentFilterServiceImpl $contentFilterService;

    public function __construct(
        ContentFilteringCases $case, 
        ?string $contentFilterBy,
        private ContentType $contentTarget,
    ) {
        $this->contentFilterService = new HiddenContentFilterServiceImpl(
            $case,
            $contentFilterBy
        );
    }

    public function toSql(ContentType $showingContent): ?SpecificationSQLData
    {
        $action = $this->contentFilterService->getContentFilterAction(
            $this->contentTarget,
            $showingContent,
        );

        if ($action === ContentFilteringAction::hideContent) {
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
                    )"
                ], [
                    "user_report_amount_to_hide" => $this->contentFilterService->getReportsAmountToHideContent(ContentType::user)
                ]),
                ContentType::post => new SpecificationSQLData([
                    "NOT EXISTS (
                        SELECT 1
                        FROM posts HiddenContentFiltering_posts
                        LEFT JOIN post_info HiddenContentFiltering_post_info ON HiddenContentFiltering_post_info.userid = HiddenContentFiltering_posts.userid
                        WHERE HiddenContentFiltering_posts.postid = p.postid
                        AND (
                            (HiddenContentFiltering_post_info.reports >= :post_report_amount_to_hide AND HiddenContentFiltering_posts.visibility_status = 'normal')
                            OR 
                            HiddenContentFiltering_posts.visibility_status = 'hidden'
                        )
                    )"
                ], [
                    "post_report_amount_to_hide" => $this->contentFilterService->getReportsAmountToHideContent(ContentType::post)
                ]),
                ContentType::comment => new SpecificationSQLData([
                    "NOT EXISTS (
                        SELECT 1
                        FROM comments HiddenContentFiltering_comments 
                        LEFT JOIN comment_info HiddenContentFiltering_comment_info ON HiddenContentFiltering_comment_info.userid = HiddenContentFiltering_comments.userid
                        WHERE HiddenContentFiltering_comments.commentid = c.commentid
                        AND (
                            (HiddenContentFiltering_comment_info.reports >= :comment_report_amount_to_hide AND HiddenContentFiltering_comments.visibility_status = 'normal')
                            OR 
                            HiddenContentFiltering_comments.visibility_status = 'hidden'
                        )
                    )"
                ], [
                    "comment_report_amount_to_hide" => $this->contentFilterService->getReportsAmountToHideContent(ContentType::comment)
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
            $this->contentTarget,
                $showingContent,
                $subject->getReports(),
                $subject->visibilityStatus()
        );
        if ($action === ContentFilteringAction::replaceWithPlaceholder) {
            return ContentReplacementPattern::hidden;
        }
        return null;
    }
}
