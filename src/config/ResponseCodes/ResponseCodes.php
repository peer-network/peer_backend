<?php
declare(strict_types=1);

namespace Fawaz\Config\ResponseCodes;

require_once __DIR__ . '/InputSanitization.php';

interface ResponseCodeSection {
  public function message(): string;
  public function code(): int;
}

class ResponseCodes {
     /** @var array<string, array<ResponseCodeSection>> */
    public array $codes;

     public function __construct() {
        $this->codes = [
            InputSanitization::cases()
        ];
    }

     public function printAllCodes(): void {
        foreach ($this->codes as $enumCases) {
            foreach ($enumCases as $case) {
                echo $case->name . ": " . $case->value . " - " . $case->message() . "\n";
            }
        }
    }
}

$codes = new ResponseCodes();
$codes->printAllCodes();
