<?php

declare(strict_types=1);

namespace Fawaz\App;

use Fawaz\App\Validation\RequestValidator;
use Fawaz\App\Validation\ValidatorErrors;
use Fawaz\Database\UserMapper;
use Fawaz\Utils\ResponseHelper;

class FieldValidationService
{
    use ResponseHelper;

    public function __construct(
        protected UserMapper $userMapper
    ) {
    }

    public function validateField(string $key, string $value, string $clientRequestId): array
    {
        $normalizedKey = strtoupper(trim($key));
        if ($normalizedKey === '') {
            return [
                'response' => self::respondWithError(30105, clientRequestId: $clientRequestId),
            ];
        }

        $result = match ($normalizedKey) {
            'EMAIL' => $this->validateEmail($value),
            'USERNAME' => $this->validateUsername($value),
            'PASSWORD' => $this->validatePassword($value),
            default => self::respondWithError(30105,clientRequestId: $clientRequestId),
        };

        return $result;
    }

    private function validateEmail(string $email): array
    {
        $validation = RequestValidator::validate(['email' => $email], ['email']);
        if ($validation instanceof ValidatorErrors) {
            return self::respondWithError((int)$validation->errors[0]);
        }

        if ($this->userMapper->isEmailTaken(trim($email))) {
            return self::respondWithError(30601);
        }

        return self::createSuccessResponse(12501, [], false);
    }

    private function validateUsername(string $username): array
    {
        $validation = RequestValidator::validate(['username' => $username], ['username']);
        if ($validation instanceof ValidatorErrors) {
            return self::respondWithError((int)$validation->errors[0]);
        }

        if (!empty($this->userMapper->loadByName(trim($username)))) {
            return self::respondWithError(30602);
        }

        return self::createSuccessResponse(12501, [], false);
    }

    private function validatePassword(string $password): array
    {
        $validation = RequestValidator::validate(['password' => $password], ['password']);
        if ($validation instanceof ValidatorErrors) {
            return self::respondWithError((int)$validation->errors[0]);
        }

        return self::createSuccessResponse(12501, [], false);
    }
}
