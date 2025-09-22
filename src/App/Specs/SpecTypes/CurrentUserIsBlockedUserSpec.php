<?php

namespace Fawaz\App\Specs\SpecTypes;

use Fawaz\App\Specs\Specification;
use Fawaz\App\Specs\SpecificationSQLData;

final class CurrentUserIsBlockedUserSpec implements Specification
{
    public function __construct(
        private string $userid,
        private string $blockerUserId,
    ) {}
    
    public function toSql(): SpecificationSQLData
    {
        return new SpecificationSQLData(
            [
                "NOT EXISTS (
                    SELECT 1
                    FROM user_block_user CurrentUserIsBlockedUserSpec_user_block_user
                    WHERE CurrentUserIsBlockedUserSpec_user_block_user.blockerid = :CurrentUserIsBlockedUserSpec_user_block_user_blockerUserId
                    AND CurrentUserIsBlockedUserSpec_user_block_user.blockedid = :CurrentUserIsBlockedUserSpec_user_block_user_userid
                )"
            ],[
                "CurrentUserIsBlockedUserSpec_user_block_user_userid" => $this->userid,
                "CurrentUserIsBlockedUserSpec_user_block_user_blockerUserId" => $this->blockerUserId
            ]
        );
    }
}