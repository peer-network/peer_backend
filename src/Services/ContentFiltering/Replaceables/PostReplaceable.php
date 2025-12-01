<?php

declare(strict_types=1);

namespace Fawaz\Services\ContentFiltering\Replaceables;

use Fawaz\Services\ContentFiltering\Capabilities\HasActiveReports;
use Fawaz\Services\ContentFiltering\Capabilities\HasUserId;
use Fawaz\Services\ContentFiltering\Capabilities\HasVisibilityStatus;

/**
 * Marker interface for post-like subjects that can be replaced/masked.
 */
interface PostReplaceable extends HasVisibilityStatus, HasActiveReports, HasUserId
{
    public function setTitle(string $titleConfig);
    public function setMedia(string $mediaPath);
    public function setCover(string $mediaPath);
    public function setContentType(string $contentType);
    public function setDescription(string $descriptionConfig);
}
