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
    case user = 'user' ;
    case post = 'post';
    case comment = 'comment';

    /**
     * Returns the uppercase representation of the enum value.
     *
     * @return string
     */
    public function constantsArrayKey(): string {
        return strtoupper($this->value);
    }
}
