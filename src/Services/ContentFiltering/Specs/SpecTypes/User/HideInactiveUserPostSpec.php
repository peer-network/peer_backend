<?php

namespace Fawaz\App\Services\ContentFiltering\Specs\SpecTypes\User;

use Fawaz\App\Services\ContentFiltering\Specs\Specification;
use Fawaz\App\Services\ContentFiltering\Specs\SpecificationSQLData;
use Fawaz\config\ContentReplacementPattern;
use Fawaz\Services\ContentFiltering\Replaceables\ProfileReplaceable;
use Fawaz\Services\ContentFiltering\Replaceables\PostReplaceable;
use Fawaz\Services\ContentFiltering\Replaceables\CommentReplaceable;
use Fawaz\Services\ContentFiltering\Types\ContentType;

final class HideInactiveUserPostSpec implements Specification
{
    public function __construct(
    ) {}

    
    public function toSql(ContentType $targetContent): SpecificationSQLData
    {
        return new SpecificationSQLData(
            [
                "EXISTS (
                SELECT 1
                FROM users activeUserSpec_users
                WHERE activeUserSpec_users.uid = p.userid
                AND activeUserSpec_users.status IN (0))"
            ],
            []
        );
    }

    public function toReplacer(
        ProfileReplaceable|PostReplaceable|CommentReplaceable $subject
    ): ?ContentReplacementPattern { 
        return null;
    }
}
