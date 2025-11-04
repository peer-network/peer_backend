<?php

namespace Fawaz\Services\ContentFiltering\Specs;

use Fawaz\config\ContentReplacementPattern;
use Fawaz\Services\ContentFiltering\Replaceables\ProfileReplaceable;
use Fawaz\Services\ContentFiltering\Replaceables\PostReplaceable;
use Fawaz\Services\ContentFiltering\Replaceables\CommentReplaceable;
use Fawaz\Services\ContentFiltering\Types\ContentType;

interface Specification {
    public function toSql(ContentType $showingContent): ?SpecificationSQLData;

    public function toReplacer(ProfileReplaceable|PostReplaceable|CommentReplaceable $subject): ?ContentReplacementPattern;

    // public function forbidInteractions(ContentType $targetContent, string $targetContentId): bool;
}
