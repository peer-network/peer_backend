<?php

declare(strict_types=1);

namespace Fawaz\Services;

class CoverPostService
{
    private readonly Base64FileHandler $fileHandler;

    public function __construct()
    {
        $this->fileHandler = new Base64FileHandler();
    }

    public function getErrors(): array
    {
        return $this->fileHandler->getErrors();
    }

    public function handleFileUpload(string $base64Image, string $imageId): array
    {
        return $this->fileHandler->handleFileUpload($base64Image, 'image', $imageId, 'cover');
    }
}
