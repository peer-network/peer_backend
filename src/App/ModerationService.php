<?php

declare(strict_types=1);

namespace Fawaz\App;

use Fawaz\config\constants\ConstantsModeration;
use Fawaz\Utils\ResponseHelper;
use Fawaz\Database\Interfaces\TransactionManager;
use Fawaz\Database\ModerationMapper;
use Fawaz\Utils\PeerLoggerInterface;

class ModerationService
{
    use ResponseHelper;
    protected ?string $currentUserId = null;

    public function __construct(
        protected PeerLoggerInterface $logger,
        protected ModerationMapper $moderationMapper,
        protected TransactionManager $transactionManager
    ) {

    }

    /**
     * Check for Authorization
     */
    public function isAuthorized(): bool
    {
        if (!$this->moderationMapper->isAuthenticatedUserModerator($this->currentUserId)) {
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
        try{
            
            if (!$this->isAuthorized()) {
                $this->logger->warning("Unauthorized access attempt to get moderation stats by user ID: {$this->currentUserId}");
                return self::respondWithError(62101); // Unauthorized access attempt to get moderation stats
            }

            $statuses = $this->moderationMapper->getModerationStats();

            return self::createSuccessResponse(12101, $statuses, false);
        }catch(\Exception $e){
            $this->logger->error("Error getting moderation stats: " . $e->getMessage());
            return self::respondWithError(40301);
        }
    }

    /**
     * Get Moderation Items
     */
    public function getModerationItems(array $args): array
    {
        try {
            if (!$this->isAuthorized()) {
                $this->logger->warning("Unauthorized access attempt to get moderation items by user ID: {$this->currentUserId}");
                return self::respondWithError(62101); // Unauthorized access attempt to get moderation items
            }

            $offset = max((int)($args['offset'] ?? 1), 0);
            $limit = min(max((int)($args['limit'] ?? 10), 1), 20);

            $results = $this->moderationMapper->getModerationItems($offset, $limit, $args);

            return self::createSuccessResponse(12102, $results, true); // Moderation items retrieved successfully

        } catch (\Exception $e) {
            $this->logger->error("Error getting moderation items: " . $e->getMessage());
            return self::respondWithError(40301);
        }
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
        try{
            if (!$this->isAuthorized()) {
                $this->logger->warning("Unauthorized access attempt to perform moderation action by user ID: {$this->currentUserId}");
                return self::respondWithError(62101); // Unauthorized access attempt to perform moderation action
            }
            
            if(empty($args['moderationTicketId']) || empty($args['moderationAction'])){
                return self::respondWithError(30101); // Missing required fields
            }

            $moderationTicketId = $args['moderationTicketId'];
            $moderationAction = $args['moderationAction'];

            if (!$moderationTicketId || !self::isValidUUID($moderationTicketId) || !in_array($moderationAction, array_keys(ConstantsModeration::contentModerationStatus()))) {
                return self::respondWithError(32101); // Invalid input
            }

            $report = $this->moderationMapper->findModerationAction($moderationTicketId);
            if (empty($report)) {
                return self::respondWithError(22103); // Report not found
            }

            // If report is an array and already has a moderation id, it's already processed
            if (is_array($report) && array_key_exists('moderationid', $report) && !empty($report['moderationid'])) {
                return self::respondWithError(32103); // Moderation action already performed
            }

            $this->transactionManager->beginTransaction();
            
            // Create Moderation Record
            $result = $this->moderationMapper->performModerationAction($moderationTicketId, $moderationAction, $this->currentUserId);

            if(!$result){
                $this->transactionManager->rollBack();
                return self::respondWithError(42101); // Error performing moderation action
            }

            $this->transactionManager->commit();

            return self::createSuccessResponse(12103, [], false); // Moderation action performed successfully
        }catch(\Exception $e){
            $this->transactionManager->rollBack();
            $this->logger->error("Error performing moderation action: " . $e->getMessage());
            return self::respondWithError(42101);
        }
    }

    
}
