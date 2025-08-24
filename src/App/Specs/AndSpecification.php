<?php

namespace Fawaz\App\Specs;

final class AndSpecification implements Specification
{
    public function __construct(private array $specs) {}

    public function toSql(): string
    {
        return implode(' AND ', array_map(fn($s) => '(' . $s->toSql() . ')', $this->specs));
    }

    public function getParameters(): array
    {
        return array_merge(...array_map(fn($s) => $s->getParameters(), $this->specs));
    }
}
