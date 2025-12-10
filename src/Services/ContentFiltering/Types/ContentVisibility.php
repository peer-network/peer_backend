<?php

declare(strict_types=1);

namespace Fawaz\Services\ContentFiltering\Types;

enum ContentVisibility: string
{
    case normal  = 'normal';
    case hidden  = 'hidden';
    case illegal = 'illegal';
}
