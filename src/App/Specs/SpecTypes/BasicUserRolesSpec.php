<?php

namespace Fawaz\App\Specs\SpecTypes;

use Fawaz\App\Specs\Specification;
use Fawaz\App\Specs\SpecificationSQLData;

final class BasicUserRolesSpec implements Specification
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
                FROM users activeUserSpec_users
                WHERE activeUserSpec_users.uid = :activeUserSpec_users_userid
                AND activeUserSpec_users.roles_mask IN (0,2,16))"
            ],[
                "activeUserSpec_users_userid" => $this->userid
            ]
        );
    }
}