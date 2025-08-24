<?php

namespace Fawaz\App\Specs;

interface Specification
{
    public function toSql(): ?SpecificationSQLData;
    // public function getParameters(): array;
}
