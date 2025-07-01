<?php

namespace Fawaz\Services\ContentFiltering;
use Fawaz\config\constants\ConstantsConfig;
use Fawaz\Services\ContentFiltering\Strategies\ContentFilteringStrategy;
use Fawaz\Services\ContentFiltering\Types\ContentFilteringAction;
use Fawaz\Services\ContentFiltering\Types\ContentType;

class ContentFilterServiceImpl {
    private array $contentSeverityLevels;
    private ContentFilteringStrategy $contentFilterStrategy;
    private ?string $contentFilterBy;
    
    function __construct(
        ContentFilteringStrategy $contentFilterStrategy,
        ?array $contentSeverityLevels = null,
        ?string $contentFilterBy = null
    ) {
        $contentFiltering = ConstantsConfig::contentFiltering();

        $this->contentSeverityLevels = $contentSeverityLevels ?? $contentFiltering['CONTENT_SEVERITY_LEVELS'];
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
        ContentType $showingContent
    ): ?ContentFilteringAction {
        if ($this->contentFilterBy === $this->contentSeverityLevels['0']) {
            return $this->contentFilterStrategy->getAction($contentTarget,$showingContent);
        }
        return null;
    }
}