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
                // Validate that the provided bearer access token exists in DB and is not expired
                // if (!$this->userMapper->accessTokenValidForUser($decodedToken->uid, $bearerToken)) {
                //     $this->logger->warning('Access token not found or expired for user', [
                //         'userId' => $decodedToken->uid,
                //     ]);
                //     $this->currentUserId = null;
                //     return;
                // }

                $this->currentUserId = $decodedToken->uid;
                $this->logger->debug('Query.setCurrentUserId started');
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
    public function checkForBasicValidation(array $requestObj): array
    {
        try {
            if (isset($requestObj['contentType'][0]) && !str_contains($requestObj['contentType'][0], 'multipart/form-data')) {
                throw new ValidationException("Invalid header format", [41514]); // Invalid header format
            }

            $maxFileSize = 1024 * 1024 * 500; // 500MB

            if (isset($requestObj['contentLength'][0]) && $requestObj['contentLength'][0] > $maxFileSize) {
                throw new ValidationException("Maximum file upload should be less than 500MB", [30261]); // Maximum file upload should be less than 500MB
            }

            return [
                'status' => 'success',
                'ResponseCode' => "11515",
            ];
        } catch (ValidationException $e) {
            $this->logger->warning("Validation error in MultipartPostService.handleFileUpload", ['error' => $e->getMessage(), 'mess' => $e->getErrors()]);
            return self::respondWithError($e->getErrors()[0]);
        } catch (\Exception $e) {
            $this->logger->warning("Validation error in MultipartPostService.handleFileUpload (Exception)", ['error' => $e->getMessage()]);
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
                return self::respondWithError(60501);
            }
            // Check For Wallet Balance
            $this->postService->setCurrentUserId($this->currentUserId);
            $hasPostCredits = $this->postService->postEligibility(false);

            if (isset($hasPostCredits['status']) && $hasPostCredits['status'] == 'error') {
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
            $this->logger->warning("Validation error in MultipartPostService.handleFileUpload", ['error' => $e->getMessage(), 'mess' => $e->getErrors()]);
            return self::respondWithError($e->getErrors()[0]);
        } catch (\Exception $e) {
            $this->logger->warning("Validation error in MultipartPostService.handleFileUpload (Exception)", ['error' => $e->getMessage()]);
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
            $updateStmt->bindValue(':status', 'FILE_UPLOADED', \PDO::PARAM_STR);
            $updateStmt->bindValue(':token', $eligibilityToken, \PDO::PARAM_STR);
            $updateStmt->execute();
            $this->logger->info("Inserted new token into database", ['eligibilityToken' => $eligibilityToken]);

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
            throw new ValidationException("Eligibility Token has been expired", [40902]);
        }
    }


}
