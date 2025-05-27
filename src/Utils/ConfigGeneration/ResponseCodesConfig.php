<?php
declare(strict_types=1);

namespace Fawaz\Utils\ConfigGeneration;

use Exception;
use Fawaz\Utils\ConfigGeneration\JSONHandler;

require __DIR__ . '../../../../vendor/autoload.php';

class ResponseCodesConfig implements DataGeneratable {
    /** @var array<string, MessageEntry> */
    private array $data = [];


    public function __construct($filePath) {           
        $decoded = JSONHandler::parseInputJson($filePath, true);

        if (!$decoded || empty($decoded)) {
            throw new Exception("Error: File " . $filePath . " is empty");        
        }

        foreach ($decoded as $code => $entry) { 
            if (in_array($code, $this->data)) {
                throw new Exception("Error: Duplicated Code: " . $code);
            }
            $this->data[$code] = new MessageEntry(
                $entry['comment'],
                $entry['userFriendlyComment']
            );
        }

        $this->validate();
    }

    public function getData(): array {
        return $this->data;
    }

    private function validate() {
        $this->validateMessages();
        $this->validateCodes();
    }

    private function validateMessages() {
         foreach ($this->data as $code => $entry) {
            if (empty($entry->comment) || empty($entry->userFriendlyComment)) {
                throw new Exception("Error: Empty Message found for code " . $code);
            }
        }
    }

    private function validateCodes() {
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

class MessageEntry {
    public string $comment;
    public string $userFriendlyComment;

    public function __construct(string $comment, string $userFriendlyComment) {
        $this->comment = $comment;
        $this->userFriendlyComment = $userFriendlyComment;
    }
}