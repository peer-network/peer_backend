<?php

namespace Fawaz\Services\ContentFiltering\Types;

enum ContentFilteringAction: string
{
    case hideContent = 'hideContent';
    case replaceWithPlaceholder = 'replaceWithPlaceholder';
}
