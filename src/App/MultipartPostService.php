<?php

namespace Fawaz\App;

use Fawaz\App\Models\MultipartPost;
use Fawaz\Services\JWTService;
use Psr\Log\LoggerInterface;
use Fawaz\Utils\ResponseHelper;

class MultipartPostService
{
	use ResponseHelper;

    protected ?string $currentUserId = null;

    public function __construct(
        protected LoggerInterface $logger,
        protected JWTService $tokenService

    ) {
    }

    /**
     * Set Current UserId of Logged In user
     */
    public function setCurrentUserId(string $bearerToken): void
    {
        if ($bearerToken !== null && $bearerToken !== '') {
            try {
                $decodedToken = $this->tokenService->validateToken($bearerToken);
                if ($decodedToken) {
                    $this->currentUserId = $decodedToken->uid;
                    $this->logger->info('Query.setCurrentUserId started');
                } else {
                    $this->currentUserId = null;
                }
            } catch (\Throwable $e) {
                $this->logger->error('Invalid token', ['exception' => $e]);
                $this->currentUserId = null;
            }
        } else {
            $this->currentUserId = null;
        }
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
            if (!self::checkAuthentication($this->currentUserId)) {
                return self::respondWithError(60501);
            }

            // Apply Validation
            $multipartPost = new MultipartPost($requestObj);
            $multipartPost->applyAdditionalFilter($requestObj);
            $multipartPost->validateEligibilityToken($this->tokenService);
            $multipartPost->validateMediaContentTypes();

            // Move file to tmp folder
            $allMetadata = $multipartPost->moveFileToTmp();

            return [
                'status' => 'success',
                'ResponseCode' => 0000, // Files uploaded successfully
                'uploadedFiles' => implode(',', $allMetadata),
            ];
        } catch (ValidationException $e) {
            $this->logger->warning("Validation error in MultipartPostService.handleFileUpload", ['error' => $e->getMessage(), 'mess'=> $e->getErrors()]);
            return self::respondWithError($e->getErrors()[0]);
        } catch(\Exception $e){
            $this->logger->warning("Validation error in MultipartPostService.handleFileUpload (Exception)", ['error' => $e->getMessage()]);
            return self::respondWithError(40301);
        }

    }
   
}
