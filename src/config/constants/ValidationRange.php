<?php
declare(strict_types=1);

namespace Fawaz\Config\Constants;

readonly class ValidationRange {
    public int $min;
    public int $max;

    public function __construct($min, $max) {
        $this->min = $min;
        $this->max = $max;
    }
}