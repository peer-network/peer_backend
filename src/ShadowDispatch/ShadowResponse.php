<?php

declare(strict_types=1);

namespace Fawaz\ShadowDispatch;

final class ShadowResponse
{
    public function __construct(
        public readonly int $status,
        public readonly ?string $body,
        public readonly float $latencyMs,
        public readonly int $dbWriteCount = 0,
    ) {}
}
