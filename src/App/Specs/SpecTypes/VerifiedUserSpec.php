<?php

namespace Fawaz\App\Specs\SpecTypes;

use Fawaz\App\Specs\Specification;
use Fawaz\App\Specs\SpecificationSQLData;

final class VerifiedUserSpec implements Specification
{
    public function __construct() {}

    
    public function toSql(): SpecificationSQLData
    {
        return new SpecificationSQLData(
            ["u.verified = :verified"],
            ["verified" => 1]
        );
    }

    public function getParameters(): array
    {
        return [];
    }
}