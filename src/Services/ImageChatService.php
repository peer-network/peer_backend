<?php

namespace Fawaz\Services;

class ImageChatService
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

    public function handleFileUpload(string $base64Image, string $chatId): array
    {
        return $this->fileHandler->handleFileUpload($base64Image, 'image', $chatId, 'chat');
    }
}
