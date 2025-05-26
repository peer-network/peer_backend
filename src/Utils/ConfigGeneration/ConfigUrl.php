<?php
declare(strict_types=1);

namespace Fawaz\Utils\ConfigGeneration;

use Exception;
use Fawaz\Utils\ConfigGeneration\Constants;

class ConfigUrl implements DataGeneratable {
    private array $data = [];


    public function __construct()
    {  
        $configs = ConfigGenerationConstants::cases();

        foreach ($configs as $config) { 
            $createdAtKey = "createdAt";
            $file = JSONHandler::parseInputJson(Constants::$pathToAssets . $config->outputFileName());

            if (!isset($file[$createdAtKey]) || empty($file[$createdAtKey])) {
                throw new Exception("Invalid Config: ".$config->getName() . ": " . "createdAt field is empty");
            }
            $fileCreatedAt = $file[$createdAtKey];

            $this->data[$config->getName()] = new ConfigUrlEntry(
                $fileCreatedAt,
                Constants::$configUrlBase . $config->outputFileName()
            );
        }
    }

    public function getData(): array {
        return $this->data;
    }
}

class ConfigUrlEntry {
    public int $createdAt;
    public string $url;

    public function __construct(int $createdAt, string $url) {
        $this->createdAt = $createdAt;
        $this->url = $url;
    }
}