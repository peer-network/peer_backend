<?php

declare(strict_types=1);

namespace Fawaz\Database;

use DateTime;
use Fawaz\App\Comment;
use Fawaz\App\CommentInfo;
use Fawaz\App\Models\UserReport;
use Fawaz\config\constants\ConstantsModeration;
use Fawaz\Database\Interfaces\ProfileRepository;
use Fawaz\Utils\ResponseHelper;
use Fawaz\App\Models\Moderation;
use Fawaz\App\Models\ModerationTicket;
use Fawaz\App\Post;
use Fawaz\App\PostInfo;
use Fawaz\App\Role;
use Fawaz\App\User;
use Fawaz\App\UserInfo;
use Fawaz\Utils\PeerLoggerInterface;
use PDO;

class ModerationMapper
{
    use ResponseHelper;

    public function __construct(
        protected PeerLoggerInterface $logger,
        protected ProfileRepository $profileRepository,
        protected PDO $db
    ) {

    }


    /**
     * Get Moderation Stats
     */
    public function getModerationStats(): array
    {
        $amountAwaitingReview = ModerationTicket::query()->where('status', array_keys(ConstantsModeration::contentModerationStatus())[0])->count();
        $amountHidden = ModerationTicket::query()->where('status', array_keys(ConstantsModeration::contentModerationStatus())[1])->count();
        $amountRestored = ModerationTicket::query()->where('status', array_keys(ConstantsModeration::contentModerationStatus())[2])->count();
        $amountIllegal = ModerationTicket::query()->where('status', array_keys(ConstantsModeration::contentModerationStatus())[3])->count();

        return [ // Moderation stats retrieved successfully
            'AmountAwaitingReview' => $amountAwaitingReview,
            'AmountHidden' => $amountHidden,
            'AmountRestored' => $amountRestored,
            'AmountIllegal' => $amountIllegal,
        ];
    }

    /**
     * Get Moderation Items
     */
    public function getModerationItems(int $offset, int $limit, array $args): array
    {
        $statuses = array_keys(ConstantsModeration::contentModerationStatus());

        $items = ModerationTicket::query();

        // Apply Status filters
        if (isset($args['status']) && in_array($args['status'], $statuses)) {
            $items = $items->where('moderation_tickets.status', $args['status']);
        }

        // Apply Target Type filters
        if (isset($args['contentType']) && in_array($args['contentType'], array_keys(ConstantsModeration::CONTENT_MODERATION_TARGETS))) {
            $items = $items->where('moderation_tickets.contenttype', $args['contentType']);
        }

        $items = $items->orderByValue('status', 'ASC', $statuses)
                        ->orderBy('reportscount', 'DESC')
                        ->orderBy('createdat', 'DESC')
                        ->latest()
                        ->paginate($offset, $limit);

        $items['data'] = array_map(function ($item) {

        $userReport = UserReport::query()
            ->join('posts', 'user_reports.targetid', '=', 'posts.postid')
            ->join('comments', 'user_reports.targetid', '=', 'comments.commentid')
            ->join('users as target_user', 'user_reports.targetid', '=', 'target_user.uid')
            ->select(
                'user_reports.reportid',
                'user_reports.reporter_userid',
                'user_reports.targetid',
                'user_reports.targettype',
                'user_reports.message',
                'user_reports.moderationid',
                'posts.postid as post_postid', // Need to refactor this later
                'posts.userid',
                'posts.contenttype',
                'posts.title',
                'posts.mediadescription',
                'posts.visibility_status',
                'posts.media',
                'posts.cover',
                'posts.options',
                'comments.userid',
                'comments.parentid',
                'comments.content',
                'comments.visibility_status',
                'target_user.uid as target_user_uid',
                'target_user.username as target_user_username',
                'target_user.email as target_user_email',
                'target_user.img as target_user_img',
                'target_user.slug as target_user_slug',
                'target_user.status as target_user_status',
                'target_user.biography as target_user_biography',
                'target_user.updatedat as target_user_updatedat',
                'target_user.visibility_status as target_user_visibility_status',
            )
            ->where('moderationticketid', $item['uid'])
            ->latest()
            ->first();


            $targetContent = $this->mapTargetContent($userReport);

            // Get all reporters for the ModerationTicket
            $reporters = UserReport::query()
                                    ->join('users', 'user_reports.reporter_userid', '=', 'users.uid')
                                    ->select(
                                        'users.uid',
                                        'users.username',
                                        'users.email',
                                        'users.img',
                                        'users.slug',
                                        'users.status as userstatus',
                                        'users.biography',
                                        'users.updatedat',
                                        'users.visibility_status',
                                    )
                                    ->where('moderationticketid', $item['uid'])
                                    ->latest()
                                    ->all();

            $item['reporters'] = array_map(fn($reporter) => (new User($reporter, [], false))->getArrayCopy(), $reporters);

            $item['targetcontent'] = $targetContent['targetcontent'];
            $item['targettype'] = $targetContent['targettype'];

            return $item;
        }, $items['data']);

        return $items['data'];

    }

    /**
     * Map Target Content based on Target Type
     */
    private function mapTargetContent(array $item): array
    {
        $item['targetcontent']['post'] = null;
        $item['targetcontent']['comment'] = null;
        $item['targetcontent']['user'] = null;

        if ($item['targettype'] === 'post') {
            $item['postid'] = $item['targetid']; // Temporary fix, need to refactor this later
            $item['targetcontent']['post'] = (new Post($item, [], false))->getArrayCopy();
        } elseif ($item['targettype'] === 'comment') {
            $item['targetcontent']['comment'] = (new Comment($item, [], false))->getArrayCopy();
        } elseif ($item['targettype'] === 'user') {
            $item['targetcontent']['user'] = (new User([
                'uid' => $item['uid'],
                'username' => $item['username'],
                'email' => $item['email'],
                'img' => $item['img'],
                'slug' => $item['slug'],
                'status' => $item['status'],
                'biography' => $item['biography'],
                'updatedat' => $item['updatedat'],
                'visibility_status' => $item['visibility_status'],
            ], [], false))->getArrayCopy();
        }

        return $item;
    }

    /**
     * Is Authenticated User Moderator
     */
    public function isAuthenticatedUserModerator(string $userId): bool
    {
        return User::query()->where('uid', $userId)->where('roles_mask', Role::MODERATOR)->exists();
    }


    /**
     * Check if Moderation Action Already Performed
     */
    public function findModerationAction(string $moderationTicketId): array|bool
    {
        return UserReport::query()->where('moderationticketid', $moderationTicketId)->first();
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
    public function performModerationAction(string $moderationTicketId, string $moderationAction, string $currentUserId): bool
    {
        try {
            $moderationId = self::generateUUID();
            $createdat = (string) (new DateTime())->format('Y-m-d H:i:s.u');

            $report = UserReport::query()->where('moderationticketid', $moderationTicketId)->first();

            Moderation::insert([
                'uid' => $moderationId,
                'moderationticketid' => $moderationTicketId,
                'moderatorid' => $currentUserId,
                'status' => $moderationAction,
                'createdat' => $createdat,
            ]);

            UserReport::query()->where('targetid', $report['targetid'])->where('targettype', $report['targettype'])->updateColumns([
                'moderationid' => $moderationId
            ]);

            ModerationTicket::query()->where('uid', $moderationTicketId)->updateColumns([
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
                        'visibility_status' => ConstantsModeration::VISIBILITY_STATUS[2]
                    ]);
                    PostInfo::query()->where('postid', $report['targetid'])->updateColumns([
                            'reports' => 0
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
                        'reports' => 0
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
                    UserInfo::query()->where('userid', $report['targetid'])->updateColumns([
                        'reports' => 0
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
                        'reports' => 0
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
                    CommentInfo::query()->where('commentid', $report['targetid'])->updateColumns([
                        'reports' => 0
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
                        'reports' => 0
                    ]);
                    Comment::query()->where('commentid', $report['targetid'])->updateColumns([
                        'visibility_status' => ConstantsModeration::VISIBILITY_STATUS[1]
                    ]);
                }
            }

            return true; // Moderation action performed successfully
        } catch (\Exception $e) {
            // $this->transactionManager->rollBack();
            $this->logger->error("Error performing moderation action: " . $e->getMessage());
            return false;
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
                $this->logger->error("Directory does not exist: $illegalDirectoryPath"); // Directory does not exist
            }
        }

        // Target Media File for Post
        if ($targetType !== 'post') {
            return;
        }

        $mediaRecord = Post::query()->where('postid', $targetId)->first();
        if (!$mediaRecord || !$mediaRecord['media']) {
            $this->logger->error("Media not found for Post ID: $targetId");
            return;
        }

        $pathsToMove = [];

        $media = json_decode($mediaRecord['media'], true);
        if (is_array($media)) {
            foreach ($media as $mediaItem) {
                if (!isset($mediaItem['path'])) {
                    $this->logger->error("Invalid media path for Post ID: $targetId");
                    continue;
                }
                $pathsToMove[] = $mediaItem['path'];
            }
        }

        $coverPath = null;

        if (!empty($mediaRecord['cover'])) {
            $cover = json_decode($mediaRecord['cover'], true);

            if (is_array($cover) && isset($cover[0]['path'])) {
                $coverPath = $cover[0]['path'];
            } else {
                $this->logger->error("Invalid cover path for Post ID: $targetId");
            }
        }

        if ($coverPath !== null) {
            $pathsToMove[] = $coverPath;
        }

        foreach ($pathsToMove as $path) {
            $fullPath = $directoryPath . $path;

            if (!file_exists($fullPath)) {
                if ($path === $coverPath) {
                    $this->logger->error("Cover file does not exist for Post ID: $targetId, path: $fullPath");
                } else {
                    $this->logger->error("Invalid media path for Post ID: $targetId");
                }
                continue;
            }

            $resource = fopen($fullPath, 'r');
            if ($resource === false) {
                if ($path === $coverPath) {
                    $this->logger->error("Unable to open cover file for Post ID: $targetId, path: $fullPath");
                } else {
                    $this->logger->error("Unable to open media file for Post ID: $targetId");
                }
                continue;
            }

            $stream = new \Slim\Psr7\Stream($resource);

            $uploadedFile = new \Slim\Psr7\UploadedFile(
                $stream,
                null,
                null
            );

            $mediaDetails = explode('/', $path);
            $fileName = end($mediaDetails);
            $destinationPath = $illegalDirectoryPath . '/' . $fileName;

            try {
                $uploadedFile->moveTo($destinationPath);
            } catch (\RuntimeException $e) {
                if ($path === $coverPath) {
                    $this->logger->error("Failed to move cover file: $destinationPath");
                } else {
                    $this->logger->error("Failed to move file: $destinationPath");
                }
            }
        }
    }

    /**
     * Check if content was previously moderated and restored
     *
     */
    public function wasContentRestored(string $targetid, string $targettype): bool
    {
        $this->logger->debug("ModerationMapper.wasContentRestored started", [
            'targetid' => $targetid,
            'targettype' => $targettype
        ]);

        try {
            $sql = "SELECT 1 
                    FROM moderations m
                    INNER JOIN moderation_tickets mt ON mt.uid = m.moderationticketid
                    WHERE mt.targetcontentid = :targetid
                    AND mt.contenttype = :targettype
                    AND m.status = 'restored'
                    LIMIT 1";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                'targetid' => $targetid,
                'targettype' => $targettype
            ]);

            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            $wasRestored = !empty($result);

            $this->logger->debug("ModerationMapper.wasContentRestored result", [
                'wasRestored' => $wasRestored
            ]);

            return $wasRestored;

        } catch (\Exception $e) {
            $this->logger->error("ModerationMapper.wasContentRestored error", [
                'exception' => $e->getMessage()
            ]);
            return false;
        }
    }

}
