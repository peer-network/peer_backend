<?php

namespace Fawaz\Services;
use FFMpeg\FFMpeg;
use FFMpeg\Coordinate\TimeCode;
use FFMpeg\Exception\RuntimeException as FFMpegException;


class VideoCoverGenerator
{
    private FFMpeg $ffmpeg;
    
    public function __construct()
    {
        $this->ffmpeg = FFMpeg::create([
            'ffmpeg.binaries'  => getenv('FFMPEG_PATH') ?: '/usr/bin/ffmpeg',
            'ffprobe.binaries' => getenv('FFPROBE_PATH') ?: '/usr/bin/ffprobe',
            'timeout'          => 30, // Seconds
        ]);
    }

    public function generate(string $videoPath): string
    {
        if (!is_file($videoPath)) {
            throw new \InvalidArgumentException("Video file not found: $videoPath");
        }

        $outputPath = $this->generateSecureTempPath();

        try {
            $video = $this->ffmpeg->open($videoPath);
            $video->frame(TimeCode::fromSeconds(0))
                  ->save($outputPath);
        } catch (FFMpegException $e) {
            // Clean up if frame extraction failed mid-process
            if (file_exists($outputPath)) {
                @unlink($outputPath);
            }
            throw new \RuntimeException("Cover generation failed: " . $e->getMessage());
        }

        return $outputPath;
    }

    private function generateSecureTempPath(): string
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'cover_');
        unlink($tempFile); // Remove tempnam-created file
        return $tempFile . '.jpg';
    }

    public function deleteTemporaryFile(?string $path): void
    {
        if ($path && file_exists($path)) {
            @unlink($path);
        }
    }
}