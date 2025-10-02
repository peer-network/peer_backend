<?php
declare(strict_types=1);

namespace Tests\utils\ConfigGeneration;

use Exception;
use Tests\utils\ConfigGeneration\JSONHandler;
use Tests\utils\ConfigGeneration\ConstantsInjection\ConstantValuesInjectorImpl;

require __DIR__ . '../../../../vendor/autoload.php';

class ResponseCodesConfig implements DataGeneratable {
    private array $data = [];


    public function __construct(string $filePath) {
        $decoded = JSONHandler::parseInputJson($filePath, true);

        $injector = new ConstantValuesInjectorImpl();
        $injectedData = $injector->injectConstants($decoded);

        if (empty($injectedData)) {
            throw new Exception("ResponseCodesConfig: injectConstantsToMessages: result is empty");
        }

        $this->data = $injectedData;
        $this->validate();
    }

    public function getData(): array {
        return $this->data;
    }

    private function validate(): void {
        echo("ConfigGeneration: ResponseCodesConfig: validate: start \n");
        $this->validateMessages();
        $this->validateCodes();
    }

    /**
     * Validates that all string fields in the given array/object are non-empty
     * and contain only valid placeholders if present.
     *
     * @throws Exception
     */
    private function validateMessages(): void
    {
        foreach ($this->data as $code => $entry) {
            $this->validateValue($entry, $code);
        }
    }

    private function validateValue($value, string|int $path = ''): void
    {
        if (is_string($value)) {
            if (trim($value) === '') {
                throw new Exception("ResponseCodesConfig: validateMessages: Empty string found at path {$path}");
            }
            $this->isStringContainsPlaceholders($value);
        } elseif (is_array($value)) {
            foreach ($value as $key => $subValue) {
                $this->validateValue($subValue, $path . '.' . $key);
            }
        } elseif (is_object($value)) {
            foreach (get_object_vars($value) as $key => $subValue) {
                $this->validateValue($subValue, $path . '.' . $key);
            }
        }
    }

    private function isStringContainsPlaceholders($input) {
        // Returns false if curly braces are found
        if (preg_match('/[{}]/', $input)) {
            throw new Exception("ResponseCodesConfig: validateMessages: message still contains a placeholder" . $input);
        }
    }

    private function validateCodes(): void {
        $codes = array_keys($this->data);

        foreach ($codes as $code) {
            $codeString = (string)$code;
            if (filter_var($codeString, FILTER_VALIDATE_INT) == false) {
                throw new Exception("Error: Invalid Code " . $code . ": should be a number");
            }
            if (strlen($codeString) != 5) {
                throw new Exception("Error: Invalid Code " . $code . ": should have a lenght of 5");
            }
            $firstSection = (int)$codeString[0];
            if ($firstSection < 1 || $firstSection > 6 ) {
                throw new Exception("Error: Invalid Code " . $code . ": first digit should be within 1 and 6");
            }
        }
    }
}