<?php

namespace Fawaz\Services;

class UserBiographyService
{
    private Base64FileHandler $fileHandler;

    public function __construct()
    {
        $this->fileHandler = new Base64FileHandler();
    }

    public function handleFileUpload(string $base64Pdf, string $userId): array
    {
        return $this->fileHandler->handleFileUpload($base64Pdf, 'text', $userId, 'userData');
    }
}
