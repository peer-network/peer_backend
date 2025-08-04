<?php
declare(strict_types=1);

namespace Fawaz\Services\ContentFiltering;
use Fawaz\config\constants\ConstantsConfig;
use Fawaz\Services\ContentFiltering\Strategies\ContentFilteringStrategy;
use Fawaz\Services\ContentFiltering\Strategies\ListPostsContentFilteringStrategy;
use Fawaz\Services\ContentFiltering\Types\ContentFilteringAction;
use Fawaz\Services\ContentFiltering\Types\ContentType;

class ContentFilterServiceImpl {
    private array $contentSeverityLevels;
    private array $reports_amount_to_hide_content;
    private array $moderationsDismissAmountToRestoreContent;
    private ContentFilteringStrategy $contentFilterStrategy;
    private ?string $contentFilterBy;
    
    function __construct(
        ?ContentFilteringStrategy $contentFilterStrategy = null,
        ?array $contentSeverityLevels = null,
        ?string $contentFilterBy = null
    ) {
        $contentFiltering = ConstantsConfig::contentFiltering();
        $this->contentSeverityLevels = $contentSeverityLevels ?? $contentFiltering['CONTENT_SEVERITY_LEVELS'];
        $this->reports_amount_to_hide_content = $contentFiltering['REPORTS_COUNT_TO_HIDE_FROM_IOS'];
        $this->moderationsDismissAmountToRestoreContent = $contentFiltering['DISMISSING_MODERATION_COUNT_TO_RESTORE_TO_IOS'];
        
        $this->contentFilterStrategy = $contentFilterStrategy ?? new ListPostsContentFilteringStrategy();
        
        $this->contentFilterBy = $contentFilterBy;
    }

    public function validateContentFilter(?string $contentFilterBy): bool {
        if (!empty($contentFilterBy) && is_array($contentFilterBy)) {
            $allowedTypes = $this->contentSeverityLevels;

            $invalidTypes = array_diff(array_map('strtoupper', $contentFilterBy), $allowedTypes);

            if (!empty($invalidTypes)) {
                // echo("ContentFilterServiceImpl: validateContentFilter: invalid contentFilter: $contentFilterBy" . "\n");
                return false;
            }
        }
        return true;
    }

    public function getContentFilteringSeverityLevel(string $contentFilterBy): ?int {
        $allowedTypes = $this->contentSeverityLevels;

        $key = array_search($contentFilterBy, $allowedTypes);
        return $key;
    }

    public function getContentFilteringStringFromSeverityLevel(?int $contentFilterSeverityLevel): ?string {
        $allowedTypes = $this->contentSeverityLevels;
        
        if ($contentFilterSeverityLevel && isset($allowedTypes[$contentFilterSeverityLevel]) && !empty($allowedTypes[$contentFilterSeverityLevel])) {
            return $allowedTypes[$contentFilterSeverityLevel];
        }
        return $this->getDefaultContentFilteringString();
    }

    public function getDefaultContentFilteringString(): string {
        $allowedTypes = $this->contentSeverityLevels;
    
        return $allowedTypes[0];
    }

    public function getContentFilterAction(
        ContentType $contentTarget, 
        ContentType $showingContent,
        ?int $showingContentReportAmount = null,
        ?int $showingContentDismissModerationAmount = null,
        ?string $currentUserId = null,
        ?string $targetUserId = null,
    ): ?ContentFilteringAction {
        if ($this->contentFilterBy === $this->contentSeverityLevels[0]) {
            $showingContentString = strtoupper($showingContent->value);

            $reportAmountToHide = $this->reports_amount_to_hide_content[$showingContentString];
            $dismissModerationAmounToHideFromIos = $this->moderationsDismissAmountToRestoreContent[$showingContentString];


            if (!$reportAmountToHide || !$dismissModerationAmounToHideFromIos) {
                // echo("GetContentFilterAction: getContentFilterAction: reportAmountToHide, dismissModerationAmounToHideFromIos is null" . "\n");
                return null;
            }
            
            // show all personal content
            if ($currentUserId && $currentUserId == $targetUserId){
                return null;
            }

            if ($showingContentReportAmount === null && $showingContentDismissModerationAmount === null){
                return $this->contentFilterStrategy->getAction($contentTarget,$showingContent);
            }

            if (
                $showingContentReportAmount >= $reportAmountToHide &&
                $showingContentDismissModerationAmount < $dismissModerationAmounToHideFromIos
            ) {
                // echo("GetContentFilterAction: getContentFilterAction: start: $this->contentFilterBy" . " / target: " .$contentTarget->value . " / show: " . $contentTarget->value . "\n");
                return $this->contentFilterStrategy->getAction($contentTarget,$showingContent);
            }
        }
        return null;
    }
}