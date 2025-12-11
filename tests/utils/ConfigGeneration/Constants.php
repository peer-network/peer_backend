<?php

declare(strict_types=1);

namespace Tests\utils\ConfigGeneration;

use Dotenv\Dotenv;

class Constants
{
    public static string $pathToAssets        = './runtime-data/media/assets/';
    public static string $pathForEditing      = './src/config/backend-config-for-editing/';
    public static string $inputFileNameSuffix = '-editable';
    public static string $extension           = '.json';
    public static string $mediaProfix         = 'media.';

    public static function configUrlBase(): string
    {
        $dotenv = Dotenv::createImmutable(__DIR__.'/../../../');
        $dotenv->safeLoad();

        return ($_ENV['MEDIA_SERVER_URL'] ?? getenv('MEDIA_SERVER_URL') ?: '').'/assets/';
    }
}
