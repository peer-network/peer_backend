<?php

declare(strict_types=1);

namespace Fawaz\App\Validation;

/**
 * Small factory for building validator specifications without inline string literals.
 * Each method returns a partial spec keyed by the provided field name.
 */
class ValidationSpec
{
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

        $map = [
            // UUID-like identifiers
            'userid' => fn(string $f, bool $r) => self::uuid($f, $r),
            'postid' => fn(string $f, bool $r) => self::uuid($f, $r),
            'commentid' => fn(string $f, bool $r) => self::uuid($f, $r),

            // Content filter
            'contentFilterBy' => fn(string $f, bool $r) => self::contentFilter($f, $r),

            // User credentials and profile
            'email' => fn(string $f, bool $r) => self::email($f, $r),
            'username' => fn(string $f, bool $r) => self::username($f, $r),
            'password' => fn(string $f, bool $r) => self::password($f, $r),
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
