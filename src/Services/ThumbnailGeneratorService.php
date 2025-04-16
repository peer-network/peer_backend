<?php

namespace App\Services;

use Ramsey\Uuid\Uuid;

class ThumbnailGeneratorService
{
    private string $outputDir;

    public function __construct()
    {
        $this->outputDir = base_path('runtime-data/media/cover');

        if (!is_dir($this->outputDir)) {
            mkdir($this->outputDir, 0755, true);
        }
    }

    public function generateFromVideo(string $videoPath, string $coverFilename = null): ?array
    {
        if (!file_exists($videoPath)) {
            throw new \Exception("Video file not found: $videoPath");
        }

        $filename = $coverFilename ?? Uuid::uuid4()->toString() . '.jpg';
        $fullPath = $this->outputDir . '/' . $filename;

        // Get duration
        $durationCmd = "ffprobe -v error -show_entries format=duration -of default=noprint_wrappers=1:nokey=1 " . escapeshellarg($videoPath);
        $duration = (float) trim(shell_exec($durationCmd));
        $randomSecond = ($duration <= 1) ? 1 : rand(1, (int) floor($duration - 1));

        // Generate thumbnail
        $command = "ffmpeg -y -i " . escapeshellarg($videoPath) .
            " -ss 00:00:" . str_pad((string) $randomSecond, 2, '0', STR_PAD_LEFT) .
            ".000 -vframes 1 " . escapeshellarg($fullPath);
        shell_exec($command);

        if (!file_exists($fullPath)) {
            return null;
        }

        [$width, $height] = getimagesize($fullPath);
        $size = filesize($fullPath);

        return [
            'path' => '/media/cover/' . $filename,
            'options' => [
                'size' => round($size / 1048576, 2) . ' MB',
                'resolution' => $width . 'x' . $height,
            ]
        ];
    }
}
