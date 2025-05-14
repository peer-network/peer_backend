<?php
declare(strict_types=1);

namespace Fawaz\config\constants;

readonly class ValidationRange {
    public int $min;
    public int $max;

    public function __construct($min, $max) {
        $this->min = $min;
        $this->max = $max;
    }
}