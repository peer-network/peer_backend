<?php

declare(strict_types=1);

namespace Fawaz\Database\Interfaces;

interface InteractionsPermissionsMapper
{
    public function isInteractionAllowed(array $specs, string $targetContentId): bool;
}
