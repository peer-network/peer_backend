<?php
declare(strict_types=1);

namespace Tests\Utils\ConfigGeneration;

use Tests\Utils\ConfigGeneration\ResponseCodesConfig;
use Tests\Utils\ConfigGeneration\EndpointsConfig;
use Tests\Utils\ConfigGeneration\ConstantsConfigGeneratable;

enum ConfigGenerationConstants : string implements DataGeneratable {
    case constants = "constants";
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
            ConfigGenerationConstants::constants => (new ConstantsConfigGeneratable())->getData(),
        };
    }    

    public function getName(): string
    {
        return $this->value;
    }  
}