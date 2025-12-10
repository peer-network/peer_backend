<?php

declare(strict_types=1);

namespace Fawaz\Database\Interfaces;

interface Hashable
{
    /**
     * Returns the string content used to compute the SHA-256 hash.
     */
    public function hashValue(): string;

    public function getHashableContent(): string;
}
