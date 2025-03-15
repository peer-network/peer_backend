<?php

namespace Fawaz\Services;

class NotesPostService
{
    private Base64FileHandler $fileHandler;

    public function __construct()
    {
        $this->fileHandler = new Base64FileHandler();
    }

    public function handleFileUpload(string $base64Text, string $noteId): array
    {
        return $this->fileHandler->handleFileUpload($base64Text, 'text', $noteId, 'text');
    }
}
