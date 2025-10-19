<?php

namespace Fawaz\App\Interfaces;

use Fawaz\App\Profile;
use Fawaz\App\Specs\ProfileSpecsFactory;
use Fawaz\App\ValidationException;
use Fawaz\Database\Interfaces\ProfileRepository;
use Fawaz\Utils\ErrorResponse;
use Fawaz\Utils\PeerLoggerInterface;
use Fawaz\Utils\ResponseHelper;
use Fawaz\Services\ContentFiltering\Replacers\ProfileReplacer;

final class ProfileServiceImpl implements ProfileService
{
    use ResponseHelper;
    protected ?string $currentUserId = null;

    public function __construct(
        protected PeerLoggerInterface $logger,
        protected ProfileRepository $profileRepository,
        protected ProfileSpecsFactory $profileSpecsFactory
    ) {}

    public function setCurrentUserId(string $userId): void {
        $this->currentUserId = $userId;
    }

    public function profile(array $args): Profile | ErrorResponse {
        $this->logger->info('ProfileService.Profile started');
        
        $userId = $args['userid'] ?? $this->currentUserId;
        $contentFilterBy = $args['contentFilterBy'] ?? null;

        $specs = $this->profileSpecsFactory->build($this->currentUserId, $userId, $contentFilterBy);

        try {
            $profileData = $this->profileRepository->fetchProfileData(
                $userId,
                $this->currentUserId,
                $specs
            );

            if (!$profileData) {
                $this->logger->warning('Query.resolveProfile User not found');
                return self::respondWithErrorObject(21001);
            }
            
            ProfileReplacer::placeholderProfile($profileData, $specs);

            $this->logger->debug("Fetched profile data", ['userid' => $profileData->getUserId()]);

            return $profileData;

        } catch (ValidationException $e) {
            $this->logger->error('Validation error: Failed to fetch profile data', [
                'userid' => $userId,
                'exception' => $e->getMessage(),
            ]);
            return $this::respondWithErrorObject(31007);
        } catch (\Throwable $e) {
            $this->logger->error('Failed to fetch profile data', [
                'userid' => $userId,
                'exception' => $e->getMessage(),
            ]);
            return $this::respondWithErrorObject(41007);
        }
    }
}
