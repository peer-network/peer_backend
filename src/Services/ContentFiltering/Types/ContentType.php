<?php

declare(strict_types=1);

namespace Fawaz\Services\ContentFiltering\Types;

/**
 * Enum ContentType
 *
 * Represents the type of content in the system.
 *
 * @method static self user()
 * @method static self post()
 * @method static self comment()
 */
enum ContentType: string {
    case user = 'USER' ;
    case post = 'POST';
    case comment = 'COMMENT';
}
