<?php

namespace Fawaz\App\Services\ContentFiltering\Specs\SpecTypes\IllegalContent;

use Fawaz\App\Services\ContentFiltering\Specs\Specification;
use Fawaz\App\Services\ContentFiltering\Specs\SpecificationSQLData;
use Fawaz\config\ContentReplacementPattern;
use Fawaz\Services\ContentFiltering\ContentFilterServiceImpl;
use Fawaz\Services\ContentFiltering\Replaceables\ProfileReplaceable;
use Fawaz\Services\ContentFiltering\Replaceables\PostReplaceable;
use Fawaz\Services\ContentFiltering\Replaceables\CommentReplaceable;
use Fawaz\Services\ContentFiltering\Types\ContentType;


final class PlaceholderIllegalContentFilterSpec implements Specification
{
    private ContentFilterServiceImpl $contentFilterService;
    public function __construct() {}

    public function toSql(ContentType $targetContent): ?SpecificationSQLData
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