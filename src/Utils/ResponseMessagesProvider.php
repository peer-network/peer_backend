<?php

namespace Fawaz\Utils;

interface ResponseMessagesProvider
{
    /**
     * Get a specific message by code
     */
    public function getMessage(string $code): ?string;
}
