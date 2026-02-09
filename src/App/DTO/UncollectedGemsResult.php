<?php

declare(strict_types=1);

namespace Fawaz\App\DTO;

/**
 * Immutable DTO representing the full output of fetchUncollectedGemsForMint.
 */
final class UncollectedGemsResult
{
    /**
     * @param UncollectedGemsRow[] $rows
     */
    public function __construct(
        public readonly array $rows,
        public readonly string $overallTotal,
    ) {
    }
}
