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
use Fawaz\Utils\PeerLoggerInterface;
use Tests\utils\ConfigGeneration\Constants;

class ModerationService
{
    use ResponseHelper;
    protected ?string $currentUserId = null;

    public function __construct(
        protected PeerLoggerInterface $logger,
        protected TransactionManager $transactionManager
    ) {

    }

    /**
     * Check for Authorization
     */
    public function isAuthorized(): bool
    {
        if (!User::query()->where('uid', $this->currentUserId)->where('roles_mask', Role::SUPER_MODERATOR)->exists()) {
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
        if (!$this->isAuthorized()) {
            $this->logger->warning("Unauthorized access attempt to get moderation stats by user ID: {$this->currentUserId}");
            return self::respondWithError(0000);
        }

        $amountAwaitingReview = ModerationTicket::query()->where('status', array_keys(ConstantsModeration::contentModerationStatus())[0])->count();
        $amountHidden = ModerationTicket::query()->where('status', array_keys(ConstantsModeration::contentModerationStatus())[1])->count();
        $amountRestored = ModerationTicket::query()->where('status', array_keys(ConstantsModeration::contentModerationStatus())[2])->count();
        $amountIllegal = ModerationTicket::query()->where('status', array_keys(ConstantsModeration::contentModerationStatus())[3])->count();

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
        if (!$this->isAuthorized()) {
            $this->logger->warning("Unauthorized access attempt to get moderation items by user ID: {$this->currentUserId}");
            return self::respondWithError(0000);
        }

        $page = max((int)($args['page'] ?? 1), 0);
        $limit = min(max((int)($args['limit'] ?? 10), 1), 20);
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
                        ->paginate($page, $limit);

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
                                        'posts.media',
                                        'posts.cover',
                                        'posts.options',
                                        'comments.userid',
                                        'comments.parentid',
                                        'comments.content',
                                        'target_user.uid as target_user_uid',
                                        'target_user.username as target_user_username',
                                        'target_user.email as target_user_email',
                                        'target_user.img as target_user_img',
                                        'target_user.slug as target_user_slug',
                                        'target_user.status as target_user_status',
                                        'target_user.biography as target_user_biography',
                                        'target_user.updatedat as target_user_updatedat',
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
                                    )
                                    ->where('moderationticketid', $item['uid'])
                                    ->latest()
                                    ->all();

            $item['reporters'] = array_map(function ($reporter) {
                return (new User($reporter, [], false))->getArrayCopy();
            }, $reporters);

            $item['targetcontent'] = $targetContent['targetcontent'];
            $item['targettype'] = $targetContent['targettype'];

            return $item;
        }, $items['data']);

        return self::createSuccessResponse(0000, $items['data'], true);
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
            ], [], false))->getArrayCopy();
        }

        return $item;
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
                return self::respondWithError(0000);
            }

            $targetContentId = $args['targetContentId'] ?? null;
            $moderationAction = $args['moderationAction'] ?? null;

            if (!$targetContentId || !in_array($moderationAction, array_keys(ConstantsModeration::contentModerationStatus()))) {
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

            return self::createSuccessResponse(20001, [], false); // Moderation action performed successfully
        }catch(\Exception $e){
            $this->transactionManager->rollBack();
            $this->logger->error("Error performing moderation action: " . $e->getMessage());
            return self::respondWithError(0000);
        }
    }
}
