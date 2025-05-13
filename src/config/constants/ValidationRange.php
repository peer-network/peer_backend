<?php
declare(strict_types=1);

namespace Fawaz\Config\Constants;

class ValidationRange {
    public readonly int $min;
    public readonly int $max;

    public function __construct($min, $max) {
        $this->max = $min;
        $this->max = $max;
    }
}