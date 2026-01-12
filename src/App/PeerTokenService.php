<?php

declare(strict_types=1);

namespace Fawaz\App;

use Fawaz\App\Assembler\ProfileEnrichmentAssembler;
use Fawaz\App\Errors\PermissionDeniedException;
use Fawaz\Database\Interfaces\InteractionsPermissionsMapper;
use Fawaz\Database\Interfaces\TransactionManager;
use Fawaz\Database\PeerTokenMapper;
use Fawaz\Database\UserMapper;
use Fawaz\Services\ContentFiltering\Specs\SpecTypes\IllegalContent\IllegalContentFilterSpec;
use Fawaz\Services\ContentFiltering\Specs\SpecTypes\User\DeletedUserSpec;
use Fawaz\Services\ContentFiltering\Specs\SpecTypes\User\SystemUserSpec;
use Fawaz\Services\ContentFiltering\Types\ContentFilteringCases;
use Fawaz\Services\ContentFiltering\Types\ContentType;
use Fawaz\Services\TokenTransfer\Strategies\DefaultTransferStrategy;
use Fawaz\Utils\ResponseHelper;
use Fawaz\Utils\PeerLoggerInterface;
use Fawaz\Database\Interfaces\ProfileRepository;
use Fawaz\config\constants\ConstantsConfig;

use function grapheme_strlen;

class PeerTokenService
{
    use ResponseHelper;

    protected ?string $currentUserId = null;

    public function __construct(
        protected PeerLoggerInterface $logger,
        protected PeerTokenMapper $peerTokenMapper,
        protected TransactionManager $transactionManager,
        protected UserMapper $userMapper,
        protected InteractionsPermissionsMapper $interactionsPermissionsMapper,
        protected ProfileRepository $profileRepository,
        protected ProfileEnrichmentAssembler $profileAssembler
    ) {}

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
     * Make Transfer token to receipients
     *
     */

    public function transferToken(array $args): array
    {
        $this->logger->debug('WalletService.transferToken started');

        if (!$this->checkAuthentication()) {
            throw new PermissionDeniedException(60501, 'Unauthorized');
        }

        $recipientid =  $args['recipient'];

        if (!$this->userMapper->isUserExistById($recipientid)) {
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
            return $this::respondWithError(31203, ['recipientid' => $recipientid]);
        }

        try {
            $this->logger->debug('PeerTokenMapper.transferToken started');

            $recipientId = (string) $args['recipient'];
            $numberOfTokens = (string) $args['numberoftokens'];

            if (!self::isValidUUID($recipientId)) {
                $this->logger->warning('Incorrect recipientId Exception.', [
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
                    $this->logger->warning('Transfer message length is too high', [
                        'maxLength' => $maxLength,
                    ]);
                    return self::respondWithError(30270);
                }
                if (preg_match($controlPattern, $message) === 1) {
                    $this->logger->warning('Transfer message contains control characters');
                    return self::respondWithError(30271);
                }
                if (preg_match($urlPattern, $message) === 1) {
                    $this->logger->warning('Transfer message contains URL/link');
                    return self::respondWithError(30271);
                }
            }
            $transferConfig = $userConfig['TRANSACTION'];
            $minAmount = (float) $transferConfig['MIN_AMOUNT'];
            $maxDecimals = (int) $transferConfig['MAX_DECIMALS'];

            $parts = explode('.', (string) $numberOfTokens);

            if (isset($parts[1]) && strlen($parts[1]) > $maxDecimals) {
                return self::respondWithError(30264);
            }

            if ((float) $numberOfTokens < $minAmount) {
                $this->logger->warning('Incorrect Amount Exception: less than minimum transfer amount', [
                    'numberOfTokens' => $numberOfTokens,
                    'minAmount'      => $minAmount,
                ]);
                return self::respondWithError(30264);
            }

            if ((string) $recipientId === $this->currentUserId) {
                $this->logger->warning('Send and Receive Same Wallet Error.');
                return self::respondWithError(31202);
            }

            // Strict numeric validation for decimals (e.g., "1", "1.0", "0.25")
            $numberOfTokens = sprintf('%.10F', $numberOfTokens);
            // Accepts unsigned decimal numbers with optional fractional part
            $isStrictDecimal = $numberOfTokens !== '' && preg_match('/^(?:\d+)(?:\.\d+)?$/', $numberOfTokens) === 1;
            if (!$isStrictDecimal) {
                return self::respondWithError(30264);
            }

            $receipientUserObj = $this->userMapper->loadById($recipientId);
            if (empty($receipientUserObj)) {
                $this->logger->warning('Unknown Id Exception.');
                return self::respondWithError(31007);
            }

            if (!$this->peerTokenMapper->recipientShouldNotBeFeesAccount($recipientId)) {
                $this->logger->warning('Unauthorized to send token');
                return self::respondWithError(31203);
            }

            $this->transactionManager->beginTransaction();

            $currentBalance = $this->peerTokenMapper->getUserWalletBalance($this->currentUserId);
            if (empty($currentBalance) || $currentBalance < $numberOfTokens) {
                $this->logger->warning('Incorrect Amount Exception: Insufficient balance', [
                    'Balance' => $currentBalance,
                ]);
                $this->transactionManager->rollback();
                return self::respondWithError(51301);
            }

            $requiredAmount = $this->peerTokenMapper->calculateRequiredAmount($this->currentUserId, $numberOfTokens);
            if ($currentBalance < $requiredAmount) {
                $this->logger->warning('No Coverage Exception: Not enough balance to perform this action.', [
                    'senderId' => $this->currentUserId,
                    'Balance' => $currentBalance,
                    'requiredAmount' => $requiredAmount,
                ]);
                $this->transactionManager->rollback();
                return self::respondWithError(51301);
            }

            $transferStrategy = new DefaultTransferStrategy();

            $response = $this->peerTokenMapper->transferToken(
                $this->currentUserId,
                $recipientId,
                $numberOfTokens,
                $transferStrategy,
                $message
            );
            if ($response['status'] === 'error') {
                $this->transactionManager->rollback();
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
            $this->logger->error("Error in PeerTokenService.transferToken", ['exception' => $e->getMessage()]);
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
            $this->logger->error("Error in PeerTokenService.transactionsHistory", ['exception' => $e->getMessage()]);
            throw new \RuntimeException("Database error while fetching transactions: " . $e->getMessage());
        }

    }

    public function transactionsHistoryItems(array $args): array
    {
        $this->logger->info('WalletService.transactionsHistoryItems started');

        if (!$this->checkAuthentication()) {
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
            $this->logger->error("Error in PeerTokenService.transactionsHistory", ['exception' => $e->getMessage()]);
            throw new \RuntimeException("Database error while fetching transactions: " . $e->getMessage());
        }
    }
}
