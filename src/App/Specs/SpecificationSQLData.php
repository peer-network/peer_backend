<?php

namespace Fawaz\App\Specs;

final class SpecificationSQLData {
    public function __construct(
        public array $whereClauses,
        public array $paramsToPrepare
    ) {}

    /**
     * Merge an array of SpecificationSQLData into one.
     */
    public static function merge(array $items): self
    {
        $allWhere = [];
        $allParams = [];

        foreach ($items as $item) {
            if (!$item instanceof self) {
                continue;
            }

            $allWhere = array_merge($allWhere, $item->whereClauses);
            $allParams = array_merge($allParams, $item->paramsToPrepare);
        }

        return new self($allWhere, $allParams);
    }

}