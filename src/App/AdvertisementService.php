<?php

declare(strict_types=1);

namespace Fawaz\App;

use DateTimeImmutable;
use Fawaz\config\constants\ConstantsConfig;
use Fawaz\Database\AdvertisementMapper;
use Fawaz\Database\CommentMapper;
use Fawaz\Database\Interfaces\InteractionsPermissionsMapper;
use Fawaz\Database\Interfaces\ProfileRepository;
use Fawaz\Database\PostMapper;
use Fawaz\Database\UserMapper;
use Fawaz\Services\ContentFiltering\Capabilities\HasUserId;
use Fawaz\Services\ContentFiltering\Replacers\ContentReplacer;
use Fawaz\Services\ContentFiltering\Specs\SpecTypes\HiddenContent\HiddenContentFilterSpec;
use Fawaz\Services\ContentFiltering\Specs\SpecTypes\HiddenContent\NormalVisibilityStatusSpec;
use Fawaz\Services\ContentFiltering\Specs\SpecTypes\IllegalContent\IllegalContentFilterSpec;
use Fawaz\Services\ContentFiltering\Specs\SpecTypes\User\DeletedUserSpec;
use Fawaz\Services\ContentFiltering\Specs\SpecTypes\User\SystemUserSpec;
use Fawaz\Services\ContentFiltering\Types\ContentFilteringCases;
use Fawaz\Services\ContentFiltering\Types\ContentType;
use Fawaz\Services\TokenTransfer\Strategies\AdsTransferStrategy;
use Fawaz\Services\TokenTransfer\Strategies\TransferStrategy;
use Fawaz\Utils\ContentFilterHelper;
use Fawaz\Utils\ResponseHelper;
use Fawaz\Utils\PeerLoggerInterface;
use InvalidArgumentException;

class AdvertisementService
{
    use ResponseHelper;
    protected ?string $currentUserId = null;
    public const PLAN_BASIC = 'BASIC';
    public const PLAN_PINNED = 'PINNED';

    public const DURATION_ONE_DAY = 'ONE_DAY';
    public const DURATION_TWO_DAYS = 'TWO_DAYS';
    public const DURATION_THREE_DAYS = 'THREE_DAYS';
    public const DURATION_FOUR_DAYS = 'FOUR_DAYS';
    public const DURATION_FIVE_DAYS = 'FIVE_DAYS';
    public const DURATION_SIX_DAYS = 'SIX_DAYS';
    public const DURATION_SEVEN_DAYS = 'SEVEN_DAYS';

    public function __construct(
        protected PeerLoggerInterface $logger,
        protected AdvertisementMapper $advertisementMapper,
        protected UserMapper $userMapper,
        protected PostMapper $postMapper,
        protected PostService $postService,
        protected WalletService $walletService,
        protected InteractionsPermissionsMapper $interactionsPermissionsMapper,
        protected CommentMapper $commentMapper,
        protected ProfileRepository $profileRepository,
    ) {
    }

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

    // Werbeanzeige prüfen, validieren und freigeben
    public function resolveAdvertisePost(?array $args = []): ?array
    {
        // Authentifizierung prüfen
        if (!$this->checkAuthentication()) {
            return $this->respondWithError(60501);
        }

        //$this->logger->info('Query.resolveAdvertisePost gestartet');

        $postId = $args['postid'] ?? null;
        $durationInDays = $args['durationInDays'] ?? null;
        $startdayInput = $args['startday'] ?? null;
        $advertisePlan = $args['advertisePlan'] ?? null;
        $reducePrice = false;
        $CostPlan = 0;

        // postId validieren
        if ($postId !== null && !self::isValidUUID($postId)) {
            return $this->respondWithError(30209);
        }

        if ($this->postService->postExistsById($postId) === false) {
            return $this->respondWithError(31510);
        }

        $contentFilterCase = ContentFilteringCases::searchById;

        $systemUserSpec = new SystemUserSpec(
            $contentFilterCase,
            ContentType::post
        );

        $illegalContentSpec = new IllegalContentFilterSpec(
            $contentFilterCase,
            ContentType::post
        );

        $specs = [
            $illegalContentSpec,
            $systemUserSpec
        ];

        if ($this->interactionsPermissionsMapper->isInteractionAllowed(
            $specs,
            $postId
        ) === false) {
            return $this::respondWithError(32020, ['postid' => $postId]);
        }

        $advertiseActions = ['BASIC', 'PINNED'];

        // Werbeplan validieren
        if (!in_array($advertisePlan, $advertiseActions, true)) {
            $this->logger->warning('Ungültiger Werbeplan', ['advertisePlan' => $advertisePlan]);
            return $this->respondWithError(32006);
        }

        $prices = ConstantsConfig::tokenomics()['ACTION_TOKEN_PRICES'];

        $actionPrices = [
            'BASIC' => $prices['advertisementBasic'],
            'PINNED' => $prices['advertisementPinned'],
        ];

        // Preisvalidierung
        if (!isset($actionPrices[$advertisePlan])) {
            $this->logger->warning('Ungültiger Preisplan', ['advertisePlan' => $advertisePlan]);
            return $this->respondWithError(32005);
        }

        if ($advertisePlan === $this::PLAN_BASIC) {
            // Startdatum validieren
            if (isset($startdayInput) && empty($startdayInput)) {
                $this->logger->warning('Startdatum fehlt oder ist leer', ['startdayInput' => $startdayInput]);
                return $this->respondWithError(32007);
            }

            // Startdatum prüfen und Format validieren
            $startday = DateTimeImmutable::createFromFormat('Y-m-d', $startdayInput);
            $errors = DateTimeImmutable::getLastErrors();

            if (!$startday) {
                $this->logger->warning("Ungültiges Startdatum: '$startdayInput'. Format muss YYYY-MM-DD sein.");
                return $this->respondWithError(32008);
            }

            if (isset($errors['warning_count']) && $errors['warning_count'] > 0 || isset($errors['error_count']) && $errors['error_count'] > 0) {
                $this->logger->error("Ungültiges Startdatum: '$startdayInput'. Format muss YYYY-MM-DD sein.");
                return $this->respondWithError(42004);
            }

            // Prüfen, ob das Startdatum in der Vergangenheit liegt
            $tomorrow = new DateTimeImmutable('tomorrow');
            if ($startday < $tomorrow) {
                $this->logger->warning('Startdatum darf nicht in der Vergangenheit liegen', ['today' => $startdayInput]);
                return $this->respondWithError(32008);
            }

            $durationActions = ['ONE_DAY', 'TWO_DAYS', 'THREE_DAYS', 'FOUR_DAYS', 'FIVE_DAYS', 'SIX_DAYS', 'SEVEN_DAYS'];

            // Laufzeit validieren
            if ($durationInDays !== null && !in_array($durationInDays, $durationActions, true)) {
                $this->logger->warning('Ungültige Laufzeit', ['durationInDays' => $durationInDays]);
                return $this->respondWithError(32009);
            }
        }

        if ($this->isAdvertisementDurationValid($postId) === true) {
            $reducePrice = true;
        }

        if ($reducePrice === false) {
            if ($this->hasShortActiveAdWithUpcomingAd($postId) === true) {
                $reducePrice = true;
            }
        }

        // Kosten berechnen je nach Plan (BASIC oder PINNED)
        if ($advertisePlan === $this::PLAN_PINNED) {
            $CostPlan = $this->advertisePostPinnedResolver($args); // PINNED Kosten berechnen

            // 20% discount weil advertisement >= 24 stunde aktive noch
            if ($reducePrice === true) {
                $CostPlan = $CostPlan - ($CostPlan * 0.20); // 80% vom ursprünglichen Wert
                //$CostPlan *= 0.80; // 80% vom ursprünglichen Wert
                $this->logger->info('20% Discount Exestiert:', ['CostPlan' => $CostPlan]);
            }

            $this->logger->info('Werbeanzeige PINNED', ['CostPlan' => $CostPlan]);
            $rescode = 12003;
        } elseif ($advertisePlan === $this::PLAN_BASIC) {
            $CostPlan = $this->advertisePostBasicResolver($args); // BASIC Kosten berechnen
            $this->logger->info('Werbeanzeige BASIC', ["Kosten für $durationInDays Tage: " => $CostPlan]);
            $rescode = 12004;
        } else {
            $this->logger->warning('Ungültige Ads Plan', ['CostPlan' => $CostPlan]);
            return $this->respondWithError(32005);
        }

        $args['tokencost'] = $CostPlan;
        // Wenn Kosten leer oder 0 sind, Fehler zurückgeben
        $args['eurocost'] = $CostPlan / 10;
        if (empty($CostPlan) || (int)$CostPlan === 0) {
            $this->logger->error('Kostenprüfung fehlgeschlagen', ['CostPlan' => $CostPlan]);
            return $this->respondWithError(42005);
        }

        // // Euro in PeerTokens umrechnen
        // $results = $this->convertEuroToTokens($CostPlan, $rescode);
        // if (isset($results['status']) && $results['status'] === 'error') {
        //     $this->logger->error('Fehler bei convertEuroToTokens', ['results' => $results]);
        //     return $results;
        // }
        // if (isset($results['status']) && $results['status'] === 'success') {
        //     $this->logger->info('Umrechnung erfolgreich', ["€$CostPlan in PeerTokens: " => $results['affectedRows']['TokenAmount']]);
        //     $CostPlan = $results['affectedRows']['TokenAmount'];
        //     $args['tokencost'] = $CostPlan;
        // }

        try {
            // Wallet prüfen
            $balance = $this->walletService->getUserWalletBalance($this->currentUserId);
            if ($balance < $CostPlan) {
                $this->logger->error('Unzureichendes Wallet-Guthaben', ['userId' => $this->currentUserId, 'balance' => $balance, 'CostPlan' => $CostPlan]);
                return $this->respondWithError(51301);
            }

            // Werbeanzeige erstellen
            $transferStrategy = new AdsTransferStrategy();
            $args['operationid'] = $transferStrategy->getOperationId();

            $response = $this->createAdvertisement($args);
            if (isset($response['status']) && $response['status'] === 'success') {
                $args['art'] = ($advertisePlan === $this::PLAN_BASIC) ? 6 : (($advertisePlan === $this::PLAN_PINNED) ? 7 : null);
                $args['price'] = $CostPlan;

                $deducted = $this->walletService->performPayment($this->currentUserId, $transferStrategy, $args);
                if (isset($deducted['status']) && $deducted['status'] === 'error') {
                    return $deducted;
                }

                if (!$deducted) {
                    $this->logger->warning('Abbuchung vom Wallet fehlgeschlagen', ['userId' => $this->currentUserId]);
                    return $this->respondWithError($deducted['ResponseCode']);
                }

                return $response;
            }

            return $response;

        } catch (\Throwable $e) {
            return $this->respondWithError(40301);
        }
    }

    // Berechne den Basispreis des Beitrags
    protected function advertisePostBasicResolver(?array $args = []): int
    {
        try {
            $this->logger->debug('Query.advertisePostBasicResolver started');

            $postId = $args['postid'];
            $duration = $args['durationInDays'];

            $price = $this::calculatePrice($this::PLAN_BASIC, $duration);

            return $price;
        } catch (\Throwable $e) {
            $this->logger->warning('Invalid price provided.', ['Error' => $e]);
            return 0;
        }
    }

    // Berechne den Preis für angehefteten Beitrag
    protected function advertisePostPinnedResolver(?array $args = []): int
    {
        try {
            $this->logger->debug('Query.advertisePostPinnedResolver started');

            $postId = $args['postid'];

            $price = $this::calculatePrice($this::PLAN_PINNED);

            return $price;
        } catch (\Throwable $e) {
            $this->logger->warning('Invalid price provided.', ['Error' => $e]);
            return 0;
        }
    }
    // @param string $plan @param string|null $duration
    // Convert the price + days of the advertisement -> Basic (Plan, Days) -> Pinned (Plan) in Euro
    public static function calculatePrice(string $plan, ?string $duration = null): int
    {
        $priceBasic = ConstantsConfig::tokenomics()['ACTION_TOKEN_PRICES']['advertisementBasic'];
        $pricePinned = ConstantsConfig::tokenomics()['ACTION_TOKEN_PRICES']['advertisementPinned'];


        if ($plan === self::PLAN_PINNED) {
            return (int)$pricePinned;
        }

        if ($plan === self::PLAN_BASIC) {
            if ($duration === null) {
                throw new InvalidArgumentException('BASIC plan requires a duration');
            }

            if (!isset(self::$durationDaysMap[$duration])) {
                throw new InvalidArgumentException('Unknown duration value: ' . $duration);
            }

            return (int)$priceBasic * self::$durationDaysMap[$duration];
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

    private function formatStartAndEndTimestamps(DateTimeImmutable $startDate, string $durationKey): array
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

        $this->logger->debug('AdvertisementService.createAdvertisement started');


        // UUID generieren
        $advertisementId = self::generateUUID();

        $postId = $args['postid'] ?? null;
        $operationId = $args['operationid'] ?? '';
        $date = $args['durationInDays'] ?? null;
        $startday = $args['startday'] ?? null;
        $CostPlan = $args['advertisePlan'] ?? null;
        // $forcing = $args['forceUpdate'] ?? false;
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

            if ($CostPlan !== null && $CostPlan === self::PLAN_BASIC) {
                if ($startday) {
                    $startDate = DateTimeImmutable::createFromFormat('Y-m-d', $startday);
                    $timestamps = $this->formatStartAndEndTimestamps($startDate, $date);

                    $timestart = $timestamps['timestart']; // Set timestart
                    $timeend = $timestamps['timeend']; // Set Timeend
                    $this->logger->info('PLAN IS BASIC');
                } else {
                    $this->logger->warning('BASIC: es fehlt eine teil von (postid, date, costplan)');
                    return self::respondWithError(32017); // BASIC: es fehlt eine teil von (postid, date, costplan)
                }

                if ($this->advertisementMapper->hasTimeConflict($postId, \strtolower($CostPlan), $timestart, $timeend, $this->currentUserId) === true) {
                    $this->logger->warning('Basic Reservierungskonflikt: Der Zeitraum ist bereits belegt. Bitte ändern Sie den Startzeitpunkt, um fortzufahren.');
                    return self::respondWithError(32018); // Basic Reservierungskonflikt: Der Zeitraum ist bereits belegt. Bitte ändern Sie den Startzeitpunkt, um fortzufahren.
                }
            } elseif ($CostPlan !== null && $CostPlan === self::PLAN_PINNED) {
                if ($this->advertisementMapper->hasActiveAdvertisement($postId, \strtolower($CostPlan)) === true) {
                    $this->logger->warning('Pinned Reservierungskonflikt: Die Anzeige ist noch aktiv (noch nicht abgelaufen). Das Fortfahren erfolgt unter Zwangsnutzung (‘forcing’).', ['advertisementid' => $advertisementId, 'postId' => $postId]);
                    return self::respondWithError(32018); // Basic Reservierungskonflikt: Die Anzeige ist noch aktiv (noch nicht abgelaufen). Das Fortfahren erfolgt unter Zwangsnutzung (‘forcing’).
                }

                $timestart = new \DateTime()->format('Y-m-d H:i:s.u'); // Setze timestart
                $timeend = new \DateTime('+1 days')->format('Y-m-d H:i:s.u'); // Setze Timeend

                if ($this->advertisementMapper->hasTimeConflict($postId, \strtolower($CostPlan), $timestart, $timeend, $this->currentUserId) === true) {
                    $this->logger->warning('Pinned.Basic Reservierungskonflikt: Der Zeitraum ist bereits belegt. Bitte ändern Sie den Startzeitpunkt, um fortzufahren.');
                    return self::respondWithError(32018); // Basic Reservierungskonflikt: Der Zeitraum ist bereits belegt. Bitte ändern Sie den Startzeitpunkt, um fortzufahren.
                }

                $this->logger->info('PLAN IS PINNED');
            } else {
                $this->logger->error('Fehler, Falsche CostPlan angegeben.', ['CostPlan' => $CostPlan]);
                return self::respondWithError(42007); // Fehler, Falsche CostPlan angegeben
            }

            $advertisementData = [
                'advertisementid' => $advertisementId,
                'operationid' => $operationId,
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
                return self::respondWithError((int)$e->getMessage());
            }

            if ($CostPlan === self::PLAN_BASIC) {
                $resp = $this->advertisementMapper->insert($advertisement);
                $this->logger->info('Create Post Advertisement', ['advertisementid' => $advertisementId, 'postId' => $postId]);
                $rescode = 12001; // Advertisement post erfolgreich erstellt.
            } elseif ($CostPlan === self::PLAN_PINNED) {
                // NOTE: Repinning functionality commented out (not in current feature scope)
                // if ($this->advertisementMapper->isAdvertisementIdExist($postId, \strtolower($CostPlan)) === true) {
                //     $advertData = $this->advertisementMapper->fetchByAdvID($postId, \strtolower($CostPlan));
                //     $data = $advertData[0];
                //     $data->setUserId($this->currentUserId);
                //     $data->setTimestart($timestart);
                //     $data->setTimeend($timeend);
                //     $data->setTokencost($tokencost);
                //     $data->setEurocost($eurocost);
                //     $this->logger->info('Befor Update Get Advertisement Data', ['data' => $data->getArrayCopy()]);
                //     $resp = $this->advertisementMapper->update($data);
                //     $this->logger->info('Update Post Advertisement', ['advertisementid' => $advertisementId, 'postId' => $postId]);
                //     $rescode = 12005; // Advertisement post erfolgreich aktualisiert.
                // } else {
                //     $resp = $this->advertisementMapper->insert($advertisement);
                //     $this->logger->info('Create Post Advertisement', ['advertisementid' => $advertisementId, 'postId' => $postId]);
                //     $rescode = 12001; // Advertisement post erfolgreich erstellt.
                // }


                // Always create new advertisement with unique ID and current timestamp
                $resp = $this->advertisementMapper->insert($advertisement);
                $this->logger->info('Create Post Advertisement', ['advertisementid' => $advertisementId, 'postId' => $postId]);
                $rescode = 12001; // Advertisement post erfolgreich erstellt.
            } else {
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

        $this->logger->debug('AdvertisementService.fetchAll started');

        $advertiseActions = ['BASIC', 'PINNED'];
        $filter = $args['filter'] ?? [];
        $from = $filter['from'] ?? null;
        $to = $filter['to'] ?? null;
        $advertisementtype = $filter['type'] ?? null;
        $advertisementId = $filter['advertisementId'] ?? null;
        $contentFilterBy = $filter['contentFilterBy'] ?? null;
        $postId = $filter['postId'] ?? null;
        $userId = $filter['userId'] ?? null;
        $sortBy = $args['sort'] ?? [];


        if ($from !== null && !self::validateDate($from)) {
            return self::respondWithError(30212);
        }

        if ($to !== null && !self::validateDate($to)) {
            return self::respondWithError(30213);
        }

        if ($advertisementtype !== null && !in_array($advertisementtype, $advertiseActions, true)) {
            return $this->respondWithError(32006);
        }

        if ($advertisementId !== null && !self::isValidUUID($advertisementId)) {
            return $this->respondWithError(30269);
        }

        if ($advertisementId !== null && !$this->advertisementMapper->advertisementExistsById($advertisementId)) {
            return $this->respondWithError(32019);
        }

        if ($postId !== null && !self::isValidUUID($postId)) {
            return $this->respondWithError(30209);
        }

        if ($postId !== null && !$this->postMapper->postExistsById($postId)) {
            return $this->respondWithError(31510);
        }

        if ($userId !== null && !self::isValidUUID($userId)) {
            return $this->respondWithError(30201);
        }

        if ($userId !== null && !$this->userMapper->isUserExistById($userId)) {
            return $this->respondWithError(31007);
        }

        $contentFilterCase = ContentFilteringCases::searchById;

        $deletedUserSpec = new DeletedUserSpec(
            $contentFilterCase,
            ContentType::post
        );
        $systemUserSpec = new SystemUserSpec(
            $contentFilterCase,
            ContentType::post
        );

        $hiddenContentFilterSpec = new HiddenContentFilterSpec(
            $contentFilterCase,
            $contentFilterBy,
            ContentType::post,
            $this->currentUserId,
        );

        $illegalContentSpec = new IllegalContentFilterSpec(
            $contentFilterCase,
            ContentType::post
        );
        $normalVisibilityStatusSpec = new NormalVisibilityStatusSpec($contentFilterBy);

        $specs = [
            $illegalContentSpec,
            $systemUserSpec,
            $deletedUserSpec,
            $hiddenContentFilterSpec,
            $normalVisibilityStatusSpec
        ];

        // Normalize sort to a single uppercase string for mapper
        $allowedSortTypes = ['NEWEST', 'OLDEST', 'BIGGEST_COST', 'SMALLEST_COST'];
        if (array_key_exists('sort', $args)) {
            $sortByInput = $args['sort'];
            if (is_array($sortByInput)) {
                $sortKey = strtoupper((string)($sortByInput[0] ?? 'NEWEST'));
            } elseif (is_string($sortByInput)) {
                $sortKey = strtoupper($sortByInput);
            } else {
                return $this->respondWithError(30103);
            }

            if (!in_array($sortKey, $allowedSortTypes, true)) {
                return $this->respondWithError(30103);
            }
            $args['sort'] = $sortKey;
        }

        try {
            $result = $this->advertisementMapper->fetchAllWithStats($specs, $args);

            return self::createSuccessResponse(12002, $result['affectedRows'], false);
        } catch (\Throwable $e) {
            $this->logger->error('Error fetching Advertisements', ['error' => $e->getMessage()]);
            return self::respondWithError(42001);
        }
    }

    public function isAdvertisementDurationValid(string $postId): bool
    {
        $this->logger->debug('AdvertisementService.isAdvertisementDurationValid started');

        try {
            return $this->advertisementMapper->isAdvertisementDurationValid($postId, $this->currentUserId);

        } catch (\Throwable $e) {
            return false;
        }
    }

    public function hasShortActiveAdWithUpcomingAd(string $postId): bool
    {

        $this->logger->debug('AdvertisementService.hasShortActiveAdWithUpcomingAd started');

        try {
            return $this->advertisementMapper->hasShortActiveAdWithUpcomingAd($postId, $this->currentUserId);

        } catch (\Throwable $e) {
            return false;
        }
    }

    // public function convertEuroToTokens(float $amount = 0, int $rescode = 0): array
    // {

    //     $this->logger->debug('AdvertisementService.convertEuroToTokens started');

    //     try {
    //         $fetchPrices = $this->advertisementMapper->convertEuroToTokens($amount, $rescode);

    //         if ($fetchPrices) {
    //             $fetchPrices['ResponseCode'] = json_encode($fetchPrices['affectedRows']);
    //             return $fetchPrices;
    //         }

    //         return self::respondWithError(42002);
    //     } catch (\Throwable $e) {
    //         return self::respondWithError(42005);
    //     }
    // }

    public function findAdvertiser(?array $args = []): array|false
    {
        if (!$this->checkAuthentication()) {
            return $this->respondWithError(60501);
        }

        $filterBy = $args['filterBy'] ?? [];
        $tag = $args['tag'] ?? null;
        $postId = $args['postid'] ?? null;
        $userId = $args['userid'] ?? null;
        $contentFilterBy = $args['contentFilterBy'] ?? null;
        $tagConfig = ConstantsConfig::post()['TAG'];
        $commentOffset = max((int)($args['commentOffset'] ?? 0), 0);
        $commentLimit = min(max((int)($args['commentLimit'] ?? 10), 1), 20);

        if ($postId !== null && !self::isValidUUID($postId)) {
            return $this->respondWithError(30209);
        }

        if ($userId !== null && !self::isValidUUID($userId)) {
            return $this->respondWithError(30201);
        }

        if ($userId !== null && !$this->userMapper->isUserExistById($userId)) {
            return $this->respondWithError(31007);
        }

        if ($postId !== null && !$this->postMapper->postExistsById($postId)) {
            return $this->respondWithError(31510);
        }

        if ($tag !== null) {
            if (!preg_match('/' . $tagConfig['PATTERN'] . '/u', $tag)) {
                $this->logger->warning('Invalid tag format provided', ['tag' => $tag]);
                return $this->respondWithError(30211);
            }
        }

        // Normalize and validate filterBy (accept scalar or array)
        if (!empty($filterBy)) {
            if (is_string($filterBy)) {
                $filterBy = [$filterBy];
            }

            if (!is_array($filterBy)) {
                return $this->respondWithError(30103);
            }

            $invalidTypes = ContentFilterHelper::invalidAgainstAllowed(
                $filterBy,
                ContentFilterHelper::CONTENT_TYPES
            );

            if (!empty($invalidTypes)) {
                return $this->respondWithError(30103);
            }

            // Ensure mapper receives normalized (UPPER) enums
            $args['filterBy'] = ContentFilterHelper::normalizeToUpper($filterBy);
        }

        $this->logger->debug("AdvertisementService.findAdvertiser started");

        $contentFilterCase = ContentFilteringCases::postFeed;

        if ($tag) {
            $contentFilterCase = ContentFilteringCases::searchByMeta;
        }
        if ($userId || $postId) {
            $contentFilterCase = ContentFilteringCases::searchById;
        }
        if ($userId && $userId === $this->currentUserId) {
            $contentFilterCase = ContentFilteringCases::myprofile;
        }

        $deletedUserSpec = new DeletedUserSpec(
            $contentFilterCase,
            ContentType::post
        );
        $systemUserSpec = new SystemUserSpec(
            $contentFilterCase,
            ContentType::post
        );

        $hiddenContentFilterSpec = new HiddenContentFilterSpec(
            $contentFilterCase,
            $contentFilterBy,
            ContentType::post,
            $this->currentUserId,
        );

        $illegalContentSpec = new IllegalContentFilterSpec(
            $contentFilterCase,
            ContentType::post
        );
        $normalVisibilityStatusSpec = new NormalVisibilityStatusSpec($contentFilterBy);

        $specs = [
            $illegalContentSpec,
            $systemUserSpec,
            $deletedUserSpec,
            $hiddenContentFilterSpec,
            $normalVisibilityStatusSpec
        ];

        $results = $this->advertisementMapper->findAdvertiser($this->currentUserId, $specs, $args);
        //$this->logger->info('findAdvertiser', ['results' => $results]);
        $this->logger->info("AdvertisementService.findAdvertiser Done");
        if (empty($results) && $postId != null) {
            return $this->respondWithError(31510);
        }

        // results is an array of ['post' => PostAdvanced, 'advertisement' => Advertisements]
        // Extract only posts, enrich them, then merge back with their ads and return
        if (!empty($results)) {
            $posts = [];
            foreach ($results as $item) {
                if (isset($item['post']) && $item['post'] instanceof PostAdvanced) {
                    $posts[] = $item['post'];
                }
            }

            if (!empty($posts)) {
                $enrichedPosts = $this->enrichWithProfileAndComment(
                    $posts,
                    $specs,
                    $this->currentUserId,
                    $commentOffset,
                    $commentLimit
                );
                foreach ($enrichedPosts as $post) {
                    ContentReplacer::placeholderPost($post, $specs);
                }
                // Merge enriched posts back to the original structure preserving order
                $idx = 0;
                foreach ($results as $key => $item) {
                    if (isset($item['post']) && $item['post'] instanceof PostAdvanced) {
                        $results[$key]['post'] = $enrichedPosts[$idx] ?? $item['post'];
                        $idx++;
                    }
                }
            }

            // Enrich Advertisements with user profiles
            $adUserIds = [];
            $adUserIds = [];
            foreach ($results as $item) {
                if (isset($item['advertisement']) && $item['advertisement'] instanceof Advertisements) {
                    $uid = $item['advertisement']->getUserId();
                    if (!empty($uid)) {
                        $adUserIds[$uid] = $uid;
                    }
                }
            }

            if (!empty($adUserIds)) {
                $adProfiles = $this->profileRepository->fetchByIds(array_values($adUserIds), $this->currentUserId, $specs);

                foreach ($results as $key => $item) {
                    if (isset($item['advertisement']) && $item['advertisement'] instanceof Advertisements) {
                        $ad = $item['advertisement'];
                        $adData = $ad->getArrayCopy();
                        $profile = $adProfiles[$ad->getUserId()] ?? null;
                        $adEnriched = $this->enrichAndPlaceholderWithProfile($adData, $profile, $specs);
                        $results[$key]['advertisement'] = new Advertisements($adEnriched, [], false);
                    }
                }
            }

        }

        return $results;
    }

    private function enrichWithProfileAndComment(
        array $posts,
        array $specs,
        string $currentUserId,
        int $commentOffset,
        int $commentLimit
    ): array {

        $userIdsFromPosts = array_values(
            array_unique(
                array_filter(
                    array_map(fn (HasUserId $post) => $post->getUserId(), $posts)
                )
            )
        );

        if (empty($userIdsFromPosts)) {
            return $posts;
        }

        $profiles = $this->profileRepository->fetchByIds($userIdsFromPosts, $currentUserId, $specs);

        $enriched = [];
        foreach ($posts as $post) {
            $data = $post->getArrayCopy();
            $enrichedWithProfiles = $this->enrichAndPlaceholderWithProfile($data, $profiles[$post->getUserId()], $specs);
            $enrichedWithCommentsAndProfiles = $this->enrichAndPlaceholderWithComments(
                $enrichedWithProfiles,
                $specs,
                $commentOffset,
                $commentLimit,
                $currentUserId
            );
            $post = new PostAdvanced($enrichedWithCommentsAndProfiles, [], false);
            $enriched[] = $post;
        }

        return $enriched;
    }

    /**
     * Enrich a single PostAdvanced with a Profile and return PostAdvancedWithUser.
     */
    private function enrichAndPlaceholderWithProfile(array $data, ?Profile $profile, array $specs): array
    {
        if ($profile instanceof Profile) {
            ContentReplacer::placeholderProfile($profile, $specs);
            $data['user'] = $profile->getArrayCopy();
        }
        return $data;
    }

    private function enrichAndPlaceholderWithComments(array $data, array $specs, int $commentOffset, int $commentLimit, string $currentUserId): array
    {
        $comments = $this->commentMapper->fetchAllByPostIdetaild($data['postid'], $specs, $currentUserId, $commentOffset, $commentLimit);
        if (empty($comments)) {
            return $data;
        }

        $userIdsFromComments = array_values(
            array_unique(
                array_filter(
                    array_map(fn (CommentAdvanced $c) => $c->getUserId(), $comments)
                )
            )
        );

        if (empty($userIdsFromComments)) {
            return $comments;
        }

        $profiles = $this->profileRepository->fetchByIds($userIdsFromComments, $currentUserId, $specs);
        $commentsArray = [];

        foreach ($comments as $comment) {
            if ($comment instanceof CommentAdvanced) {
                ContentReplacer::placeholderComments($comment, $specs);
                $dataComment = $comment->getArrayCopy();
                $enrichedWithProfiles = $this->enrichAndPlaceholderWithProfile($dataComment, $profiles[$comment->getUserId()], $specs);
                $commentsArray[] = $enrichedWithProfiles;
            }
        }
        $data['comments'] = $commentsArray;
        return $data;
    }
}
