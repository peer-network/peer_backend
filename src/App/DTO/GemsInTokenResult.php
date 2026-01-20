<?php

declare(strict_types=1);

namespace Fawaz\App\DTO;

/**
 * Carries computed values for a mint distribution run.
 */
final class GemsInTokenResult
{
    public function __construct(
        public readonly string $totalGems,
        public readonly string $gemsInToken,
        public readonly string $confirmation
    ) {}

    /**
     * Array shape used by GraphQL winStatus mapping when needed.
     */
    public function toWinStatusArray(): array
    {
        return [
            'totalGems' => $this->totalGems,
            'gemsintoken' => $this->gemsInToken,
            'bestatigung' => $this->confirmation,
        ];
    }
}
