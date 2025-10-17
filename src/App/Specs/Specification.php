<?php

namespace Fawaz\App\Specs;

use Fawaz\Services\ContentFiltering\ContentReplacementPattern;
use Fawaz\Services\ContentFiltering\Replaceables\ProfileReplaceable;
use Fawaz\Services\ContentFiltering\Replaceables\PostReplaceable;
use Fawaz\Services\ContentFiltering\Replaceables\CommentReplaceable;

interface Specification {
    public function toSql(): ?SpecificationSQLData;

    /**
     * Decide which replacement pattern should be applied based on the subject type/state.
     * Accepts a Profile, Post, or Comment object and returns a replacement pattern or null.
     */
    public function toReplacer(ProfileReplaceable|PostReplaceable|CommentReplaceable $subject): ?ContentReplacementPattern;
}
