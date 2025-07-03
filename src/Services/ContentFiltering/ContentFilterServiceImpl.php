<?php

namespace Fawaz\Services\ContentFiltering;
use Fawaz\config\constants\ConstantsConfig;
use Fawaz\Services\ContentFiltering\Strategies\ContentFilteringStrategy;
use Fawaz\Services\ContentFiltering\Types\ContentFilteringAction;
use Fawaz\Services\ContentFiltering\Types\ContentType;

class ContentFilterServiceImpl {
    private array $contentSeverityLevels;
    private array $reports_amount_to_hide_content;
    private array $moderationsDismissAmountToRestoreContent;
    private ContentFilteringStrategy $contentFilterStrategy;
    private ?string $contentFilterBy;
    
    function __construct(
        ContentFilteringStrategy $contentFilterStrategy,
        ?array $contentSeverityLevels = null,
        ?string $contentFilterBy = null
    ) {
        $contentFiltering = ConstantsConfig::contentFiltering();

        $this->contentSeverityLevels = $contentSeverityLevels ?? $contentFiltering['CONTENT_SEVERITY_LEVELS'];
        $this->reports_amount_to_hide_content = $contentFiltering['REPORTS_COUNT_TO_HIDE_FROM_IOS'];
        $this->moderationsDismissAmountToRestoreContent = $contentFiltering['DISMISSING_MODERATION_COUNT_TO_RESTORE_TO_IOS'];
        $this->contentFilterStrategy = $contentFilterStrategy;
        $this->contentFilterBy = $contentFilterBy;
    }

    public function validateContentFilter(?string $contentFilterBy): bool {
        if (!empty($contentFilterBy) && is_array($contentFilterBy)) {
            $allowedTypes = $this->contentSeverityLevels;

            $invalidTypes = array_diff(array_map('strtoupper', $contentFilterBy), $allowedTypes);

            if (!empty($invalidTypes)) {
                return false;
            }
        }
        return true;
    }

    public function getContentFilterAction(
        ContentType $contentTarget, 
        ContentType $showingContent,
        ?int $showingContentReportAmount = null,
        ?int $showingContentDismissModerationAmount = null,
        ?string $currentUserId = null,
        ?string $targetUserId = null,
    ): ?ContentFilteringAction {
        if ($this->contentFilterBy === $this->contentSeverityLevels['0']) {
            $showingContentString = strtoupper($showingContent->value);

            $reportAmountToHide = $this->reports_amount_to_hide_content[$showingContentString];
            $dismissModerationAmounToHideFromIos = $this->moderationsDismissAmountToRestoreContent[$showingContentString];

            if (!$reportAmountToHide || !$dismissModerationAmounToHideFromIos) {
                return null;
            }
            
            // show all personal content
            if ($currentUserId && $currentUserId == $targetUserId){
                return null;
            }

            // if $showingContentReportAmount $showingContentDismissModerationAmount are null
            // (they can be null if filtration is performed in SQL query)
            // we are bypassing this comparison, because it will be performed in SQL query directly 
            if (
                !$showingContentReportAmount && !$showingContentDismissModerationAmount || 
                $showingContentReportAmount >= $reportAmountToHide &&
                $showingContentDismissModerationAmount < $dismissModerationAmounToHideFromIos
            ) {
                return $this->contentFilterStrategy->getAction($contentTarget,$showingContent);
            }
        }
        return null;
    }
}