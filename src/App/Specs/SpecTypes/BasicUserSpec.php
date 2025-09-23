<?php

namespace Fawaz\App\Specs\SpecTypes;

use Fawaz\App\Specs\Specification;
use Fawaz\App\Specs\SpecificationSQLData;

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
                basicUserSpec_users.roles_mask IN (0,2,16)) AND
                basicUserSpec_users.verified = 1"
            ],[
                "basicUserSpec_users_userid" => $this->userid
            ]
        );
    }
}