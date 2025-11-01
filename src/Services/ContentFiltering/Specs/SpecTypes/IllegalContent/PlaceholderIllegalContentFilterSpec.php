<?php

namespace Fawaz\Services\ContentFiltering\Specs\SpecTypes\IllegalContent;

use Fawaz\Services\ContentFiltering\HiddenContentFilterServiceImpl;
use Fawaz\Services\ContentFiltering\Specs\Specification;
use Fawaz\Services\ContentFiltering\Specs\SpecificationSQLData;
use Fawaz\config\ContentReplacementPattern;
use Fawaz\Services\ContentFiltering\Replaceables\ProfileReplaceable;
use Fawaz\Services\ContentFiltering\Replaceables\PostReplaceable;
use Fawaz\Services\ContentFiltering\Replaceables\CommentReplaceable;
use Fawaz\Services\ContentFiltering\Types\ContentType;


final class PlaceholderIllegalContentFilterSpec implements Specification
{
    private HiddenContentFilterServiceImpl $contentFilterService;
    public function __construct() {}

    public function toSql(ContentType $showingContent): ?SpecificationSQLData
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