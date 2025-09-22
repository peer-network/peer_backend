<?php

namespace Fawaz\App\Specs\SpecTypes;

use Fawaz\App\Specs\Specification;
use Fawaz\App\Specs\SpecificationSQLData;

final class VerifiedUserSpec implements Specification
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
                    FROM users verified_user_spec_users
                    WHERE verified_user_spec_users.uid = :verified_user_spec_users_userid
                    AND verified_user_spec_users.verified = 1
                )"
            ],[
                "verified_user_spec_users_userid" => $this->userid
            ]
        );
    }

    public function getParameters(): array
    {
        return [];
    }
}