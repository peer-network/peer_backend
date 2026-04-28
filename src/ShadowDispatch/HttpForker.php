<?php

declare(strict_types=1);

namespace Fawaz\ShadowDispatch;

interface HttpForker
{
    /**
     * Issue a single HTTP request to the shadow target. Must not throw
     * on non-2xx responses — those are part of the verdict classification.
     * Should return status=null when the request never reached the target
     * (timeout, connection refused, dns failure).
     *
     * @param array<string,string> $headers
     * @return array{
     *     status: int|null,
     *     body: string|null,
     *     latency_ms: float,
     *     db_write_count: int
     * }
     */
    public function fork(
        string $method,
        string $url,
        array $headers,
        string $body,
        float $timeoutSeconds
    ): array;
}
