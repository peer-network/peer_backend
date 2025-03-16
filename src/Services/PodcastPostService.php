<?php

namespace Fawaz\Services;

class PodcastPostService
{
    private Base64FileHandler $fileHandler;

    public function __construct()
    {
        $this->fileHandler = new Base64FileHandler();
    }

    public function handleFileUpload(string $base64Audio, string $audioId): array
    {
        return $this->fileHandler->handleFileUpload($base64Audio, 'audio', $audioId, 'audio');
    }
}
