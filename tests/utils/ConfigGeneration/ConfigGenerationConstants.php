<?php
declare(strict_types=1);

namespace Tests\Utils\ConfigGeneration;

use Tests\Utils\ConfigGeneration\ResponseCodesConfig;
use Tests\Utils\ConfigGeneration\EndpointsConfig;

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
            ConfigGenerationConstants::repsonseCodes => (new ResponseCodesConfig($path))->getData(),
        };
    }    

    public function getName(): string
    {
        return $this->value;
    }  
}