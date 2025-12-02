<?php

namespace Fawaz\Services\ContentFiltering\Specs\SpecTypes\HiddenContent;

use Fawaz\config\constants\ConstantsConfig;
use Fawaz\Services\ContentFiltering\Specs\Specification;
use Fawaz\Services\ContentFiltering\Specs\SpecificationSQLData;
use Fawaz\config\ContentReplacementPattern;
use Fawaz\Services\ContentFiltering\Replaceables\ProfileReplaceable;
use Fawaz\Services\ContentFiltering\Replaceables\PostReplaceable;
use Fawaz\Services\ContentFiltering\Replaceables\CommentReplaceable;
use Fawaz\Services\ContentFiltering\Types\ContentType;

final class NormalVisibilityStatusSpec implements Specification
{
    private array $contentSeverityLevels;
    public function __construct(
        private readonly ?string $contentFilterBy
    ) {
        $contentFiltering = ConstantsConfig::contentFiltering();
        $this->contentSeverityLevels = $contentFiltering['CONTENT_SEVERITY_LEVELS'];
    }

    public function toSql(ContentType $showingContent): ?SpecificationSQLData
    {
        return null;
    }

    public function toReplacer(
        ProfileReplaceable|PostReplaceable|CommentReplaceable $subject
    ): ?ContentReplacementPattern {
        if ($subject->visibilityStatus() === 'hidden' && ($this->contentFilterBy === null || $this->contentFilterBy !== $this->contentSeverityLevels[0])) {
            return ContentReplacementPattern::normal;
        }
        return null;
    }

    public function forbidInteractions(string $targetContentId): ?SpecificationSQLData
    {
        return null;
    }
}
