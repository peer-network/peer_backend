<?php

declare(strict_types=1);

namespace Fawaz\Filter;

use Exception;
use DateTime;
use Fawaz\config\constants\ConstantsConfig;
use Fawaz\Services\ContentFiltering\HiddenContentFilterServiceImpl;

use function trim;
use function preg_match;
use function strlen;
use function filter_var;
use function in_array;
use function method_exists;

class PeerInputGenericValidator
{
    protected array $specification;
    protected array $data = [];
    protected array $errors = [];

    public function __construct(array $specification)
    {
        $this->specification = $specification;
    }

    public function setData(array $data): void
    {
        $this->data = $data;
    }

    public function isValid(): bool
    {
        $this->errors = [];

        foreach ($this->specification as $field => $rules) {
            // If the field is required but missing from input, record an error
            if (!isset($this->data[$field])) {
                if (!empty($rules['required'])) {
                    // Use a generic "required/missing" error code consistent with existing patterns
                    $this->errors[$field][] = '30101';
                }
                continue;
            }

            if (isset($this->data[$field])) {
                // Apply filters (transformers) first, if any
                foreach ($rules['filters'] ?? [] as $filter) {
                    $filterName = $filter['name'];
                    $options = $filter['options'] ?? [];
                    if (method_exists($this, $filterName)) {
                        $this->data[$field] = $this->$filterName($this->data[$field], $options);
                    } else {
                        throw new ValidationException("Filter method $filterName does not exist.");
                    }
                }

                // Then run validators
                foreach ($rules['validators'] ?? [] as $validator) {
                    $validatorName = $validator['name'];
                    $options = $validator['options'] ?? [];
                    // Ensure validators know which field to record errors under
                    if (!isset($options['field'])) {
                        $options['field'] = $field;
                    }
                    // Debug output removed
                    if (method_exists($this, $validatorName)) {
                        if (!$this->$validatorName($this->data[$field], $options)) {
                            // Respect chain breaking if provided; individual validators push specific errors
                            if (!empty($validator['break_chain_on_failure'])) {
                                break;
                            }
                        }
                    } else {
                        throw new ValidationException("Validator method $validatorName does not exist.");
                    }
                }
            }
        }

        return empty($this->errors);
    }

    public function getErrors(): array
    {
        return $this->errors;
    }

    /**
     * Returns a de-duplicated flat list of numeric error codes found in the
     * collected errors. Non-numeric messages are ignored.
     *
     * @return int[]
     */
    public function getErrorCodes(): array
    {
        $codes = [];
        $extract = function ($value) use (&$codes, &$extract): void {
            if (is_int($value)) {
                $codes[] = $value;
                return;
            }
            if (is_string($value) && ctype_digit($value)) {
                $codes[] = (int)$value;
                return;
            }
            if (is_array($value)) {
                foreach ($value as $v) {
                    $extract($v);
                }
            } elseif (is_object($value)) {
                foreach (get_object_vars($value) as $v) {
                    $extract($v);
                }
            }
        };

        $extract($this->errors);

        return array_values(array_unique($codes));
    }

    public function getValues(): array
    {
        return $this->data;
    }

    /**
     * Centralized helper to record an error using options['field'] and options['errorCode'].
     * Falls back to provided defaults to avoid hardcoded literals throughout validators.
     */
    protected function pushError(array $options, string $defaultField, string $defaultCode): void
    {
        $fieldKey = $options['field'] ?? $defaultField;
        if ($options['errorCode'] !== 0) {
            $code = (string)($options['errorCode']);
        } else {
            $code = $defaultCode;
        }
        $this->errors[$fieldKey][] = $code;
    }

    // Validators
    protected function Uuid(mixed $value, array $options = []): bool
    {
        $fieldKey = $options['field'] ?? 'uuid';
        if ($value !== null && $value === '') {
            return false;
        }
        if (!is_string($value)) {
            $this->pushError($options, $fieldKey, '30201');
            return false;
        }
        if (preg_match('/^\{?[a-fA-F0-9]{8}\-[a-fA-F0-9]{4}\-[a-fA-F0-9]{4}\-[a-fA-F0-9]{4}\-[a-fA-F0-9]{12}\}?$/', $value) === 1) {
            return true;
        }
        $this->pushError($options, $fieldKey, '30201');
        return false;
    }

    protected function Date(string $value, array $options = []): bool
    {
        $fieldKey = $options['field'] ?? 'Date';
        // Type guard: must be string

        $format = $options['format'] ?? 'Y-m-d H:i:s.u';

        if (preg_match('/\.\d+$/', $value, $matches)) {
            $microseconds = $matches[0];
            if (strlen($microseconds) < 7) {
                $value = str_replace($microseconds, str_pad($microseconds, 7, '0'), $value);
            }
        }

        $dateTime = DateTime::createFromFormat($format, $value);

        if ($dateTime) {
            $formatted = $dateTime->format($format);

            $formatted = preg_replace_callback('/\.(\d{1,6})(0*)$/', function ($matches) {
                return '.' . str_pad($matches[1], 6, '0');
            }, $formatted);

            $value = trim($value);
            $formatted = trim($formatted);

            if ($formatted === $value) {
                return true;
            }
        }

        $this->pushError($options, $fieldKey, '30258');
        return false;
    }

    protected function EmailAddress(string $value, array $options = []): bool
    {
        $fieldKey = $options['field'] ?? 'email';
        // Type guard: must be string

        if (filter_var($value, FILTER_VALIDATE_EMAIL) == false) {
            $this->pushError($options, $fieldKey, '30224');
            return false;
        }
        return true;
    }

    /**
     * Validates contentFilterBy using HiddenContentFilterServiceImpl::validateContentFilter
     * Accepts null, string, or array per existing usage. Returns true if valid or empty.
     */
    protected function ContentFilter(mixed $value, array $options = []): bool
    {
        // Treat empty values as valid (optional field semantics)
        if ($value === null || $value === '' || $value === []) {
            return true;
        }

        // Delegate validation to the service implementation
        $isValid = HiddenContentFilterServiceImpl::getContentFilteringSeverityLevel($value);
        if ($isValid === false) {
            $this->errors['contentFilterBy'][] = "30103";
            return false;
        }
        return true;
    }

    protected function IsIp(string $value, array $options = []): bool
    {
        $flags = 0;
        if (!empty($options['ipv4'])) {
            $flags |= FILTER_FLAG_IPV4;
        }
        if (!empty($options['ipv6'])) {
            $flags |= FILTER_FLAG_IPV6;
        }
        return filter_var($value, FILTER_VALIDATE_IP, $flags) !== false;
    }

    protected function isImage(string $value, array $options = []): bool
    {
        $allowedExtensions = $options['extensions'] ?? ['jpg', 'jpeg', 'png', 'gif', 'heic', 'heif', 'tiff', 'webp'];
        $allowedMimeTypes = $options['mime_types'] ?? ['image/jpeg', 'image/png', 'image/gif', 'image/heic', 'image/heif', 'image/tiff', 'image/webp'];

        $extension = pathinfo($value, PATHINFO_EXTENSION);
        if (!in_array(strtolower($extension), $allowedExtensions)) {
            return false;
        }

        if (file_exists($value)) {
            $mimeType = mime_content_type($value);
            if (!in_array($mimeType, $allowedMimeTypes)) {
                return false;
            }
        }

        return true;
    }

    protected function validatePassword(string $value, array $options = []): bool
    {
        $fieldKey = $options['field'] ?? 'password';
        $passwordConfig = ConstantsConfig::user()['PASSWORD'];

        if ($value === '') {
            $this->pushError($options, $fieldKey, '30101');
            return false;
        }

        if (strlen($value) < $passwordConfig['MIN_LENGTH'] || strlen($value) > $passwordConfig['MAX_LENGTH']) {
            $this->pushError($options, $fieldKey, '30226');
            return false;
        }

        if (!preg_match('/' . $passwordConfig['PATTERN'] . '/u', $value)) {
            $this->pushError($options, $fieldKey, '30226');
            return false;
        }

        return true;
    }

    // Validate a single offset/limit-like field based on options['field']
    protected function validateOffsetAndLimit(int $value, array $options = []): bool
    {
        // Options normalisations
        // - normalise field name to lowercase
        // - accept common typo 'litim' as 'limit'
        // - accept snake_case by removing underscores (e.g., post_offset)
        $field = $options['field'] ?? null;
        if (is_string($field)) {
            $normalized = strtolower($field);
            // remove underscores to support snake_case variants
            $field = $normalized;
            // reflect normalised field back into options for consistent error keys
            $options['field'] = $field;
        }

        $paging = ConstantsConfig::paging();
        $minOffset = (int)$paging['OFFSET']['MIN'];
        $maxOffset = (int)$paging['OFFSET']['MAX'];
        $minPostOffset = (int)$paging['POST_OFFSET']['MIN'];
        $maxPostOffset = (int)$paging['POST_OFFSET']['MAX'];
        $minLimit = (int)$paging['LIMIT']['MIN'];
        $maxLimit = (int)$paging['LIMIT']['MAX'];
        $minPostLimit = (int)$paging['POST_LIMIT']['MIN'];
        $maxPostLimit = (int)$paging['POST_LIMIT']['MAX'];

        $num = (int)$value;

        // Determine range and error code based on the specific field
        switch (true) {
            case $field === 'offset':
                if ($num < $minOffset || $num > $maxOffset) {
                    $this->pushError($options, $field, '30203');
                    return false;
                }
                break;

            case $field === 'limit':
                if ($num < $minLimit || $num > $maxLimit) {
                    $this->pushError($options, $field, '30204');
                    return false;
                }
                break;

            case $field === 'postoffset':
                if ($num < $minPostOffset || $num > $maxPostOffset) {
                    $this->pushError($options, $field, '30214');
                    return false;
                }
                break;

            case $field === 'postlimit':
                if ($num < $minPostLimit || $num > $maxPostLimit) {
                    $this->pushError($options, $field, '30205');
                    return false;
                }
                break;

            case $field === 'commentoffset':
                if ($num < $minOffset || $num > $maxOffset) {
                    $this->pushError($options, $field, '30215');
                    return false;
                }
                break;

            case $field === 'commentlimit':
                if ($num < $minLimit || $num > $maxLimit) {
                    $this->pushError($options, $field, '30216');
                    return false;
                }
                break;

            case $field === 'messageoffset':
                if ($num < $minOffset || $num > $maxOffset) {
                    $this->pushError($options, $field, '30219');
                    return false;
                }
                break;

            case $field === 'messagelimit':
                if ($num < $minLimit || $num > $maxLimit) {
                    $this->pushError($options, $field, '30220');
                    return false;
                }
                break;

            default:
                $this->pushError($options, $field, '40301');
        }

        return true;
    }

    protected function validateUsername(string $value, array $options = []): bool
    {
        $fieldKey = $options['field'] ?? 'username';
        $forbiddenUsernames = ['moderator', 'admin', 'owner', 'superuser', 'root', 'master', 'publisher', 'manager', 'developer'];
        $usernameConfig = ConstantsConfig::user()['USERNAME'];

        if ($value === '') {
            $this->pushError($options, $fieldKey, '30202');
            return false;
        }

        if (strlen($value) < $usernameConfig['MIN_LENGTH'] || strlen($value) > $usernameConfig['MAX_LENGTH']) {
            $this->pushError($options, $fieldKey, '30202');
            return false;
        }

        if (!preg_match('/' . $usernameConfig['PATTERN'] . '/u', $value)) {
            $this->pushError($options, $fieldKey, '30202');
            return false;
        }

        if (!preg_match('/[a-zA-Z]/', $value)) {
            $this->pushError($options, $fieldKey, '30202');
            return false;
        }

        if (in_array(strtolower($value), $forbiddenUsernames, true)) {
            $this->pushError($options, $fieldKey, '31002');
            return false;
        }

        return true;
    }

    protected function validateTagName(string $value, array $options = []): bool
    {
        $fieldKey = $options['field'] ?? 'tag';
        $tagConfig = ConstantsConfig::post()['TAG'];
        $inputConfig  = ConstantsConfig::input();
        $controlPattern = '/'.$inputConfig['FORBID_CONTROL_CHARS_PATTERN'].'/u';

        if ($value === '') {
            $this->pushError($options, $fieldKey, '30101');
            return false;
        }

        if (strlen($value) < $tagConfig['MIN_LENGTH'] || strlen($value) > $tagConfig['MAX_LENGTH']) {
            $this->pushError($options, $fieldKey, '30103');
            return false;
        }

        if (preg_match($controlPattern, $value) === 1) {
            $this->pushError($options, $fieldKey, '30103');
            return false;
        }

        return true;
    }

    protected function validatePkey(string $value, array $options = []): bool
    {
        $fieldKey = $options['field'] ?? 'pkey';
        if ($value === '') {
            $this->pushError($options, $fieldKey, '30103');
            return false;
        }
        $walletConst = ConstantsConfig::wallet();

        if (strlen($value) < $walletConst['SOLANA_PUBKEY']['MIN_LENGTH'] || strlen($value) > $walletConst['SOLANA_PUBKEY']['MAX_LENGTH']) {
            $this->pushError($options, $fieldKey, '30254');
            return false;
        }

        if (!preg_match('/' . $walletConst['SOLANA_PUBKEY']['PATTERN'] . '/u', $value)) {
            $this->pushError($options, $fieldKey, '30254');
            return false;
        }

        return true;
    }

    protected function validatePhoneNumber(string $value, array $options = []): bool
    {
        $fieldKey = $options['field'] ?? 'phone';
        $phoneConfig = ConstantsConfig::user()['PHONENUMBER'];

        if ($value === '') {
            $this->pushError($options, $fieldKey, '30103');
            return false;
        }


        if (!preg_match('/' . $phoneConfig['PATTERN'] . '/u', $value)) {
            $this->pushError($options, $fieldKey, '30253');
            return false;
        }

        return true;
    }

    protected function validateActivationToken(string $value, array $options = []): bool
    {
        $fieldKey = $options['field'] ?? 'activation_token';
        if ($value === '') {
            $this->pushError($options, $fieldKey, 'Activation token is required.');
            return false;
        }

        if (strlen($value) !== 64) {
            $this->pushError($options, $fieldKey, 'Activation token must be exactly 64 characters.');
            return false;
        }

        if (!preg_match('/^[a-f0-9]{64}$/i', $value)) {
            $this->pushError($options, $fieldKey, 'Invalid activation token format.');
            return false;
        }

        return true;
    }
}
