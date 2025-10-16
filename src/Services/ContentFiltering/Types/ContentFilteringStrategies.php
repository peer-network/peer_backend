<?php

declare(strict_types=1);

namespace Fawaz\Services\ContentFiltering\Types;

enum ContentFilteringStrategies
{
    case postFeed;
    case searchById; 
    case searchByMeta;
    case profile;
}