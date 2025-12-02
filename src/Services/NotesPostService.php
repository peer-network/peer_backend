<?php

declare(strict_types=1);

namespace Fawaz\Services;

class NotesPostService
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

    public function handleFileUpload(string $base64Text, string $noteId): array
    {
        return $this->fileHandler->handleFileUpload($base64Text, 'text', $noteId, 'text');
    }
}
