<?php
declare(strict_types=1);


namespace Tests\utils\ConfigGeneration;

use Dotenv\Dotenv;

class Constants
{
    public static string $pathToAssets = "./runtime-data/media/assets/";
    public static string $pathForEditing = "./src/config/backend-config-for-editing/";
    public static string $inputFileNameSuffix = "-editable";
    public static string $extension = ".json";

    public static function configUrlBase(): string {
        $dotenv = Dotenv::createImmutable(__DIR__ . '/../../../');
        $dotenv->safeLoad();
        return ( $_ENV['BASE_URL'] ?? getenv('BASE_URL') ?: '' ) . '/assets/';
    }
}
