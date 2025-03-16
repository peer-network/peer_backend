<?php

namespace Fawaz\App;

const LIKE_=2;
const COMMENT_=4;
const POST_=5;

const DAILYFREEPOST=1;
const DAILYFREELIKE=3;
const DAILYFREECOMMENT=4;

use Fawaz\App\DailyFree;
use Fawaz\Database\DailyFreeMapper;
use Psr\Log\LoggerInterface;

class DailyFreeService
{
    protected ?string $currentUserId = null;

    public function __construct(protected LoggerInterface $logger, protected DailyFreeMapper $dailyFreeMapper)
    {
    }

    public function setCurrentUserId(string $userId): void
    {
        $this->currentUserId = $userId;
    }

    private function generateUUID(): string
    {
        return sprintf(
            '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff), mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        );
    }

    public static function isValidUUID(string $uuid): bool
    {
        return preg_match('/^[a-fA-F0-9]{8}-[a-fA-F0-9]{4}-[a-fA-F0-9]{4}-[a-fA-F0-9]{4}-[a-fA-F0-9]{12}$/', $uuid) === 1;
    }

    private function respondWithError(string $message): array
    {
        return ['status' => 'error', 'ResponseCode' => $message];
    }

    private function checkAuthentication(): bool
    {
        if ($this->currentUserId === null) {
            $this->logger->warning("Unauthorized action attempted.");
            return false;
        }
        return true;
    }

    public function createDaily(array $args): array
    {
        if (!$this->checkAuthentication()) {
            return $this->respondWithError('Unauthorized');
        }

        if (empty($args)) {
            return $this->respondWithError('Could not find mandatory args');
        }

        $this->logger->info('DailyFreeService.createDaily started');

        $dailyData = [
            'userid' => $userid,
            'liken' => 0,
            'comments' => 0,
            'posten' => 0,
            'createdat' => (new \DateTime())->format('Y-m-d H:i:s.u')
        ];

        try {
            $Daily = new DailyFree($dailyData);
            $daily = $this->dailyFreeMapper->insert($Daily);

            $this->logger->info('Daily created successfully.');
            return ['status' => 'success', 'affectedRows' => $daily];
        } catch (\Exception $e) {
            return $this->respondWithError('Failed to create daily.');
        }
    }

    public function updateDaily(array $args): array
    {
        if (!$this->checkAuthentication()) {
            return $this->respondWithError('Unauthorized');
        }

        $requiredFields = ['liken', 'comments', 'posten'];
        foreach ($requiredFields as $field) {
            if (empty($args[$field])) {
                $this->logger->warning("$field is required", ['args' => $args]);
                return $this->respondWithError("$field is required");
            }
        }

        $dailyid = $this->currentUserId;

        if (!$this->dailyFreeMapper->isCreator($dailyid, $this->currentUserId)) {
            return $this->respondWithError('Unauthorized: You can only update your own dailys.');
        }

        try {
            $daily = $this->dailyFreeMapper->loadById($dailyid);
            if (!$daily) {
                return $this->respondWithError('Daily not found');
            }

            $daily->update($args);
            $this->dailyFreeMapper->update($daily);

            $this->logger->info('Daily updated successfully', ['dailyid' => $dailyid]);
            return ['status' => 'success', 'affectedRows' => $daily->getArrayCopy()];
        } catch (\Exception $e) {
            $this->logger->error('Failed to update daily', ['dailyid' => $dailyid, 'exception' => $e]);
            return $this->respondWithError('Failed to update daily');
        }
    }

    public function deleteDaily(int $dailyid): array
    {
        if (!$this->checkAuthentication()) {
            return $this->respondWithError('Unauthorized');
        }

        $dailyid = (int)$dailyid;

        try {
            $daily = $this->dailyFreeMapper->loadById($dailyid);
            if (!$daily) {
                return $this->respondWithError('Daily not found');
            }

            if ($daily->getArrayCopy()['userid'] !== $this->currentUserId && !$this->dailyFreeMapper->isCreator($dailyid, $this->currentUserId)) {
                return $this->respondWithError('Unauthorized: You can only delete your own dailys.');
            }

            $this->dailyFreeMapper->delete($dailyid);
            $this->logger->info('Daily deleted successfully', ['dailyid' => $dailyid]);

            return ['status' => 'success', 'ResponseCode' => 'Daily deleted successfully'];
        } catch (\Exception $e) {
            return $this->respondWithError('Failed to delete daily');
        }
    }

    public function fetchAll(?array $args = []): array
    {
        if (!$this->checkAuthentication()) {
            return $this->respondWithError('Unauthorized');
        }

        $this->logger->info("DailyFreeService.fetchAll started");

        $offset = max((int)($args['offset'] ?? 0), 0);
        $limit = min(max((int)($args['limit'] ?? 10), 1), 20);

        try {
            $dailys = $this->surveyMapper->fetchAll($offset, $limit);
            $result = array_map(fn(DailyFree $daily) => $daily->getArrayCopy(), $dailys);

            $this->logger->info("Dailys fetched successfully", ['count' => count($result)]);
            return $this->createSuccessResponse('Dailys fetched successfully', [$result]);

        } catch (\Throwable $e) {
            $this->logger->error("Error fetching Dailys", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return $this->respondWithError('Failed to fetch Dailys');
        }
    }

    public function loadById(): array
    {

        $this->logger->info('DailyFreeService.loadById started');

        try {
            $results = $this->dailyFreeMapper->loadById($this->currentUserId);

            if ($results !== false) {
                $affectedRows = $results->getArrayCopy();
                $this->logger->info("DailyFreeService.loadById dailyfree found", ['affectedRows' => $affectedRows]);
                unset($affectedRows['userid'], $affectedRows['createdat']);
                $success = [
                    'status' => 'success',
                    'ResponseCode' => 'DailyFree data prepared successfully',
                    'affectedRows' => $affectedRows,
                ];
                return $success;
            }

            return $this->respondWithError('No dailyfree found for the user.');
        } catch (\Exception $e) {
            return $this->respondWithError('Failed to retrieve dailyfree list.');
        }
    }

    public function dailyFreeFuncFront(string $userId, int $art): array
    {
        $this->logger->info('DailyFreeService.dailyFreeFuncFront started');
        $message = 'Daily Free Record Exist';

        if (empty($userId)) {
            return $this->respondWithError('No userid provided. Please provide valid userid parameters.');
        }

        if (empty($art)) {
            return $this->respondWithError('No art provided. Please provide valid art parameters.');
        }

        try {
            $user = $this->dailyFreeMapper->loadById($userId);

            if (!$user) {
                $dailyData = [
                    'userid' => $userId,
                    'liken' => 0,
                    'comments' => 0,
                    'posten' => 0,
                    'createdat' => (new \DateTime())->format('Y-m-d H:i:s.u')
                ];
                $user = $this->dailyFreeMapper->insert(new DailyFree($dailyData));

                if (!$user) {
                    return $this->respondWithError('Failed to initialize Daily Free Record');
                }
            }

            if (\date('Y-m-d', \strtotime($user->getCreatedAt())) !== \date('Y-m-d')) {
                $user->setLiken(0);
                $user->setComments(0);
                $user->setPosten(0);
                $user->setCreatedAt((new \DateTime())->format('Y-m-d H:i:s.u'));
                $message = 'Daily Free Record Updated successfully';

                $user = $this->dailyFreeMapper->update($user);
            }

            if ($art === LIKE_ && $user->getLiken() >= DAILYFREELIKE) {
                return $this->respondWithError('LIMIT_REACHED');
            }

            if ($art === COMMENT_ && $user->getComments() >= DAILYFREECOMMENT) {
                return $this->respondWithError('LIMIT_REACHED');
            }

            if ($art === POST_ && $user->getPosten() >= DAILYFREEPOST) {
                return $this->respondWithError('LIMIT_REACHED');
            }

            return ['status' => 'success', 'ResponseCode' => $message];

        } catch (\Throwable $e) {
            $this->logger->error('Error in DailyFreeFuncFront', [
                'userid' => $userId,
                'art' => $art,
                'error' => $e->getMessage()
            ]);
            return $this->respondWithError($e->getMessage());
        }
    }

    public function dailyFreeFunc(string $userId, string $postId, int $art): array
    {
        $this->logger->info('DailyFreeService.dailyFreeFunc started');

        if (empty($userId)) {
            return $this->respondWithError('No userid provided. Please provide valid userid parameters.');
        }

        if (empty($postId)) {
            return $this->respondWithError('No postid provided. Please provide valid postid parameters.');
        }

        if (empty($art)) {
            return $this->respondWithError('No art provided. Please provide valid art parameters.');
        }

        $column = match ($art) {
            LIKE_ => 'liken',
            COMMENT_ => 'comments',
            POST_ => 'posten',
            default => null,
        };

        $text = match ($art) {
            LIKE_ => 'Likes',
            COMMENT_ => 'Comments',
            POST_ => 'Post',
            default => null,
        };

        if ($column === null) {
            return $this->respondWithError('Method Not Exist Exception');
        }

        try {
            $user = $this->dailyFreeMapper->loadById($userId);

            if (!$user) {
                $dailyData = [
                    'userid' => $userId,
                    'liken' => 0,
                    'comments' => 0,
                    'posten' => 0,
                    'createdat' => (new \DateTime())->format('Y-m-d H:i:s.u')
                ];
                $user = $this->dailyFreeMapper->insert(new DailyFree($dailyData));

                if (!$user) {
                    return $this->respondWithError('Failed to initialize Daily Free Record');
                }
            }

            if (\date('Y-m-d', \strtotime($user->getCreatedAt())) !== \date('Y-m-d')) {
                $user->setLiken(0);
                $user->setComments(0);
                $user->setPosten(0);
                $user->setCreatedAt((new \DateTime())->format('Y-m-d H:i:s.u'));
            }

            $currentValue = $user->{'get' . ucfirst($column)}();

            if (($art === LIKE_ && $currentValue >= DAILYFREELIKE) ||
                ($art === COMMENT_ && $currentValue >= DAILYFREECOMMENT) ||
                ($art === POST_ && $currentValue >= DAILYFREEPOST)) {
                return $this->respondWithError("LIMIT_REACHED");
            }

            $user->{'set' . ucfirst($column)}($currentValue + 1);
            $this->dailyFreeMapper->update($user);

            return ['status' => 'success', 'ResponseCode' => "Daily Free Record: {$text}"];

        } catch (\Throwable $e) {
            $this->logger->error('Error in DailyFreeFunc', [
                'userid' => $userId,
                'postId' => $postId,
                'art' => $art,
                'error' => $e->getMessage()
            ]);
            return $this->respondWithError($e->getMessage());
        }
    }

    public function getUserDailyUsage(string $userId, string $artType): int
    {
        $this->logger->info('DailyFreeService.getUserDailyUsage started');

        try {
            $results = $this->dailyFreeMapper->getUserDailyUsage($userId, $artType);
            $this->logger->info('DailyFreeService.getUserDailyUsage results:', ['results' => $results]);

            if ($results !== false) {
                return (int)$results;
            }

            return 0;
        } catch (\Exception $e) {
            return 0;
        }
    }

    public function getUserDailyUsageWithColumnNames(string $userId): array
    {
        $this->logger->info('DailyFreeService.getUserDailyUsageWithColumnNames started');

        try {
            $results = $this->dailyFreeMapper->getUserDailyUsageWithColumnNames($userId);

            if ($results !== false) {
                return $results;
            }

            return [];
        } catch (\Exception $e) {
            return [];
        }
    }

    public function getUserDailyAvailability(string $userId): array
    {
        $this->logger->info('DailyFreeService.getUserDailyAvailability started');

        try {
            $affectedRows = $this->dailyFreeMapper->getUserDailyAvailability($userId);

            if ($affectedRows !== false) {

                $success = [
                    'status' => 'success',
                    'ResponseCode' => 'DailyFree data prepared successfully',
                    'affectedRows' => $affectedRows,
                ];
                return $success;
            }

            return [];
        } catch (\Exception $e) {
            return [];
        }
    }

    public function incrementUserDailyUsage(string $userId, string $artType): bool
    {
        $this->logger->info('DailyFreeService.incrementUserDailyUsage started');

        try {

            if ($this->dailyFreeMapper->incrementUserDailyUsage($userId, $artType)) {
                return true;
            }

            return false;
        } catch (\Exception $e) {
            return false;
        }
    }

}
