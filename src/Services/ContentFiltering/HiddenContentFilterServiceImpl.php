<?php

declare(strict_types=1);

namespace Fawaz\Services\ContentFiltering;

use Fawaz\config\constants\ConstantsConfig;
use Fawaz\Services\ContentFiltering\Strategies\ContentFilteringStrategyFactory;
use Fawaz\Services\ContentFiltering\Types\ContentFilteringAction;
use Fawaz\Services\ContentFiltering\Types\ContentFilteringCases;
use Fawaz\Services\ContentFiltering\Types\ContentType;

class HiddenContentFilterServiceImpl  {
    /** @var string[] Severity levels in order of importance */
    private array $contentSeverityLevels;
    
    /** @var array<string,int> Map of content type => reports amount to hide */
    private array $reports_amount_to_hide_content;
    private ContentFilteringCases $contentFilterCase;
    private ?string $contentFilterBy;

    public function __construct(
        ?ContentFilteringCases $contentFilterCase,
        ?string $contentFilterBy = null
    ) {
        $contentFiltering = ConstantsConfig::contentFiltering();
        $this->contentSeverityLevels = $contentFiltering['CONTENT_SEVERITY_LEVELS'];
        $this->reports_amount_to_hide_content = $contentFiltering['REPORTS_COUNT_TO_HIDE_FROM_IOS'];
        $this->contentFilterBy = $contentFilterBy;
        $this->contentFilterCase = $contentFilterCase;
    }

    public static function getContentFilteringSeverityLevel(string $contentFilterBy): ?int
    {
        $contentFiltering = ConstantsConfig::contentFiltering();
        $contentSeverityLevels = $contentFiltering['CONTENT_SEVERITY_LEVELS'];

        $allowedTypes = $contentSeverityLevels;

        $key = array_search($contentFilterBy, $allowedTypes);
        return $key;
    }

    public static function getContentFilteringStringFromSeverityLevel(?int $contentFilterSeverityLevel): ?string
    {
        $contentFiltering = ConstantsConfig::contentFiltering();
        $contentSeverityLevels = $contentFiltering['CONTENT_SEVERITY_LEVELS'];
        $allowedTypes = $contentSeverityLevels;

        if ($contentFilterSeverityLevel !== null && isset($allowedTypes[$contentFilterSeverityLevel]) && !empty($allowedTypes[$contentFilterSeverityLevel])) {
            return $allowedTypes[$contentFilterSeverityLevel];
        }
        return null;
    }

    public function getReportsAmountToHideContent(ContentType $type): int {
        return $this->reports_amount_to_hide_content[$type->value];
    }

     /**
     * @param ContentType $contentTarget
     * @param ContentType $showingContent
     * @param int|null $showingContentReportAmount
     * @param string|null $visibilityStatus
     * @return ContentFilteringAction|null
     */
    public function getContentFilterAction(
        ContentType $contentTarget,
        ContentType $showingContent,
        ?int $showingContentReportAmount = null,
        ?string $visibilityStatus = null,
    ): ?ContentFilteringAction {
        if ($visibilityStatus === null) {
            if ($showingContentReportAmount === null && $this->contentFilterBy === $this->contentSeverityLevels[0]) {
                
                $strategy = ContentFilteringStrategyFactory::create($this->contentFilterCase);
                
                if ($strategy === null) {
                    return null;
                }
                return $strategy::getAction($contentTarget, $showingContent);
            }
        } else {
            if (( $visibilityStatus === "hidden" || ( $visibilityStatus === "normal" && $showingContentReportAmount >= $this->getReportsAmountToHideContent($showingContent))) && 
            $this->contentFilterBy === $this->contentSeverityLevels[0]
        ) {
            $strategy = ContentFilteringStrategyFactory::create(
                $this->contentFilterCase
            );
            
            if ($strategy === null) {
                return null;
            }
            return $strategy::getAction($contentTarget, $showingContent);
        }
        }
        return null;
    }
}
