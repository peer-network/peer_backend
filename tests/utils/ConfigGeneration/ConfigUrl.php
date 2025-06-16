<?php
declare(strict_types=1);

namespace Tests\utils\ConfigGeneration;

use Exception;
use Tests\utils\ConfigGeneration\Constants;

class ConfigUrl implements DataGeneratable {
    private array $data = [];


    public function __construct()
    {  
        $configs = ConfigGenerationConstants::cases();

        foreach ($configs as $config) { 
            $createdAtKey = "createdAt";
            $hashKey = "hash";
            $file = JSONHandler::parseInputJson(Constants::$pathToAssets . $config->outputFileName());

            if (!isset($file[$createdAtKey]) || empty($file[$createdAtKey])) {
                throw new Exception("Invalid Config: ".$config->getName() . ": " . "createdAt field is empty");
            }
            if (!isset($file[$hashKey]) || empty($file[$hashKey])) {
                throw new Exception("Invalid Config: ".$config->getName() . ": " . "hash field is empty");
            }
            $fileCreatedAt = $file[$createdAtKey];
            $hash = $file[$hashKey];

            $this->data[$config->getName()] = new ConfigUrlEntry(
                $fileCreatedAt,
                Constants::$configUrlBase . $config->outputFileName(),
                $hash
            );
        }
    }

    public function getData(): array {
        return $this->data;
    }
}

class ConfigUrlEntry {
    public int $createdAt;
    public string $hash;
    public string $url;

    public function __construct(int $createdAt, string $url, string $hash) {
        $this->createdAt = $createdAt;
        $this->url = $url;
        $this->hash = $hash;
    }
}