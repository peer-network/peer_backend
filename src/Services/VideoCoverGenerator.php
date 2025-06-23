<?php

namespace Fawaz\Services;

class VideoCoverGenerator
{
    public function generate(string $videoPath): string
    {
        if (!is_file($videoPath)) {
            throw new \InvalidArgumentException("Video file not found: $videoPath");
        }

        $outputPath = sys_get_temp_dir() . '/' . uniqid('cover_', true) . '.jpg';

        $command = ['ffmpeg', '-y', '-ss', '0', '-i', $videoPath, '-vframes', '1', $outputPath];
        $descriptors = [
            1 => ['pipe', 'w'], 
            2 => ['pipe', 'w'], 
        ];

        $process = proc_open($command, $descriptors, $pipes);

        if (!is_resource($process)) {
            throw new \RuntimeException('Could not start FFmpeg process');
        }

        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);

        fclose($pipes[1]);
        fclose($pipes[2]);

        $exitCode = proc_close($process);

        if ($exitCode !== 0 || !file_exists($outputPath)) {
            throw new \RuntimeException("FFmpeg failed with code $exitCode. Error: $stderr");
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