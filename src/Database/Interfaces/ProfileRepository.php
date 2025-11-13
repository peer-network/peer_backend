<?php

declare(strict_types=1);

namespace Fawaz\Database\Interfaces;

use Fawaz\App\Profile;
use Fawaz\Services\ContentFiltering\Specs\Specification;

interface ProfileRepository
{
    /**
     * Load a single profile, applying the provided specifications to constrain the query.
     * @param array<int, Specification> $specifications
     */
    public function fetchProfileData(string $userid, string $currentUserId, array $specifications): ?Profile;

    /**
     * Fetch multiple profiles by IDs with optional specifications.
     * @param array<int, string> $userIds
     * @param array<int, Specification> $specifications
     * @return array<string,Profile>
     */
    public function fetchByIds(array $userIds, string $currentUserId, array $specifications = []): array;
}
