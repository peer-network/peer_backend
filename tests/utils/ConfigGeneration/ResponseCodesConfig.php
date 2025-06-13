<?php
declare(strict_types=1);

namespace Tests\Utils\ConfigGeneration;

use Exception;
use Tests\Utils\ConfigGeneration\JSONHandler;
use Tests\Utils\ConfigGeneration\ResponseMessagesValueInjector;
use Tests\Utils\ConfigGeneration\MessageEntry;

require __DIR__ . '../../../../vendor/autoload.php';

class ResponseCodesConfig implements DataGeneratable {
    /** @var array<string, MessageEntry> */
    private array $data = [];


    public function __construct(string $filePath) {
        $decoded = JSONHandler::parseInputJson($filePath, true);

        foreach ($decoded as $code => $entry) { 
            $this->data[$code] = new MessageEntry(
                $entry['comment'],
                $entry['userFriendlyComment']
            );
        }

        $injector = new ResponseMessagesValueInjector();
        $injectedData = $injector->injectConstants($this->data);

        if (!$injectedData || empty($injectedData)) {
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

    private function validateMessages(): void {
         foreach ($this->data as $code => $entry) {
            if (empty($entry->comment) || empty($entry->userFriendlyComment)) {
                throw new Exception("ResponseCodesConfig: validateMessages: Empty Message found for code " . $code);
            }
            $this->isStringContainsPlaceholders($entry->comment);
            $this->isStringContainsPlaceholders($entry->userFriendlyComment);
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