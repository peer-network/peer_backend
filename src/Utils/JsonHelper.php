<?php

declare(strict_types=1);

namespace Fawaz\Utils;

class JsonHelper
{
    public static function decode(mixed $raw): ?array
    {
        if (!\is_string($raw)) {
            return null;
        }
        $decoded = json_decode($raw, true);

        if (\JSON_ERROR_NONE !== json_last_error()) {
            return null;
        }

        return $decoded;
    }
}
