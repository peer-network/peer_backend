<?php

declare(strict_types=1);

namespace Tests\utils\ConfigGeneration;

use Tests\utils\ConstantsInjection\ConstantsInjectionValidator;
use Tests\utils\ConstantsInjection\ConstantValuesInjectorImpl;

require __DIR__.'../../../../vendor/autoload.php';

class ResponseCodesConfig implements DataGeneratable
{
    private array $data = [];

    public function __construct(string $filePath)
    {
        $decoded = JSONHandler::parseInputJson($filePath, true);

        $injector     = new ConstantValuesInjectorImpl();
        $injectedData = $injector->injectConstants($decoded);

        if (empty($injectedData)) {
            throw new \Exception('ResponseCodesConfig: injectConstantsToMessages: result is empty');
        }

        $this->data = $injectedData;
        ConstantsInjectionValidator::validate($injectedData);
    }

    public function getData(): array
    {
        return $this->data;
    }
}
