<?php
declare(strict_types=1);

namespace Fawaz\Config\ResponseCodes;
use Fawaz\Config\ResponseCodes\ResponseCodesSection;

enum InputSanitization: int implements ResponseCodesSection {
    case allRequiredFieldsMustBeProvided = 30101;

    public function message(): string {
        return match($this) {
            self::allRequiredFieldsMustBeProvided => "All required fields must be provided."
        };
    }
    public function code(): int {
        return $this->value;
    }
}