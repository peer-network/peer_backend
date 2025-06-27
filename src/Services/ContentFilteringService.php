<?php

namespace Fawaz\Services;
use Fawaz\config\constants\ConstantsConfig;

class ContentFilteringService {
    public static function validateContentFilter(?string $contentFilterBy): bool {
        if ($contentFilterBy && !empty($contentFilterBy) && is_array($contentFilterBy)) {
            $allowedTypes = ConstantsConfig::moderationContent()['CONTENT_SEVERITY_LEVELS'];

            $invalidTypes = array_diff(array_map('strtoupper', $contentFilterBy), $allowedTypes);

            if (!empty($invalidTypes)) {
                return false;
            }
        }
        return true;
    }   
}