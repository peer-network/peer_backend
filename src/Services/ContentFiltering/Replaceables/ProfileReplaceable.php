<?php

declare(strict_types=1);

namespace Fawaz\Services\ContentFiltering\Replaceables;

use Fawaz\Services\ContentFiltering\Capabilities\HasActiveReports;
use Fawaz\Services\ContentFiltering\Capabilities\HasUserId;
use Fawaz\Services\ContentFiltering\Capabilities\HasVisibilityStatus;

/**
 * Contract for profile-like subjects that can be replaced/masked.
 */
interface ProfileReplaceable extends HasVisibilityStatus, HasActiveReports, HasUserId
{
    public function getStatus(): int;

    public function getRolesmask(): int;

    public function getName(): string;

    public function getImg(): ?string;

    public function getBiography(): ?string;

    /** Setters for masking/updating values */
    public function setName(string $name): void;

    public function setImg(?string $img): void;

    public function setBiography(?string $biography): void;
}
