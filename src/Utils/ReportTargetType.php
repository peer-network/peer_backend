<?php

namespace Fawaz\Utils;

enum ReportTargetType: string {
    case POST = 'post';
    case USER = 'user';
    case COMMENT = 'comment';  
}