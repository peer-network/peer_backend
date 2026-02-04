<?php

declare(strict_types=1);

namespace Fawaz\App\Validation;

/**
 * Small factory for building validator specifications without inline string literals.
 * Each method returns a partial spec keyed by the provided field name.
 */
class ValidationSpec
{
    public static function dateMonthYearString(string $field, bool $required = false, int $errorCode = 30258): array
    {
        return [
            $field => [
                'required' => $required,
                'validators' => [
                    ['name' => 'validateDateMonthYearString', 'options' => ['field' => $field, 'errorCode' => $errorCode]],
                ],
            ],
        ];
    }
    public static function offsetAndLimit(string $field, bool $required = false, int $errorCode = 00000): array
    {
        return [
            $field => [
                'required' => $required,
                'validators' => [
                    ['name' => 'validateOffsetAndLimit', 'options' => ['field' => $field, 'errorCode' => $errorCode]],
                ],
            ],
        ];
    }
    public static function uuid(string $field, bool $required = false, int $errorCode = 30201): array
    {
        return [
            $field => [
                'required' => $required,
                'validators' => [
                    ['name' => 'Uuid', 'options' => ['field' => $field, 'errorCode' => $errorCode]],
                ],
            ],
        ];
    }

    public static function contentFilter(string $field = 'contentFilterBy', bool $required = false, int $errorCode = 30103): array
    {
        return [
            $field => [
                'required' => $required,
                'validators' => [
                    ['name' => 'ContentFilter', 'options' => ['field' => $field, 'errorCode' => $errorCode]],
                ],
            ],
        ];
    }

    public static function email(string $field = 'email', bool $required = false, int $errorCode = 30224): array
    {
        return [
            $field => [
                'required' => $required,
                'validators' => [
                    ['name' => 'EmailAddress', 'options' => ['field' => $field, 'errorCode' => $errorCode]],
                ],
            ],
        ];
    }

    public static function username(string $field = 'username', bool $required = false, int $errorCode = 30202): array
    {
        return [
            $field => [
                'required' => $required,
                'validators' => [
                    ['name' => 'validateUsername', 'options' => ['field' => $field, 'errorCode' => $errorCode]],
                ],
            ],
        ];
    }

    public static function password(string $field = 'password', bool $required = true, int $errorCode = 30226): array
    {
        return [
            $field => [
                'required' => $required,
                'validators' => [
                    ['name' => 'validatePassword', 'options' => ['field' => $field, 'errorCode' => $errorCode]],
                ],
            ],
        ];
    }

    public static function tokenAmount(string $field = 'tokenAmount', bool $required = true, int $errorCode = 30264): array
    {
        return [
            $field => [
                'required' => $required,
                'validators' => [
                    ['name' => 'validateTokenAmount', 'options' => ['field' => $field, 'errorCode' => $errorCode]],
                ],
            ],
        ];
    }

    public static function addressLine1(string $field = 'addressline1', bool $required = false, int $errorCode = 30273): array
    {
        return [
            $field => [
                'required' => $required,
                'validators' => [
                    ['name' => 'validateAddressLine1', 'options' => ['field' => $field, 'errorCode' => $errorCode]],
                ],
            ],
        ];
    }

    
    public static function addressLine2(string $field = 'addressline2', bool $required = false, int $errorCode = 30279): array
    {
        return [
            $field => [
                'required' => $required,
                'validators' => [
                    ['name' => 'validateAddressLine2', 'options' => ['field' => $field, 'errorCode' => $errorCode]],
                ],
            ],
        ];
    }

    public static function city(string $field = 'city', bool $required = false, int $errorCode = 30274): array
    {
        return [
            $field => [
                'required' => $required,
                'validators' => [
                    ['name' => 'validateCity', 'options' => ['field' => $field, 'errorCode' => $errorCode]],
                ],
            ],
        ];
    }

    public static function zipCode(string $field = 'zipCode', bool $required = false, int $errorCode = 30275): array
    {
        return [
            $field => [
                'required' => $required,
                'validators' => [
                    ['name' => 'validateZipCode', 'options' => ['field' => $field, 'errorCode' => $errorCode]],
                ],
            ],
        ];
    }

    public static function country(string $field = 'country', bool $required = false, int $errorCode = 30276): array
    {
        return [
            $field => [
                'required' => $required,
                'validators' => [
                    ['name' => 'validateCountry', 'options' => ['field' => $field, 'errorCode' => $errorCode]],
                ],
            ],
        ];
    }

    public static function name(string $field = 'name', bool $required = false, int $errorCode = 30277): array
    {
        return [
            $field => [
                'required' => $required,
                'validators' => [
                    ['name' => 'validateName', 'options' => ['field' => $field, 'errorCode' => $errorCode]],
                ],
            ],
        ];
    }

    public static function validatePositiveNumber(string $field, bool $required = false, int $errorCode = 33001): array
    {
        return [
            $field => [
                'required' => $required,
                'validators' => [
                    ['name' => 'validatePositiveNumber', 'options' => ['field' => $field, 'errorCode' => $errorCode]],
                ],
            ],
        ];
    }

    public static function genericUuid(string $field, bool $required = false, int $errorCode = 30272): array
    {
        return [
            $field => [
                'required' => $required,
                'validators' => [
                    ['name' => 'validateGenericUuid', 'options' => ['field' => $field, 'errorCode' => $errorCode]],
                ],
            ],
        ];
    }


    public static function size(string $field, bool $required = false, int $errorCode = 30278): array
    {
        return [
            $field => [
                'required' => $required,
                'validators' => [
                    ['name' => 'validateSize', 'options' => ['field' => $field, 'errorCode' => $errorCode]],
                ],
            ],
        ];
    }
    
    /**
     * Merge multiple partial specs into one spec array.
     */
    public static function merge(array ...$parts): array
    {
        $merged = [];
        foreach ($parts as $part) {
            foreach ($part as $k => $v) {
                $merged[$k] = $v;
            }
        }
        return $merged;
    }

    /**
     * Build a validation spec by inspecting input keys and applying known validators.
     * Optional `$requiredKeys` marks specific fields as required.
     */
    public static function auto(array $inputKeysOrArgs, array $requiredKeys = []): array
    {
        // If associative args passed, use keys; otherwise assume it's already keys
        $keys = array_keys($inputKeysOrArgs) === range(0, count($inputKeysOrArgs) - 1)
            ? $inputKeysOrArgs
            : array_keys($inputKeysOrArgs);

        // Ensure all required keys are present in the spec even if missing from input
        // so that required-field checks can trigger correctly.
        $keys = array_values(array_unique(array_merge($keys, $requiredKeys)));

        $map = [
            // UUID-like identifiers
            'userid' => fn (string $f, bool $r) => self::uuid($f, $r),
            'postid' => fn (string $f, bool $r) => self::uuid($f, $r),
            'commentid' => fn (string $f, bool $r) => self::uuid($f, $r),

            // Content filter
            'contentFilterBy' => fn (string $f, bool $r) => self::contentFilter($f, $r),

            // User credentials and profile
            'email' => fn (string $f, bool $r) => self::email($f, $r),
            'username' => fn (string $f, bool $r) => self::username($f, $r),
            'password' => fn (string $f, bool $r) => self::password($f, $r),

            'offset' => fn (string $f, bool $r) => self::offsetAndLimit($f, $r),
            'limit' => fn (string $f, bool $r) => self::offsetAndLimit($f, $r),
            'postOffset' => fn (string $f, bool $r) => self::offsetAndLimit($f, $r),
            'postLimit' => fn (string $f, bool $r) => self::offsetAndLimit($f, $r),
            'commentOffset' => fn (string $f, bool $r) => self::offsetAndLimit($f, $r),
            'commentLimit' => fn (string $f, bool $r) => self::offsetAndLimit($f, $r),
            'messageOffset' => fn (string $f, bool $r) => self::offsetAndLimit($f, $r),
            'messageLimit' => fn (string $f, bool $r) => self::offsetAndLimit($f, $r),

            'start_date' => fn (string $f, bool $r) => self::dateMonthYearString($f, $r),
            'end_date' => fn (string $f, bool $r) => self::dateMonthYearString($f, $r),

            'tokenAmount' => fn (string $f, bool $r) => self::tokenAmount($f, $r),
            'name' => fn (string $f, bool $r) => self::name($f, $r),
            'addressline1' => fn (string $f, bool $r) => self::addressLine1($f, $r),
            'city' => fn (string $f, bool $r) => self::city($f, $r),
            'zipcode' => fn (string $f, bool $r) => self::zipCode($f, $r),
            'country' => fn (string $f, bool $r) => self::country($f, $r),
            'shopItemId' => fn (string $f, bool $r) => self::genericUuid($f, $r),
            'transactionId' => fn (string $f, bool $r) => self::genericUuid($f, $r),
            'size' => fn (string $f, bool $r) => self::size($f, false),
            'addressline2' => fn (string $f, bool $r) => self::addressLine2($f, false),

            'leaderboardUsersCount' => fn (string $f, bool $r) => self::validatePositiveNumber($f, $r),
        ];

        $spec = [];
        foreach ($keys as $field) {
            $required = in_array($field, $requiredKeys, true);
            if (isset($map[$field])) {
                $spec = array_replace($spec, $map[$field]($field, $required));
                continue;
            }

            // // Heuristics: fields that look like identifiers → UUID validator
            // $low = strtolower($field);
            // if ($low === 'id' || str_ends_with($low, 'id')) {
            //     $spec = array_replace($spec, self::uuid($field, $required));
            // }
        }

        return $spec;
    }
}
