<?php

declare(strict_types=1);

namespace Fawaz\Services;

class UserBiographyService
{
    private Base64FileHandler $fileHandler;

    public function __construct()
    {
        $this->fileHandler = new Base64FileHandler();
    }

    public function getErrors(): array
    {
        return $this->fileHandler->getErrors();
    }

    public function handleFileUpload(string $base64Pdf, string $userId): array
    {
        return $this->fileHandler->handleFileUpload($base64Pdf, 'text', $userId, 'userData');
    }
}
