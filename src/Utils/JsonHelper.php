<?php
declare(strict_types=1);

namespace Fawaz\Utils;

class JsonHelper
{
    public static function decode(mixed $raw): ?array
    {
        if (!is_string($raw)) {
            return null;
        }
        $decoded = json_decode($raw, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return null;
        }
        return $decoded;
    }
}