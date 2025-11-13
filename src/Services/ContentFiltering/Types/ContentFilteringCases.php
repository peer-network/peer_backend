<?php

declare(strict_types=1);

namespace Fawaz\Services\ContentFiltering\Types;

enum ContentFilteringCases
{
    case postFeed;
    case searchById; 
    case searchByMeta;
    case myprofile;
    case hideAll;
}