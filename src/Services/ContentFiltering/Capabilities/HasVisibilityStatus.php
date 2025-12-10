<?php

declare(strict_types=1);

namespace Fawaz\Services\ContentFiltering\Capabilities;

/**
 * Exposes a visibility status usable by content filtering/specs.
 */
interface HasVisibilityStatus
{
    /** Returns the visibility status code for this subject. */
    public function visibilityStatus(): string;

    /** Sets the visibility status code for this subject. */
    public function setVisibilityStatus(string $status): void;
}
