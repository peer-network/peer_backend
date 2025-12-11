<?php

declare(strict_types=1);

namespace Tests\utils\ConfigGeneration;

require __DIR__.'../../../../vendor/autoload.php';

try {
    $files = ConfigGenerationConstants::cases();

    foreach ($files as $file) {
        JSONHandler::generateJSONtoFile(Constants::$pathToAssets.$file->outputFileName(), $file->getData(), $file->getName());
    }

    $pathsConfig = new ConfigUrl();
    JSONHandler::generateJSONtoFile(Constants::$pathToAssets.'config.json', $pathsConfig->getData(), 'config', false);

    // generateSchema();
    echo "ConfigGeneration: Done! \n";
    exit(0);
} catch (\Exception $e) {
    echo 'ConfigGeneration: Error: '.$e->getMessage();
    exit(1);
}
