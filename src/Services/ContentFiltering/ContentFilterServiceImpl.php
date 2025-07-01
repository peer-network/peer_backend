<?php

namespace Fawaz\Services\ContentFiltering;
use Fawaz\config\constants\ConstantsConfig;

class ContentFilterServiceImpl {
    private array $contentSeverityLevels;
    
    function __construct(?array $contentSeverityLevels = null) {
        $contentFiltering = ConstantsConfig::contentFiltering();

        $this->contentSeverityLevels = $contentSeverityLevels ?? $contentFiltering['CONTENT_SEVERITY_LEVELS'];
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
}