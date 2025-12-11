<?php

declare(strict_types=1);

namespace Fawaz\Services\ContentFiltering\Types;

/**
 * Enum ContentFilteringAction.
 *
 * Represents what action to take when content is filtered.
 *
 * @method static self hideContent()
 * @method static self replaceWithPlaceholder()
 */
enum ContentFilteringAction: string
{
    case hideContent            = 'hideContent';
    case replaceWithPlaceholder = 'replaceWithPlaceholder';
}
