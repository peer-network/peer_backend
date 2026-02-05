<?php

declare(strict_types=1);

namespace Fawaz\App;

use DateTimeImmutable;
use Fawaz\config\constants\ConstantsConfig;
use Fawaz\Database\AdvertisementMapper;
use Fawaz\Database\CommentMapper;
use Fawaz\Database\Interfaces\InteractionsPermissionsMapper;
use Fawaz\Database\Interfaces\ProfileRepository;
use Fawaz\Database\Interfaces\TransactionManager;
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
        protected TransactionManager $transactionManager
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
            $this->logger->error('AdvertisementService.resolveAdvertisePost: Authentication failed');
            return $this->respondWithError(60501);
        }

        $postId = $args['postid'] ?? null;
        $durationInDays = $args['durationInDays'] ?? null;
        $startdayInput = $args['startday'] ?? null;
        $advertisePlan = $args['advertisePlan'] ?? null;
        $reducePrice = false;
        $CostPlan = 0;

        // postId validieren
        if ($postId !== null && !self::isValidUUID($postId)) {
            $this->logger->error('AdvertisementService.resolveAdvertisePost: Invalid postId', ['postId' => $postId]);
            return $this->respondWithError(30209);
        }

        if ($this->postService->postExistsById($postId) === false) {
            $this->logger->error('AdvertisementService.resolveAdvertisePost: Post does not exist', ['postId' => $postId]);
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
            $this->logger->error('AdvertisementService.resolveAdvertisePost: Interaction not allowed', ['postId' => $postId]);
            return $this::respondWithError(32020, ['postid' => $postId]);
        }

        $advertiseActions = ['BASIC', 'PINNED'];

        // Werbeplan validieren
        if (!in_array($advertisePlan, $advertiseActions, true)) {
            $this->logger->error('AdvertisementService.resolveAdvertisePost: Ungültiger Werbeplan', ['advertisePlan' => $advertisePlan]);
            return $this->respondWithError(32006);
        }

        $prices = ConstantsConfig::tokenomics()['ACTION_TOKEN_PRICES'];

        $actionPrices = [
            'BASIC' => $prices['advertisementBasic'],
            'PINNED' => $prices['advertisementPinned'],
        ];

        // Preisvalidierung
        if (!isset($actionPrices[$advertisePlan])) {
            $this->logger->error('AdvertisementService.resolveAdvertisePost: Ungültiger Preisplan', ['advertisePlan' => $advertisePlan]);
            return $this->respondWithError(32005);
        }

        if ($advertisePlan === $this::PLAN_BASIC) {
            // Startdatum validieren
            if (isset($startdayInput) && empty($startdayInput)) {
                $this->logger->error('AdvertisementService.resolveAdvertisePost: Startdatum fehlt oder ist leer', ['startdayInput' => $startdayInput]);
                return $this->respondWithError(32007);
            }

            // Startdatum prüfen und Format validieren
            $startday = DateTimeImmutable::createFromFormat('Y-m-d', $startdayInput);
            $errors = DateTimeImmutable::getLastErrors();

            if (!$startday) {
                $this->logger->error("AdvertisementService.resolveAdvertisePost: Ungültiges Startdatum: '$startdayInput'. Format muss YYYY-MM-DD sein.");
                return $this->respondWithError(32008);
            }

            if (isset($errors['warning_count']) && $errors['warning_count'] > 0 || isset($errors['error_count']) && $errors['error_count'] > 0) {
                $this->logger->error("AdvertisementService.resolveAdvertisePost: Ungültiges Startdatum: '$startdayInput'. Format muss YYYY-MM-DD sein.");
                return $this->respondWithError(42004);
            }

            // Prüfen, ob das Startdatum in der Vergangenheit liegt
            $tomorrow = new DateTimeImmutable('tomorrow');
            if ($startday < $tomorrow) {
                $this->logger->error('AdvertisementService.resolveAdvertisePost: Startdatum darf nicht in der Vergangenheit liegen', ['today' => $startdayInput]);
                return $this->respondWithError(32008);
            }

            $durationActions = ['ONE_DAY', 'TWO_DAYS', 'THREE_DAYS', 'FOUR_DAYS', 'FIVE_DAYS', 'SIX_DAYS', 'SEVEN_DAYS'];

            // Laufzeit validieren
            if ($durationInDays !== null && !in_array($durationInDays, $durationActions, true)) {
                $this->logger->error('AdvertisementService.resolveAdvertisePost: Ungültige Laufzeit', ['durationInDays' => $durationInDays]);
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
                $this->logger->info('AdvertisementService.resolveAdvertisePost: 20% Discount Exestiert:', ['CostPlan' => $CostPlan]);
            }

            $this->logger->info('AdvertisementService.resolveAdvertisePost: Werbeanzeige PINNED', ['CostPlan' => $CostPlan]);
            $rescode = 12003;
        } elseif ($advertisePlan === $this::PLAN_BASIC) {
            $CostPlan = $this->advertisePostBasicResolver($args); // BASIC Kosten berechnen
            $this->logger->info('AdvertisementService.resolveAdvertisePost: Werbeanzeige BASIC', ["Kosten für $durationInDays Tage: " => $CostPlan]);
            $rescode = 12004;
        } else {
            $this->logger->error('AdvertisementService.resolveAdvertisePost: Ungültige Ads Plan', ['CostPlan' => $CostPlan]);
            return $this->respondWithError(32005);
        }

        $args['tokencost'] = $CostPlan;
        // Wenn Kosten leer oder 0 sind, Fehler zurückgeben
        $args['eurocost'] = $CostPlan / 10;
        if (empty($CostPlan) || (int)$CostPlan === 0) {
            $this->logger->error('AdvertisementService.resolveAdvertisePost: Kostenprüfung fehlgeschlagen', ['CostPlan' => $CostPlan]);
            return $this->respondWithError(42005);
        }

        try {
            $this->transactionManager->beginTransaction();
            // Wallet prüfen
            $balance = $this->walletService->getUserWalletBalance($this->currentUserId);
            if ($balance < $CostPlan) {
                $this->transactionManager->rollback();
                $this->logger->error('AdvertisementService.resolveAdvertisePost: Unzureichendes Wallet-Guthaben', ['userId' => $this->currentUserId, 'balance' => $balance, 'CostPlan' => $CostPlan]);
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
                    $this->logger->error('AdvertisementService.resolveAdvertisePost: Error in performPayment', ['userId' => $this->currentUserId, 'details' => $deducted]);
                    $this->transactionManager->rollback();
                    return $deducted;
                }

                if (!$deducted) {
                    $this->logger->error('AdvertisementService.resolveAdvertisePost: Abbuchung vom Wallet fehlgeschlagen', ['userId' => $this->currentUserId]);
                    $this->transactionManager->rollback();
                    return $this->respondWithError(40301);
                }
                $this->transactionManager->commit();
                return $response;
            }
            $this->transactionManager->rollback();
            $this->logger->error('AdvertisementService.resolveAdvertisePost: Error in creating advertisement', ['response' => $response]);
            return $response;

        } catch (\Throwable $e) {
            $this->transactionManager->rollback();
            $this->logger->error('AdvertisementService.resolveAdvertisePost: Exception caught', ['exception' => $e]);
            return $this->respondWithError(40301);
        }
    }

    // Berechne den Basispreis des Beitrags
    protected function advertisePostBasicResolver(?array $args = []): int
    {
        try {
            $this->logger->debug('AdvertisementService.advertisePostBasicResolver started');

            $postId = $args['postid'];
            $duration = $args['durationInDays'];

            $price = $this::calculatePrice($this::PLAN_BASIC, $duration);

            return $price;
        } catch (\Throwable $e) {
            $this->logger->error('AdvertisementService.advertisePostBasicResolver: Invalid price provided.', ['Error' => $e]);
            return 0;
        }
    }

    // Berechne den Preis für angehefteten Beitrag
    protected function advertisePostPinnedResolver(?array $args = []): int
    {
        try {
            $this->logger->debug('AdvertisementService.advertisePostPinnedResolver started');

            $postId = $args['postid'];

            $price = $this::calculatePrice($this::PLAN_PINNED);

            return $price;
        } catch (\Throwable $e) {
            $this->logger->error('AdvertisementService.advertisePostPinnedResolver: Invalid price provided.', ['Error' => $e]);
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
                throw new InvalidArgumentException('AdvertisementService.calculatePrice: BASIC plan requires a duration');
            }

            if (!isset(self::$durationDaysMap[$duration])) {
                throw new InvalidArgumentException('AdvertisementService.calculatePrice: Unknown duration value: ' . $duration);
            }

            return (int)$priceBasic * self::$durationDaysMap[$duration];
        }

        throw new InvalidArgumentException('AdvertisementService.calculatePrice: Unknown advertisement plan: ' . $plan);
    }

    private function checkAuthentication(): bool
    {
        if ($this->currentUserId === null) {
            $this->logger->error('AdvertisementService.checkAuthentication: Unauthorized action attempted.');
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
            $this->logger->error("AdvertisementService.formatStartAndEndTimestamps: Ungültige Werbedauer: $durationKey");
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
            $this->logger->error('AdvertisementService.createAdvertisement: Unauthorized action attempted.');
            return self::respondWithError(60501);
        }

        if (empty($args)) {
            $this->logger->error('AdvertisementService.createAdvertisement: Empty arguments provided.');
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
            $this->logger->error('AdvertisementService.createAdvertisement: Missing postId.');
            return self::respondWithError(32002);
        }

        if (empty($date) && $date !== null) {
            $this->logger->error('AdvertisementService.createAdvertisement: Missing durationInDays.');
            return self::respondWithError(32003);
        }

        if (empty($CostPlan)) {
            $this->logger->error('AdvertisementService.createAdvertisement: Missing advertisePlan.');
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
                    $this->logger->error('AdvertisementService.createAdvertisement: BASIC plan missing part of (postid, date, costplan)');
                    return self::respondWithError(32017); // BASIC: es fehlt eine teil von (postid, date, costplan)
                }

                if ($this->advertisementMapper->hasTimeConflict($postId, \strtolower($CostPlan), $timestart, $timeend, $this->currentUserId) === true) {
                    $this->logger->error('AdvertisementService.createAdvertisement: Basic reservation conflict: The time period is already occupied. Please change the start time to proceed.');
                    return self::respondWithError(32018); // Basic Reservierungskonflikt: Der Zeitraum ist bereits belegt. Bitte ändern Sie den Startzeitpunkt, um fortzufahren.
                }
            } elseif ($CostPlan !== null && $CostPlan === self::PLAN_PINNED) {
                if ($this->advertisementMapper->hasActiveAdvertisement($postId, \strtolower($CostPlan)) === true) {
                    $this->logger->error('AdvertisementService.createAdvertisement: Pinned reservation conflict: The advertisement is still active (not yet expired). Proceeding under forced usage (‘forcing’).', ['advertisementid' => $advertisementId, 'postId' => $postId]);
                    return self::respondWithError(32018); // Basic Reservierungskonflikt: Die Anzeige ist noch aktiv (noch nicht abgelaufen). Das Fortfahren erfolgt unter Zwangsnutzung (‘forcing’).
                }

                $timestart = new \DateTime()->format('Y-m-d H:i:s.u'); // Setze timestart
                $timeend = new \DateTime('+1 days')->format('Y-m-d H:i:s.u'); // Setze Timeend

                if ($this->advertisementMapper->hasTimeConflict($postId, \strtolower($CostPlan), $timestart, $timeend, $this->currentUserId) === true) {
                    $this->logger->error('AdvertisementService.createAdvertisement: Pinned reservation conflict: The time period is already occupied. Please change the start time to proceed.');
                    return self::respondWithError(32018); // Basic Reservierungskonflikt: Der Zeitraum ist bereits belegt. Bitte ändern Sie den Startzeitpunkt, um fortzufahren.
                }

                $this->logger->info('PLAN IS PINNED');
            } else {
                $this->logger->error('AdvertisementService.createAdvertisement: Invalid CostPlan provided.', ['CostPlan' => $CostPlan]);
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
                $this->logger->error('AdvertisementService.createAdvertisement: Error validating advertisement', ['exception' => $e]);
                // Die richtige errorCode.
                return self::respondWithError((int)$e->getMessage());
            }

            if ($CostPlan === self::PLAN_BASIC) {
                $resp = $this->advertisementMapper->insert($advertisement);
                $this->logger->info('AdvertisementService.createAdvertisement: Create Post Advertisement', ['advertisementid' => $advertisementId, 'postId' => $postId]);
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
                $this->logger->info('AdvertisementService.createAdvertisement: Create Post Advertisement', ['advertisementid' => $advertisementId, 'postId' => $postId]);
                $rescode = 12001; // Advertisement post erfolgreich erstellt.
            } else {
                $this->logger->error('AdvertisementService.createAdvertisement: Invalid CostPlan provided.', ['CostPlan' => $CostPlan]);
                return self::respondWithError(32005); // Fehler, Falsche CostPlan angegeben.
            }

            $data = $resp->getArrayCopy();
            $this->logger->info('AdvertisementService.createAdvertisement: Successfully created advertisement.', ['data' => $data]);
            return self::createSuccessResponse($rescode, [$data]);

        } catch (\Throwable $e) {
            $this->logger->error('AdvertisementService.createAdvertisement: Failed to create advertisement', ['exception' => $e]);
            return self::respondWithError(42007); // Erstellen der Post Advertisement fehlgeschlagen.
        }
    }

    public function fetchAll(?array $args = []): array
    {
        if (!$this->checkAuthentication()) {
            $this->logger->error('AdvertisementService.fetchAll: Authentication failed.');
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
        $commentOffset = max((int)($args['commentOffset'] ?? 0), 0);
        $commentLimit = min(max((int)($args['commentLimit'] ?? 10), 1), 20);


        if ($from !== null && !self::validateDate($from)) {
            $this->logger->error('AdvertisementService.fetchAll: Invalid from date', ['from' => $from]);
            return self::respondWithError(30212);
        }

        if ($to !== null && !self::validateDate($to)) {
            $this->logger->error('AdvertisementService.fetchAll: Invalid to date', ['to' => $to]);
            return self::respondWithError(30213);
        }

        if ($advertisementtype !== null && !in_array($advertisementtype, $advertiseActions, true)) {
            $this->logger->error('AdvertisementService.fetchAll: Invalid advertisement type', ['advertisementtype' => $advertisementtype]);
            return $this->respondWithError(32006);
        }

        if ($advertisementId !== null && !self::isValidUUID($advertisementId)) {
            $this->logger->error('AdvertisementService.fetchAll: Invalid advertisementId', ['advertisementId' => $advertisementId]);
            return $this->respondWithError(30269);
        }

        if ($advertisementId !== null && !$this->advertisementMapper->advertisementExistsById($advertisementId)) {
            $this->logger->error('AdvertisementService.fetchAll: Advertisement not found', ['advertisementId' => $advertisementId]);
            return $this->respondWithError(32019);
        }

        if ($postId !== null && !self::isValidUUID($postId)) {
            $this->logger->error('AdvertisementService.fetchAll: Invalid postId', ['postId' => $postId]);
            return $this->respondWithError(30209);
        }

        if ($postId !== null && !$this->postMapper->postExistsById($postId)) {
            $this->logger->error('AdvertisementService.fetchAll: Post does not exist', ['postId' => $postId]);
            return $this->respondWithError(31510);
        }

        if ($userId !== null && !self::isValidUUID($userId)) {
            $this->logger->error('AdvertisementService.fetchAll: Invalid userId', ['userId' => $userId]);
            return $this->respondWithError(30201);
        }

        if ($userId !== null && !$this->userMapper->isUserExistById($userId)) {
            $this->logger->error('AdvertisementService.fetchAll: User does not exist', ['userId' => $userId]);
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
                $this->logger->error('AdvertisementService.fetchAll: Invalid sort input type', ['sort' => $sortByInput]);
                return $this->respondWithError(30103);
            }

            if (!in_array($sortKey, $allowedSortTypes, true)) {
                $this->logger->error('AdvertisementService.fetchAll: Invalid sort value', ['sort' => $sortKey]);
                return $this->respondWithError(30103);
            }
            $args['sort'] = $sortKey;
        }

        try {
            $result = $this->advertisementMapper->fetchAllWithStats($specs, $args);

            $historyRows = $result['affectedRows']['advertisements'] ?? [];
            if (!empty($historyRows)) {
                $entries = $this->convertHistoryRowsToEntries($historyRows);
                $entries = $this->enrichPostAndAdvertisementResults(
                    $entries,
                    $specs,
                    $commentOffset,
                    $commentLimit
                );
                $result['affectedRows']['advertisements'] = $this->flattenHistoryEntries($entries);
            }

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
            $this->logger->error('AdvertisementService.findAdvertiser: Authentication failed');
            return $this->respondWithError(60501);
        }

        $filterBy = $args['filterBy'] ?? [];
        $tag = $args['tag'] ?? null;
        $title = $args['title'] ?? null;
        $postId = $args['postid'] ?? null;
        $userId = $args['userid'] ?? null;
        $contentFilterBy = $args['contentFilterBy'] ?? null;
        $tagConfig = ConstantsConfig::post()['TAG'];
        $titleConfig = ConstantsConfig::post()['TITLE'];
        $commentOffset = max((int)($args['commentOffset'] ?? 0), 0);
        $commentLimit = min(max((int)($args['commentLimit'] ?? 10), 1), 20);

        if ($postId !== null && !self::isValidUUID($postId)) {
            $this->logger->error('AdvertisementService.findAdvertiser: Invalid postId', ['postId' => $postId]);
            return $this->respondWithError(30209);
        }

        if ($userId !== null && !self::isValidUUID($userId)) {
            $this->logger->error('AdvertisementService.findAdvertiser: Invalid userId', ['userId' => $userId]);
            return $this->respondWithError(30201);
        }

        if ($userId !== null && !$this->userMapper->isUserExistById($userId)) {
            $this->logger->error('AdvertisementService.findAdvertiser: User does not exist', ['userId' => $userId]);
            return $this->respondWithError(31007);
        }

        if ($postId !== null && !$this->postMapper->postExistsById($postId)) {
            $this->logger->error('AdvertisementService.findAdvertiser: Post does not exist', ['postId' => $postId]);
            return $this->respondWithError(31510);
        }

        if ($tag !== null) {
            if (!preg_match('/' . $tagConfig['PATTERN'] . '/u', $tag)) {
                $this->logger->error('Invalid tag format provided', ['tag' => $tag]);
                return $this->respondWithError(30211);
            }
        }

        if ($title !== null && (grapheme_strlen((string)$title) < $titleConfig['MIN_LENGTH'] || grapheme_strlen((string)$title) > $titleConfig['MAX_LENGTH'])) {
            $this->logger->error('AdvertisementService.findAdvertiser: Invalid title length', ['title' => $title]);
            return $this::respondWithError(30210);
        }

        // Normalize and validate filterBy (accept scalar or array)
        if (!empty($filterBy)) {
            if (is_string($filterBy)) {
                $filterBy = [$filterBy];
            }

            if (!is_array($filterBy)) {
                $this->logger->error('AdvertisementService.findAdvertiser: Invalid filterBy type', ['filterBy' => $filterBy]);
                return $this->respondWithError(30103);
            }

            $invalidTypes = ContentFilterHelper::invalidAgainstAllowed(
                $filterBy,
                ContentFilterHelper::CONTENT_TYPES
            );

            if (!empty($invalidTypes)) {
                $this->logger->error('AdvertisementService.findAdvertiser: Invalid filterBy values', ['filterBy' => $filterBy]);
                return $this->respondWithError(30103);
            }

            // Ensure mapper receives normalized (UPPER) enums
            $args['filterBy'] = ContentFilterHelper::normalizeToUpper($filterBy);
        }

        $this->logger->debug("AdvertisementService.findAdvertiser started");

        $contentFilterCase = ContentFilteringCases::postFeed;

        if ($title || $tag) {
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
            $this->logger->error('AdvertisementService.findAdvertiser: Post does not exist', ['postId' => $postId]);
            return $this->respondWithError(31510);
        }

        if (!empty($results)) {
            $results = $this->enrichPostAndAdvertisementResults(
                $results,
                $specs,
                $commentOffset,
                $commentLimit
            );

        }

        return $results;
    }

    private function enrichPostAndAdvertisementResults(
        array $results,
        array $specs,
        int $commentOffset,
        int $commentLimit
    ): array {
        if (empty($results)) {
            return $results;
        }

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

            $idx = 0;
            foreach ($results as $key => $item) {
                if (isset($item['post']) && $item['post'] instanceof PostAdvanced) {
                    $results[$key]['post'] = $enrichedPosts[$idx] ?? $item['post'];
                    $idx++;
                }
            }
        }

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

        return $results;
    }

    private function convertHistoryRowsToEntries(array $historyRows): array
    {
        $entries = [];
        foreach ($historyRows as $row) {
            $post = $this->createPostFromArray($row['post'] ?? []);
            $advertisement = $this->createAdvertisementFromArray($row);

            if (!$post instanceof PostAdvanced || !$advertisement instanceof Advertisements) {
                continue;
            }

            $entries[] = [
                'post' => $post,
                'advertisement' => $advertisement,
            ];
        }

        return $entries;
    }

    private function flattenHistoryEntries(array $entries): array
    {
        $flattened = [];
        foreach ($entries as $entry) {
            $post = $entry['post'] instanceof PostAdvanced ? $entry['post']->getArrayCopy() : (array)$entry['post'];
            $advertisement = $entry['advertisement'] instanceof Advertisements ? $entry['advertisement']->getArrayCopy() : (array)$entry['advertisement'];
            $advertisement['post'] = $post;
            $flattened[] = $advertisement;
        }

        return $flattened;
    }

    private function createPostFromArray(array $postData): ?PostAdvanced
    {
        if (empty($postData['postid'])) {
            return null;
        }

        if (empty($postData['userid']) && isset($postData['user']['uid'])) {
            $postData['userid'] = (string)$postData['user']['uid'];
        }

        return new PostAdvanced($postData, [], false);
    }

    private function createAdvertisementFromArray(array $adData): ?Advertisements
    {
        if (empty($adData['advertisementid'] ?? null)) {
            return null;
        }

        return new Advertisements($adData, [], false);
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
