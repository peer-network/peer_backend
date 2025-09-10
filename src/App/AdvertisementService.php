<?php

namespace Fawaz\App;

use Fawaz\config\constants\ConstantsConfig;
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
			$this->logger->warning("Ungültige Werbedauer: $durationKey");
			return self::respondWithError(32001);
		}

        // Fix microsecond. 
		do {
			$microseconds = (string) random_int(100000, 999999);
			$firstDigit = $microseconds[0];
			$lastDigit = $microseconds[5];
		} while ($firstDigit === '0' || $lastDigit === '0' || $lastDigit === '1');

        // Startdatum um 00:00:00
		$start = $startDate->setTime(0, 0, 0);
		
        // Enddatum = start + duration - 1 Second, Geandert weil es hat konflikte mit dem enddate gehabt.
		$end = $start->modify($dateFilters[$durationKey])->modify('-1 second');

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


        // UUID generieren
        $advertisementId = self::generateUUID();
        if (empty($advertisementId)) {
            $this->logger->critical('Fehler beim Generieren der AdvertisementId-ID');
            return self::respondWithError(42006); // Fehler beim Generieren der AdvertisementId-ID
        }

        $postId = $args['postid'] ?? null;
        $date = $args['durationInDays'] ?? null;
        $startday = $args['startday'] ?? null;
        $CostPlan = $args['advertisePlan'] ?? null;
        $forcing = $args['forceUpdate'] ?? false;
        $eurocost = $args['eurocost'] ?? 0.0;
        $tokencost = $args['tokencost'] ?? 0.0;

        if (empty($postId)) {
            return self::respondWithError(32002);
        }

        if (empty($date) && $date !== null) {
            return self::respondWithError(32003);
        }

        if (empty($CostPlan)) {
            return self::respondWithError(32004);
        }

        try {

            if ($CostPlan !== null && $CostPlan === self::PLAN_BASIC) 
            {
                if ($postId && $date && $CostPlan && $startday) {
                    $startDate = \DateTimeImmutable::createFromFormat('Y-m-d', $startday);
                    $timestamps = $this->formatStartAndEndTimestamps($startDate, $date);

                    $timestart = $timestamps['timestart']; // Set timestart
                    $timeend = $timestamps['timeend']; // Set Timeend
                    $this->logger->info('PLAN IS BASIC');
                } else {
                    $this->logger->warning('BASIC: es fehlt eine teil von (postid, date, costplan)');
                    return self::respondWithError(32017); // BASIC: es fehlt eine teil von (postid, date, costplan)
                }

                if ($this->advertisementMapper->hasTimeConflict($postId, \strtolower($CostPlan), $timestart, $timeend) === true) {
                    $this->logger->warning('Basic Reservierungskonflikt: Der Zeitraum ist bereits belegt. Bitte ändern Sie den Startzeitpunkt, um fortzufahren.');
                    return self::respondWithError(32018); // Basic Reservierungskonflikt: Der Zeitraum ist bereits belegt. Bitte ändern Sie den Startzeitpunkt, um fortzufahren.
                }
            } 
            elseif ($CostPlan !== null && $CostPlan === self::PLAN_PINNED) 
            {
                if ($this->advertisementMapper->hasActiveAdvertisement($postId, \strtolower($CostPlan)) === true && empty($forcing)) 
                {
                    $this->logger->warning('Pinned Reservierungskonflikt: Die Anzeige ist noch aktiv (noch nicht abgelaufen). Das Fortfahren erfolgt unter Zwangsnutzung (‘forcing’).', ['advertisementid' => $advertisementId, 'postId' => $postId]);
                    return self::respondWithError(32018); // Basic Reservierungskonflikt: Die Anzeige ist noch aktiv (noch nicht abgelaufen). Das Fortfahren erfolgt unter Zwangsnutzung (‘forcing’).
                }

                $timestart = (new \DateTime())->format('Y-m-d H:i:s.u'); // Setze timestart
                $timeend = (new \DateTime('+1 days'))->format('Y-m-d H:i:s.u'); // Setze Timeend

                if ($this->advertisementMapper->hasTimeConflict($postId, \strtolower('BASIC'), $timestart, $timeend) === true && empty($forcing)) {
                    $this->logger->warning('Pinned.Basic Reservierungskonflikt: Der Zeitraum ist bereits belegt. Bitte ändern Sie den Startzeitpunkt, um fortzufahren.');
                    return self::respondWithError(32018); // Basic Reservierungskonflikt: Der Zeitraum ist bereits belegt. Bitte ändern Sie den Startzeitpunkt, um fortzufahren.
                }

                $this->logger->info('PLAN IS PINNED');
            } 
            else 
            {
                $this->logger->warning('Fehler, Falsche CostPlan angegeben.', ['CostPlan' => $CostPlan]);
                return self::respondWithError(42007); // Fehler, Falsche CostPlan angegeben
            }

            $advertisementData = [
                'advertisementid' => $advertisementId,
                'postid' => $postId,
                'userid' => $this->currentUserId,
                'status' => \strtolower($CostPlan),
                'timestart' => $timestart,
                'timeend' => $timeend,
                'tokencost' => $tokencost,
                'eurocost' => $eurocost,
            ];
            $this->logger->info('Post advertisementData', ['advertisementData' => $advertisementData]);

            try {
                $advertisement = new Advertisements($advertisementData);
            } catch (\Throwable $e) {
                $this->logger->error('Fehler beim Validieren des Advertisements', ['exception' => $e]);
                // Die richtige errorCode.
                return self::respondWithError($e->getMessage());
            }

            if ($CostPlan === self::PLAN_BASIC) 
            {
                $resp = $this->advertisementMapper->insert($advertisement);
                $this->logger->info('Create Post Advertisement', ['advertisementid' => $advertisementId, 'postId' => $postId]);
                $rescode = 12001; // Advertisement post erfolgreich erstellt.
            } 
            elseif ($CostPlan === self::PLAN_PINNED) 
            {
                if ($this->advertisementMapper->isAdvertisementIdExist($postId, \strtolower($CostPlan)) === true) 
                {                    
                    $advertData = $this->advertisementMapper->fetchByAdvID($postId, \strtolower($CostPlan));
                    $data = $advertData[0];
                    $data->setUserId($this->currentUserId);
                    $data->setTimestart($timestart);
                    $data->setTimeend($timeend);
                    $data->setTokencost($tokencost);
                    $data->setEurocost($eurocost);
                    $this->logger->info('Befor Update Get Advertisement Data', ['data' => $data->getArrayCopy()]);
                    $resp = $this->advertisementMapper->update($data);
                    $this->logger->info('Update Post Advertisement', ['advertisementid' => $advertisementId, 'postId' => $postId]);
                    $rescode = 12005; // Advertisement post erfolgreich aktualisiert.
                } 
                else 
                {
                    $resp = $this->advertisementMapper->insert($advertisement);
                    $this->logger->info('Create Post Advertisement', ['advertisementid' => $advertisementId, 'postId' => $postId]);
                    $rescode = 12001; // Advertisement post erfolgreich erstellt.
                }
            }
            else 
            {
                $this->logger->warning('Fehler, Falsche CostPlan angegeben.');
                return self::respondWithError(32005); // Fehler, Falsche CostPlan angegeben.
            }

            $data = $resp->getArrayCopy();
            $this->logger->info('Erfolgreich alles gut gegangen.', ['data' => $data]);
            return self::createSuccessResponse($rescode, [$data]);

        } catch (\Throwable $e) {
            $this->logger->error('Failed to create Post Advertisement', ['exception' => $e]);
            return self::respondWithError(42007); // Erstellen der Post Advertisement fehlgeschlagen.
        }
    }

    public function fetchAll(?array $args = []): array
    {
        if (!$this->checkAuthentication()) {
            return self::respondWithError(60501);
        }

        $this->logger->info('AdvertisementService.fetchAll started');

        $from = $args['filter']['from'] ?? null;
        $to = $args['filter']['to'] ?? null;

        $this->logger->info('AdvertisementService.fetchAll Befor check');
		if ($from !== null && !self::validateDate($from)) {
            $this->logger->info('AdvertisementService.fetchAll im check');
            return self::respondWithError(30212);
        }

        $this->logger->info('AdvertisementService.fetchAll Befor 2 check');
        if ($to !== null && !self::validateDate($to)) {
            $this->logger->info('AdvertisementService.fetchAll im 2 check');
            return self::respondWithError(30213);
        }

        try {
            $result = $this->advertisementMapper->fetchAllWithStats($args);

            //return $result;
            return self::createSuccessResponse(12002, $result['affectedRows'], false);
        } catch (\Throwable $e) {
            $this->logger->error('Error fetching Advertisements', ['error' => $e->getMessage()]);
            return self::respondWithError(42001);
        }
    }

    public function isAdvertisementDurationValid(string $postId): bool
    {

        $this->logger->info('AdvertisementService.isAdvertisementDurationValid started');

        try {
            return $this->advertisementMapper->isAdvertisementDurationValid($postId, $this->currentUserId);

        } catch (\Throwable $e) {
            return false;
        }
    }

    public function hasShortActiveAdWithUpcomingAd(string $postId): bool
    {

        $this->logger->info('AdvertisementService.hasShortActiveAdWithUpcomingAd started');

        try {
            return $this->advertisementMapper->hasShortActiveAdWithUpcomingAd($postId, $this->currentUserId);

        } catch (\Throwable $e) {
            return false;
        }
    }

    public function convertEuroToTokens(float $amount = 0, int $rescode = 0): array
    {

        $this->logger->info('AdvertisementService.convertEuroToTokens started');

        try {
            $fetchPrices = $this->advertisementMapper->convertEuroToTokens($amount, $rescode);

            if ($fetchPrices) {
                $fetchPrices['ResponseCode'] = json_encode ($fetchPrices['affectedRows']);
                return $fetchPrices;
            }

            return self::respondWithError(42002);
        } catch (\Throwable $e) {
            return self::respondWithError(42005);
        }
    }

    public function findAdvertiser(?array $args = []): array|false
    {
        if (!$this->checkAuthentication()) {
            return $this->respondWithError(60501);
        }

        $filterBy = $args['filterBy'] ?? [];
        $tag = $args['tag'] ?? null; 
        $postId = $args['postid'] ?? null;
        $userId = $args['userid'] ?? null;
        $titleConfig = ConstantsConfig::post()['TITLE'];

        if ($postId !== null && !self::isValidUUID($postId)) {
            return $this->respondWithError(30209);
        }

        if ($userId !== null && !self::isValidUUID($userId)) {
            return $this->respondWithError(30201);
        }

        if ($tag !== null) {
            if (!preg_match('/' . $titleConfig['PATTERN'] . '/u', $tag)) {
                $this->logger->error('Invalid tag format provided', ['tag' => $tag]);
                return $this->respondWithError(30211);
            }
        }

        if (!empty($filterBy) && is_array($filterBy)) {
            $allowedTypes = ['IMAGE', 'AUDIO', 'VIDEO', 'TEXT'];

            $invalidTypes = array_diff(array_map('strtoupper', $filterBy), $allowedTypes);

            if (!empty($invalidTypes)) {
                return $this->respondWithError(30103);
            }
        }

        $this->logger->info("AdvertisementService.findAdvertiser started");

        $results = $this->advertisementMapper->findAdvertiser($this->currentUserId, $args);
        //$this->logger->info('findAdvertiser', ['results' => $results]);
        $this->logger->info("AdvertisementService.findAdvertiser Done");
        if (empty($results) && $postId != null) {
            return $this->respondWithError(31510); 
        }

        return $results;
    }
}
