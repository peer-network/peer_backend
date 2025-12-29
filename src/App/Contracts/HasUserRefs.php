<?php

declare(strict_types=1);

namespace Fawaz\App\Contracts;

use Fawaz\App\Profile;
use Fawaz\App\ReadModels\UserRef;

/**
 * Implemented by read models that expose one or more user references
 * that can be batch-enriched with profiles.
 */
interface HasUserRefs
{
    /**
     * Returns a list of user references present in the object.
     * Each ref has a stable key (e.g., 'sender', 'recipient').
     *
     * @return UserRef[]
     */
    public function getUserRefs(): array;

    /**
     * Attaches a hydrated profile for a given ref key.
     * Implementations decide where to store the enriched profile.
     */
    public function attachUserProfile(string $refKey, Profile $profile): void;
}

