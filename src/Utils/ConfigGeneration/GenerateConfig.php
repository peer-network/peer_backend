<?php
declare(strict_types=1);

namespace Fawaz\Utils\ConfigGeneration;

require __DIR__ . '../../../../vendor/autoload.php';

use Fawaz\Utils\ConfigGeneration\JSONHandler;
use Fawaz\Utils\ConfigGeneration\ConfigUrl;
use Fawaz\Utils\ConfigGeneration\ConfigGenerationConstants;

interface DataGeneratable {
    public function getData(): array;
}

try {
    $files = ConfigGenerationConstants::cases();
    $pathsConfig = new ConfigUrl();

    foreach($files as $file) {
        JSONHandler::generateJSONtoFile(Constants::$pathToAssets . $file->outputFileName(), $file->getData(), $file->getName());
    }
    JSONHandler::generateJSONtoFile(Constants::$pathToAssets . "config.json", $pathsConfig->getData(), "config");

} catch (\Exception $e) {
    echo $e->getMessage();
}