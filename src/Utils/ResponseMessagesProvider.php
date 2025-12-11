<?php

declare(strict_types=1);

namespace Fawaz\Utils;

interface ResponseMessagesProvider
{
    /**
     * Get a specific message by code.
     */
    public function getMessage(string $code): ?string;
}
