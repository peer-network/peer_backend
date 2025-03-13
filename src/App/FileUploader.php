<?php

namespace Fawaz\App;

use Psr\Log\LoggerInterface;

class FileUploader
{
    private $logger;

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    public function handleFileUpload(string $base64File, string $contentType, string $identifier, bool $isSinglePath = false, string $defaultSubfolder = null): ?string
    {
        $allowedContentTypes = ['image', 'text', 'video', 'audio', 'imagegallery', 'videogallery', 'audiogallery'];
        if (!in_array($contentType, $allowedContentTypes)) {
            $this->logger->error('Invalid content type provided for file upload', ['contentType' => $contentType]);
            return null;
        }

        $mediaPaths = $isSinglePath ? null : [];
        $searchStrings = $this->getSearchStrings($contentType);

        foreach ($searchStrings as $searchStr) {
            $pos = 0;
            $x = 1;
            while (($pos = strpos($base64File, $searchStr, $pos)) !== false) {
                $end = strpos($base64File, '"', $pos + strlen($searchStr));
                if ($end === false) {
                    $this->logger->error('Failed to find the end of the base64 encoded string');
                    return null;
                }

                $b64 = substr($base64File, $pos, $end - $pos);
                $fileContent = base64_decode(substr($b64, strlen($searchStr)), true);

                if ($fileContent === false) {
                    $this->logger->error('Failed to decode base64 file content');
                    return null;
                }

                $fileExtension = $this->getFileExtension($searchStr);
                if ($fileExtension === null) {
                    $this->logger->error('Failed to get file extension from base64 header', ['base64Header' => $searchStr]);
                    return null;
                }

                $fileName = $isSinglePath 
                    ? $identifier . '.' . $fileExtension 
                    : $identifier . '_' . $x++ . '.' . $fileExtension;
                
                $subfolder = $this->getSubfolder($contentType, $defaultSubfolder);
                $directoryPath = __DIR__ . '/../../runtime-data/media/' . $subfolder;

                if (!is_dir($directoryPath) && !mkdir($directoryPath, 0755, true)) {
                    $this->logger->error('Failed to create media subfolder', ['subfolder' => $subfolder, 'directoryPath' => $directoryPath]);
                    return null;
                }

                $filePath = $directoryPath . '/' . $fileName;

                try {
                    if (file_put_contents($filePath, $fileContent) === false) {
                        $this->logger->error('Failed to create post upload');
                        return null;
                    }
                    if ($isSinglePath) {
                        return '/' . $subfolder . '/' . $fileName;
                    } else {
                        $mediaPaths[] = '/' . $subfolder . '/' . $fileName;
                    }
                } catch (\Exception $e) {
                    $this->logger->error('Exception occurred while saving uploaded file', ['exception' => $e, 'filePath' => $filePath]);
                    return null;
                }

                $pos = $end + 1;
            }
        }

        return $isSinglePath ? null : implode(',', $mediaPaths);
    }

    private function getSearchStrings(string $contentType): array
    {
        return match ($contentType) {
            'image' => ['data:image/webp;base64,', 'data:image/jpeg;base64,', 'data:image/png;base64,', 'data:image/gif;base64,', 'data:image/heic;base64,', 'data:image/heif;base64,', 'data:image/tiff;base64,'],
            'video' => ['data:video/mp4;base64,', 'data:video/ogg;base64,', 'data:video/quicktime;base64,'],
            'audio' => ['data:audio/mpeg;base64,', 'data:audio/wav;base64,'],
            'text' => ['data:text/plain;base64,'],
            default => []
        };
    }

    private function getSubfolder(string $contentType, ?string $defaultSubfolder = null): string
    {
        if ($defaultSubfolder !== null && $defaultSubfolder !== '') {
            return $defaultSubfolder;
        }

        return match($contentType) {
            'image' => 'image',
            'audio' => 'audio',
            'video' => 'video',
            'text' => 'text',
            default => 'other'
        };
    }

    private function getFileExtension(string $base64Header): ?string
    {
        return match (true) {
            str_contains($base64Header, 'data:image/jpeg;base64,') => 'jpg',
            str_contains($base64Header, 'data:image/png;base64,') => 'png',
            str_contains($base64Header, 'data:image/gif;base64,') => 'gif',
            str_contains($base64Header, 'data:image/webp;base64,') => 'webp',
            str_contains($base64Header, 'data:image/heic;base64,') => 'heic',
            str_contains($base64Header, 'data:image/heif;base64,') => 'heif',
            str_contains($base64Header, 'data:image/tiff;base64,') => 'tiff',
            str_contains($base64Header, 'data:image/svg+xml;base64,') => 'svg',
            str_contains($base64Header, 'data:video/mp4;base64,') => 'mp4',
            str_contains($base64Header, 'data:video/ogg;base64,') => 'ogg',
            str_contains($base64Header, 'data:video/quicktime;base64,') => 'mov',
            str_contains($base64Header, 'data:audio/mpeg;base64,') => 'mp3',
            str_contains($base64Header, 'data:audio/wav;base64,') => 'wav',
            str_contains($base64Header, 'data:application/pdf;base64,') => 'pdf',
            str_contains($base64Header, 'data:text/plain;base64,') => 'txt',
            default => null
        };
    }
}
