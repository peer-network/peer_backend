<?php

namespace Fawaz\App\Specs;

final class ContentFilterSpec implements Specification
{
    public function __construct(private array $allowedRatings) {}

    public function toSql(): string
    {
        return "p.rating IN (:ratings)";
    }

    public function getParameters(): array
    {
        return ['ratings' => $this->allowedRatings];
    }
}