<?php

declare(strict_types=1);

namespace Fawaz\App;

use Fawaz\App\Errors\PermissionDeniedException;
use Fawaz\Utils\ResponseHelper;
use Fawaz\Utils\PeerLoggerInterface;
use Fawaz\Database\LeaderBoardMapper;
use Fawaz\Utils\ErrorResponse;

class LeaderBoardService
{
    use ResponseHelper;

    protected ?string $currentUserId = null;

    public function __construct(
        protected PeerLoggerInterface $logger,
        protected LeaderBoardMapper $leaderBoardMapper
    ) {
    }

    public function setCurrentUserId(string $userId): void
    {
        $this->currentUserId = $userId;
    }

    private function checkAuthentication(): bool
    {
        if ($this->currentUserId === null) {
            $this->logger->warning('Unauthorized access attempt');
            return false;
        }
        return true;
    }

    /**
     * Get Records and Prepare CSV
     * 
     * @param array $args
     * @return array|ErrorResponse
     *
     */
    public function generateLeaderboard(array $args): array | ErrorResponse
    {
        $this->logger->debug('LeaderBoardService.generateLeaderboard started');

        if (!$this->checkAuthentication()) {
            throw new PermissionDeniedException(60501, 'Unauthorized');
        }

        try {
            $start_date = (string) $args['start_date'];
            $end_date = (string) $args['end_date'];
            $leaderboardUsersCount = isset($args['leaderboardUsersCount']) ? (int) $args['leaderboardUsersCount'] : 20;
            
            $getResult = $this->leaderBoardMapper->getLeaderboardResult($start_date, $end_date, $leaderboardUsersCount);
            
            if (empty($getResult)) {
                return $this::respondWithError(22201); // no leaderboard users found
            }

            $startDate = \DateTimeImmutable::createFromFormat('Y-m-d', $start_date);
            $endDate = \DateTimeImmutable::createFromFormat('Y-m-d', $end_date);

            if ($startDate === false || $endDate === false) {
                return $this::respondWithError(30301);
            }

            $fileName = sprintf(
                'leaderboard_%s_%s_top%d.csv',
                $startDate->format('Ymd'),
                $endDate->format('Ymd'),
                $leaderboardUsersCount
            );

            $leaderboardResultLink = $this->generateCsv($getResult, $fileName);
            
            return self::createSuccessResponse(12301, ['leaderboardResultLink' => $leaderboardResultLink], false); // leadboard loaded successfully

        } catch (\Exception $e) {
            $this->logger->error("Error in LeaderBoardService.generateLeaderboard", ['exception' => $e->getMessage()]);
            return $this::respondWithError(42201); // Failed to create leaderboard result
        }
    }

    /**
     * Generate CSV from records
     * 
     * @param array $records
     * @param string $fileName
     * @return string
     */
    private function generateCsv(array $records, string $fileName): string
    {
        
        $relativeDir = 'runtime-data/media/other/power_power_contest_leaderboards_data';
        $mediaDir = 'other/power_power_contest_leaderboards_data';
        $basePath = dirname(__DIR__, 2);
        $targetDir = $basePath . DIRECTORY_SEPARATOR . $relativeDir;

        if (!is_dir($targetDir) && !mkdir($targetDir, 0775, true) && !is_dir($targetDir)) {
            throw new \RuntimeException('Failed to create leaderboard directory');
        }

        $filePath = $targetDir . DIRECTORY_SEPARATOR . $fileName;
        $handle = fopen($filePath, 'wb');

        if ($handle === false) {
            throw new \RuntimeException('Failed to open leaderboard CSV file');
        }

        $columns = [
            'uid',
            'username',
            'comments_on_posts',
            'likes_on_posts',
            'ppc_points',
            'likes_given',
            'comments_given',
            'referrals',
            'total_points'
        ];

        fputcsv($handle, $columns);

        foreach ($records as $record) {
            $row = [];
            foreach ($columns as $column) {
                $row[] = $record[$column] ?? null;
            }
            fputcsv($handle, $row);
        }

        fclose($handle);

        $this->logger->info('LeaderBoardService.generateLeaderboard completed successfully');

        $mediaServer = $_ENV['MEDIA_SERVER_URL'];
        
        $leaderboardResultLink = $mediaServer . '/' . $mediaDir . '/' . $fileName;

        return $leaderboardResultLink;

    }

}
