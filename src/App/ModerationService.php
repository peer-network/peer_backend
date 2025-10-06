<?php

declare(strict_types=1);

namespace Fawaz\App;

use DateTime;
use Fawaz\App\Models\UserReport;
use Fawaz\config\constants\ConstantsModeration;
use Fawaz\Utils\ResponseHelper;
use Fawaz\App\Models\Moderation;
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

    /**
     * Get Moderation Items
     */
    public function getModerationItems(array $args): array
    {
        if(!$this->isAuthorized()) {
            $this->logger->warning("Unauthorized access attempt to get moderation items by user ID: {$this->currentUserId}");
            return self::respondWithError(0000);
        }

        $page = max((int)($args['page'] ?? 1), 0);
        $limit = min(max((int)($args['limit'] ?? 10), 1), 20);

        $items = UserReport::query()
            ->join('users', 'user_reports.reporter_userid', '=', 'users.uid')
            ->paginate($page, $limit);

        return self::createSuccessResponse(20001,  $items['data'], false);
    }

    /**
     * Perform Moderation Action
     * 
     * Update status of a moderation item
     *  1. waiting_for_review
     *  2. hidden
     *  3. restored
     *  4. illegal
     */
    public function performModerationAction(array $args): array
    {
        if(!$this->isAuthorized()) {
            $this->logger->warning("Unauthorized access attempt to perform moderation action by user ID: {$this->currentUserId}");
            return self::respondWithError(0000);
        }

        $targetContentId = $args['targetContentId'] ?? null;
        $moderationAction = $args['moderationAction'] ?? null;

        if(!$targetContentId || !in_array($moderationAction, array_keys(ConstantsModeration::contentModerationStatus()))) {
            return self::respondWithError(0000); // Invalid input
        }

        $report = UserReport::query()->where('reportid', $targetContentId)->first();
        if(!$report) {
            return self::respondWithError(0000); // Report not found
        }

        UserReport::query()->where('reportid', $targetContentId)->updateColumns([
            'status' => $moderationAction,
        ]); 

        $createdat = (string)(new DateTime())->format('Y-m-d H:i:s.u');

        // var_dump($report[0]); exit;
        Moderation::insert([
            'uid' => self::generateUUID(),
            'moderationticketid' => $report[0]['moderationticketid'],
            'moderatorid' => $this->currentUserId,
            'status' => $moderationAction,
            'createdat' => $createdat,
        ]);

        return self::createSuccessResponse(20001, [], false); // Moderation action performed successfully
    }
}
