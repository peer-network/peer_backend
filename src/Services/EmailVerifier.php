<?php

declare(strict_types=1);

namespace Fawaz\Services;

use FFI;

class EmailVerifier
{
    private const CDEF = <<<CDEF
        intptr_t verify_email_json(const char* email);
        void free_verification_result(intptr_t ptr);
    CDEF;

    private static ?FFI $ffi = null;

    /**
     * Run the Rust-based verifier and return its associative array response.
     *
     * @return array{status: string, data?: array<string, mixed>, message?: string}
     */
    public function verify(string $email): array
    {
        try {
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new \RuntimeException('Invalid email format provided to verifier.');
            }

            $json = $this->callVerifier($email);
            $payload = json_decode($json, true);

            if (!is_array($payload) || !isset($payload['status'])) {
                throw new \RuntimeException('Malformed response from email verifier.');
            }

            return $payload;
        } catch (\Throwable $e) {
            return [
                'status' => 'error',
                'message' => $e->getMessage(),
            ];
        }
    }

    private function callVerifier(string $email): string
    {
        $ffi = self::getFfi();

        $result = $ffi->verify_email_json($email);

        if ($result === null) {
            throw new \RuntimeException('Email verifier did not return a response.');
        }

        try {
            $pointer = FFI::cast('char*', $result);
            return FFI::string($pointer);
        } finally {
            $ffi->free_verification_result($result);
        }
    }

    private static function getFfi(): FFI
    {
        if (self::$ffi === null) {
            $libraryPath = self::resolveLibraryPath();
            self::$ffi = FFI::cdef(self::CDEF, $libraryPath);
        }

        return self::$ffi;
    }

    private static function resolveLibraryPath(): string
    {
        $rootDir = dirname(__DIR__, 2);

        if (PHP_OS_FAMILY === 'Windows') {
            $library = $rootDir . DIRECTORY_SEPARATOR . 'emailverification/target/release/emailverification.dll';
        } elseif (PHP_OS_FAMILY === 'Darwin') {
            $library = $rootDir . DIRECTORY_SEPARATOR . 'emailverification/target/release/libemailverification.dylib';
        } else {
            $library = $rootDir . DIRECTORY_SEPARATOR . 'emailverification/target/release/libemailverification.so';
        }

        if (!file_exists($library)) {
            throw new \RuntimeException(sprintf('Email verification library not found at path: %s', $library));
        }

        return $library;
    }
}
