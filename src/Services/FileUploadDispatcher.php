<?php

namespace Fawaz\Services;

class FileUploadDispatcher
{
    private CoverPostService $coverPostService;
    private ImagePostService $imagePostService;
    private PodcastPostService $podcastPostService;
    private VideoPostService $videoPostService;
    private NotesPostService $notesPostService;

    public function __construct()
    {
        $this->coverPostService = new CoverPostService();
        $this->imagePostService = new ImagePostService();
        $this->podcastPostService = new PodcastPostService();
        $this->videoPostService = new VideoPostService();
        $this->notesPostService = new NotesPostService();
    }

    private function argsToJsString($args) {
        return json_encode($args);
    }

    public function handleUploads(array $files, string $contentType, string $identifiers): array
    {
        $results = [];

        if (empty($files)) {
            return ['success' => false, 'error' => 'Invalid files parameter provided'];
        }

        $serviceMap = [
            'cover' => $this->coverPostService,
            'image' => $this->imagePostService,
            'audio' => $this->podcastPostService,
            'video' => $this->videoPostService,
            'text' => $this->notesPostService,
        ];

        if (!isset($serviceMap[$contentType])) {
            return ['success' => false, 'error' => 'Invalid contentType parameter provided'];
        }

        $imageCount = count(array_filter($files));

        foreach ($files as $index => $file) {
            if (!$file) {
                continue;
            }

            $identifier = ($imageCount > 1) ? "{$identifiers}_" . ($index + 1) : $identifiers;

            $result = $serviceMap[$contentType]->handleFileUpload($file, $identifier);

            if (!empty($result['success'])) {
                unset($result['success']);
                $results[] = $result;
            } else {
                return ['success' => false, 'error' => $this->argsToJsString($result['error'] ?? 'Unknown error')];
            }
        }

        return !empty($results) ? ['path' => $this->argsToJsString($results)] : ['success' => false, 'error' => 'No valid files uploaded'];
    }
}
