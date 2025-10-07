<?php

declare(strict_types=1);

namespace Fawaz\Utils;

enum ReportTargetType: string
{
    case POST = 'post';
    case USER = 'user';
    case COMMENT = 'comment';
}
