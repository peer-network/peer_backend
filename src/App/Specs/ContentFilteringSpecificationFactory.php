<?php

namespace Fawaz\App\Specs;

use Fawaz\Services\ContentFiltering\ContentFilterServiceImpl;
use Fawaz\Services\ContentFiltering\Types\ContentFilteringAction;
use Fawaz\Services\ContentFiltering\Types\ContentType;


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
final class ContentFilteringSpecificationFactory {
    public function __construct(
        private readonly ContentFilterServiceImpl $contentFilterService
    ) {}

    public function build(ContentType $type, ContentFilteringAction $action): ?SpecificationSQLData
    {
        $paramsToPrepare = [];
        $whereClauses = [];

        switch ($action) {
            case ContentFilteringAction::replaceWithPlaceholder:
                return null;
            case ContentFilteringAction::hideContent:
                $paramsToPrepare["($type->value)_report_amount_to_hide"] = $this->contentFilterService->getReportsAmountToHideContent($type);
                $paramsToPrepare["($type->value)_dismiss_moderation_amount"] = $this->contentFilterService->moderationsDismissAmountToRestoreContent($type);
                switch ($type) {
                case ContentType::user:
                    $whereClauses[] = '((ui.reports < :user_report_amount_to_hide OR ui.count_content_moderation_dismissed > :user_dismiss_moderation_amount) OR u.userid = :currentUserId)';
                    break;
                case ContentType::post:
                    $whereClauses[] = '((pi.reports < :post_report_amount_to_hide OR pi.count_content_moderation_dismissed > :post_dismiss_moderation_amount) OR p.userid = :currentUserId)';
                    break;
                case ContentType::comment:
                    $whereClauses[] = '((ci.reports < :comment_report_amount_to_hide OR ci.count_content_moderation_dismissed > :comment_dismiss_moderation_amount) OR c.userid = :currentUserId)';
                    break;
                }
        }
        return new SpecificationSQLData($whereClauses, $paramsToPrepare);
    }
}