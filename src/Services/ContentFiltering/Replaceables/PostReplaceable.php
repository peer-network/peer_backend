<?php

declare(strict_types=1);

namespace Fawaz\Services\ContentFiltering\Replaceables;

use Fawaz\Services\ContentFiltering\Capabilities\HasActiveReports;
use Fawaz\Services\ContentFiltering\Capabilities\HasVisibilityStatus;

/**
 * Marker interface for post-like subjects that can be replaced/masked.
 */
interface PostReplaceable extends HasVisibilityStatus, HasActiveReports {
    public function setTitle(string $titleConfig);
    public function setMedia(string $mediaPath);
    public function setDescription(string $descriptionConfig);
}

