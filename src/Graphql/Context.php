<?php

declare(strict_types=1);

namespace Fawaz\GraphQL;

use Psr\Http\Message\ServerRequestInterface;

/**
 * Per-request GraphQL context passed to resolvers.
 */
class Context
{
    /** @param array<string,mixed> $dataloaders */
    public function __construct(
        public readonly ServerRequestInterface $request,
        public readonly ?array $user,
        public readonly ?string $token,
        public readonly array $dataloaders = [],
        public readonly ?string $userId = null,
        public readonly ?int $roles = null,
    ) {
    }
}
