<?php
declare(strict_types=1);

namespace Fawaz\Services;

use FFMpeg\FFMpeg;
use FFMpeg\Coordinate\TimeCode;

class VideoCoverGenerator
{
    public function generate(string $videoPath): string
    {
        if (!is_file($videoPath)) {
            throw new \InvalidArgumentException("Video file not found: $videoPath");
        }

        $ffmpeg = FFMpeg::create();
        $media = $ffmpeg->open($videoPath);

        if (!($media instanceof \FFMpeg\Media\Video)) {
            throw new \RuntimeException("Provided file is not a valid video with visual stream: $videoPath");
        }

        $outputPath = sys_get_temp_dir() . '/' . uniqid('cover_', true) . '.jpg';

        $media->frame(TimeCode::fromSeconds(0))->save($outputPath);

        if (!file_exists($outputPath)) {
            throw new \RuntimeException("Failed to generate video frame.");
        }

        return $outputPath;
    }
    public function deleteTemporaryFile(?string $path): void
    {
        if ($path && is_file($path)) {
            unlink($path);

        }
    }
}