<?php

declare(strict_types=1);

namespace Fawaz\App\Errors;

final class ErrorMapper
{
    /**
     * Convert an exception to the project-standard error response array.
     * Only shapes the payload; logging should happen at the call site.
     */
    public static function toResponse(\Throwable $e): array
    {
        // 1) If exception carries a ResponseCode explicitly, use it
        if ($e instanceof HasResponseCode) {
            return self::build((string)$e->getResponseCode());
        }

        // 2) Map common framework/runtime exceptions to known codes
        // Validation/argument errors
        if ($e instanceof ValidationException) {
            return self::build('30301'); // Missing/invalid required fields
        }

        // Database/IO failures
        if ($e instanceof \PDOException) {
            return self::build('40301'); // Generic database error
        }

        if ($e instanceof PermissionDeniedException) {
            return self::build('60501'); // Generic database error
        }

        // 3) Fallback generic error
        return self::build('40301');
    }

    private static function build(string $code): array
    {
        return [
            'status' => 'error',
            'ResponseCode' => $code,
        ];
    }
}

