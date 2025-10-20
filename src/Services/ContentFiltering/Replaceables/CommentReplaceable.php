<?php

declare(strict_types=1);

namespace Fawaz\Services\ContentFiltering\Replaceables;

use Fawaz\Services\ContentFiltering\Capabilities\HasActiveReports;
use Fawaz\Services\ContentFiltering\Capabilities\HasVisibilityStatus;

/**
 * Marker interface for comment-like subjects that can be replaced/masked.
 */
interface CommentReplaceable extends HasVisibilityStatus, HasActiveReports {}

