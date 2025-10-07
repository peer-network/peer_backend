<?php
declare(strict_types=1);

namespace Fawaz\Services\ContentFiltering\Types;

enum ContentType: string {
    case user = 'user' ;
    case post = 'post';
    case comment = 'comment';
}