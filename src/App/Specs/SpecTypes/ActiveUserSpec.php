<?php

namespace Fawaz\App\Specs\SpecTypes;

use Fawaz\App\Specs\Specification;
use Fawaz\App\Specs\SpecificationSQLData;
use Fawaz\App\Status;
use Fawaz\Services\ContentFiltering\ContentReplacementPattern;
use Fawaz\Services\ContentFiltering\Replaceables\ProfileReplaceable;
use Fawaz\Services\ContentFiltering\Replaceables\PostReplaceable;
use Fawaz\Services\ContentFiltering\Replaceables\CommentReplaceable;

final class ActiveUserSpec implements Specification
{
    public function __construct(
        private string $userid
    ) {}

    
    // public function toSql(): SpecificationSQLData
    // {
    //     return new SpecificationSQLData(
    //         [
    //             "EXISTS (
    //             SELECT 1
    //             FROM users activeUserSpec_users
    //             WHERE activeUserSpec_users.uid = :activeUserSpec_users_userid
    //             AND activeUserSpec_users.status IN (0))"
    //         ],[
    //             "activeUserSpec_users_userid" => $this->userid
    //         ]
    //     );
    // }

    public function toSql(): SpecificationSQLData
    {
        return new SpecificationSQLData(
            [],
            []
        );
    }

    public function toReplacer(ProfileReplaceable|PostReplaceable|CommentReplaceable $subject): ?ContentReplacementPattern
    {
        if ($subject instanceof ProfileReplaceable) {
            if ($subject->getStatus() === Status::DELETED) {
                return ContentReplacementPattern::deleted;
            }
        }
        return null;
    }
}
