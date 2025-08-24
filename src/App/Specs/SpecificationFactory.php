<?php

namespace Fawaz\App\Specs;

use Fawaz\Services\ContentFiltering\ContentFilterServiceImpl;
use Fawaz\Services\ContentFiltering\Types\ContentFilteringAction;
use Fawaz\Services\ContentFiltering\Types\ContentType;

final class SpecificationFactory {
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