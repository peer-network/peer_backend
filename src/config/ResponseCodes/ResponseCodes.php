<?php
declare(strict_types=1);

namespace Fawaz\config\ResponseCodes;

require_once __DIR__ . '/InputSanitization.php';
require_once __DIR__ . '/ArgumentFields.php';

class ResponseCodes {
    /** @var array<string, ResponseCodeSection> */
    public array $codes;

     public function __construct() {
        $this->codes = $this->mergeArrays(
            [ 
                InputSanitization::cases(), 
                ArgumentFields::cases()
            ]
        );
    }

     public function printAllCodes(): void {
        foreach ($this->codes as $enumCases) {
            foreach ($enumCases as $case) {
                echo $case->name . ": " . $case->value . " - " . $case->message() . "\n";
            }
        }
    }

    /**
     * @param array<array<string, ResponseCodeSection>> $arrays
     * @return array<string, ResponseCodeSection>
     */
    private function mergeArrays(array $arrays): array {
        $merged = [];

        foreach ($arrays as $array) {
            foreach ($array as $key => $value) {
                echo $key;
                echo $value->code();
                echo $value->message();
                $merged[$key] = $value;
            }
        }

        return $merged;
    }
}

$codes = new ResponseCodes();
$codes->printAllCodes();
