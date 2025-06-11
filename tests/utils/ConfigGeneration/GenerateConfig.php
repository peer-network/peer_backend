<?php
declare(strict_types=1);

namespace Tests\Utils\ConfigGeneration;

require __DIR__ . '../../../../vendor/autoload.php';

use Tests\Utils\ConfigGeneration\JSONHandler;
use Tests\Utils\ConfigGeneration\ConfigUrl;
use Tests\Utils\ConfigGeneration\ConfigGenerationConstants;

interface DataGeneratable {
    public function getData(): array;
}

try {
    $files = ConfigGenerationConstants::cases();

    foreach($files as $file) {
        JSONHandler::generateJSONtoFile(Constants::$pathToAssets . $file->outputFileName(), $file->getData(), $file->getName());
    }

    $pathsConfig = new ConfigUrl();
    JSONHandler::generateJSONtoFile(Constants::$pathToAssets . "config.json", $pathsConfig->getData(), "config", false);
    
    echo("ConfigGeneration: Done! \n");
} catch (\Exception $e) {
    echo "ConfigGeneration: Erorr: " . $e->getMessage();
}