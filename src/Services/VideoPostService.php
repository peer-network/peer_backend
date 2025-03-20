<?php

namespace Fawaz\Services;

class VideoPostService
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

    public function handleFileUpload(string $base64Video, string $videoId): array
    {
        return $this->fileHandler->handleFileUpload($base64Video, 'video', $videoId, 'video');
    }
}
