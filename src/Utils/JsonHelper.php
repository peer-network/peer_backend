<?php
declare(strict_types=1);

namespace Fawaz\Utils;

class JsonHelper
{
    public static function decode(mixed $raw): array
    {
        if (!is_string($raw)) {
            throw new \InvalidArgumentException('JsonHelper::decode expects a JSON string');
        }
        $decoded = json_decode($raw, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \InvalidArgumentException(
                'JsonHelper::decode error: ' . json_last_error_msg()
            );
        }
        return $decoded;
    }
}