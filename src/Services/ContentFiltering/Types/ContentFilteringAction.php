<?php

declare(strict_types=1);

namespace Fawaz\Services\ContentFiltering\Types;

enum ContentFilteringAction: string
{
    case hideContent = 'hideContent';
    case replaceWithPlaceholder = 'replaceWithPlaceholder';
}
