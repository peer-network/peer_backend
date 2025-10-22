<?php

declare(strict_types=1);

namespace Fawaz\App;

use DateTime;
use Fawaz\App\Models\UserReport;
use Fawaz\config\constants\ConstantsModeration;
use Fawaz\Utils\ResponseHelper;
use Fawaz\App\Models\Moderation;
use Fawaz\App\Models\ModerationTicket;
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
                return self::respondWithError(0000); // Unauthorized access attempt to get moderation stats
            }

            $statuses = $this->moderationMapper->getModerationStats();

            return self::createSuccessResponse(0000, $statuses, false);
        }catch(\Exception $e){
            $this->logger->error("Error getting moderation stats: " . $e->getMessage());
            return self::respondWithError(0000);
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
                return self::respondWithError(0000); // Unauthorized access attempt to get moderation items
            }

            $offset = max((int)($args['offset'] ?? 1), 0);
            $limit = min(max((int)($args['limit'] ?? 10), 1), 20);

            $results = $this->moderationMapper->getModerationItems($offset, $limit, $args);

            return self::createSuccessResponse(0000, $results, true); // Moderation items retrieved successfully

        } catch (\Exception $e) {
            $this->logger->error("Error getting moderation items: " . $e->getMessage());
            return self::respondWithError(0000);
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
                return self::respondWithError(0000); // Unauthorized access attempt to perform moderation action
            }
            
            if(empty($args['targetContentId']) || empty($args['moderationAction'])){
                return self::respondWithError(30101); // Missing required fields
            }

            $targetContentId = $args['targetContentId'];
            $moderationAction = $args['moderationAction'];

            if (!$targetContentId || !self::isValidUUID($targetContentId) || !in_array($moderationAction, array_keys(ConstantsModeration::contentModerationStatus()))) {
                return self::respondWithError(0000); // Invalid input
            }

            $report = UserReport::query()->where('moderationticketid', $targetContentId)->first();
            if (!$report) {
                return self::respondWithError(0000); // Report not found
            }

            if($report['moderationid']) {
                return self::respondWithError(0000); // Moderation action already performed
            }

            $createdat = (string) (new DateTime())->format('Y-m-d H:i:s.u');

            $moderationId = self::generateUUID();

            $this->transactionManager->beginTransaction();
            Moderation::insert([
                'uid' => $moderationId,
                'moderationticketid' => $targetContentId,
                'moderatorid' => $this->currentUserId,
                'status' => $moderationAction,
                'createdat' => $createdat,
            ]);

            UserReport::query()->where('targetid', $report['targetid'])->where('targettype', $report['targettype'])->updateColumns([
                'moderationid' => $moderationId
            ]);

            ModerationTicket::query()->where('uid', $targetContentId)->updateColumns([
                'status' => $moderationAction,
                'updatedat' => $createdat
            ]);

            /**
             * Apply Content Action based on Moderation Action
             *
             * For Post Content Type Only
             *  1. illegal: Set post status to '2' (illegal) in posts table
             *  2. restored: Set post status to '0' (published) in posts table and update REPORTS counts to ZERO
             *  3. hidden: Update REPORTS counts to FIVE or more
             */
            if ($report['targettype'] === 'post') {

                /**
                 * Moderation Status: illegal
                 */
                if ($moderationAction === array_keys(ConstantsModeration::contentModerationStatus())[3]) {
                    Post::query()->where('postid', $report['targetid'])->updateColumns([
                        'status' => ConstantsModeration::POST_STATUS_ILLEGAL,
                        'visibility_status' => ConstantsModeration::VISIBILITY_STATUS[2]
                    ]);

                    // Move file to illegal folder
                    $this->moveFileToIllegalFolder($report['targetid'], 'post');
                }

                /**
                 * Moderation Status: restored
                 */
                if ($moderationAction === array_keys(ConstantsModeration::contentModerationStatus())[2]) {
                    $postInfo = PostInfo::query()->where('postid', $report['targetid'])->first();
                    if ($postInfo) {
                        PostInfo::query()->where('postid', $report['targetid'])->updateColumns([
                            'reports' => 0
                        ]);
                        Post::query()->where('postid', $report['targetid'])->updateColumns([
                            'visibility_status' => ConstantsModeration::VISIBILITY_STATUS[0]
                        ]);
                    }
                }

                /**
                 * hidden: Update REPORTS counts to FIVE or more
                 * This will ensure that the post remains hidden in the listPosts logic
                 */
                if ($moderationAction === array_keys(ConstantsModeration::contentModerationStatus())[1]) {
                    PostInfo::query()->where('postid', $report['targetid'])->updateColumns([
                        'reports' => ConstantsModeration::contentFiltering()['REPORTS_COUNT_TO_HIDE_FROM_IOS']['POST']
                    ]);
                    Post::query()->where('postid', $report['targetid'])->updateColumns([
                        'visibility_status' => ConstantsModeration::VISIBILITY_STATUS[1]
                    ]);
                }
            }

            /**
             * For User Content Type Only
             *  1. illegal:  // TBC
             *  2. restored: Set user status to '0' (active) in users table and update REPORTS counts to ZERO
             *  3. hidden: Update REPORTS counts to FIVE or more
             */
            if ($report['targettype'] === 'user') {

                /**
                 * Moderation Status: illegal
                 */
                // TBC
                if ($moderationAction === array_keys(ConstantsModeration::contentModerationStatus())[3]) {
                    User::query()->where('uid', $report['targetid'])->updateColumns([
                        'visibility_status' => ConstantsModeration::VISIBILITY_STATUS[2]
                    ]);
                }

                /**
                 * Moderation Status: restored
                 */
                if ($moderationAction === array_keys(ConstantsModeration::contentModerationStatus())[2]) {
                    UserInfo::query()->where('userid', $report['targetid'])->updateColumns([
                        'reports' => 0
                    ]);
                    User::query()->where('uid', $report['targetid'])->updateColumns([
                        'visibility_status' => ConstantsModeration::VISIBILITY_STATUS[0]
                    ]);
                }

                /**
                 * hidden: Update REPORTS counts to FIVE or more
                 * This will ensure that the user remains hidden in the listPosts logic
                 */
                if ($moderationAction === array_keys(ConstantsModeration::contentModerationStatus())[1]) {
                    UserInfo::query()->where('userid', $report['targetid'])->updateColumns([
                        'reports' => ConstantsModeration::contentFiltering()['REPORTS_COUNT_TO_HIDE_FROM_IOS']['USER']
                    ]);
                    User::query()->where('uid', $report['targetid'])->updateColumns([
                        'visibility_status' => ConstantsModeration::VISIBILITY_STATUS[1]
                    ]);
                }

            }

            /**
             * For Comment Content Type Only
             *  1. illegal: // TBC
             *  2. restored: Set comment status to '0' (published) in comments table and update REPORTS counts to ZERO
             *  3. hidden: Update REPORTS counts to FIVE or more
             */
            if ($report['targettype'] === 'comment') {

                /**
                 * Moderation Status: illegal
                 */
                if ($moderationAction === array_keys(ConstantsModeration::contentModerationStatus())[3]) {
                    Comment::query()->where('commentid', $report['targetid'])->updateColumns([
                        'visibility_status' => ConstantsModeration::VISIBILITY_STATUS[2]
                    ]);
                }

                /**
                 * Moderation Status: restored
                 */
                if ($moderationAction === array_keys(ConstantsModeration::contentModerationStatus())[2]) {
                    CommentInfo::query()->where('commentid', $report['targetid'])->updateColumns([
                        'reports' => 0
                    ]);
                    Comment::query()->where('commentid', $report['targetid'])->updateColumns([
                        'visibility_status' => ConstantsModeration::VISIBILITY_STATUS[0]
                    ]);
                }

                /**
                 * hidden: Update REPORTS counts to FIVE or more
                 * This will ensure that the comment remains hidden in the listPosts logic
                 */
                if ($moderationAction === array_keys(ConstantsModeration::contentModerationStatus())[1]) {
                    CommentInfo::query()->where('commentid', $report['targetid'])->updateColumns([
                        'reports' => ConstantsModeration::contentFiltering()['REPORTS_COUNT_TO_HIDE_FROM_IOS']['COMMENT']
                    ]);
                    Comment::query()->where('commentid', $report['targetid'])->updateColumns([
                        'visibility_status' => ConstantsModeration::VISIBILITY_STATUS[1]
                    ]);
                }
            }

            $this->transactionManager->commit();

            return self::createSuccessResponse(0000, [], false); // Moderation action performed successfully
        }catch(\Exception $e){
            // $this->transactionManager->rollBack();
            $this->logger->error("Error performing moderation action: " . $e->getMessage());
            return self::respondWithError(0000);
        }
    }

    /**
     * Move File to Illegal Folder
     * Placeholder function for moving files to an illegal folder
     * 
     * 
     * Example:
     * $sourcePath = "/path/to/media/{childMedia}/{$targetType}/{$targetId}";
     * $destinationPath = "/path/to/media/illegal/{$targetType}/{$targetId}";
     */
    private function moveFileToIllegalFolder(string $targetId, string $targetType): void
    {
        $this->logger->info("Moving {$targetType} with ID {$targetId} to illegal folder.");
        
        $illegalDirectoryPath = __DIR__ . "/../../runtime-data/media/illegal";
        $directoryPath = __DIR__ . "/../../runtime-data/media";
        if (!is_dir($illegalDirectoryPath)) {
            try {
                mkdir($illegalDirectoryPath, 0777, true);
            } catch (\RuntimeException $e) {
                throw new \Exception("Directory does not exist: $illegalDirectoryPath"); // Directory does not exist
            }
        }

        // Target Media File for Post
        if($targetType == 'post'){
            $mediaRecord = Post::query()->where('postid', $targetId)->first();
            if(!$mediaRecord || !$mediaRecord['media']){
                throw new \Exception("Media not found for Post ID: $targetId");
            }
            $media = json_decode($mediaRecord['media'], true);

            foreach ($media as $mediaItem) {

                if (!isset($mediaItem['path']) || !file_exists($directoryPath.$mediaItem['path'])) {
                    throw new \Exception("Invalid media path for Post ID: $targetId");
                }
                $media = $mediaItem['path'];

                $stream = new \Slim\Psr7\Stream(fopen($directoryPath.$media, 'r'));

                $uploadedFile = new \Slim\Psr7\UploadedFile(
                    $stream,
                    null,
                    null
                );

                $mediaDetails = explode('/', $media);
                $mediaUrl = end($mediaDetails);
                
                $filePath = $illegalDirectoryPath.'/'.$mediaUrl;
                
                try {
                    $uploadedFile->moveTo($filePath);
                } catch (\RuntimeException $e) {
                    throw new \Exception("Failed to move file: $filePath");
                }
            }
        }
    }
    
}
