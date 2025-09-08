<?php

namespace Fawaz\App\Specs\SpecTypes;

use Fawaz\App\Specs\Specification;
use Fawaz\App\Specs\SpecificationSQLData;

final class VerifiedAndActiveUserSpec implements Specification
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
                FROM users verified_and_active_user_spec_users
                WHERE verified_and_active_user_spec_users.userid = $this->userid
                AND verified_and_active_user_spec_users.verified == 1
                AND verified_and_active_user_spec_users.status == 0
                AND verified_and_active_user_spec_users.status IN (0,2,16))"
            ],
            []
        );
    }

    public function getParameters(): array
    {
        return [];
    }
}