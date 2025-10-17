<?php

declare(strict_types=1);

namespace Fawaz\Database\Interfaces;

use Fawaz\App\Profile;
use Fawaz\App\Specs\Specification;

interface ProfileRepository
{
    /**
     * List basic users with minimal fields and constraints.
     * @return array<int, mixed>
     */
    public function fetchAll(string $currentUserId, array $args = []): array;

    /**
     * List advanced users with extra aggregates and follow flags.
     * @return array<int, mixed>
     */
    public function fetchAllAdvance(array $args = [], ?string $currentUserId = null, ?string $contentFilterBy = null): array;

    /**
     * Load a single profile, applying the provided specifications to constrain the query.
     * @param array<int, Specification> $specifications
     */
    public function fetchProfileData(string $userid, string $currentUserId, array $specifications): ?Profile;
}

