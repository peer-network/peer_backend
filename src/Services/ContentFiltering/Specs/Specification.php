<?php

namespace Fawaz\Services\ContentFiltering\Specs;

use Fawaz\config\ContentReplacementPattern;
use Fawaz\Services\ContentFiltering\Replaceables\ProfileReplaceable;
use Fawaz\Services\ContentFiltering\Replaceables\PostReplaceable;
use Fawaz\Services\ContentFiltering\Replaceables\CommentReplaceable;
use Fawaz\Services\ContentFiltering\Types\ContentType;

interface Specification {
    public function toSql(ContentType $showingContent): ?SpecificationSQLData;

    /**
     * Decide which replacement pattern should be applied based on the subject type/state.
     * Accepts a Profile, Post, or Comment object and returns a replacement pattern or null.
     */
    public function toReplacer(ProfileReplaceable|PostReplaceable|CommentReplaceable $subject): ?ContentReplacementPattern;

    // private static function createStrategy(ContentFilteringCases $strategy): ContentFilteringStrategy;
}
