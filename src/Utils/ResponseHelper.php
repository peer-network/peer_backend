<?php

declare(strict_types=1);

namespace Fawaz\Utils;

trait ResponseHelper
{
    private function argsToJsString($args)
    {
        return json_encode($args);
    }

    private function argsToString($args)
    {
        return serialize($args);
    }

    private function validateRequiredFields(array $args, array $requiredFields): array
    {
        foreach ($requiredFields as $field) {
            if (empty($args[$field])) {
                return self::respondWithError(30301);
            }
        }

        return [];
    }

    private static function createSuccessResponse(int $responseCode, array|object $data = [], bool $countEnabled = true, ?string $countKey = null): array
    {
        return self::createResponse(
            $responseCode,
            $data,
            $countEnabled,
            $countKey,
            false
        );
    }

    private static function createSuccessResponseObject(int $responseCode, array|object $data = [], bool $countEnabled = true, ?string $countKey = null): SuccessResponse
    {
        return new SuccessResponse(
            self::createResponse(
                $responseCode,
                $data,
                $countEnabled,
                $countKey,
                true
            )
        );
    }

    private static function respondWithError(int $responseCode, array|object $data = [], bool $countEnabled = true, ?string $countKey = null): array
    {
        return self::createResponse(
            $responseCode,
            $data,
            $countEnabled,
            $countKey,
            true
        );
    }

    private static function respondWithErrorObject(int $responseCode, array|object $data = [], bool $countEnabled = true, ?string $countKey = null): ErrorResponse
    {
        return new ErrorResponse(
            self::createResponse(
                $responseCode,
                $data,
                $countEnabled,
                $countKey,
                true
            )
        );
    }

    private static function createResponse(int $responseCode, array|object $data = [], bool $countEnabled = true, ?string $countKey = null, ?bool $isError = null): array
    {
        // Determine if it is success (codes starting with 1 or 2) or error (3,4,5,6)
        $firstDigit = (int) substr((string) $responseCode, 0, 1);
        $isSuccess  = 1 === $firstDigit || 2 === $firstDigit;

        if (null !== $isError) {
            $isSuccess = !$isError;
        }

        $response = [
            'status'       => $isSuccess ? 'success' : 'error',
            'ResponseCode' => (string) $responseCode,
        ];

        if ($isSuccess) {
            $response['affectedRows'] = $data;

            if ($countEnabled && \is_array($data)) {
                if (null !== $countKey && isset($data[$countKey]) && \is_array($data[$countKey])) {
                    $response['counter'] = \count($data[$countKey]);
                } else {
                    $response['counter'] = \count($data);
                }
            }
        }

        return $response;
    }

    private function generateUUID(): string
    {
        return \sprintf(
            '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xFFFF),
            mt_rand(0, 0xFFFF),
            mt_rand(0, 0xFFFF),
            mt_rand(0, 0x0FFF) | 0x4000,
            mt_rand(0, 0x3FFF) | 0x8000,
            mt_rand(0, 0xFFFF),
            mt_rand(0, 0xFFFF),
            mt_rand(0, 0xFFFF)
        );
    }

    private function isValidUUID(string $uuid): bool
    {
        return 1 === preg_match('/^\{?[a-fA-F0-9]{8}\-[a-fA-F0-9]{4}\-[a-fA-F0-9]{4}\-[a-fA-F0-9]{4}\-[a-fA-F0-9]{12}\}?$/', $uuid);
    }

    /**
     * Validate Authenticated User.
     */
    private function checkAuthentication($currentUserId): bool
    {
        if (null === $currentUserId) {
            return false;
        }

        return true;
    }

    private static function validateDate(string $date, string $format = 'Y-m-d'): bool
    {
        $d = \DateTime::createFromFormat($format, $date);

        return $d && $d->format($format) === $date;
    }
}
