<?php

namespace Fawaz\Services\ContentFiltering\Specs\SpecTypes\Advertisements;

use Fawaz\Services\ContentFiltering\Specs\Specification;
use Fawaz\Services\ContentFiltering\Specs\SpecificationSQLData;
use Fawaz\config\ContentReplacementPattern;
use Fawaz\Services\ContentFiltering\Replaceables\ProfileReplaceable;
use Fawaz\Services\ContentFiltering\Replaceables\PostReplaceable;
use Fawaz\Services\ContentFiltering\Replaceables\CommentReplaceable;
use Fawaz\Services\ContentFiltering\Types\ContentType;

final class ExcludeAdvertisementsForNormalFeedSpec implements Specification
{
    public function __construct(private readonly ?string $postId)
    {
    }

    public function toSql(ContentType $showingContent): ?SpecificationSQLData
    {
        if ($showingContent !== ContentType::post) {
            return null;
        }

        // If postid is not NULL, return NOT EXISTS (active advertisements)
        if ($this->postId !== null) {
            return new SpecificationSQLData([
                "NOT EXISTS (
                  SELECT 1
                  FROM advertisements a
                  WHERE a.postid = p.postid
                    AND a.timestart <= NOW()
                    AND a.timeend > NOW()
                )"
            ], []);
        }

        return null;
    }

    public function toReplacer(ProfileReplaceable|PostReplaceable|CommentReplaceable $subject): ?ContentReplacementPattern
    {
        return null;
    }
}

