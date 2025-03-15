<?php

namespace Fawaz\Services;

use getID3;

class Base64FileHandler
{
    private array $errors = [];

    public function getErrors(): array
    {
        return $this->errors;
    }

    private function getAllowedMimeTypes(string $contentType): array
    {
        return match ($contentType) {
            'image' => [
                'image/webp', 'image/jpeg', 'image/png', 'image/gif',
                'image/heic', 'image/heif', 'image/tiff'
            ],
            'video' => [
                'video/mp4', 'video/quicktime', 'video/x-m4v',
                'video/x-msvideo', 'video/3gpp', 'video/x-matroska',
                'video/webm'
            ],
            'audio' => ['audio/mpeg', 'audio/wav'],
            'text' => ['text/plain'],
            default => []
        };
    }

    private function getAllowedExtensions(string $contentType): array
    {
        return match ($contentType) {
            'image' => ['webp', 'jpeg', 'jpg', 'png', 'gif', 'heic', 'heif', 'tiff'],
            'video' => ['mp4', 'mov', 'avi', 'm4v', 'mkv', '3gp', 'webm'],
            'audio' => ['mp3', 'wav'],
            'text' => ['txt'],
            default => []
        };
    }

    private function getSubfolder(string $contentType, ?string $defaultSubfolder = null): string
    {

        if ($defaultSubfolder !== null && $defaultSubfolder !== '') {
            return $defaultSubfolder;
        }

        return match($contentType) {
            'audio' => 'audio',
            'chat' => 'chat',
            'cover' => 'cover',
            'image' => 'image',
            'profile' => 'profile',
            'text' => 'text',
            'userData' => 'userData',
            'video' => 'video',
            default => 'other'
        };
    }

    private function formatDuration(float $durationInSeconds): string
    {
        $hours = \floor($durationInSeconds / 3600);
        $minutes = \floor(($durationInSeconds % 3600) / 60);
        $seconds = \floor($durationInSeconds % 60);
        $milliseconds = \round(($durationInSeconds - \floor($durationInSeconds)) * 100);

        return \sprintf('%02d:%02d:%02d', $hours, $minutes, $seconds);
    }

	private function formatBytes(int $bytes): string
	{
		$units = ['B', 'KB', 'MB', 'GB', 'TB', 'PB'];
		$index = 0;

		while ($bytes >= 1024 && $index < count($units) - 1) {
			$bytes /= 1024;
			$index++;
		}

		return \sprintf('%.2f %s', $bytes, $units[$index]);
	}

    private function getMediaDuration(string $filePath): ?array
    {
        if (!\file_exists($filePath)) {
            return null;
        }

        try {
            $getID3 = new getID3();
            $fileInfo = $getID3->analyze($filePath);
			$information = [];

			if (!empty($fileInfo['video']['resolution_x']) && !empty($fileInfo['video']['resolution_y'])) {
				$width = $fileInfo['video']['resolution_x'];
				$height = $fileInfo['video']['resolution_y'];
				
				$gcd = gmp_intval(gmp_gcd($width, $height));
				$ratio = ($width / $gcd) . ':' . ($height / $gcd);
				$auflg = "{$width}x{$height}";
			}

			$information['duration'] = isset($fileInfo['playtime_seconds']) ? (float)$fileInfo['playtime_seconds'] : null;
			$information['ratiofrm'] = isset($ratio) ? $ratio : null;
			$information['resolution'] = isset($auflg) ? $auflg : null;

            return isset($information) ? (array)$information : null;
            
        } catch (\Exception $e) {
            \error_log("getID3 Error: " . $e->getMessage());
            return null;
        }
    }

    private function sanitizeBase64Input(string $input): string
    {
        if (preg_match('/data:[a-zA-Z0-9+.-]+\/([a-zA-Z0-9+.-]+);base64,([A-Za-z0-9+\/=\r\n]+)/', $input, $matches)) {
            return $matches[0];
        } else {
			$this->errors['Base64'][] = 'Invalid Base64 format: ' . substr($input, 0, 100);
        }
        return $input;
    }

    private function sanitizeBase64Data(string $inputs): array {
        $sanitizedBase64Array = [];

        foreach ((array) $inputs as $input) {
            if (preg_match('/data:[a-zA-Z0-9+.-]+\/([a-zA-Z0-9+.-]+);base64,([A-Za-z0-9+\/=\r\n]+)/', $input, $matches)) {
                $sanitizedBase64Array[] = $matches[0];
            } else {
				$this->errors['Base64'][] = 'Invalid Base64 format: ' . substr($input, 0, 100);
            }
        }

        return $sanitizedBase64Array;
    }

    public function isValidBase64Media(string $base64File, string $contentType, array $options = []): bool
    {
        $maxFileSize = $options['max_size'] ?? 79 * 1024 * 1024;
        $base64File = $this->sanitizeBase64Input($base64File);

        $pattern = '#^data:(?<type>[a-z]+)/(?<extension>[a-z0-9]+);base64,(?<content>[A-Za-z0-9+/=\r\n]+)#i';

        if (!preg_match($pattern, $base64File, $matches)) {
            $this->errors['unknown'][] = 'No valid Base64 media found in the input.';
            return false;
        }

        $extension = strtolower($matches['extension']);

        if ($contentType === 'audio' && $extension === 'mpeg') {
            $extension = 'mp3';
        }
        if ($contentType === 'text' && $extension === 'plain') {
            $extension = 'txt';
        }

        if (!in_array($extension, $this->getAllowedExtensions($contentType))) {
            $this->errors[$contentType][] = "Invalid extension ($extension). Allowed: " . implode(', ', $this->getAllowedExtensions($contentType));
            return false;
        }

        $base64String = preg_replace('/\s+/', '', $matches['content']);
        $decodedFile = base64_decode($base64String, true);

        if ($decodedFile === false) {
            $this->errors[$contentType][] = 'Failed to decode the Base64 media.';
            return false;
        }

        if (strlen($decodedFile) > $maxFileSize) {
            $this->errors[$contentType][] = ucfirst($contentType) . " size exceeds the max limit of " . ($maxFileSize / 1024 / 1024) . " MB.";
            return false;
        }

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_buffer($finfo, $decodedFile);
        finfo_close($finfo);

        $allowedMimeTypes = $this->getAllowedMimeTypes($contentType);
        if (!in_array($mimeType, $allowedMimeTypes)) {
            $this->errors[$contentType][] = "Invalid MIME type ($mimeType). Allowed: " . implode(', ', $allowedMimeTypes);
            return false;
        }

        return true;
    }

    public function handleFileUpload(string $base64File, string $contentType, string $identifier, string $defaultSubfolder = null): array
    {
        if (!$this->isValidBase64Media($base64File, $contentType)) {
            return ['success' => false, 'error' => $this->errors];
        }

        $base64String = preg_replace('/\s+/', '', explode(',', $base64File, 2)[1] ?? '');
        $decodedFile = base64_decode($base64String, true);

        if ($decodedFile === false || strlen($decodedFile) === 0) {
            $this->errors[$contentType][] = 'Failed to decode Base64 data or empty file.';
            return ['success' => false, 'error' => $this->errors];
        }

        $fileExtension = explode('/', explode(';', explode(',', $base64File)[0])[0])[1] ?? '';

        if ($contentType === 'audio' && $fileExtension === 'mpeg') {
            $fileExtension = 'mp3';
        }
        if ($contentType === 'text' && $fileExtension === 'plain') {
            $fileExtension = 'txt';
        }

        if (!$fileExtension || !in_array($fileExtension, $this->getAllowedExtensions($contentType))) {
            $this->errors[$contentType][] = 'Could not determine file extension or extension not allowed.';
            return ['success' => false, 'error' => $this->errors];
        }

        $subfolder = $this->getSubfolder($contentType, $defaultSubfolder);
        $directoryPath = __DIR__ . "/../../runtime-data/media/$subfolder";

        if (!is_dir($directoryPath)) {
            $this->errors[$contentType][] = 'Could not create directory: ' . $subfolder;
            return ['success' => false, 'error' => $this->errors];
        }

        $filePath = "$directoryPath/$identifier.$fileExtension";

        if (file_put_contents($filePath, $decodedFile) === false) {
            $this->errors[$contentType][] = 'Could not save file.';
            return ['success' => false, 'error' => $this->errors];
        }

        $getfileinfo = $this->getMediaDuration($filePath);

        $duration = $ratiofrm = $resolution = null;
		$size = $this->formatBytes(\strlen($decodedFile));

        if (in_array($contentType, ['audio', 'video'])) {
            $duration = $getfileinfo['duration'] ?? null;
        }

        if ($contentType === 'video') {
            $ratiofrm = $getfileinfo['ratiofrm'] ?? null;
        }

        if (in_array($contentType, ['image', 'video'])) {
            $resolution = $getfileinfo['resolution'] ?? null;
        }

        $options = array_filter([
            'size' => $size,
            'duration' => $duration ? $this->formatDuration($duration) : null,
            'ratio' => $ratiofrm,
            'resolution' => $resolution
        ]);

        return array_filter([
            'success' => true,
            'path' => "/$subfolder/$identifier.$fileExtension",
            'options' => $options ?: null
        ]);
    }
}
