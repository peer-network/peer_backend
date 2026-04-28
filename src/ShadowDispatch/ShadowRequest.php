<?php

declare(strict_types=1);

namespace Fawaz\ShadowDispatch;

final class ShadowRequest
{
    /**
     * @param array<string,string> $headers Lowercased header names.
     */
    public function __construct(
        public readonly string $method,
        public readonly string $path,
        public readonly string $query,
        public readonly array $headers,
        public readonly string $body,
        public readonly string $requestId,
        public readonly int $startUnixNano,
        public readonly ?string $graphqlOperationName = null,
    ) {}
}
