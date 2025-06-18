<?php

namespace Fawaz\Services;

class VideoCoverGenerator
{
    public function generate(string $videoPath): string
    {
        $outputPath = sys_get_temp_dir() . '/' . uniqid('cover_', true) . '.jpg';

        $cmd = sprintf(
            'ffmpeg -y -i %s -ss 00:00:01.000 -vframes 1 %s 2>&1',
            escapeshellarg($videoPath),
            escapeshellarg($outputPath)
        );

        exec($cmd, $output, $code);

        if ($code !== 0) {
            throw new \RuntimeException('FFmpeg error: ' . implode("\n", $output));
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