<?php

declare(strict_types=1);

namespace Fawaz\App;

use Fawaz\App\Models\UserReport;
use Fawaz\config\constants\ConstantsModeration;
use Fawaz\Utils\ResponseHelper;
use Psr\Log\LoggerInterface;

class ModerationService {

    use ResponseHelper;
    protected ?string $currentUserId = null;

    public function __construct(
        protected LoggerInterface $logger
    ){
        
    }

    /**
     * Check for Authorization
     */
    public function isAuthorized(): bool
    {
        if(!User::query()->where('uid', $this->currentUserId)->where('roles_mask', Role::SUPER_MODERATOR)->exists()) {
            return false;
        }
        return true;
    }

    /**
     * Bind Current User ID
     */
    public function setCurrentUserId(string $userId): void
    {
        $this->currentUserId = $userId;
    }

    /**
     * Get Moderation Stats
     */
    public function getModerationStats(): array
    {
        if(!$this->isAuthorized()) {
            $this->logger->warning("Unauthorized access attempt to get moderation stats by user ID: {$this->currentUserId}");
            return self::respondWithError(0000);
        }

        $amountAwaitingReview = UserReport::query()->where('status', array_keys(ConstantsModeration::contentModerationStatus())[0])->count();
        $amountHidden = UserReport::query()->where('status', array_keys(ConstantsModeration::contentModerationStatus())[1])->count();
        $amountRestored = UserReport::query()->where('status', array_keys(ConstantsModeration::contentModerationStatus())[2])->count();
        $amountIllegal = UserReport::query()->where('status', array_keys(ConstantsModeration::contentModerationStatus())[3])->count();

        return self::createSuccessResponse(20001, [
            'AmountAwaitingReview' => $amountAwaitingReview,
            'AmountHidden' => $amountHidden,
            'AmountRestored' => $amountRestored,
            'AmountIllegal' => $amountIllegal,
        ], false);
    }
}