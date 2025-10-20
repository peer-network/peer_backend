<?php

namespace Fawaz\App\Specs\SpecTypes;

use Fawaz\App\Specs\Specification;
use Fawaz\App\Specs\SpecificationSQLData;
use Fawaz\config\ContentReplacementPattern;
use Fawaz\Services\ContentFiltering\Capabilities\HasVisibilityStatus;
use Fawaz\Services\ContentFiltering\ContentFilterServiceImpl;
use Fawaz\Services\ContentFiltering\Replaceables\ProfileReplaceable;
use Fawaz\Services\ContentFiltering\Replaceables\PostReplaceable;
use Fawaz\Services\ContentFiltering\Replaceables\CommentReplaceable;


final class PlaceholderIllegalContentFilterSpec implements Specification
{
    private ContentFilterServiceImpl $contentFilterService;
    public function __construct() {}

    public function toSql(): ?SpecificationSQLData
    {
        return null;
    }

    public function toReplacer(ProfileReplaceable|PostReplaceable|CommentReplaceable $subject): ?ContentReplacementPattern
    {
        if ($subject->visibilityStatus() === 'illegal') {
            return ContentReplacementPattern::illegal;
        }
        return null;
    }
}