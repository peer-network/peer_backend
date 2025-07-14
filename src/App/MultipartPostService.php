<?php

namespace Fawaz\App;

use Fawaz\App\Models\MultipartPost;
use Fawaz\Services\JWTService;
use Psr\Log\LoggerInterface;

class MultipartPostService
{
    protected ?string $currentUserId = null;

    public function __construct(
        protected LoggerInterface $logger,
        protected JWTService $tokenService

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
     * Handle File Upload
     * 
     * Apply Validation, includes request params, media file
     * 
     */
    public function handleFileUpload(array $requestObj): array
    {
        try{
            // Apply Validation
            $multipartPost = new MultipartPost($requestObj);
            $multipartPost->applyAdditionalFilter($requestObj);
            $multipartPost->validateEligibilityToken($this->tokenService);
            $multipartPost->validateMediaContentTypes();

            $allMetadata = [];

            foreach ($multipartPost->getMedia() as $key => $media) {
                $originalType = $media->getClientMediaType();
                $fileName = $media->getClientFilename();
                $extension = pathinfo($fileName, PATHINFO_EXTENSION);
                
                $tmpFilename = self::generateUUID();
                $directoryPath = __DIR__ . "/../../runtime-data/media/tmp";
                
                if (!is_dir($directoryPath)) {
                    throw new ValidationException("Directory does not exist: $directoryPath", [5465]); // Directory does not exist
                }

                $filePath = "$directoryPath/$tmpFilename.$extension";

                $media->moveTo($filePath);

                $metadata = [
                    'fileName' => $tmpFilename.'.'.$extension,
                    'mimeType' => $originalType,
                    'mediaType' => $extension,
                ];

                $allMetadata[] = $metadata;
            }

            return [
                'status' => 'success',
                'message' => 0000, // Files uploaded successfully
                'data' => $allMetadata,
            ];

        }
        catch (ValidationException $e) {
            $this->logger->warning("Validation error in MultipartPostService.handleFileUpload", ['error' => $e->getMessage(), 'mess'=> $e->getErrors()]);
            return $this->respondWithError($e->getErrors()[0]);
        } catch(\Exception $e){
            $this->logger->warning("Validation error in MultipartPostService.handleFileUpload (Exception)", ['error' => $e->getMessage()]);
            return $this->respondWithError(40301);
        }

    }
   
}
