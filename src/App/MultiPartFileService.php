<?php

namespace Fawaz\App;

use Psr\Log\LoggerInterface;

class MultiPartFileService
{
    protected ?string $currentUserId = null;

    public function __construct(
        protected LoggerInterface $logger,
    ) {
    }

    /**
     * Set Current UserId of Logged In user
     */
    public function setCurrentUserId(string $userId): void
    {
        $this->currentUserId = $userId;
    }

    /**
     * Generate UUID
     */
    private function generateUUID(): string
    {
        return sprintf(
            '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff), mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        );
    }

    /**
     * Validate UUID
     */
    public static function isValidUUID(string $uuid): bool
    {
        return preg_match('/^[a-fA-F0-9]{8}-[a-fA-F0-9]{4}-[a-fA-F0-9]{4}-[a-fA-F0-9]{4}-[a-fA-F0-9]{12}$/', $uuid) === 1;
    }

    /**
     * Return Structured Response
     */
    protected function respondWithError(string $message): array
    {
        return ['status' => 'error', 'ResponseCode' => $message];
    }

    /**
     * Validate Authenticated User
     */
    protected function checkAuthentication(): bool
    {
        if ($this->currentUserId === null) {
            $this->logger->warning('Unauthorized access attempt');
            return false;
        }
        return true;
    }

   
    /**
     * Upload Medias
     */
    public function uploadFile(array $files): array
    {
        return [
            'status' => 'success',
            'ResponseCode' => 2200,
        ];
        var_dump('sdfds');
        var_dump($files);
        exit;

    }
}
