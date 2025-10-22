<?php

namespace Fawaz\App\Specs;

use Fawaz\Services\ContentFiltering\ContentFilterServiceImpl;
use Fawaz\Services\ContentFiltering\Types\ContentFilteringAction;
use Fawaz\Services\ContentFiltering\Types\ContentType;
use function PHPUnit\Framework\returnArgument;


/**
 * Factory for building SQL specifications used in content filtering.
 *
 * This class generates conditional SQL fragments (WHERE clauses and
 * bound parameters) based on the given content type and filtering action.
 *
 * Responsibilities:
 * - Delegates thresholds (e.g., number of reports required to hide content,
 *   or dismissals needed to restore visibility) to ContentFilterServiceImpl.
 * - Produces parameterized SQL conditions specific to users, posts, or comments.
 * - Encapsulates filtering logic so that database queries can remain clean
 *   and reusable.
 *
 * Behavior:
 * - If the filtering action is "replaceWithPlaceholder", no SQL filtering
 *   is applied (returns null).
 * - If the filtering action is "hideContent", generates a SQL specification
 *   that hides content exceeding a report threshold unless:
 *   - It has been dismissed by moderation more times than the configured threshold, OR
 *   - The content belongs to the currently logged-in user (always visible to them).
 */
final class HiddenContentFilteringSpecificationFactory {
    public function __construct(
        private readonly ContentFilterServiceImpl $contentFilterService
    ) {}

    public function build(
        ContentType $type, 
        ?ContentFilteringAction $action
    ): ?SpecificationSQLData {
        $paramsToPrepare = [];
        $whereClauses = [];
        if ($action === ContentFilteringAction::hideContent) {
            switch ($type) {
            case ContentType::user:
                $whereClauses[] = "
                NOT EXISTS (
                    SELECT 1
                    FROM users HiddenContentFiltering_users
                    LEFT JOIN users_info HiddenContentFiltering_users_info ON HiddenContentFiltering_users_info.userid = HiddenContentFiltering_users.uid
                    WHERE HiddenContentFiltering_users.uid = u.uid
                    AND (
                        (HiddenContentFiltering_users_info.reports >= :user_report_amount_to_hide AND HiddenContentFiltering_users.visibility_status = 'normal')
                        OR 
                        HiddenContentFiltering_users.visibility_status = 'hidden'
                    )
                )";
                $paramsToPrepare["user_report_amount_to_hide"] = $this->contentFilterService->getReportsAmountToHideContent(ContentType::user);
                break;
            case ContentType::post:
                $whereClauses[] = "
                NOT EXISTS (
                    SELECT 1
                    FROM posts HiddenContentFiltering_posts
                    LEFT JOIN post_info HiddenContentFiltering_post_info ON HiddenContentFiltering_post_info.userid = HiddenContentFiltering_posts.userid
                    WHERE HiddenContentFiltering_posts.postid = p.postid
                    AND (
                        (HiddenContentFiltering_post_info.reports >= :post_report_amount_to_hide AND HiddenContentFiltering_posts.visibility_status = 'normal')
                        OR 
                        HiddenContentFiltering_posts.visibility_status = 'hidden'
                    )
                )";
                $paramsToPrepare["post_report_amount_to_hide"] = $this->contentFilterService->getReportsAmountToHideContent(ContentType::post);
                break;
            case ContentType::comment:
                $whereClauses[] = "
                NOT EXISTS (
                    SELECT 1
                    FROM comments HiddenContentFiltering_comments 
                    LEFT JOIN comment_info HiddenContentFiltering_comment_info ON HiddenContentFiltering_comment_info.userid = HiddenContentFiltering_comments.userid
                    WHERE HiddenContentFiltering_comments.commentid = c.commentid
                    AND (
                        (HiddenContentFiltering_comment_info.reports >= :comment_report_amount_to_hide AND HiddenContentFiltering_comments.visibility_status = 'normal')
                        OR 
                        HiddenContentFiltering_comments.visibility_status = 'hidden'
                    )
                )";
                $paramsToPrepare["comment_report_amount_to_hide"] = $this->contentFilterService->getReportsAmountToHideContent(ContentType::comment);
                break;
            default:
                return null;
            }
        } else {
            return null;
        }
        return new SpecificationSQLData(
            $whereClauses, 
            $paramsToPrepare
        );
    }
}
