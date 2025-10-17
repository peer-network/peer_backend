<?php

declare(strict_types=1);

namespace Fawaz\Services\ContentFiltering\Capabilities;

/**
 * Indicates whether a subject currently has active moderation reports.
 */
interface HasActiveReports
{
    /** True if there are active reports that should affect visibility/filtering. */
    public function getReports(): ?int;
}
