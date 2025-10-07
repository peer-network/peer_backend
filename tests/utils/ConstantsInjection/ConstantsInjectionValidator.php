<?php
declare(strict_types=1);

namespace Tests\utils\ConstantsInjection;

use Exception;

require __DIR__ . '../../../../vendor/autoload.php';

class ConstantsInjectionValidator {

    public static function validate(array $data,string $pattern = '/[{}]/'): void {
        echo("ConstantsInjectionValidator: start \n");
        self::validateMessages($data,$pattern);
        self::validateCodes($data);
    }

    /**
     * Validates that all string fields in the given array/object are non-empty
     * and contain only valid placeholders if present.
     *
     * @throws Exception
     */
    private static function validateMessages(array $data,string $pattern): void
    {
        foreach ($data as $code => $entry) {
            self::validateValue($entry,$pattern, $code);
        }
    }

    private static function validateValue($value,string $pattern, string|int $path = ''): void
    {
        if (is_string($value)) {
            if (trim($value) === '') {
                throw new Exception("ConstantsInjectionValidator: validateValue: Empty string found at path {$path}");
            }
            self::isStringContainsPlaceholders($value,$pattern);
        } elseif (is_array($value)) {
            foreach ($value as $key => $subValue) {
                self::validateValue($subValue, $path . '.' . $key,$pattern);
            }
        } elseif (is_object($value)) {
            foreach (get_object_vars($value) as $key => $subValue) {
                self::validateValue($subValue, $path . '.' . $key,$pattern);
            }
        }
    }

    private static function isStringContainsPlaceholders(string $input, string $pattern) {
        // Returns false if curly braces are found
        echo $pattern;
        if (preg_match($pattern, $input)) {
            throw new Exception("ConstantsInjectionValidator: message still contains a placeholder" . $input);
        }
    }

    private static function validateCodes(array $data): void {
        $codes = array_keys($data);

        foreach ($codes as $code) {
            $codeString = (string)$code;
            if (filter_var($codeString, FILTER_VALIDATE_INT) == false) {
                throw new Exception("ConstantsInjectionValidator: Invalid Code " . $code . ": should be a number");
            }
            if (strlen($codeString) != 5) {
                throw new Exception("ConstantsInjectionValidator: Invalid Code " . $code . ": should have a lenght of 5");
            }
            $firstSection = (int)$codeString[0];
            if ($firstSection < 1 || $firstSection > 6 ) {
                throw new Exception("ConstantsInjectionValidator: Invalid Code " . $code . ": first digit should be within 1 and 6");
            }
        }
    }
}