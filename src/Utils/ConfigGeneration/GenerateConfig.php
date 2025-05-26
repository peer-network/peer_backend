<?php
declare(strict_types=1);

namespace Fawaz\Utils\ConfigGeneration;

use Fawaz\Utils\ConfigGeneration\ResponseCodesStore;
use Fawaz\Utils\ConfigGeneration\EndpointsConfig;
use Fawaz\Utils\ConfigGeneration\JSONHandler;
use Fawaz\Utils\ConfigGeneration\ConfigUrl;

interface DataGeneratable {
    public function getData(): array;
}

class Constants {
    static $pathToAssets = "/Users/fcody/Desktop/Peer/peer_backend/runtime-data/media/assets/"; 
    static $pathForEditing = "/Users/fcody/Desktop/Peer/peer_backend/src/Utils/ConfigGeneration/src/";
    static $configUrlBase = "https://media.getpeer.eu/assets/";


    static $inputFileNameSuffix = "-editable";
    static $extension = ".json";
}

enum ConfigGenerationConstants : string implements DataGeneratable {
    case repsonseCodes = "response-codes";
    case endpoints = "endpoints";

    public function outputFileName(): string
    {
        return $this->value . Constants::$extension;
    }    

    public function getData(): array
    {
        $path = Constants::$pathForEditing . 
                $this->value . 
                Constants::$inputFileNameSuffix . 
                Constants::$extension;

        return match($this) 
        {
            ConfigGenerationConstants::endpoints => (new EndpointsConfig($path))->getData(),
            ConfigGenerationConstants::repsonseCodes => (new ResponseCodesStore($path))->getData(),
        };
    }    

    public function getName(): string
    {
        return $this->value;
    }  
}

require __DIR__ . '../../../../vendor/autoload.php';

try {
    $files = ConfigGenerationConstants::cases();
    foreach($files as $file) {
        JSONHandler::generateJSONtoFile(Constants::$pathToAssets . $file->outputFileName(), $file->getData(), $file->getName());
    }

    $pathsConfig = new ConfigUrl();
    JSONHandler::generateJSONtoFile(Constants::$pathToAssets . "config.json", $pathsConfig->getData(), "config");
} catch (\Exception $e) {
    echo $e->getMessage();
}