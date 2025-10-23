<?php

namespace Fawaz\App\Specs\SpecTypes\User;

use Fawaz\App\Specs\Specification;
use Fawaz\App\Specs\SpecificationSQLData;
use Fawaz\config\ContentReplacementPattern;
use Fawaz\Services\ContentFiltering\Replaceables\ProfileReplaceable;
use Fawaz\Services\ContentFiltering\Replaceables\PostReplaceable;
use Fawaz\Services\ContentFiltering\Replaceables\CommentReplaceable;

final class UserIsBlockedByMeSpec implements Specification
{
    public function __construct(
        private string $userid,
        private string $blockedUserId,
    ) {}

    
    public function toSql(): SpecificationSQLData
    {
        return new SpecificationSQLData(
            [
                "NOT EXISTS (
                    SELECT 1
                    FROM user_block_user UserIsBlockedByMeSpec_user_block_user
                    WHERE UserIsBlockedByMeSpec_user_block_user.blockerid = :UserIsBlockedByMeSpec_user_block_user_userid
                    AND UserIsBlockedByMeSpec_user_block_user.blockedid = :UserIsBlockedByMeSpec_user_block_user_blockedUserId
                )"
            ],[
                "UserIsBlockedByMeSpec_user_block_user_userid" => $this->userid,
                "UserIsBlockedByMeSpec_user_block_user_blockedUserId" => $this->blockedUserId
            ]
        );
    }

    public function toReplacer(ProfileReplaceable|PostReplaceable|CommentReplaceable $subject): ?ContentReplacementPattern
    {
        return null;
    }
}
