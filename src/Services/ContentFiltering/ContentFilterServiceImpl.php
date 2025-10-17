<?php

declare(strict_types=1);

namespace Fawaz\Services\ContentFiltering;

use Fawaz\config\constants\ConstantsConfig;
use Fawaz\Services\ContentFiltering\Strategies\ContentFilteringStrategyFactory;
use Fawaz\Services\ContentFiltering\Types\ContentFilteringAction;
use Fawaz\Services\ContentFiltering\Types\ContentFilteringStrategies;
use Fawaz\Services\ContentFiltering\Types\ContentType;
use Fawaz\Services\ContentFiltering\Types\ContentVisibility;

class ContentFilterServiceImpl {
    /** @var string[] Severity levels in order of importance */
    private array $contentSeverityLevels;
    
    /** @var array<string,int> Map of content type => reports amount to hide */
    private array $reports_amount_to_hide_content;
    private ContentFilteringStrategies $contentFilterStrategyTag;
    private ?string $contentFilterBy;

    public function __construct(
        ?ContentFilteringStrategies $contentFilterStrategyTag = ContentFilteringStrategies::searchByMeta,
        ?array $contentSeverityLevels = null,
        ?string $contentFilterBy = null
    ) {
        $contentFiltering = ConstantsConfig::contentFiltering();
        $this->contentSeverityLevels = $contentSeverityLevels ?? $contentFiltering['CONTENT_SEVERITY_LEVELS'];
        $this->reports_amount_to_hide_content = $contentFiltering['REPORTS_COUNT_TO_HIDE_FROM_IOS'];
        $this->contentFilterBy = $contentFilterBy;
        $this->contentFilterStrategyTag = $contentFilterStrategyTag;
    }

    public function validateContentFilter(?string $contentFilterBy): bool
    {
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

    public function getContentFilteringSeverityLevel(string $contentFilterBy): ?int
    {
        $allowedTypes = $this->contentSeverityLevels;

        $key = array_search($contentFilterBy, $allowedTypes);
        return $key;
    }

    public function getContentFilteringStringFromSeverityLevel(?int $contentFilterSeverityLevel): ?string
    {
        $allowedTypes = $this->contentSeverityLevels;

        if ($contentFilterSeverityLevel !== null && isset($allowedTypes[$contentFilterSeverityLevel]) && !empty($allowedTypes[$contentFilterSeverityLevel])) {
            return $allowedTypes[$contentFilterSeverityLevel];
        }
        return $this->getDefaultContentFilteringString();
    }

    public function getDefaultContentFilteringString(): ?string
    {
        $allowedTypes = $this->contentSeverityLevels;

        return null;
    }

     /**
     * @param ContentType $contentTarget
     * @param ContentType $showingContent
     * @param int|null $showingContentReportAmount
     * @param string|null $currentUserId
     * @param string|null $targetUserId
     * @return ContentFilteringAction|null
     */
    public function getContentFilterAction(
        ContentType $contentTarget,
        ContentType $showingContent,
        ?int $showingContentReportAmount = null,
        ?string $currentUserId = null,
        ?string $targetUserId = null,
    ): ?ContentFilteringAction {

        if (
            $showingContentReportAmount >= $this->reports_amount_to_hide_content && 
            $this->contentFilterBy === $this->contentSeverityLevels[0]
        ) {
            $strategy = ContentFilteringStrategyFactory::create(
                $this->contentFilterStrategyTag
            );

            if ($strategy === null) {
                return null;
            }
        
            if ($currentUserId && $currentUserId == $targetUserId) {
                $strategy = ContentFilteringStrategyFactory::create(ContentFilteringStrategies::profile);
            }

            return $strategy->getAction($contentTarget, $showingContent);
        }
        return null;
    }
}
