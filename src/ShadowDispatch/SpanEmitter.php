<?php

declare(strict_types=1);

namespace Fawaz\ShadowDispatch;

interface SpanEmitter
{
    /**
     * Emit one span with the given attributes. Implementations must be
     * non-throwing on transport failures — return false to signal a
     * recoverable failure (caller routes to DLQ); throwing is reserved
     * for programmer errors.
     *
     * @param array<string,mixed> $attributes
     */
    public function emit(
        string $name,
        int $startUnixNano,
        int $endUnixNano,
        string $traceIdHex,
        string $spanIdHex,
        array $attributes,
        int $statusCode = 0
    ): bool;
}
