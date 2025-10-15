<?php

declare(strict_types=1);

namespace Fawaz\Database;

use Fawaz\App\Models\BtcSwapTransaction;
use Fawaz\App\Models\Transaction;
use Fawaz\App\Repositories\BtcSwapTransactionRepository;
use Fawaz\App\Repositories\TransactionRepository;
use PDO;
use Fawaz\Services\LiquidityPool;
use Fawaz\Utils\ResponseHelper;
use Fawaz\Utils\TokenCalculations\TokenHelper;
use Fawaz\Utils\PeerLoggerInterface;
use RuntimeException;
use Fawaz\App\Status;
use Fawaz\App\User;
use Fawaz\config\constants\ConstantsConfig;
use Fawaz\Services\BtcService;
use Fawaz\Utils\TokenCalculations\SwapTokenHelper;

class PeerTokenMapper
{
    use ResponseHelper;
    private string $poolWallet;
    private string $burnWallet;
    private string $peerWallet;
    private string $btcpool;

    public function __construct(protected PeerLoggerInterface $logger, protected PDO $db, protected LiquidityPool $pool, protected WalletMapper $walletMapper)
    {
    }

    /**
     * Loads and validates the liquidity pool and FEE's wallets.
     *
     * @throws \RuntimeException if accounts are missing or invalid
     */
    private function initializeLiquidityPool(): void
    {
        $accounts = $this->pool->returnAccounts();
        if (($accounts['status'] ?? '') === 'error') {
            throw new \RuntimeException("Failed to load pool accounts");
        }

        $data = $accounts['response'] ?? [];
        if (!isset($data['pool'], $data['burn'], $data['peer'])) {
            throw new \RuntimeException("Liquidity pool wallets incomplete");
        }

        $this->poolWallet = $data['pool'];
        $this->burnWallet = $data['burn'];
        $this->peerWallet = $data['peer'];
        $this->btcpool = $data['btcpool'];
    }

    /**
     * get LP account tokens amount.
     * 
     */
    public function getLpToken(): string
    {
        $this->logger->debug("PeerTokenMapper.getLpToken started");

        $query = "SELECT * from wallett WHERE userid = :userId";

        $accounts = $this->pool->returnAccounts();
        $liqpool = $accounts['response'] ?? null;
        $this->poolWallet = $liqpool['pool'];

        try {
            $stmt = $this->db->prepare($query);

            $stmt->bindValue(':userId', $this->poolWallet, \PDO::PARAM_STR);
            $stmt->execute();
            $walletInfo = $stmt->fetch(\PDO::FETCH_ASSOC);

            return (string) $walletInfo['liquidity'];
        } catch (\PDOException $e) {
            $this->logger->error(
                "PeerTokenMapper.getLpToken: Exception occurred while getting loop accounts",
                [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]
            );
            throw new \RuntimeException("Failed to get accounts: " . $e->getMessage());
        } catch (\Exception $e) {
            $this->logger->error(
                "PeerTokenMapper.getLpToken: Exception occurred while getting loop accounts",
                [
                    'error' => $e->getMessage()
                ]
            );
            throw new \RuntimeException("Failed to get accounts: " . $e->getMessage());
        }
    }

    /*
     * Validate fees wallet UUID.
     *
     * @param $inputPassword string
     * @param $hashedPassword string
     *
     * @return bool value
     */
    private function validateFeesWalletUUIDs(): bool
    {
        return self::isValidUUID($this->poolWallet)
            && self::isValidUUID($this->burnWallet)
            && self::isValidUUID($this->peerWallet)
            && self::isValidUUID($this->btcpool);
    }

    /**
     * Make PEER to PEER Token Transfer.
     * 
     * This function handles the transfer of tokens between users, applying necessary fees and ensuring all validations.
     * It also stores the transaction details.
     * Different fees are stored individually for transparency.
     *
     */
    public function transferToken(string $userId, array $args = []): ?array
    {
        \ignore_user_abort(true);

        $this->logger->debug('PeerTokenMapper.transferToken started');

        $recipient = (string) $args['recipient'];

        if ((string) $recipient === $userId) {
            $this->logger->warning('Send and Receive Same Wallet Error.');
            return self::respondWithError(31202);
        }

        if (!self::isValidUUID($recipient)) {
            $this->logger->warning('Incorrect recipientid Exception.', [
                'recipient' => $recipient,
            ]);
            return self::respondWithError(30201);
        }

        $this->initializeLiquidityPool();

        if (!$this->validateFeesWalletUUIDs()) {
            return self::respondWithError(41222);
        }

        $currentBalance = $this->getUserWalletBalance($userId);
        $numberoftokens = (string) $args['numberoftokens'];

        if (empty($currentBalance) || $currentBalance < $numberoftokens) {
            $this->logger->warning('Incorrect Amount Exception: Insufficient balance', [
                'Balance' => $currentBalance,
            ]);
            return self::respondWithError(51301);
        }
        
        if ($numberoftokens <= 0) {
            $this->logger->warning('Incorrect Amount Exception: ZERO or less than token should not be transfer', [
                'numberoftokens' => $numberoftokens,
                'Balance' => $currentBalance,
            ]);
            return self::respondWithError(30264);
        }
        if ($this->poolWallet == $recipient || $this->burnWallet == $recipient || $this->peerWallet == $recipient || $this->btcpool == $recipient) {
            $this->logger->warning('Unauthorized to send token');
            return self::respondWithError(31203);
        }

        if (!isset($args['numberoftokens']) || !is_numeric($args['numberoftokens']) || (float) $args['numberoftokens'] != $args['numberoftokens']) {
            return self::respondWithError(30264);
        }

        
        $message = isset($args['message']) ? (string) $args['message'] : null;


        if ($message !== null && strlen($message) > 200) {
            $this->logger->warning('message length is too high');
            return self::respondWithError(30210); // message length is too high.
        }

        try {
            $sql = "SELECT uid FROM users WHERE uid = :uid AND status != :status";
            $stmt = $this->db->prepare($sql);
            $stmt->bindValue(':uid', $recipient);
            $stmt->bindValue(':status', Status::DELETED);
            $stmt->execute();
            $row = $stmt->fetchColumn();
        } catch (\Throwable $e) {
            $this->logger->error('Database error while fetching recipient user', [
                'error' => $e->getMessage(),
                'recipient' => $recipient
            ]);
            return self::respondWithError(31007);
        }

        if (empty($row)) {
            $this->logger->warning('Unknown Id Exception.');
            return self::respondWithError(31007);
        }

        if ((string)$row === $userId) {
            $this->logger->warning('Send and Receive Same Wallet Error.');
            return self::respondWithError(31202);
        }

        $fees = ConstantsConfig::tokenomics()['FEES'];
        $peerFee = (string) $fees['PEER'];
        $poolFee = (string) $fees['POOL'];
        $burnFee = (string) $fees['BURN'];
        $inviteFee = (string) $fees['INVITATION'];
        $requiredAmount = TokenHelper::calculateTokenRequiredAmount($numberoftokens, $peerFee, $poolFee, $burnFee);

        $inviterId = $this->getInviterID($userId);
        try {
            if ($inviterId && !empty($inviterId)) {
                $inviterWin = TokenHelper::mulRc($numberoftokens, $inviteFee);

                $requiredAmount = TokenHelper::calculateTokenRequiredAmount($numberoftokens, $peerFee, $poolFee, $burnFee, $inviteFee);

                $this->logger->debug('Invited By', [
                    'invited' => $inviterId,
                ]);
            }
        } catch (\Throwable $e) {
            $this->logger->error('Error while fetching inviter ID', [
                'error' => $e->getMessage(),
                'userId' => $userId
            ]);
            return self::respondWithError(31007);
        }

        if ($currentBalance < $requiredAmount) {
            $this->logger->warning('No Coverage Exception: Not enough balance to perform this action.', [
                'userId' => $userId,
                'Balance' => $currentBalance,
                'requiredAmount' => $requiredAmount,
            ]);
            return self::respondWithError(51301);
        }

        try {

            $operationid = self::generateUUID();
            $transRepo = new TransactionRepository($this->logger, $this->db);


            // 1. SENDER: Debit From Account
            if ($requiredAmount) {
                // Remove this records we don't need it anymore.
                // $this->createAndSaveTransaction($transRepo, [
                //     'operationid' => $operationid,
                //     'transactiontype' => 'transferDeductSenderToRecipient',
                //     'senderid' => $userId,
                //     'tokenamount' => -$requiredAmount,
                //     'message' => $message
                // ]);

                $this->walletMapper->saveWalletEntry($userId, $requiredAmount, 'DEDUCT');

            }

            // 2. RECIPIENT: Credit To Account
            if ($numberoftokens) {
                $this->createAndSaveTransaction($transRepo, [
                    'operationid' => $operationid,
                    'transactiontype' => 'transferSenderToRecipient',
                    'senderid' => $userId,
                    'recipientid' => $recipient,
                    'tokenamount' => $numberoftokens,
                    'message' => $message,
                    'transferaction' => 'CREDIT'
                ]);

                $this->walletMapper->saveWalletEntry($recipient, $numberoftokens);

            }

            // 3. INVITER: Fees To Inviter (if applicable)
            if (!empty($inviterId) && $inviterWin) {
                $this->createAndSaveTransaction($transRepo, [
                    'operationid' => $operationid,
                    'transactiontype' => 'transferSenderToInviter',
                    'senderid' => $userId,
                    'recipientid' => $inviterId,
                    'tokenamount' => $inviterWin,
                    'transferaction' => 'INVITER_FEE'
                ]);
                $this->walletMapper->saveWalletEntry($inviterId, $inviterWin);

            }

            // 4. POOLWALLET: Fee To Pool Wallet
            $feeAmount = TokenHelper::mulRc($numberoftokens, $poolFee);
            if ($feeAmount) {
                $this->createAndSaveTransaction($transRepo, [
                    'operationid' => $operationid,
                    'transactiontype' => 'transferSenderToPoolWallet',
                    'senderid' => $userId,
                    'recipientid' => $this->poolWallet,
                    'tokenamount' => $feeAmount,
                    'transferaction' => 'POOL_FEE'
                ]);
                $this->walletMapper->saveWalletEntry($this->poolWallet, ($feeAmount));

            }

            // 5. PEERWALLET: Fee To Peer Wallet
            $peerAmount = TokenHelper::mulRc($numberoftokens, $peerFee);
            if ($peerAmount) {
                $this->createAndSaveTransaction($transRepo, [
                    'operationid' => $operationid,
                    'transactiontype' => 'transferSenderToPeerWallet',
                    'senderid' => $userId,
                    'recipientid' => $this->peerWallet,
                    'tokenamount' => $peerAmount,
                    'transferaction' => 'PEER_FEE'
                ]);
                $this->walletMapper->saveWalletEntry($this->peerWallet, ($peerAmount));
            }

            // 6. BURNWALLET: Burn Tokens
            $burnAmount = TokenHelper::mulRc($numberoftokens, $burnFee);
            if ($burnAmount) {
                $this->createAndSaveTransaction($transRepo, [
                    'operationid' => $operationid,
                    'transactiontype' => 'transferSenderToBurnWallet',
                    'senderid' => $userId,
                    'recipientid' => $this->burnWallet,
                    'tokenamount' => $burnAmount,
                    'transferaction' => 'BURN_FEE'
                ]);
                $this->walletMapper->saveWalletEntry($this->burnWallet, ($burnAmount));
            }

            $this->logger->debug('Token transfer completed successfully');

            return [
                'status' => 'success',
                'ResponseCode' => "11212",
                'tokenSend' => $numberoftokens,
                'tokensSubstractedFromWallet' => $requiredAmount,
                'createdat' => date('Y-m-d H:i:s.u')
            ];
        } catch (\Throwable $e) {
            return self::respondWithError(40301);
        }
    }


    /**
     * Admin Function Only
     * Get Transactions of Cash-Out Requests (BTC SWAP).
     * In this function we are fetching all the transactions where user has swapped their PEER tokens for BTC.
     * 
     * Admin will get all the transactions where transaction type is 'btcSwap'.
     * They can see all the details of the transaction and can process the BTC payment manually.
     * 
     * @param $userId string
     * @param $offset int
     * @param $limit int
     * 
     */
    public function getLiquidityPoolHistory(string $userId, int $offset, int $limit): ?array
    {
        $this->logger->debug('Fetching transaction history - PeerTokenMapper.getLiquidityPoolHistory', ['userId' => $userId]);

        $query = "
                    SELECT 
                        *
                    FROM transactions AS tt
                    LEFT JOIN btc_swap_transactions AS bt ON tt.transactionid = bt.operationid
                    WHERE 
                        tt.transactiontype = :transactiontype
                    ORDER BY tt.createdat DESC
                    LIMIT :limit OFFSET :offset
                ";

        try {
            $stmt = $this->db->prepare($query);
            $stmt->bindValue(':transactiontype', "btcSwapToPool", \PDO::PARAM_STR);
            $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, \PDO::PARAM_INT);
            $stmt->execute();

            $transactions = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            return [
                'status' => 'success',
                'ResponseCode' => '11213', // Liquidity Pool History retrived
                'affectedRows' => $transactions
            ];
        } catch (\PDOException $e) {
            $this->logger->error("Database error while fetching transactions - PeerTokenMapper.getLiquidityPoolHistory", ['error' => $e->getMessage()]);
        }
        return [
            'status' => 'error',
            'ResponseCode' => '41223', // Error while retriveing Liquidity Pool History
            'affectedRows' => []
        ];
    }


    /**
     * Admin Function Only
     * Update BTC swap transaction status to PAID.
     * 
     * @param string $swapId
     * @return array|null
     */
    public function updateSwapTranStatus(string $swapId): ?array
    {
        \ignore_user_abort(true);
        $this->logger->debug('PeerTokenMapper.updateSwapTranStatus started', ['swapId' => $swapId]);

        try {

            // 1. Check if transaction exists and is PENDING
            $query = "SELECT 1 FROM btc_swap_transactions WHERE swapid = :swapid AND status = :status";
            $stmt = $this->db->prepare($query);
            $stmt->bindValue(':swapid', $swapId, \PDO::PARAM_STR);
            $stmt->bindValue(':status', 'PENDING', \PDO::PARAM_STR);
            $stmt->execute();

            if (!$stmt->fetchColumn()) {
                $this->logger->warning('No matching PENDING transaction found for swapId.', ['swapId' => $swapId]);
                return [
                    'status' => 'error',
                    'ResponseCode' => '41224', // No Transaction Found with Pending Status
                ];
            }

            // 2. Update status to PAID
            $updateQuery = "UPDATE btc_swap_transactions SET status = :status WHERE swapid = :swapid";
            $updateStmt = $this->db->prepare($updateQuery);
            $updateStmt->bindValue(':swapid', $swapId, \PDO::PARAM_STR);
            $updateStmt->bindValue(':status', 'PAID', \PDO::PARAM_STR);
            $updateStmt->execute();


            $this->logger->debug('Transaction marked as PAID', ['swapId' => $swapId]);

            $query = "SELECT BTC_T.swapid, TNX.transactionid, BTC_T.transactiontype, TNX.senderid, BTC_T.tokenamount, BTC_T.btcamount, BTC_T.status, BTC_T.message, BTC_T.createdat FROM btc_swap_transactions AS BTC_T LEFT JOIN transactions AS TNX ON TNX.transactionid = BTC_T.operationid WHERE BTC_T.swapid = :swapid";
            $stmt = $this->db->prepare($query);
            $stmt->bindValue(':swapid', $swapId, \PDO::PARAM_STR);
            $stmt->execute();
            $swapTnx = $stmt->fetch(\PDO::FETCH_ASSOC);

            return [
                'status' => 'success',
                'ResponseCode' => '11214',
                'affectedRows' => $swapTnx
            ];
        } catch (\PDOException $e) {
            $this->logger->error(
                "PeerTokenMapper.getLpToken: Exception occurred while getting loop accounts",
                [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]
            );
            return [
                'status' => 'error',
                'ResponseCode' => '40302',
            ];
        } catch (\Throwable $e) {
            $this->logger->error('PeerTokenMapper.updateSwapTranStatus failed', [
                'error' => $e->getMessage(),
                'swapId' => $swapId,
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'status' => 'error',
                'ResponseCode' => '41225', // Failed to update transaction status
            ];
        }
    }

    /**
     * Get Inviter ID of a user.
     */
    private function getInviterID(string $userId): ?string
    {
        try {
            $query = "SELECT invited FROM users_info WHERE userid = :userid AND invited IS NOT NULL";
            $stmt = $this->db->prepare($query);
            $stmt->execute(['userid' => $userId]);
            $result = $stmt->fetch(\PDO::FETCH_ASSOC);

            if (isset($result['invited']) && !empty($result['invited'])) {
                return $result["invited"];
            }
            return null;
        } catch (\Throwable $e) {
            throw new RuntimeException($e->getMessage());
        }
    }


    /**
     * Get User balance
     *
     * @param $userId string
     * @param $hashedPassword string
     *
     * @return string value
     */
    public function getUserWalletBalance(string $userId): string
    {
        $this->logger->debug('WalletMapper.getUserWalletBalance started');

        $query = "SELECT liquidity AS balance 
                  FROM wallett 
                  WHERE userid = :userId";

        try {
            $stmt = $this->db->prepare($query);
            $stmt->bindValue(':userId', $userId, \PDO::PARAM_STR);
            $stmt->execute();
            $balance = $stmt->fetchColumn();

            $this->logger->debug('Fetched wallet balance', ['balance' => $balance]);

            return $balance;
        } catch (\PDOException $e) {
            $this->logger->error('Database error in getUserWalletBalance: ' . $e->getMessage());
            throw new \RuntimeException('Unable to fetch wallet balance');
        }
    }

    /**
     * Helper to create and save a transaction
     */
    private function createAndSaveTransaction($transRepo, array $transObj): void
    {
        $transaction = new Transaction($transObj, ['operationid', 'senderid', 'tokenamount'], false);
        $transRepo->saveTransaction($transaction);
    }

    /**
     *
     * get transcations history of current user.
     *
     */
    public function getTransactions(string $userId, array $args): ?array
    {
        $this->logger->debug("PeerTokenMapper.getTransactions started");

        // Define FILTER mappings.
        $typeMap = [
            'TRANSACTION' => ['transferSenderToRecipient', 'transferDeductSenderToRecipient'],
            'AIRDROP' => ['airdrop'],
            'MINT' => ['mint'],
            'FEES' => ['transferSenderToBurnWallet', 'transferSenderToPeerWallet', 'transferSenderToPoolWallet', 'transferSenderToInviter']
        ];

        // Define DIRECTION FILTER mappings.
        $directionMap = [
            'INCOME' => ['CREDIT'],
            'DEDUCTION' => ['DEDUCT', 'BURN_FEE', 'POOL_FEE', 'PEER_FEE', 'INVITER_FEE']
        ];

        $transactionTypes = isset($args['type']) ? ($typeMap[$args['type']] ?? []) : [];
        $transferActions = isset($args['direction']) ? ($directionMap[$args['direction']] ?? []) : [];

        $query = "SELECT tt.*, 
                        us.username AS sender_username,
                        us.uid AS sender_userid,
                        us.slug AS sender_slug,
                        us.status AS sender_status,
                        us.img AS sender_img,
                        us.biography AS sender_biography, 
                        us.updatedat AS sender_updatedat,
                        ur.username AS recipient_username,
                        ur.uid AS recipient_userid,
                        ur.slug AS recipient_slug,
                        ur.status AS recipient_status,
                        ur.img AS recipient_img,
                        ur.biography AS recipient_biography,
                        ur.updatedat AS recipient_updatedat
                    FROM transactions tt
                    LEFT JOIN users AS us ON us.uid = tt.senderid
                    LEFT JOIN users as ur ON ur.uid = tt.recipientid
                    WHERE (tt.senderid = :senderid OR tt.recipientid = :recipientid)";

        $params = [':senderid' => $userId, ':recipientid' => $userId];

        // Handle TRANSACTION TYPE filter.
        if (!empty($transactionTypes)) {
            $typePlaceholders = [];
            foreach ($transactionTypes as $i => $type) {
                $ph = ":type$i";
                $typePlaceholders[] = $ph;
                $params[$ph] = $type;
            }
            $query .= " AND tt.transactiontype IN (" . implode(',', $typePlaceholders) . ")";
        }

        // Handle TRANSFER ACTION filter.
        if (!empty($transferActions)) {
            $actionPlaceholders = [];
            foreach ($transferActions as $i => $action) {
                $ph = ":action$i";
                $actionPlaceholders[] = $ph;
                $params[$ph] = $action;
            }
            $query .= " AND tt.transferaction IN (" . implode(',', $actionPlaceholders) . ")";
        }

        // Handle DATE filters.(accepting only date, appending time internally)
        if (isset($args['start_date']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $args['start_date'])) {
            $query .= " AND tt.createdat >= :start_date";
            $params[':start_date'] = $args['start_date'] . ' 00:00:00';
        }

        if (isset($args['end_date']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $args['end_date'])) {
            $query .= " AND tt.createdat <= :end_date";
            $params[':end_date'] = $args['end_date'] . ' 23:59:59';
        }

        // Handle SORT safely.(accept ASCENDING or DESCENDING)
        $sortDirection = 'DESC'; // default
        if (isset($args['sort'])) {
            $sortValue = strtoupper(trim($args['sort']));
            if ($sortValue === 'OLDEST') {
                $sortDirection = 'ASC';
            } elseif ($sortValue === 'NEWEST') {
                $sortDirection = 'DESC';
            }
        }
        $query .= " ORDER BY tt.createdat $sortDirection";

        // Handle PAGINATION.(limit and offset)
        if (isset($args['limit']) && is_numeric($args['limit'])) {
            $query .= " LIMIT :limit";
            $params[':limit'] = (int) $args['limit'];
        }

        if (isset($args['offset']) && is_numeric($args['offset'])) {
            $query .= " OFFSET :offset";
            $params[':offset'] = (int) $args['offset'];
        }

        try {
            $stmt = $this->db->prepare($query);
            foreach ($params as $key => $val) {
                $stmt->bindValue($key, $val, is_int($val) ? \PDO::PARAM_INT : \PDO::PARAM_STR);
            }

            $stmt->execute();
            $transactions = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            // map results to sender and recipient user details.
            $data = array_map(
                fn ($trans) => $this->mapTransaction($trans),
                $transactions
            );

            return [
                'status' => 'success',
                'ResponseCode' => "11215",
                'affectedRows' => $data
            ];
        } catch (\Throwable $th) {
            $this->logger->error("Database error while fetching transactions - PeerTokenMapper.getTransactions", [
                'error' => $th->getMessage()
            ]);
            throw new \RuntimeException("Database error while fetching transactions: " . $th->getMessage());
        }
    }

    /**
     * Map transaction with sender and recipient details.
     */
    private function mapTransaction(array $trans): array
    {
        $items = (new Transaction($trans, [], false))->getArrayCopy();
        $items['sender'] = (new User([
            'username' => $trans['sender_username'] ?? null,
            'uid' => $trans['sender_userid'] ?? null,
            'slug' => $trans['sender_slug'] ?? null,
            'status' => $trans['sender_status'] ?? null,
            'img' => $trans['sender_img'] ?? null,
            'biography' => $trans['sender_biography'] ?? null,
            'updatedat' => $trans['sender_updatedat'] ?? null,
        ], [], false))->getArrayCopy();

        $items['recipient'] = (new User([
            'username' => $trans['recipient_username'] ?? null,
            'uid' => $trans['recipient_userid'] ?? null,
            'slug' => $trans['recipient_slug'] ?? null,
            'status' => $trans['recipient_status'] ?? null,
            'img' => $trans['recipient_img'] ?? null,
            'biography' => $trans['recipient_biography'] ?? null,
            'updatedat' => $trans['recipient_updatedat'] ?? null,
        ], [], false))->getArrayCopy();
        
        return $items;
    }

    /**
     * Get token price.
     * 
     * @return array|null
     */
    public function getTokenPrice(): ?array
    {
        $this->logger->debug('PeerTokenMapper.getTokenPrice');

        try {
            $getLpToken = $this->getLpInfo();
            $getLpTokenBtcLP = $this->getLpTokenBtcLP();

            if (empty($getLpToken) || !isset($getLpToken['liquidity'])) {
                throw new \RuntimeException("Invalid LP token data retrieved.");
            }

            // Ensure both values are strings
            $liquidity = (string) $getLpToken['liquidity'];

            if ($liquidity == 0) {
                return [
                    'status' => 'success',
                    'ResponseCode' => '11202', // Successfully retrieved Peer token price
                    'currentTokenPrice' => 0,
                    'updatedAt' => $getLpToken['updatedat'] ?? '',
                ];
            }

            $tokenPrice = TokenHelper::calculatePeerTokenPriceValue($getLpTokenBtcLP, $liquidity);

            return [
                'status' => 'success',
                'ResponseCode' => '11202', // Successfully retrieved Peer token price
                'currentTokenPrice' => $tokenPrice,
                'updatedAt' => $getLpToken['updatedat'] ?? '',
            ];
        } catch (\PDOException $e) {
            $this->logger->error("Database error while fetching transactions - PeerTokenMapper.transactionsHistory", ['error' => $e->getMessage()]);
            return [
                'status' => 'error',
                'ResponseCode' => '40302',
            ];
        } catch (\Throwable $e) {
            $this->logger->error(
                "PeerTokenMapper.getTokenPrice: Exception occurred while calculating token price",
                [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]
            );
            return [
                'status' => 'error',
                'ResponseCode' => '41203', // Failed to retrieve Peer token price
            ];
        }
    }

    /**
     * Get Token Price Value.
     */
    public function getTokenPriceValue(): string
    {
        $this->logger->debug('PeerTokenMapper.getTokenPriceValue');

        try {
            $liqPool = $this->getLpInfo();
            $btcPoolBTCAmount = $this->getLpTokenBtcLP();

            if (empty($liqPool) || !isset($liqPool['liquidity'])) {
                $this->logger->error("Invalid LP data retrieved");
                throw new \RuntimeException("Invalid LP data retrieved.");
            }

            // Ensure both values are floats
            $liqPoolTokenAmount = (string) $liqPool['liquidity'];

            if ($liqPoolTokenAmount == 0 || $btcPoolBTCAmount == 0) {
                $this->logger->error("liqudityPool or btcPool liquidity is 0");
                return '0';
            }

            $tokenPrice = TokenHelper::calculatePeerTokenPriceValue($btcPoolBTCAmount, $liqPoolTokenAmount);

            return (string) $tokenPrice;
        } catch (\PDOException $e) {
            $this->logger->error("Database error while fetching transactions - PeerTokenMapper.transactionsHistory", ['error' => $e->getMessage()]);
            return '0';
        } catch (\Throwable $e) {
            $this->logger->error(
                "PeerTokenMapper.getTokenPrice: Exception occurred while calculating token price",
                [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]
            );
            return '0';
        }
    }

    /**
     * Swap Peer Tokens for BTC.
     * 
     * This will counts applicable fees and ensure user has enough balance.
     * With Peer Tokens, user will receive BTC in their provided BTC address.
     * 
     * Transactions are stored with all fee details for transparency.
     * One more Transaction will be created in btc_swap_transactions table to track BTC swap requests to Admin.
     * 
     * After Admin marks the request as PAID, user will receive BTC in their provided BTC address.
     * 
     */
    public function swapTokens(string $userId, array $args = []): ?array
    {
        \ignore_user_abort(true);

        $this->logger->debug('PeerTokenMapper.swapTokens started');

        if (empty($args['btcAddress'])) {
            $this->logger->warning('BTC Address required');
            return self::respondWithError(31204);
        }
        $btcAddress = $args['btcAddress'];

        if (!PeerTokenMapper::isValidBTCAddress($btcAddress)) {
            $this->logger->warning('Invalid btcAddress .', [
                'btcAddress' => $btcAddress,
            ]);
            return self::respondWithError(31204);
        }

        if (!isset($args['password']) && empty($args['password'])) {
            $this->logger->warning('Password required');
            return self::respondWithError(30237);
        }

        $user = (new UserMapper($this->logger, $this->db))->loadById($userId);
        $password = $args['password'];
        if (!$this->validatePasswordMatch($password, $user->getPassword())) {
            return self::respondWithError(31001);
        }

        $this->initializeLiquidityPool();

        if (!$this->validateFeesWalletUUIDs()) {
            return self::respondWithError(41227);
        }
        $currentBalance = $this->getUserWalletBalance($userId);
        $numberoftokensToSwap = (string) $args['numberoftokens'];

        if (empty($currentBalance) || $currentBalance < $numberoftokensToSwap) {
            $this->logger->warning('Incorrect Amount Exception: Insufficient balance', [
                'Balance' => $currentBalance,
            ]);
            return self::respondWithError(51301);
        }
        $recipient = (string) $this->poolWallet;

        if (!isset($args['numberoftokens']) || !is_numeric($args['numberoftokens']) || (string) $args['numberoftokens'] != $args['numberoftokens']) {
            return self::respondWithError(30264);
        }
        $numberoftokensToSwap = (string) $args['numberoftokens'];

        // Get EUR/BTC price
        $btcPrice = BtcService::getOrUpdateBitcoinPrice($this->logger, $this->db);

        if (empty($btcPrice)) {
            $this->logger->error('Empty EUR/BTC Price');
            return self::respondWithError(41203);
        }

        $peerTokenBTCPrice = $this->getTokenPriceValue();

        if (!$peerTokenBTCPrice) {
            $this->logger->error('Peer/BTC Price is NULL');
            return self::respondWithError(41203);
        }

        $peerTokenEURPrice = TokenHelper::calculatePeerTokenEURPrice($btcPrice, $peerTokenBTCPrice);

        if (TokenHelper::mulRc($peerTokenEURPrice, $numberoftokensToSwap) < 10) {
            $this->logger->warning('Incorrect Amount Exception: Price should be above 10 EUROs', [
                'btcPrice' => $btcPrice,
                'tokenBtc' => TokenHelper::mulRc($peerTokenEURPrice, $numberoftokensToSwap),
                'peerTokenBTCPrice' => $peerTokenBTCPrice,
                'peerTokenEURPrice' => $peerTokenEURPrice,
                'numberoftokens' => $numberoftokensToSwap,
                'Balance' => $currentBalance,
            ]);
            return self::respondWithError(30271);
        }
        $message = isset($args['message']) ? (string) $args['message'] : null;


        $fees = ConstantsConfig::tokenomics()['FEES'];
        $peerFee = (string) $fees['PEER'];
        $poolFee = (string) $fees['POOL'];
        $burnFee = (string) $fees['BURN'];
        $inviteFee = (string)$fees['INVITATION'];
        $requiredAmount = TokenHelper::calculateTokenRequiredAmount($numberoftokensToSwap, $peerFee, $poolFee, $burnFee);

        $inviterId = $this->getInviterID($userId);
        try {
            if ($inviterId && !empty($inviterId)) {
                $inviterWin = TokenHelper::mulRc($numberoftokensToSwap, $inviteFee);

                $requiredAmount = TokenHelper::calculateTokenRequiredAmount($numberoftokensToSwap, $peerFee, $poolFee, $burnFee, $inviteFee);

                $this->logger->debug('Invited By', [
                    'invited' => $inviterId,
                ]);
            }
        } catch (\Throwable $e) {
            return self::respondWithError(31007);
        }

        if (($currentBalance < $requiredAmount)) {
            $this->logger->warning('No Coverage Exception: Not enough balance to perform this action.', [
                'userId' => $userId,
                'Balance' => $currentBalance,
                'requiredAmount' => $requiredAmount,
            ]);
            return self::respondWithError(51301);
        }

        try {

            $btcLpState =  $this->getLpTokenBtcLP();
            $lpState = $this->getLpToken();

            $btcAmountToUser = SwapTokenHelper::calculateBtc($btcLpState, $lpState, $numberoftokensToSwap, $poolFee);
            
            $transRepo = new TransactionRepository($this->logger, $this->db);
            $operationid = self::generateUUID();

            // 1. SENDER: Debit Token and Fees From Account
            if ($requiredAmount) {
                // $this->createAndSaveTransaction($transRepo, [
                //     'transactionid' => $transactionid,
                //     'operationid' => $operationid,
                //     'transactiontype' => 'btcSwap',
                //     'senderid' => $userId,
                //     'recipientid' => $recipient,
                //     'tokenamount' => 0 - (float) $requiredAmount,
                //     'message' => $message,
                // ]);
                $this->walletMapper->saveWalletEntry($userId, $requiredAmount, 'DEDUCT');
            }

            // 2. RECIPIENT: Credit To Account to Pool Account
            if ($numberoftokensToSwap) {
                $transactionid = self::generateUUID();
                $this->createAndSaveTransaction($transRepo, [
                    'transactionid' => $transactionid,
                    'operationid' => $operationid,
                    'transactiontype' => 'btcSwapToPool',
                    'senderid' => $userId,
                    'recipientid' => $recipient,
                    'tokenamount' => $numberoftokensToSwap,
                    'message' => $message,
                    'transferaction' => 'CREDIT'
                ]);
                $this->walletMapper->saveWalletEntry($recipient, $numberoftokensToSwap);
            }


            // 3. INVITER: Fees To inviter Account (if exist)
            if ($inviterId && $inviterWin) {
                $this->createAndSaveTransaction($transRepo, [
                    'operationid' => $operationid,
                    'transactiontype' => 'transferSenderToInviter',
                    'senderid' => $userId,
                    'recipientid' => $inviterId,
                    'tokenamount' => $inviterWin,
                    'transferaction' => 'INVITER_FEE'
                ]);
                $this->walletMapper->saveWalletEntry($inviterId, $inviterWin);
            }

            // 4. PEERWALLET: Fee To Account
            $peerAmount = TokenHelper::mulRc($numberoftokensToSwap, $peerFee);
            if ($peerAmount) {
                $this->createAndSaveTransaction($transRepo, [
                    'operationid' => $operationid,
                    'transactiontype' => 'transferSenderToPeerWallet',
                    'senderid' => $userId,
                    'recipientid' => $this->peerWallet,
                    'tokenamount' => $peerAmount,
                    'transferaction' => 'PEER_FEE'
                ]);
                $this->walletMapper->saveWalletEntry($this->peerWallet, $peerAmount);
            }

            // 5. POOLWALLET: Fee To Account
            $feeAmount = TokenHelper::mulRc($numberoftokensToSwap, $poolFee);
            if ($feeAmount) {
                $this->createAndSaveTransaction($transRepo, [
                    'operationid' => $operationid,
                    'transactiontype' => 'transferSenderToPoolWallet',
                    'senderid' => $userId,
                    'recipientid' => $this->poolWallet,
                    'tokenamount' => $feeAmount,
                    'transferaction' => 'POOL_FEE'
                ]);
                $this->walletMapper->saveWalletEntry($this->poolWallet, $feeAmount);
            }

            // 6. BURNWALLET: Fee Burning Tokens
            $burnAmount = TokenHelper::mulRc($numberoftokensToSwap, $burnFee);
            if ($burnAmount) {
                $this->createAndSaveTransaction($transRepo, [
                    'operationid' => $operationid,
                    'transactiontype' => 'transferSenderToBurnWallet',
                    'senderid' => $userId,
                    'recipientid' => $this->burnWallet,
                    'tokenamount' => $burnAmount,
                    'transferaction' => 'BURN_FEE'
                ]);
                $this->walletMapper->saveWalletEntry($this->burnWallet, $burnAmount);
            }


            // Should be placed at last because it should include 1% LP Fees
            if ($numberoftokensToSwap && isset($transactionid)) {
                $transObj = [
                    'operationid' => $transactionid,
                    'transactiontype' => 'btcSwapToPool',
                    'userId' => $userId,
                    'btcAddress' => $btcAddress,
                    'tokenamount' => $numberoftokensToSwap,
                    'btcAmount' => $btcAmountToUser,
                    'message' => $message,
                    'transferaction' => 'CREDIT'
                ];
                $btcTransactions = new BtcSwapTransaction($transObj);

                $btcTransRepo = new BtcSwapTransactionRepository($this->logger, $this->db);
                $btcTransRepo->saveTransaction($btcTransactions);
            }


            // Update BTC Pool
            if ($btcAmountToUser) {
                $this->walletMapper->saveWalletEntry($this->btcpool, $btcAmountToUser);
            }

            return [
                'status' => 'success',
                'ResponseCode' => '11217',
                'tokenSend' => $numberoftokensToSwap,
                'tokensSubstractedFromWallet' => $requiredAmount,
                'expectedBtcReturn' => $btcAmountToUser
            ];
        } catch (\Throwable $e) {
            $this->logger->error('Error during token swap', [
                'error' => $e->getMessage(),
                'userId' => $userId,
                'btcAddress' => $btcAddress,
                'numberoftokens' => $numberoftokensToSwap
            ]);
            return self::respondWithError(40301);
        }
    }



    /**
     * get LP account info.
     * 
     */
    public function getLpInfo()
    {

        $this->logger->debug("PeerTokenMapper.getLpToken started");

        $query = "SELECT * from wallett WHERE userid = :userId";

        $accounts = $this->pool->returnAccounts();
        $liqpool = $accounts['response'] ?? null;
        $this->poolWallet = $liqpool['pool'];

        try {
            $stmt = $this->db->prepare($query);

            $stmt->bindValue(':userId', $this->poolWallet, \PDO::PARAM_STR);
            $stmt->execute();
            $walletInfo = $stmt->fetch(\PDO::FETCH_ASSOC);

            return $walletInfo;
        } catch (\PDOException $e) {
            $this->logger->error(
                "PeerTokenMapper.getLpToken: Exception occurred while getting loop accounts",
                [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]
            );
            throw new \RuntimeException("Failed to get accounts: " . $e->getMessage());
        } catch (\Exception $e) {
            $this->logger->error(
                "PeerTokenMapper.getLpToken: Exception occurred while getting loop accounts",
                [
                    'error' => $e->getMessage()
                ]
            );
            throw new \RuntimeException("Failed to get accounts: " . $e->getMessage());
        }
    }

    /**
     * get BTC LP.
     * 
     * @returns float BTC Liquidity in account
     */
    public function getLpTokenBtcLP(): string
    {

        $this->logger->debug("PeerTokenMapper.getLpToken started");

        $query = "SELECT * from wallett WHERE userid = :userId";

        $accounts = $this->pool->returnAccounts();

        $liqpool = $accounts['response'] ?? null;
        $this->btcpool = $liqpool['btcpool'];

        try {
            $stmt = $this->db->prepare($query);

            $stmt->bindValue(':userId', $this->btcpool, \PDO::PARAM_STR);
            $stmt->execute();
            $walletInfo = $stmt->fetch(\PDO::FETCH_ASSOC);

            $this->logger->debug("Fetched btcPool data");

            if (!isset($walletInfo['liquidity']) || empty($walletInfo['liquidity'])) {
                throw new \RuntimeException("Failed to get accounts: " . "btcPool liquidity amount is invalid");
            }
            $liquidity = $walletInfo['liquidity'];

            return (string) $liquidity;
        } catch (\PDOException $e) {
            $this->logger->error(
                "PeerTokenMapper.getLpToken: Exception occurred while getting loop accounts",
                [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]
            );
            throw new \RuntimeException("Failed to get accounts: " . $e->getMessage());
        } catch (\Exception $e) {
            $this->logger->error(
                "PeerTokenMapper.getLpToken: Exception occurred while getting loop accounts",
                [
                    'error' => $e->getMessage()
                ]
            );
            throw new \RuntimeException("Failed to get accounts: " . $e->getMessage());
        }
    }


    /**
     * Admin Function Only
     * 
     * Add Liquidity to Pool with respect to Liquidity and BTC.
     * 
     * Admin should be careful while adding liquidity as it will impact token price.
     * 
     * Liquidity and BTC both should be added in the same proportion as they exist in the pool. 
     * For example, 100000 PeerTokens and 1 BTC.
     */
    public function addLiquidity(string $userId, array $args): array
    {
        $this->logger->debug("addLiquidity started");

        try {
            // Validate inputs
            if (!isset($args['amountToken']) || !is_numeric($args['amountToken']) || (float) $args['amountToken'] != $args['amountToken'] || (float) $args['amountToken'] <= 0) {
                return self::respondWithError(30241);
            }
            if (!isset($args['amountBtc']) || !is_numeric($args['amountBtc']) || (float) $args['amountBtc'] != $args['amountBtc'] || (float) $args['amountBtc'] <= 0) {
                return self::respondWithError(30270); 
            }

            // Fetch pool wallets
            $accountsResult = $this->pool->returnAccounts();

            if (isset($accountsResult['status']) && $accountsResult['status'] === 'error') {
                $this->logger->warning('Incorrect returning Accounts', ['Error' => $accountsResult['status']]);
                return self::respondWithError(40701);
            }

            $poolAccounts = $accountsResult['response'] ?? null;
            if (!is_array($poolAccounts) || !isset($poolAccounts['pool'], $poolAccounts['btcpool'])) {
                $this->logger->warning('Missing pool or btcpool account', ['accounts' => $poolAccounts]);
                return self::respondWithError(30102);
            }

            $this->poolWallet = $poolAccounts['pool'];
            $this->btcpool = $poolAccounts['btcpool'];

            if (!self::isValidUUID($this->poolWallet)) {
                $this->logger->warning('Incorrect poolWallet Exception.', [
                    'poolWallet' => $this->poolWallet,
                ]);
                return self::respondWithError(41214);
            }
            if (!self::isValidUUID($this->btcpool)) {
                $this->logger->warning('Incorrect BTC Pool Exception.', [
                    'btcpool' => $this->btcpool,
                ]);
                return self::respondWithError(41214);
            }

            $amountPeerToken =  $args['amountToken'];
            $amountBtc = $args['amountBtc'];
            // Save PeerToken liquidity
            $this->saveLiquidity(
                $userId,
                $this->poolWallet,
                $amountPeerToken,
                'addPeerTokenLiquidity',
                'ADD_PEER_LIQUIDITY'
            );

            // Save BTC liquidity
            $this->saveLiquidity(
                $userId,
                $this->btcpool,
                $amountBtc,
                'addBtcTokenLiquidity',
                'ADD_BTC_LIQUIDITY'
            );

            $newTokenAmount = $this->getLpToken();
            $newBtcAmount = $this->getLpTokenBtcLP();

            $tokenPrice = TokenHelper::calculatePeerTokenPriceValue($newBtcAmount, $newTokenAmount);

            return [
                'status' => 'success',
                'ResponseCode' => '11218',
                'newTokenAmount' => $newTokenAmount,
                'newBtcAmount' => $newBtcAmount,
                'newTokenPrice' => $tokenPrice
            ];
        } catch (\Throwable $e) {
            $this->logger->error('Liquidity error', ['exception' => $e]);
            return self::respondWithError(40301);
        }
    }

    /**
     * Validate BTC address format (basic check).
     * 
     */
    static function isValidBTCAddress($address)
    {
        // Legacy and P2SH addresses
        if (preg_match('/^[13][a-km-zA-HJ-NP-Z1-9]{25,34}$/', $address)) {
            return true;
        }

        // Bech32 (SegWit) addresses
        if (preg_match('/^(bc1|BC1)[0-9a-z]{25,39}$/', $address)) {
            return true;
        }
        return false;
    }

    /**
     * Save wallet entry and log transaction.
     *
     * @params int|string $userId
     * @params string $recipientWallet
     * @params float $amount
     * @params string $transactiontype
     * @params string $transferaction
     */
    private function saveLiquidity(string $userId, string $recipientWallet, string $amount, string $transactiontype, string $transferaction): void
    {
        $this->walletMapper->saveWalletEntry($recipientWallet, $amount);

        $transaction = new Transaction([
            'operationid' => self::generateUUID(),
            'transactiontype' => $transactiontype,
            'senderid' => $userId,
            'recipientid' => $recipientWallet,
            'tokenamount' => $amount,
            'transferaction' => $transferaction,
        ], ['operationid', 'senderid', 'tokenamount'], false);

        $repo = new TransactionRepository($this->logger, $this->db);
        $repo->saveTransaction($transaction);
    }

    /**
     * Save wallet entry.
     *
     * @params $inputPassword string
     * @params $hashedPassword string
     * 
     * @returns bool value
     */
    // public function saveWalletEntry(string $userId, string $liquidity, $direction = 'CREDIT'): float
    // {
    //     \ignore_user_abort(true);
    //     $this->logger->debug('PeerTokenMapper.saveWalletEntry started');

    //     try {
    //         $this->db->beginTransaction();

    //         $query = "SELECT 1 FROM wallett WHERE userid = :userid";
    //         $stmt = $this->db->prepare($query);
    //         $stmt->bindValue(':userid', $userId, \PDO::PARAM_STR);
    //         $stmt->execute();
    //         $userExists = $stmt->fetchColumn();

    //         if ($userExists) {
    //             // Q96
    //             $currentBalance = $this->getUserWalletBalance($userId);

    //             if ($direction == 'CREDIT') {
    //                 $newLiquidity = TokenHelper::addRc((float) $currentBalance, (float) $liquidity);
    //             } elseif ('DEBIT') {
    //                 $newLiquidity = TokenHelper::subRc((float) $currentBalance, (float) $liquidity);
    //             } else {
    //                 throw new \RuntimeException('Unknown Action while save Wallet entry');
    //             }

    //             $liquiditq = TokenHelper::convertToQ96($newLiquidity);

    //             $query = "UPDATE wallett
    //                       SET liquidity = :liquidity, liquiditq = :liquiditq, updatedat = :updatedat
    //                       WHERE userid = :userid";
    //             $stmt = $this->db->prepare($query);
    //             $stmt->bindValue(':userid', $userId, \PDO::PARAM_STR);
    //             $stmt->bindValue(':liquidity', $newLiquidity, \PDO::PARAM_STR);
    //             $stmt->bindValue(':liquiditq', $liquiditq, \PDO::PARAM_STR);
    //             $stmt->bindValue(':updatedat', (new \DateTime())->format('Y-m-d H:i:s.u'), \PDO::PARAM_STR);

    //             $stmt->execute();
    //         }

    //         $this->db->commit();
    //         $this->logger->debug('Wallet entry saved successfully', ['newLiquidity' => $newLiquidity]);
    //         $this->walletMapper->updateUserLiquidity($userId, $newLiquidity);

    //         return $newLiquidity;
    //     } catch (\Throwable $e) {
    //         $this->db->rollBack();
    //         $this->logger->error('Database error in saveWalletEntry: ' . $e->getMessage());
    //         throw new \RuntimeException('Unable to save wallet entry');
    //     }
    // }

    /**
     * validate password.
     *
     * @param $inputPassword string
     * @param $hashedPassword string
     * 
     * @return bool value
     */
    private function validatePasswordMatch(?string $inputPassword, string $hashedPassword): bool
    {
        if (empty($inputPassword) || empty($hashedPassword)) {
            $this->logger->warning('Password or hash cannot be empty');
            return false;
        }

        try {
            return password_verify($inputPassword, $hashedPassword);
        } catch (\Throwable $e) {
            $this->logger->error('Password verification error', ['exception' => $e]);
            return false;
        }
    }
}
