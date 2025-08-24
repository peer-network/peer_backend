<?php

namespace Fawaz\App\Specs;

interface Specification
{
    public function toSql(): string;
    public function getParameters(): array;
}
