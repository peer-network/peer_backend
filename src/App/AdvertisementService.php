<?php

namespace Fawaz\App;

use Fawaz\Database\AdvertisementMapper;
use Fawaz\Utils\ResponseHelper;
use Psr\Log\LoggerInterface;
use InvalidArgumentException;

class AdvertisementService
{
    use ResponseHelper;
    protected ?string $currentUserId = null;
    public const PLAN_BASIC = 'BASIC';
    public const PLAN_PINNED = 'PINNED';

    public const PRICE_BASIC = 50;
    public const PRICE_PINNED = 200;

    public const DURATION_ONE_DAY = 'ONE_DAY';
    public const DURATION_TWO_DAYS = 'TWO_DAYS';
    public const DURATION_THREE_DAYS = 'THREE_DAYS';
    public const DURATION_FOUR_DAYS = 'FOUR_DAYS';
    public const DURATION_FIVE_DAYS = 'FIVE_DAYS';
    public const DURATION_SIX_DAYS = 'SIX_DAYS';
    public const DURATION_SEVEN_DAYS = 'SEVEN_DAYS';

    public function __construct(
        protected LoggerInterface $logger,
        protected AdvertisementMapper $advertisementMapper,
    ) {}

    public function setCurrentUserId(string $userid): void
    {
        $this->currentUserId = $userid;
    }

    private static array $durationDaysMap = [
        self::DURATION_ONE_DAY => 1,
        self::DURATION_TWO_DAYS => 2,
        self::DURATION_THREE_DAYS => 3,
        self::DURATION_FOUR_DAYS => 4,
        self::DURATION_FIVE_DAYS => 5,
        self::DURATION_SIX_DAYS => 6,
        self::DURATION_SEVEN_DAYS => 7,
    ];

    // @param string $plan @param string|null $duration
    // Convert the price + days of the advertisement -> Basic (Plan, Days) -> Pinned (Plan) in Euro
    public static function calculatePrice(string $plan, ?string $duration = null): int
    {
        if ($plan === self::PLAN_PINNED) {
            return self::PRICE_PINNED;
        }

        if ($plan === self::PLAN_BASIC) {
            if ($duration === null) {
                throw new InvalidArgumentException('BASIC plan requires a duration');
            }

            if (!isset(self::$durationDaysMap[$duration])) {
                throw new InvalidArgumentException('Unknown duration value: ' . $duration);
            }

            return self::PRICE_BASIC * self::$durationDaysMap[$duration];
        }

        throw new InvalidArgumentException('Unknown advertisement plan: ' . $plan);
    }

    private function checkAuthentication(): bool
    {
        if ($this->currentUserId === null) {
            $this->logger->warning('Unauthorized action attempted.');
            return false;
        }
        return true;
    }

    private function formatWithRandomMicroseconds(\DateTimeImmutable $date): string
    {
        // Generiere zufällige Mikrosekunden zwischen 100000 und 999999
        $microseconds = str_pad(random_int(100000, 999999), 6, '0', STR_PAD_LEFT);
        return $date->format("Y-m-d H:i:s") . '.' . $microseconds;
    }

    private function formatStartAndEndTimestamps(\DateTimeImmutable $startDate, string $durationKey): array
    {
        $dateFilters = [
            'ONE_DAY' => '+1 days',
            'TWO_DAYS' => '+2 days',
            'THREE_DAYS' => '+3 days',
            'FOUR_DAYS' => '+4 days',
            'FIVE_DAYS' => '+5 days',
            'SIX_DAYS' => '+6 days',
            'SEVEN_DAYS' => '+7 days',
        ];

        if (!isset($dateFilters[$durationKey])) {
            return self::respondWithError("Ungültige Werbedauer: $durationKey");
        }

        // Fix microsecond 000000
        $microseconds = str_pad(random_int(100000, 999999), 6, '0', STR_PAD_LEFT);

        // Startdatum um 00:00:00
        $start = $startDate->setTime(0, 0, 0);

        // Enddatum = start + duration + 1 Minute
        $end = $start->modify($dateFilters[$durationKey])->modify('+1 minute');

        return [
            'timestart' => $start->format("Y-m-d H:i:s") . '.' . $microseconds,
            'timeend' => $end->format("Y-m-d H:i:s") . '.' . $microseconds,
        ];
    }

    public function createAdvertisement(array $args = []): array
    {
        if (!$this->checkAuthentication()) {
            return self::respondWithError(60501);
        }

        if (empty($args)) {
            return self::respondWithError(30101);
        }

        $this->logger->info('AdvertisementService.createAdvertisement started');

        $this->logger->info('createAdvertisement', ['args' => $args]);

        $postId = $args['postid'] ?? null;
        $date = $args['durationInDays'] ?? null;
        $startday = $args['startday'] ?? null;
        $CostPlan = $args['advertisePlan'] ?? null;

        if (empty($postId)) {
            return self::respondWithError('Empty postId.');
        }

        if (empty($date) && $date !== null) {
            return self::respondWithError('Empty date.');
        }

        if (empty($CostPlan)) {
            return self::respondWithError('Empty cost plan.');
        }

        try {

            if (!empty($CostPlan) && $CostPlan === self::PLAN_BASIC) 
            {
                if ($postId && $date && $CostPlan && $startday) {
                $startDate = \DateTimeImmutable::createFromFormat('Y-m-d', $startday);
                $timestamps = $this->formatStartAndEndTimestamps($startDate, $date);

                $timestart = $timestamps['timestart']; // Set timestart
                $timeend = $timestamps['timeend']; // Set Timeend
                $this->logger->info('PLAN IS BASIC');
                } else {
                    return self::respondWithError('BASIC: es fehlt eine von (postid, date, costplan)');
                }
            } 
            elseif (!empty($CostPlan) && $CostPlan === self::PLAN_PINNED) 
            {
                $timestart = (new \DateTime())->format('Y-m-d H:i:s.u'); // Set timestart
                $timeend = (new \DateTime('+1 days'))->format('Y-m-d H:i:s.u'); // Set Timeend
                $this->logger->info('PLAN IS PINNED');
            } 
            else 
            {
                return self::respondWithError('Wrong CostPlan given.');
            }

            $advertisementData = [
                'advertisementid' => $postId,
                'userid' => $this->currentUserId,
                'status' => \strtolower($CostPlan),
                'timestart' => $timestart,
                'timeend' => $timeend,
            ];
            $this->logger->info('Post advertisementData', ['advertisementData' => $advertisementData]);

            try {
				$advertisement = new Advertisements($advertisementData);
            } catch (\Throwable $e) {
                $this->logger->error('Fehler beim Validieren des Advertisements', ['exception' => $e]);
                // Liefert die richtige errorCode.
                return $this->respondWithError($e->getMessage());
            }

            if ($this->advertisementMapper->isAdvertisementIdExist($postId) === true) 
            {
                $resp = $this->advertisementMapper->update($advertisement);
                $this->logger->info('Update Post Advertisement', ['response' => $resp]);
            } 
            else 
            {
                $resp = $this->advertisementMapper->insert($advertisement);
                $this->logger->info('Create Post Advertisement', ['response' => $resp]);
            }

            $data = $resp->getArrayCopy();
            return self::createSuccessResponse('Erfolgreich alles gut gegangen.', $data);

        } catch (\Throwable $e) {
            $this->logger->error('Failed to create post', ['exception' => $e]);
            return self::respondWithError(41508);
        }
    }

    public function fetchAll(?array $args = []): array
    {
        if (!$this->checkAuthentication()) {
            return self::respondWithError(60501);
        }

        $this->logger->info("AdvertisementService.fetchAll started");

        $offset = max((int)($args['offset'] ?? 0), 0);
        $limit = min(max((int)($args['limit'] ?? 10), 1), 20);

        try {
            $posts = $this->advertisementMapper->fetchAll($offset, $limit);
            $result = array_map(fn(Advertisements $post) => $post->getArrayCopy(), $posts);

            $this->logger->info("Advertisements fetched successfully", ['count' => count($result)]);
            return self::createSuccessResponse(10010, $result);

        } catch (\Throwable $e) {
            $this->logger->error("Error fetching Advertisements", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return self::respondWithError(10011);
        }
    }

    public function fetchByID(?array $args = []): array
    {
        if (!$this->checkAuthentication()) {
            return self::respondWithError(60501);
        }

        $this->logger->info("AdvertisementService.fetchByID started");

        $offset = max((int)($args['offset'] ?? 0), 0);
        $limit = min(max((int)($args['limit'] ?? 10), 1), 20);
		$userId = $this->currentUserId;

        try {
            $posts = $this->advertisementMapper->fetchByID($userId, $offset, $limit);
            $result = array_map(fn(Advertisements $post) => $post->getArrayCopy(), $posts);

            $this->logger->info("Userid Advertisements fetched successfully", ['count' => count($result)]);
            return self::createSuccessResponse(10010, $result);

        } catch (\Throwable $e) {
            $this->logger->error("Error fetching userid Advertisements", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return self::respondWithError(10011);
        }
    }

    public function convertEuroToTokens(float $amount = 0): array
    {

        $this->logger->info('AdvertisementService.convertEuroToTokens started');

        try {
            $fetchPrices = $this->advertisementMapper->convertEuroToTokens($amount);

            if ($fetchPrices) {
                $fetchPrices['ResponseCode'] = json_encode ($fetchPrices['affectedRows']);
                return $fetchPrices;
            }

            return self::respondWithError('error occured');
        } catch (\Exception $e) {
            return self::respondWithError('error on catch');
        }
    }

    public function convertTokensToEuro(int $amount = 0): array
    {

        $this->logger->info('AdvertisementService.convertTokensToEuro started');

        try {
            $fetchPrices = $this->advertisementMapper->convertTokensToEuro($amount);

            if ($fetchPrices) {
                $fetchPrices['ResponseCode'] = json_encode ($fetchPrices['affectedRows']);
                return $fetchPrices;
            }

            return self::respondWithError('error occured'); // 
        } catch (\Exception $e) {
            return self::respondWithError('error on catch');
        }
    }
}
