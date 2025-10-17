<?php

namespace Fawaz\App\Specs\SpecTypes;

use Fawaz\App\Specs\Specification;
use Fawaz\App\Specs\SpecificationSQLData;
use Fawaz\Services\ContentFiltering\ContentReplacementPattern;
use Fawaz\Services\ContentFiltering\Replaceables\ProfileReplaceable;
use Fawaz\Services\ContentFiltering\Replaceables\PostReplaceable;
use Fawaz\Services\ContentFiltering\Replaceables\CommentReplaceable;
final class BasicUserSpec implements Specification
{

    public function __construct(
        private string $userid
    ) {}

    public function toSql(): SpecificationSQLData
    {
        return new SpecificationSQLData(
            [
                "EXISTS (
                    SELECT 1
                    FROM users basicUserSpec_users
                    WHERE basicUserSpec_users.uid = :basicUserSpec_users_userid AND
                    basicUserSpec_users.roles_mask IN (0,2,16) AND
                    basicUserSpec_users.verified = 1
                )"
            ],[
                "basicUserSpec_users_userid" => $this->userid
            ]
        );
    }

    public function toReplacer(ProfileReplaceable|PostReplaceable|CommentReplaceable $subject): ?ContentReplacementPattern
    {
        return null;
    }
}
