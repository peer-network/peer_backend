<?php

namespace Fawaz\App;

use DateTime;
use Fawaz\App\Models\MultipartPost;
use Fawaz\Database\UserMapper;
use Fawaz\Services\JWTService;
use Fawaz\Utils\PeerLoggerInterface;
use Fawaz\Utils\ResponseHelper;
use PDO;

class MultipartPostService
{
    use ResponseHelper;

    protected ?string $currentUserId = null;

    public function __construct(
        protected PeerLoggerInterface $logger,
        protected PDO $db,
        protected PostService $postService,
        protected JWTService $tokenService,
        protected UserMapper $userMapper
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

                $this->currentUserId = $decodedToken->uid;
                $this->logger->debug('MultipartPostService.setCurrentUserId started');
            } catch (\Throwable $e) {
                $this->logger->error('MultipartPostService.setCurrentUserId: Invalid token', ['exception' => $e]);
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
    public function checkForBasicValidation(array $requestObj): array
    {
        try {
            if (isset($requestObj['contentType'][0]) && !str_contains($requestObj['contentType'][0], 'multipart/form-data')) {
                $this->logger->error('MultipartPostService.checkForBasicValidation: Invalid header format');
                throw new ValidationException("Invalid header format", [41514]); // Invalid header format
            }

            $maxFileSize = 1024 * 1024 * 500; // 500MB

            if (isset($requestObj['contentLength'][0]) && $requestObj['contentLength'][0] > $maxFileSize) {
                $this->logger->error('MultipartPostService.checkForBasicValidation: File size exceeds maximum limit');
                throw new ValidationException("Maximum file upload should be less than 500MB", [30261]); // Maximum file upload should be less than 500MB
            }

            return [
                'status' => 'success',
                'ResponseCode' => "11515",
            ];
        } catch (ValidationException $e) {
            $this->logger->error('MultipartPostService.checkForBasicValidation: Validation error', ['error' => $e->getMessage(), 'mess' => $e->getErrors()]);
            return self::respondWithError($e->getErrors()[0]);
        } catch (\Exception $e) {
            $this->logger->error("Validation error in MultipartPostService.checkForBasicValidation (Exception)", ['error' => $e->getMessage()]);
            return self::respondWithError(41514);
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
        try {
            if (!self::checkAuthentication($this->currentUserId)) {
                $this->logger->error('MultipartPostService.handleFileUpload: Authentication failed');
                return self::respondWithError(60501);
            }
            // Check For Wallet Balance
            $this->postService->setCurrentUserId($this->currentUserId);
            $hasPostCredits = $this->postService->postEligibility(false);

            if (isset($hasPostCredits['status']) && $hasPostCredits['status'] == 'error') {
                $this->logger->error('MultipartPostService.handleFileUpload: Post eligibility failed', ['responseCode' => $hasPostCredits['ResponseCode']]);
                throw new ValidationException("Post Eligibility failed ", [$hasPostCredits['ResponseCode']]); // Post Eligibility failed
            }

            $tokenObj = [
                'userid' => $this->currentUserId,
                'token' => $requestObj['eligibilityToken']
            ];

            $this->checkTokenExpiry($tokenObj);

            // Apply Validation
            $multipartPost = new MultipartPost($requestObj);
            $multipartPost->validateRequiredFields();
            $multipartPost->validateMediaContentTypes();
            $multipartPost->validateMediaAllow();

            // Move file to tmp folder
            $allMetadata = $multipartPost->moveFileToTmp();

            $this->updateTokenStatus($requestObj['eligibilityToken']);

            return [
                'status' => 'success',
                'ResponseCode' => "11515",
                'uploadedFiles' => implode(',', $allMetadata),
            ];
        } catch (ValidationException $e) {
            $this->logger->error('MultipartPostService.handleFileUpload: Validation error', ['error' => $e->getMessage(), 'mess' => $e->getErrors()]);
            return self::respondWithError($e->getErrors()[0]);
        } catch (\Exception $e) {
            $this->logger->error("MultipartPostService.handleFileUpload:  (Exception)", ['error' => $e->getMessage()]);
            return self::respondWithError(41514);
        }

    }

    /**
     * Expire Token
     *
     */
    public function updateTokenStatus($eligibilityToken): void
    {
        $this->logger->debug("MultipartPostService.updateTokenStatus started");

        try {
            $updateSql = "
                    UPDATE eligibility_token
                    SET status = :status
                    WHERE token = :token
                ";
            $updateStmt = $this->db->prepare($updateSql);
            $updateStmt->bindValue(':status', 'FILE_UPLOADED', PDO::PARAM_STR);
            $updateStmt->bindValue(':token', $eligibilityToken, PDO::PARAM_STR);
            $updateStmt->execute();
            $this->logger->info("MultipartPostService.updateTokenStatus: Updated token status to FILE_UPLOADED", ['eligibilityToken' => $eligibilityToken]);

        } catch (\Throwable $e) {
            $this->logger->error("MultipartPostService.updateTokenStatus: Exception occurred while inserting token", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }

    /**
     * Check for Expire Token
     *
     */
    public function checkTokenExpiry($requestObj): void
    {
        $this->logger->debug("MultipartPostService.checkTokenExpiry started");

        if (empty($requestObj['token'])) {
            $this->logger->error('MultipartPostService.checkTokenExpiry: Token should not be empty');
            throw new ValidationException("Token Should not be empty.", [30102]); // Token Should not be empty
        }

        try {
            $this->tokenService->validateToken($requestObj['token']);


            $sql = "SELECT 1 FROM eligibility_token WHERE token = :token AND status IN ('FILE_UPLOADED', 'POST_CREATED')";
            $stmt = $this->db->prepare($sql);
            $stmt->bindValue(':token', $requestObj['token']);
            $stmt->execute();
            $tokenExists = $stmt->fetchColumn();
        } catch (\Exception $e) {
            $this->logger->error("MultipartPostService.checkTokenExpiry: Exception occurred while getting token", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw new ValidationException("Something went wrong", [41514]);
        }

        if ($tokenExists) {
            $this->logger->error('MultipartPostService.checkTokenExpiry: Eligibility Token has been expired', ['token' => $requestObj['token']]);
            throw new ValidationException("Eligibility Token has been expired", [40902]);
        }
    }


}
