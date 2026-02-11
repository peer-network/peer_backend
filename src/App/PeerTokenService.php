<?php

declare(strict_types=1);

namespace Fawaz\App;

use Fawaz\App\Assembler\ProfileEnrichmentAssembler;
use Fawaz\App\Errors\PermissionDeniedException;
use Fawaz\Database\Interfaces\InteractionsPermissionsMapper;
use Fawaz\Database\Interfaces\TransactionManager;
use Fawaz\Database\PeerTokenMapper;
use Fawaz\Services\ContentFiltering\Specs\SpecTypes\IllegalContent\IllegalContentFilterSpec;
use Fawaz\Services\ContentFiltering\Specs\SpecTypes\User\DeletedUserSpec;
use Fawaz\Services\ContentFiltering\Specs\SpecTypes\User\SystemUserSpec;
use Fawaz\Services\ContentFiltering\Types\ContentFilteringCases;
use Fawaz\Services\ContentFiltering\Types\ContentType;
use Fawaz\Services\TokenTransfer\Strategies\UserToUserTransferStrategy;
use Fawaz\Utils\ResponseHelper;
use Fawaz\Utils\PeerLoggerInterface;
use Fawaz\Database\Interfaces\ProfileRepository;
use Fawaz\config\constants\ConstantsConfig;
use Fawaz\Database\UserMapperInterface;

use function grapheme_strlen;

class PeerTokenService
{
    use ResponseHelper;

    protected ?string $currentUserId = null;

    public function __construct(
        protected PeerLoggerInterface $logger,
        protected PeerTokenMapper $peerTokenMapper,
        protected TransactionManager $transactionManager,
        protected UserMapperInterface $userMapper,
        protected UserService $userService,
        protected InteractionsPermissionsMapper $interactionsPermissionsMapper,
        protected ProfileRepository $profileRepository,
        protected ProfileEnrichmentAssembler $profileAssembler
    ) {
    }

    public function setCurrentUserId(string $userId): void
    {
        $this->currentUserId = $userId;
    }

    private function checkAuthentication(): bool
    {
        if ($this->currentUserId === null) {
            $this->logger->warning('PeerTokenService.checkAuthentication: Unauthorized access attempt');
            return false;
        }
        return true;
    }

    /**
     * Make Transfer token to receipients
     *
     */

    public function transferToken(array $args): array
    {
        $this->logger->debug('PeerTokenService.transferToken started');

        if (!$this->checkAuthentication()) {
            $this->logger->warning('PeerTokenService.transferToken: Unauthorized access attempt');
            throw new PermissionDeniedException(60501, 'Unauthorized access attempt');
        }

        $recipientid =  $args['recipient'];

        if (!$this->userService->isVisibleUserExistById($recipientid)) {
            $this->logger->debug('PeerTokenService.transferToken: Recipient not found', ['recipientid' => $recipientid]);
            return $this::respondWithError(31007);
        }

        $contentFilterCase = ContentFilteringCases::searchById;

        $systemUserSpec = new SystemUserSpec(
            $contentFilterCase,
            ContentType::user
        );

        $specs = [
            $systemUserSpec
        ];

        if ($this->interactionsPermissionsMapper->isInteractionAllowed($specs, $recipientid) === false) {
            $this->logger->debug('PeerTokenService.transferToken: Interaction not allowed', ['recipientid' => $recipientid]);
            return $this::respondWithError(31203, ['recipientid' => $recipientid]);
        }

        try {
            $this->logger->debug('PeerTokenService.transferToken started');

            $recipientId = (string) $args['recipient'];
            $numberOfTokens = (string) $args['numberoftokens'];

            // replace , with . value
            $numberOfTokens = str_replace(',', '.', $numberOfTokens);

            if (!self::isValidUUID($recipientId)) {
                $this->logger->error('PeerTokenService.transferToken: Incorrect recipientId', [
                    'recipientId' => $recipientId,
                ]);
                return self::respondWithError(30201);
            }

            $userConfig  = ConstantsConfig::user();
            $inputConfig = ConstantsConfig::input();
            $message = isset($args['message']) ? (string) $args['message'] : null;
            if ($message !== null) {
                $messageConfig = $userConfig['TRANSFER_MESSAGE'];

                $maxLength      = (int) $messageConfig['MAX_LENGTH'];
                $controlPattern = '/'.$inputConfig['FORBID_CONTROL_CHARS_PATTERN'].'/u';
                $urlPattern     = '/'.$messageConfig['PATTERN_URL'].'/iu';

                if (grapheme_strlen($message) > $maxLength) {
                    $this->logger->error('PeerTokenService.transferToken: Transfer message length is too high', [
                        'maxLength' => $maxLength,
                    ]);
                    return self::respondWithError(30270);
                }
                if (preg_match($controlPattern, $message) === 1) {
                    $this->logger->debug('PeerTokenService.transferToken: Transfer message contains control characters');
                    return self::respondWithError(30271);
                }
                if (preg_match($urlPattern, $message) === 1) {
                    $this->logger->debug('PeerTokenService.transferToken: Transfer message contains URL/link');
                    return self::respondWithError(30271);
                }
            }
            $transferConfig = $userConfig['TRANSACTION'];
            $minAmount = (float) $transferConfig['MIN_AMOUNT'];
            $maxDecimals = (int) $transferConfig['MAX_DECIMALS'];

            $parts = explode('.', (string) $numberOfTokens);

            if (isset($parts[1]) && strlen($parts[1]) > $maxDecimals) {
                $this->logger->debug('PeerTokenService.transferToken: Too many decimal places', ['numberOfTokens' => $numberOfTokens]);
                return self::respondWithError(30264);
            }

            if ((float) $numberOfTokens < $minAmount) {
                $this->logger->error('PeerTokenService.transferToken: Less than minimum transfer amount', [
                    'numberOfTokens' => $numberOfTokens,
                    'minAmount'      => $minAmount,
                ]);
                return self::respondWithError(30264);
            }

            if ((string) $recipientId === $this->currentUserId) {
                $this->logger->debug('PeerTokenService.transferToken: Send and Receive Same Wallet Error', ['recipientId' => $recipientId]);
                return self::respondWithError(31202);
            }

            // Strict numeric validation for decimals (e.g., "1", "1.0", "0.25")
            $numberOfTokens = sprintf('%.10F', $numberOfTokens);
            // Accepts unsigned decimal numbers with optional fractional part
            $isStrictDecimal = $numberOfTokens !== '' && preg_match('/^(?:\d+)(?:\.\d+)?$/', $numberOfTokens) === 1;
            if (!$isStrictDecimal) {
                $this->logger->debug('PeerTokenService.transferToken: Invalid token amount format');
                return self::respondWithError(30264);
            }

            $receipientUserObj = $this->userMapper->loadById($recipientId);
            $senderUserObj = $this->userMapper->loadById($this->currentUserId);

            if (empty($receipientUserObj)) {
                $this->logger->debug('PeerTokenService.transferToken: Recipient user not found', ['recipientId' => $recipientId]);
                return self::respondWithError(31007);
            }
            if (empty($senderUserObj)) {
                $this->logger->error('PeerTokenService.transferToken: Sender user not found', ['senderId' => $this->currentUserId]);
                return self::respondWithError(40301);
            }

            if (!$this->peerTokenMapper->recipientShouldNotBeFeesAccount($recipientId)) {
                $this->logger->debug('PeerTokenService.transferToken: Unauthorized to send token', ['recipientId' => $recipientId]);
                return self::respondWithError(31203);
            }

            // get Fees account and check for existence
            $feesAccountExist = $this->peerTokenMapper->isFeesAccountExist();
            if (!$feesAccountExist) {
                $this->logger->error('PeerTokenService.transferToken: Fees account does not exist');
                return self::respondWithError(40301);
            }


            $this->transactionManager->beginTransaction();

            $currentBalance = $this->peerTokenMapper->getUserWalletBalance($this->currentUserId);
            if (empty($currentBalance) || $currentBalance < $numberOfTokens) {
                $this->logger->error('PeerTokenService.transferToken: Insufficient balance');
                $this->transactionManager->rollback();
                return self::respondWithError(51301);
            }

            $requiredAmount = $this->peerTokenMapper->calculateRequiredAmount($this->currentUserId, $numberOfTokens);
            if ($currentBalance < $requiredAmount) {
                $this->logger->error('PeerTokenService.transferToken: Not enough balance to perform this action', [
                    'senderId' => $this->currentUserId,
                    'requiredAmount' => $requiredAmount,
                ]);
                $this->transactionManager->rollback();
                return self::respondWithError(51301);
            }

            $transferStrategy = new UserToUserTransferStrategy();

            $response = $this->peerTokenMapper->transferToken(
                $numberOfTokens,
                $transferStrategy,
                $senderUserObj,
                $receipientUserObj,
                $message,
            );
            if ($response['status'] === 'error') {
                $this->transactionManager->rollback();
                $this->logger->error('PeerTokenService.transferToken: Token transfer failed', ['response' => $response]);
                return $response;
            } else {
                $this->logger->info('PeerTokenService.transferToken completed successfully', ['response' => $response]);
                $this->transactionManager->commit();
                return $this::createSuccessResponse(
                    11211,
                    [
                        'tokenSend'                  => sprintf('%.10F', $response['tokenSend']),
                        'tokensSubstractedFromWallet' => sprintf('%.10F', $response['tokensSubstractedFromWallet']),
                        'createdat'                  => $response['createdat'] ?? '',
                    ],
                    false // no counter needed for associative array
                );

            }

        } catch (\Exception $e) {
            $this->logger->error("PeerTokenService.transferToken: Error ", ['exception' => $e->getMessage()]);
            $this->transactionManager->rollback();
            return $this::respondWithError(41229); // Failed to transfer token
        }
    }

    /**
     * Get transcation history with Filter
     *
     */
    public function transactionsHistory(array $args): array
    {
        $this->logger->debug('PeerTokenService.transactionsHistory started');

        try {
            $results = $this->peerTokenMapper->getTransactions($this->currentUserId, $args);
            return $this::createSuccessResponse(
                (int)$results['ResponseCode'],
                $results['affectedRows'],
                false // no counter needed for existing data
            );

        } catch (\Exception $e) {
            $this->logger->error("PeerTokenService.transactionsHistory: Error ", ['exception' => $e->getMessage()]);
            throw new \RuntimeException("Database error while fetching transactions: " . $e->getMessage());
        }

    }

    public function transactionsHistoryItems(array $args): array
    {
        $this->logger->info('PeerTokenService.transactionsHistoryItems started');

        if (!$this->checkAuthentication()) {
            $this->logger->warning('PeerTokenService.transactionsHistoryItems: Unauthorized access attempt');
            throw new PermissionDeniedException(60501, 'Unauthorized');
        }

        $contentFilterCase = ContentFilteringCases::searchById;
        $targetContent = ContentType::user;

        $deletedUserSpec = new DeletedUserSpec(
            $contentFilterCase,
            $targetContent
        );
        $systemUserSpec = new SystemUserSpec(
            $contentFilterCase,
            $targetContent
        );

        $illegalContentSpec = new IllegalContentFilterSpec(
            $contentFilterCase,
            $targetContent
        );

        $specs = [
            $illegalContentSpec,
            $systemUserSpec,
            $deletedUserSpec
        ];

        try {
            $items = $this->peerTokenMapper->getTransactionHistoryItems(
                $this->currentUserId,
                $args,
                $specs
            );

            $this->profileAssembler->enrichHasUserRefs($items, $specs, (string)$this->currentUserId);

            return $items;

        } catch (\Exception $e) {
            $this->logger->error("PeerTokenService.transactionsHistoryItems: Error ", ['exception' => $e->getMessage()]);
            throw new \RuntimeException("Database error while fetching transactions: " . $e->getMessage());
        }
    }
}
