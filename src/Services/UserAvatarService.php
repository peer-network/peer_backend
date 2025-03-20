<?php

namespace Fawaz\Services;

class UserAvatarService
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

    public function handleFileUpload(string $base64Image, string $userId): array
    {
        return $this->fileHandler->handleFileUpload($base64Image, 'image', $userId, 'profile');
    }
}
