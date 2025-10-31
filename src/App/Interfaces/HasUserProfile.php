<?php

declare(strict_types=1);

namespace Fawaz\App\Interfaces;

use Fawaz\App\Profile;

/**
 * Marker interface for models that carry embedded user payloads.
 * Implementors should expose a user array suitable for serialization.
 */
interface HasUserProfile
{
    /**
     * Returns the embedded user payload (typically a Profile array).
     *
     * @return \Fawaz\App\Profile
     */
    public function getUserProfile(): ?Profile;

    /**
     * Sets or replaces the embedded user payload.
     *
     * @param \Fawaz\App\Profile $user
     */
    public function setUserProfile(Profile $user): void;
}

