<?php

declare(strict_types=1);

namespace Fawaz\App\DTO;

use Fawaz\Utils\TokenCalculations\TokenHelper;

/**
 * Immutable row DTO for uncollected gems used during mint calculation.
 */
final class UncollectedGemsRow
{
    public function __construct(
        public readonly string $userid,
        public readonly string $gemid,
        public readonly string $postid,
        public readonly string $fromid,
        public readonly string $gems,
        public readonly int $whereby,
        public readonly string $createdat,
        public readonly string $totalGems,
        public readonly string $overallTotal,
        public readonly string $percentage,
    ) {}
}
