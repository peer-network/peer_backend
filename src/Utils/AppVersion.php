<?php

declare(strict_types=1);

namespace Fawaz\Utils;

final class AppVersion
{
    private static string $versionFile = __DIR__ . '/../../VERSION';

    /**
     * Returns the application version resolved at build time.
     */
    public static function get(): string
    {
        if (!is_file(self::$versionFile)) {
            return 'unknown';
        }

        $version = trim((string) file_get_contents(self::$versionFile));

        return $version !== '' ? $version : 'unknown';
    }
}
