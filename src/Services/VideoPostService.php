<?php

namespace App\Services;

use App\Utils\Base64FileHandler;
use App\Services\ThumbnailGeneratorService;

class VideoPostService
{
    private Base64FileHandler $fileHandler;
    private ThumbnailGeneratorService $thumbnailService;

    public function __construct()
    {
        $this->fileHandler = new Base64FileHandler();
        $this->thumbnailService = new ThumbnailGeneratorService();
    }

    public function handleFileUpload(string $base64Video, string $videoId, ?array $existingCover = null): array
    {
        // 1. Saving the video to Disk
        $uploadResult = $this->fileHandler->handleFileUpload(
            $base64Video,
            'video',
            $videoId,
            'video'
        );

        $videoPath = public_path($uploadResult['path']);

        // 2. If the cover already exists, we don't do anything.
        if ($existingCover !== null) {
            $uploadResult['cover'] = $existingCover;
            return $uploadResult;
        }

        // 3. Generating the cover
        try {
            $coverData = $this->thumbnailService->generateFromVideo($videoPath);
            if ($coverData) {
                $uploadResult['cover'] = $coverData;
            }
        } catch (\Exception $e) {
            \Log::error('Thumbnail generation failed: ' . $e->getMessage());
        }

        return $uploadResult;
    }
}