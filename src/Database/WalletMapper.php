<?php

namespace Fawaz\Database;

use PDO;
use Fawaz\App\Wallet;
use Fawaz\App\Wallett;
use Psr\Log\LoggerInterface;

const TABLESTOGEMS = true;

const DAILY_NUMBER_TOKEN= 5000;
const ALL_TIME_NUMBER_TOKEN= 18250000;
const LIQUIDITYPOOL = 'c76c8fdb-0a38-11ef-b7f2-e89c25791d89';
const POOLPOST = 'b615755c-685b-41d6-9ec8-b08d1bccb3c8';
const POOLCHAT = '797a79a6-6809-4b35-892b-d8652e360263';

define('Q64_96_SCALE', bcpow(2, 96));

const VIEW_=1;
const LIKE_=2;
const DISLIKE_=3;
const COMMENT_=4;
const POST_=5;
const REPORT_=6;
const FRAME_=7;
const FRAMES_=8;
const POSTINCREASE_=9;
const CRYPTEDMSG_=10;
const INVITATION_=11;
const OWNSHARED_=12;
const OTHERSHARED_=13;
const DIRECTDEBIT_=14;
const CREDIT_=15;
const FRIEND_=16;
const MESSAGE_=17;
const TRANSFER_=18;
const SAVES_=19;
const FREELIKE_=30;
const FREECOMMENT_=31;
const FREEPOST_=32;
const TESTER_=99;
const FEEWHERBY_= 100;

const DAILYFREEPOST=1;
const DAILYFREELIKE=3;
const DAILYFREECOMMENT=4;

const RECEIVELIKE=5;
const RECEIVEDISLIKE=4;
const RECEIVECOMMENT=2;
const RECEIVEPOSTVIEW=0.25;
const RECEIVEINVITATION=10;

const PRICELIKE=3;
const PRICEDISLIKE=5;
const PRICECOMMENT=0.5;
const PRICEPOST=20;
const PRICEREPORT=20;
const PRICEFRAME=2000;
const PRICEFRAMES=75000;

class WalletMapper
{
    public function __construct(protected LoggerInterface $logger, protected PDO $db)
    {
    }

    protected function respondWithError(string $message): array
    {
        return ['status' => 'error', 'ResponseCode' => $message];
    }

    public function fetchAll(array $args = []): array
    {
        $this->logger->info('WalletMapper.fetchAll started');

        $offset = max((int)($args['offset'] ?? 0), 0);
        $limit = max((int)($args['limit'] ?? 20), 1);

        $sql = "SELECT token, userid, postid, fromid, numbers, whereby, createdat 
                FROM wallet
                WHERE whereby < 100";

        $conditions = [];
        $queryParams = [];

        foreach ($args as $field => $value) {
            if (in_array($field, ['userid', 'postid', 'fromid', 'numbers', 'whereby'], true)) {
                $conditions[] = "$field = :$field";
                $queryParams[":$field"] = $value;
            }
        }

        if ($conditions) {
            $sql .= " AND " . implode(" AND ", $conditions);
        }

        $sql .= " ORDER BY userid, whereby LIMIT :limit OFFSET :offset";
        $queryParams[':limit'] = $limit;
        $queryParams[':offset'] = $offset;

        $this->logger->info('Executing SQL query', ['sql' => $sql, 'params' => $queryParams]);

        $stmt = $this->db->prepare($sql);
        $stmt->execute($queryParams);

        $results = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            try {
                $results[] = new Wallet($row);
				$this->logger->info('Executing SQL query', ['row' => $row]);
            } catch (Exception $e) {
                $this->logger->error('Failed to create User object', ['error' => $e->getMessage(), 'data' => $row]);
            }
        }

        if ($results) {
            $this->logger->info('Fetched all transactions from database', ['count' => count($results)]);
        } else {
            $this->logger->warning('No transaction found in database');
        }

        return $results;
    }

	public function loadWalletById(?array $args = [], string $currentUserId): array|false
	{
		$this->logger->info('WalletMapper.loadWalletById started');

		$userId = $currentUserId ?? null;
		$postId = $args['postid'] ?? null;
		$fromId = $args['fromid'] ?? null;

		if ($userId === null && $postId === null && $fromId === null) {
			$this->logger->warning('WalletMapper.loadWalletById missing required identifiers in args');
			return false;
		}

		try {
			$offset = max((int)($args['offset'] ?? 0), 0);
			$limit = max((int)($args['limit'] ?? 10), 1);

			$conditions = [];
			$params = [];

			if ($userId !== null) {
				$conditions[] = "userid = :userid";
				$params['userid'] = $userId;
			}
			if ($postId !== null) {
				$conditions[] = "postid = :postid";
				$params['postid'] = $postId;
			}
			if ($fromId !== null) {
				$conditions[] = "fromid = :fromid";
				$params['fromid'] = $fromId;
			}

			$whereClause = implode(' AND ', $conditions);

			$sql = "
				SELECT 
					token, 
					userid, 
					postid, 
					fromid, 
					numbers, 
					whereby, 
					createdat 
				FROM 
					wallet 
				WHERE 
					$whereClause
				ORDER BY 
					createdat DESC
				LIMIT :limit OFFSET :offset;
			";

			$stmt = $this->db->prepare($sql);
			$params['limit'] = (int)$limit;
			$params['offset'] = (int)$offset;

			$stmt->execute($params);

			$results = [];
			while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
				$results[] = new Wallet($row);
			}

			if ($results) {
				$this->logger->info('Fetched transactions for filters from database', [
					'count' => count($results),
					'filters' => $args,
				]);
			} else {
				$this->logger->warning('No transactions found for filters in database', [
					'filters' => $args,
				]);
			}

			return $results;

		} catch (PDOException $e) {
			$this->logger->error('Database error occurred in loadWalletById', [
				'error' => $e->getMessage(),
			]);
			return false;
		}
	}

    public function loadById(string $userid): Wallet|false
    {
        $this->logger->info('WalletMapper.loadById started');

        $sql = "SELECT * FROM wallet WHERE userid = :userid";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['userid' => $userid]);
        $data = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($data !== false) {
            return new Wallet($data);
        }

        $this->logger->warning('No transaction found with', ['userid' => $userid]);

        return false;
    }

    public function loadLiquidityById(string $userid): float
    {
        $this->logger->info('WalletMapper.loadLiquidityById started');

        $sql = "SELECT SUM(numbers) AS currentliquidity FROM wallet WHERE userid = :userid";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['userid' => $userid]);
        $data = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($data !== false) {
			$this->logger->info('transaction found with', ['data' => $data]);
            return (float)$data['currentliquidity'];
        }

        $this->logger->warning('No transaction found with', ['userid' => $userid]);

        return 0.0;
    }

    public function insert(Wallet $wallet): Wallet
    {
        $this->logger->info('WalletMapper.insert started');

        $data = $wallet->getArrayCopy();

        $query = "INSERT INTO wallet (token, userid, postid, fromid, numbers, whereby, createdat) 
                  VALUES (:token, :userid, :postid, :fromid, :numbers, :whereby, :createdat)";

        try {
            $stmt = $this->db->prepare($query);

            // Explicitly bind each value
            $stmt->bindValue(':token', $data['token'], \PDO::PARAM_STR);
            $stmt->bindValue(':userid', $data['userid'], \PDO::PARAM_STR);
            $stmt->bindValue(':postid', $data['postid'], \PDO::PARAM_STR);
            $stmt->bindValue(':fromid', $data['fromid'], \PDO::PARAM_STR);
            $stmt->bindValue(':numbers', $data['numbers'], \PDO::PARAM_INT);
            $stmt->bindValue(':whereby', $data['whereby'], \PDO::PARAM_INT);
            $stmt->bindValue(':createdat', $data['createdat'], \PDO::PARAM_STR);

            $stmt->execute();

            $this->logger->info('Inserted new transaction into database', ['data' => $data]);

            return new Wallet($data);
        } catch (\PDOException $e) {
            $this->logger->error('Failed to insert transaction into database', [
                'data' => $data,
                'exception' => $e->getMessage(),
            ]);

            throw new \RuntimeException("Failed to insert wallet transaction: " . $e->getMessage());
        }
    }

    public function insertt(Wallett $wallet): Wallett
    {
        $this->logger->info('WalletMapper.insertt started');

        $data = $wallet->getArrayCopy();

        $query = "INSERT INTO wallett (userid, liquidity, liquiditq, updatedat, createdat) 
                  VALUES (:userid, :liquidity, :liquiditq, :updatedat, :createdat)";

        try {
            $stmt = $this->db->prepare($query);

            // Explicitly bind each value
            $stmt->bindValue(':userid', $data['userid'], \PDO::PARAM_STR);
            $stmt->bindValue(':liquidity', $data['liquidity'], \PDO::PARAM_STR);
            $stmt->bindValue(':liquiditq', $data['liquiditq'], \PDO::PARAM_INT);
            $stmt->bindValue(':updatedat', $data['updatedat'], \PDO::PARAM_STR);
            $stmt->bindValue(':createdat', $data['createdat'], \PDO::PARAM_STR);

            $stmt->execute();

            $this->logger->info('Inserted new transaction into database', ['data' => $data]);

            return new Wallett($data);
        } catch (\PDOException $e) {
            $this->logger->error('Failed to insert transaction into database', [
                'data' => $data,
                'exception' => $e->getMessage(),
            ]);

            throw new \RuntimeException("Failed to insert wallett transaction: " . $e->getMessage());
        }
    }

    // CRYPTO GENERATOR
    public function crypto_rand_secure(int $min, int $max): int
    {
        $range = $max - $min;
        if ($range < 0) return $min;
        $log = \log($range, 2);
        $bytes = (int)($log / 8) + 1;
        $bits = (int)$log + 1;
        $filter = (int)(1 << $bits) - 1;
        do {
            $rnd = \hexdec(\bin2hex(\openssl_random_pseudo_bytes($bytes)));
            $rnd = $rnd & $filter;
        } while ($rnd >= $range);
        return $min + $rnd;
    }

    public function getToken(int $length = 32): string
    {
        $token = '';
        $codeAlphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
        for ($i = 0; $i < $length; $i++) {
            $token .= $codeAlphabet[$this->crypto_rand_secure(0, \strlen($codeAlphabet))];
        }
        return $token;
    }

    public function getPeerToken(): string
    {
        $x = 0;
        $token = $this->getToken(12);
        do {
            $stmt = $this->db->prepare('SELECT token FROM wallet WHERE token = ?');
            $stmt->execute([$token]);
            $res = $stmt->fetch(PDO::FETCH_ASSOC);
        } while ($res && $x++ < 100);
        return $token;
    }

	public function fetchPostUsersID(): array|false
	{
		$this->logger->info('WalletMapper.fetchPostUsersID started');

		try {
			$sql = "SELECT postid, userid 
					FROM posts 
					WHERE feedid IS NULL";
			$stmt = $this->db->query($sql);
			$data = $stmt->fetchAll(PDO::FETCH_ASSOC);
		} catch (\Exception $e) {
			$this->logger->error('Error fetching post user IDs', ['exception' => $e]);
			return false;
		}

		return $data;
	}

	public function fetchWinsLog(string $userid, ?array $args = []): array
	{
		$this->logger->info('WalletMapper.fetchWinsLog started');

		if (empty($userid)) {
			$this->logger->error('UserID is missing');
			return $this->respondWithError('UserID is required.');
		}

		$offset = max((int)($args['offset'] ?? 0), 0);
		$limit = min(max((int)($args['limit'] ?? 10), 1), 20);
		$date = $args['day'] ?? null;

		$dateFilters = [
			"D0" => "lw.createdat::date = CURRENT_DATE",
			"D1" => "lw.createdat::date = CURRENT_DATE - INTERVAL '1 day'",
			"D2" => "lw.createdat::date = CURRENT_DATE - INTERVAL '2 day'",
			"D3" => "lw.createdat::date = CURRENT_DATE - INTERVAL '3 day'",
			"D4" => "lw.createdat::date = CURRENT_DATE - INTERVAL '4 day'",
			"D5" => "lw.createdat::date = CURRENT_DATE - INTERVAL '5 day'",
			"W0" => "DATE_PART('week', lw.createdat) = DATE_PART('week', CURRENT_DATE) AND EXTRACT(YEAR FROM lw.createdat) = EXTRACT(YEAR FROM CURRENT_DATE)",
			"M0" => "TO_CHAR(lw.createdat, 'YYYY-MM') = TO_CHAR(CURRENT_DATE, 'YYYY-MM')",
			"Y0" => "EXTRACT(YEAR FROM lw.createdat) = EXTRACT(YEAR FROM CURRENT_DATE)"
		];

		$dateCondition = $dateFilters['D0'];
		if ($date && array_key_exists($date, $dateFilters)) {
			$dateCondition = $dateFilters[$date];
		}

		try {
			$sql = "
				SELECT 
					u.username AS from,
					lw.token,
					lw.userid,
					lw.postid, 
					lw.nmbers, 
					lw.whereby AS action, 
					lw.createdat
				FROM logwins lw
				JOIN users u ON lw.fromid = u.uid
				WHERE $dateCondition AND lw.userid = :userid
				ORDER BY lw.postid DESC
				LIMIT :limit OFFSET :offset
			";

			$stmt = $this->db->prepare($sql);
			$stmt->bindValue(':userid', $userid, PDO::PARAM_STR);
			$stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
			$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
			$stmt->execute();
			$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
			$this->logger->info('WalletMapper.fetchWinsLog rows: ', ['rows' => $rows]);

			if (empty($rows)) {
				$success = [
					'status' => 'success',
					'counter' => 0,
					'ResponseCode' => 'No records found for the specified date.',
					'affectedRows' => [],
				];

				return $success;
			}
		} catch (\Exception $e) {
			return $this->respondWithError($e->getMessage());
		}

		$success = [
			'status' => 'success',
			'counter' => count($rows),
			'ResponseCode' => 'Records found for the specified date.',
			'affectedRows' => $rows
		];

		return $success;
	}

	protected function insertWinToLog(string $userId, array $args): bool
	{
		$this->logger->info('WalletMapper.insertWinToLog started');

		if (empty($args['postid'])) {
			$this->logger->error('postid is missing', ['userId' => $userId, 'args' => $args]);
			return false;
		}

		$sql = "INSERT INTO logwins 
				(token, userid, postid, fromid, gems, numbers, numbersq, whereby, createdat) 
				VALUES 
				(:token, :userid, :postid, :fromid, :gems, :numbers, :numbersq, :whereby, :createdat)";

		try {
			$stmt = $this->db->prepare($sql);

			// Bind values explicitly
			$stmt->bindValue(':token', $this->getPeerToken(), PDO::PARAM_STR);
			$stmt->bindValue(':userid', $userId, PDO::PARAM_STR);
			$stmt->bindValue(':postid', $args['postid'], PDO::PARAM_STR);
			$stmt->bindValue(':fromid', $args['fromid'], PDO::PARAM_STR);
			$stmt->bindValue(':gems', $args['gems'], PDO::PARAM_STR);
			$stmt->bindValue(':numbers', $args['numbers'], PDO::PARAM_STR);
			$stmt->bindValue(':numbersq', $this->decimalToQ64_96($args['numbers']), PDO::PARAM_STR); // 29 char precision
			$stmt->bindValue(':whereby', $args['whereby'], PDO::PARAM_INT);
			$stmt->bindValue(':createdat', $args['createdat'], PDO::PARAM_STR);

			$stmt->execute();
			$this->saveWalletEntry($userId, $args['numbers']);

			$this->logger->info('Inserted into logwins successfully', [
				'userId' => $userId,
				'postid' => $args['postid']
			]);

			return true;
		} catch (\PDOException $e) {
			$this->logger->error('Failed to insert into logwins', [
				'userId' => $userId,
				'exception' => $e->getMessage()
			]);

			return false;
		}
	}

    public function callGlobalWins(): array
    {
        if (!TABLESTOGEMS) {
            return $this->respondWithError('TABLESTOGEMS');
        }

        $wins = [
            ['table' => 'user_post_views', 'winType' => (int)VIEW_, 'factor' => (float)RECEIVEPOSTVIEW],
            ['table' => 'user_post_likes', 'winType' => (int)LIKE_, 'factor' => (float)RECEIVELIKE],
            ['table' => 'user_post_dislikes', 'winType' => (int)DISLIKE_, 'factor' => -(float)RECEIVEDISLIKE],
            ['table' => 'user_post_comments', 'winType' => (int)COMMENT_, 'factor' => (float)RECEIVECOMMENT]
        ];

        $totalInserts = 0;
        $winSources = []; 

        foreach ($wins as $win) {
            $result = $this->setGlobalWins($win['table'], $win['winType'], $win['factor']);
            
            if ($result['status'] === 'error') {
                $this->logger->error("Failed to set global wins for {$win['table']}");
            }

            if (isset($result['insertCount']) && $result['insertCount'] > 0) {
                $totalInserts += $result['insertCount'];

                $tablePart = strtolower(substr($win['table'], strrpos($win['table'], '_') + 1));
                $winSources[] = $tablePart;
            }
        }

        if ($totalInserts > 0) {
            $sourceList = implode(', ', $winSources);
            $success = ['status' => 'success', 'ResponseCode' => "Successfully added $totalInserts gems across wins from: $sourceList."];
			return $success;
        }

        $success = ['status' => 'success', 'ResponseCode' => 'No gems added to database across all wins.'];
		return $success;
    }

    protected function setGlobalWins(string $tableName, int $winType, float $factor): array
    {
        \ignore_user_abort(true);

        $this->logger->info('WalletMapper.setGlobalWins started');

        try {
            $sql = "SELECT s.userid, s.postid, s.createdat, p.userid as poster 
                    FROM $tableName s 
                    INNER JOIN posts p ON s.postid = p.postid AND s.userid != p.userid 
                    WHERE s.collected = 0";
            $stmt = $this->db->query($sql);
            $entries = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Exception $e) {
            $this->logger->error('Error fetching entries for ' . $tableName, ['exception' => $e]);
            return $this->respondWithError($e->getMessage());
        }

        $insertCount = 0; 

        if (!empty($entries)) {
            $entry_ids = array_map(fn($row) => isset($row['userid']) && is_string($row['userid']) ? $this->db->quote($row['userid']) : null, $entries);
            $entry_ids = array_filter($entry_ids);

            foreach ($entries as $row) {
                try {
                    $sql = "INSERT INTO gems (userid, postid, fromid, gems, whereby, createdat) 
                            VALUES (:userid, :postid, :fromid, :gems, :whereby, :createdat)";
                    $stmt = $this->db->prepare($sql);
                    $stmt->execute([
                        ':userid' => $row['poster'],
                        ':postid' => $row['postid'],
                        ':fromid' => $row['userid'],
                        ':gems' => $factor,
                        ':whereby' => $winType,
                        ':createdat' => $row['createdat']
                    ]);

                    $insertCount++;
                } catch (\Exception $e) {
                    $this->logger->error('Error inserting into gems for ' . $tableName, ['exception' => $e]);
                    return $this->respondWithError($e->getMessage());
                }
            }

            if (!empty($entry_ids)) {
                try {
                    $quoted_ids = implode(',', $entry_ids);
                    $sql = "UPDATE $tableName SET collected = 1 WHERE userid IN ($quoted_ids)";
                    $this->db->query($sql);
                } catch (\Exception $e) {
                    $this->logger->error('Error updating collected status for ' . $tableName, ['exception' => $e]);
                    return $this->respondWithError($e->getMessage());
                }
            }
        }

        return [
            'status' => 'success',
            'insertCount' => $insertCount
        ];
    }

	public function getTimeSorted()
	{
		try {

			$sql = "
				SELECT 
                COUNT(CASE WHEN createdat::date = CURRENT_DATE THEN 1 END) AS d0,
                COUNT(CASE WHEN createdat::date = CURRENT_DATE - INTERVAL '1 day' THEN 1 END) AS d1,
                COUNT(CASE WHEN createdat::date = CURRENT_DATE - INTERVAL '2 day' THEN 1 END) AS d2,
                COUNT(CASE WHEN createdat::date = CURRENT_DATE - INTERVAL '3 day' THEN 1 END) AS d3,
                COUNT(CASE WHEN createdat::date = CURRENT_DATE - INTERVAL '4 day' THEN 1 END) AS d4,
                COUNT(CASE WHEN createdat::date = CURRENT_DATE - INTERVAL '5 day' THEN 1 END) AS d5,
                COUNT(CASE WHEN DATE_PART('week', createdat) = DATE_PART('week', CURRENT_DATE) AND EXTRACT(YEAR FROM createdat) = EXTRACT(YEAR FROM CURRENT_DATE) THEN 1 END) AS w0,
                COUNT(CASE WHEN TO_CHAR(createdat, 'YYYY-MM') = TO_CHAR(CURRENT_DATE, 'YYYY-MM') THEN 1 END) AS m0,
                COUNT(CASE WHEN EXTRACT(YEAR FROM createdat) = EXTRACT(YEAR FROM CURRENT_DATE) THEN 1 END) AS y0
				FROM gems WHERE collected = 0
			";
			
			$stmt = $this->db->query($sql);
			$entries = $stmt->fetch(PDO::FETCH_ASSOC);
			$this->logger->info('fetching entries for ', ['entries' => $entries]);
		} catch (\Exception $e) {
			$this->logger->error('Error fetching entries for ', ['exception' => $e->getMessage()]);
			return $this->respondWithError($e->getMessage());
		}

		$success = [
			'status' => 'success',
			'ResponseCode' => 'Gems data prepared successfully.',
			'affectedRows' => $entries
		];

		return $success;

	}

	public function getTimeSortedMatch(string $day = 'D0'): array
	{
		\ignore_user_abort(true);

		$this->logger->info('WalletMapper.getTimeSortedMatch started');

		$dayOptions = [
			"D0" => "createdat::date = CURRENT_DATE",
			"D1" => "createdat::date = CURRENT_DATE - INTERVAL '1 day'",
			"D2" => "createdat::date = CURRENT_DATE - INTERVAL '2 day'",
			"D3" => "createdat::date = CURRENT_DATE - INTERVAL '3 day'",
			"D4" => "createdat::date = CURRENT_DATE - INTERVAL '4 day'",
			"D5" => "createdat::date = CURRENT_DATE - INTERVAL '5 day'",
			"W0" => "DATE_PART('week', createdat) = DATE_PART('week', CURRENT_DATE) AND EXTRACT(YEAR FROM createdat) = EXTRACT(YEAR FROM CURRENT_DATE)",
			"M0" => "TO_CHAR(createdat, 'YYYY-MM') = TO_CHAR(CURRENT_DATE, 'YYYY-MM')",
			"Y0" => "EXTRACT(YEAR FROM createdat) = EXTRACT(YEAR FROM CURRENT_DATE)"
		];

		if (!array_key_exists($day, $dayOptions)) {
			return $this->respondWithError("Invalid day parameter.");
		}

		$whereCondition = $dayOptions[$day];

		$sql = "
			WITH user_sums AS (
				SELECT 
					userid,
					GREATEST(SUM(gems), 0) AS total_numbers
				FROM gems
				WHERE {$whereCondition} AND collected = 0
				GROUP BY userid
			),
			total_sum AS (
				SELECT SUM(total_numbers) AS overall_total FROM user_sums
			)
			SELECT 
				g.userid,
				g.gemid,
				g.postid,
				g.fromid,
				g.gems,
				g.whereby,
				g.createdat,
				us.total_numbers,
				(SELECT SUM(total_numbers) FROM user_sums) AS overall_total,
				(us.total_numbers * 100.0 / ts.overall_total) AS percentage
			FROM gems g
			JOIN user_sums us ON g.userid = us.userid
			CROSS JOIN total_sum ts
			WHERE us.total_numbers > 0 AND g.collected = 0;
		";

		try {
			$stmt = $this->db->query($sql);
			$data = $stmt->fetchAll(PDO::FETCH_ASSOC);
			$this->logger->info('fetching data for ', ['data' => $data]);
		} catch (\Exception $e) {
			$this->logger->error('Error reading gems', ['exception' => $e->getMessage()]);
			return $this->respondWithError($e->getMessage());
		}

		if (empty($data)) {
			return $this->respondWithError('No data found.');
		}

		$totalGems = $data[0]['overall_total'] ?? 0;
		$gemsintoken = Rechnen::calculate_token_preis(DAILY_NUMBER_TOKEN, $totalGems);
		$bestatigung = $totalGems * $gemsintoken;

		$args = [];

		$args['winstatus'] = [
			'totalGems' => $totalGems,
			'gemsintoken' => $gemsintoken,
			'bestatigung' => $bestatigung
		];

		foreach ($data as $row) {
			$userId = (string)$row['userid'];

			if (!isset($args[$userId])) {
				$args[$userId] = [
					'userid' => $userId,
					'gems' => $row['total_numbers'],
					'tokens' => $row['total_numbers'] * $gemsintoken,
					'percentage' => $row['percentage'],
					'details' => []
				];
			}

			$rowgems2token = $row['gems'] * $gemsintoken;

			$args[$userId]['details'][] = [
				'userid' => $row['userid'],
				'postid' => $row['postid'],
				'fromid' => $row['fromid'],
				'gems' => $row['gems'],
				'numbers' => $rowgems2token,
				'whereby' => $row['whereby'],
				'createdat' => $row['createdat']
			];

			$this->insertWinToLog($userId, end($args[$userId]['details']));
		}

        if (!empty($data)) {
            try {
                $gemIds = array_column($data, 'gemid');
                $quotedGemIds = array_map(fn($gemId) => $this->db->quote($gemId), $gemIds);

                //$this->db->query('UPDATE gems SET collected = 1 WHERE gemid IN (' . \implode(',', $quotedGemIds) . ')');

            } catch (\Exception $e) {
                $this->logger->error('Error updating gems or liquidity', ['exception' => $e->getMessage()]);
                return $this->respondWithError($e->getMessage());
            }

			return [
				'status' => 'success',
				'counter' => count($args),
				'ResponseCode' => 'Records found for ' . $day,
				'affectedRows' => ['data' => array_values($args), 'totalGems' => $totalGems]
			];
        }
	}

    public function getPercentBeforeTransaction(string $userId, int $tokenAmount) : array
    {
        $this->logger->info('WalletMapper.getPercentBeforeTransaction started');

        $account = $this->getUserWalletBalances($userId);
		$createdat = (new \DateTime())->format('Y-m-d H:i:s.u');

        if ($account <= $tokenAmount) {
            $this->logger->warning('Insufficient funds for the transaction', ['userid' => $userId, 'accountBalance' => $account, 'tokenAmount' => $tokenAmount]);
            return $this->respondWithError('Don\'t have sufficient funds for this transaction');
        }

        try {
            $query = "SELECT invited FROM users_info WHERE userid = :userid AND invited IS NOT NULL";
            $stmt = $this->db->prepare($query);
            $stmt->execute(['userid' => $userId]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$result || !$result['invited']) {
                $this->logger->warning('No inviter found for the given user', ['userid' => $userId]);
                return $this->respondWithError('No inviter found for the given user.');
            }

            $inviterId = $result['invited'];
            $this->logger->info('Inviter found', ['inviterId' => $inviterId]);

            // Beginn Deduct 1% from the user's liquidity
            $percent = round((float)$tokenAmount * 0.01, 2);
            $tosend = round((float)$tokenAmount - $percent, 2);

            // Deduct total amount from the user's wallet
			//$result = $this->saveWalletEntry($userId, -abs($tokenAmount));

			if ($result) {

				$args = [
					'postid' => POOLPOST,
					'fromid' => null,
					'numbers' => -abs($tokenAmount),
					'whereby' => INVITATION_,
					'createdat' => $createdat,
				];

				$this->insertWinToLog($userId, $args);
			}

            // Add 1% to the inviter's wallet
			//$result = $this->saveWalletEntry($inviterId, abs($percent));

			if ($result) {

				$args = [
					'postid' => POOLPOST,
					'fromid' => null,
					'numbers' => abs($percent),
					'whereby' => INVITATION_,
					'createdat' => $createdat,
				];

				$this->insertWinToLog($inviterId, $args);
			}

            return [
                'status' => 'success', 
                'ResponseCode' => "Transaction successful: 1% transferred to inviter.",
                'affectedRows' => [
                    'inviterId' => $inviterId,
                    'tosend' => $tosend,
                    'percentTransferred' => $percent
                ]
            ];

        } catch (PDOException $pdoe) {
            $this->db->rollBack();
            $this->logger->error('PDOException occurred during transaction', ['exception' => $pdoe]);
            return $this->respondWithError("Database error: " . $pdoe->getMessage());

        } catch (Exception $e) {
            $this->db->rollBack();
            $this->logger->error('Exception occurred during transaction', ['exception' => $e]);
            return $this->respondWithError("Transaction failed: " . $e->getMessage());
        }

        return $this->respondWithError('Unknown error occurred.');
    }

	public function deductFromWallets(string $userId, ?array $args = []): array
	{
		$this->logger->info('WalletMapper.deductFromWallet started');

		$postId = $args['postid'] ?? POOLPOST;
		$art = $args['art'] ?? null;
		$fromId = $args['fromid'] ?? LIQUIDITYPOOL;

		$mapping = [
			2 => ['price' => PRICELIKE, 'whereby' => LIKE_, 'text' => 'Buy like'],
			3 => ['price' => PRICEDISLIKE, 'whereby' => DISLIKE_, 'text' => 'Buy dislike'],
			4 => ['price' => PRICECOMMENT, 'whereby' => COMMENT_, 'text' => 'Buy comment'],
			5 => ['price' => PRICEPOST, 'whereby' => POST_, 'text' => 'Buy post'],
		];

		if (!isset($mapping[$art])) {
			$this->logger->error('Invalid art type provided.', ['art' => $art]);
			return $this->respondWithError('Invalid action type.');
		}

		$price = $mapping[$art]['price'];
		$whereby = $mapping[$art]['whereby'];
		$text = $mapping[$art]['text'];

		$currentBalance = $this->getUserWalletBalances($userId);

		if ($currentBalance < $price) {
			$this->logger->warning('Insufficient balance.', [
				'userId' => $userId,
				'Balance' => $currentBalance,
				'requiredAmount' => $price,
			]);
			return $this->respondWithError('Insufficient_balance: Not enough balance to perform this action.');
		}

		$args = [
			'postid' => $postId,
			'fromid' => null,
			'gems' => null,
			'numbers' => -abs($price),
			'whereby' => $whereby,
			'createdat' => (new \DateTime())->format('Y-m-d H:i:s.u'),
		];

		try {
			$results = $this->insertWinToLog($userId, $args);
			if ($results === false) {
				return $this->respondWithError('Failed to deduct from wallet.');
			}

			//$results = $this->saveWalletEntry($userId, -abs($price));

			$this->logger->info('Wallet deduction successful.', [
				'userId' => $userId,
				'postId' => $postId,
				'gems' => null,
				'numbers' => -abs($price),
				'whereby' => $whereby,
			]);

			return [
				'status' => 'success',
				'ResponseCode' => 'Wallet deduction successful: ' . $text,
				'affectedRows' => [
					'userId' => $userId,
					'postId' => $postId,
					'numbers' => -abs($price),
					'whereby' => $whereby,
				],
			];
		} catch (\Exception $e) {
			$this->logger->error('Failed to deduct from wallet.', [
				'exception' => $e->getMessage(),
				'params' => [
					'userId' => $userId,
					'postId' => $postId,
					'numbers' => -abs($price),
					'whereby' => $whereby,
				],
			]);
			return $this->respondWithError('Failed to deduct from wallet:' . $e->getMessage());
		}
	}

	public function getUserWalletBalance(string $userId): float
	{
		$this->logger->info('WalletMapper.getUserWalletBalance started');

		$query = "SELECT COALESCE(SUM(numbers), 0) as balance 
				  FROM wallet 
				  WHERE userid = :userId";

		try {
			$stmt = $this->db->prepare($query);
			$stmt->execute(['userId' => $userId]);
			$balance = $stmt->fetchColumn();

			$this->logger->info('Fetched Wallet Stand', ['balance' => $balance]);

			return (float) $balance;
		} catch (\PDOException $e) {
			$this->logger->error('Database error in getUserWalletBalance: ' . $e->getMessage());
			throw new \RuntimeException('Unable to fetch wallet stand');
		}
	}

	public function getUserWalletBalances(string $userId): float
	{
		$this->logger->info('WalletMapper.getUserWalletBalance started');

		$query = "SELECT COALESCE(liquidity, 0) AS balance 
				  FROM wallett 
				  WHERE userid = :userId";

		try {
			$stmt = $this->db->prepare($query);
			$stmt->bindValue(':userId', $userId, \PDO::PARAM_STR);
			$stmt->execute();
			$balance = $stmt->fetchColumn();

			$this->logger->info('Fetched wallet balance', ['balance' => $balance]);

			return (float) $balance;
		} catch (\PDOException $e) {
			$this->logger->error('Database error in getUserWalletBalance: ' . $e->getMessage());
			throw new \RuntimeException('Unable to fetch wallet balance');
		}
	}

	public function updateUserLiquidity(string $userId, float $liquidity): bool
	{
		try {
			$totalNumbers = $liquidity ?? 0;

			$sqlUpdate = "UPDATE users_info SET liquidity = :liquidity, updatedat = CURRENT_TIMESTAMP WHERE userid = :userid";
			$stmt = $this->db->prepare($sqlUpdate);
			$stmt->execute([':liquidity' => $totalNumbers, ':userid' => $userId]);

			return true;
		} catch (\Exception $e) {
			$this->logger->error('Error updating liquidity', ['exception' => $e->getMessage(), 'userid' => $userId]);
			return false;
		}
	}

	public function saveWalletEntry(string $userId, float $liquidity): float
	{
		$this->logger->info('WalletMapper.saveWalletEntry started', ['userId' => $userId, 'liquidity' => $liquidity]);

		try {
			$this->db->beginTransaction();

			// Check if userId exists directly in the database
			$query = "SELECT 1 FROM wallett WHERE userid = :userid";
			$stmt = $this->db->prepare($query);
			$stmt->bindValue(':userid', $userId, \PDO::PARAM_STR);
			$stmt->execute();
			$userExists = $stmt->fetchColumn(); // Returns 1 if user exists, false otherwise

			if (!$userExists) {
				// Insert new entry
				$newLiquidity = $liquidity;
				$liquiditq = $this->decimalToQ64_96($newLiquidity);

				$query = "INSERT INTO wallett (userid, liquidity, liquiditq, updatedat)
						  VALUES (:userid, :liquidity, :liquiditq, :updatedat)";
				$stmt = $this->db->prepare($query);
				$stmt->bindValue(':userid', $userId, \PDO::PARAM_STR);
				$stmt->bindValue(':liquidity', $newLiquidity, \PDO::PARAM_STR);
				$stmt->bindValue(':liquiditq', $liquiditq, \PDO::PARAM_STR);
				$stmt->bindValue(':updatedat', (new \DateTime())->format('Y-m-d H:i:s.u'), \PDO::PARAM_STR);

				$stmt->execute();
			} else {
				// Fetch current balance
				$currentBalance = $this->getUserWalletBalanceS($userId);
				$newLiquidity = $currentBalance + $liquidity;
				$liquiditq = $this->decimalToQ64_96($newLiquidity);

				// Update existing entry
				$query = "UPDATE wallett
						  SET liquidity = :liquidity, liquiditq = :liquiditq, updatedat = :updatedat
						  WHERE userid = :userid";
				$stmt = $this->db->prepare($query);
				$stmt->bindValue(':userid', $userId, \PDO::PARAM_STR);
				$stmt->bindValue(':liquidity', $newLiquidity, \PDO::PARAM_STR);
				$stmt->bindValue(':liquiditq', $liquiditq, \PDO::PARAM_STR);
				$stmt->bindValue(':updatedat', (new \DateTime())->format('Y-m-d H:i:s.u'), \PDO::PARAM_STR);

				$stmt->execute();
			}

			$this->db->commit();
			$this->logger->info('Wallet entry saved successfully', ['newLiquidity' => $newLiquidity]);
			// update users_info
			$this->updateUserLiquidity($userId, $newLiquidity);

			return $newLiquidity;
		} catch (\PDOException $e) {
			$this->db->rollBack();
			$this->logger->error('Database error in saveWalletEntry: ' . $e->getMessage());
			throw new \RuntimeException('Unable to save wallet entry');
		}
	}

    // Fetch the ranked posts
    public function getRankedPosts($limit = 10) {
        $sql = "
            WITH post_totals AS (
                SELECT
                    w.postid,
                    SUM(w.numbers) AS total_numbers
                FROM logwins w
                GROUP BY w.postid
            ),
            ranked_posts AS (
                SELECT
                    pt.postid,
                    pt.total_numbers,
                    pi.likes,
                    pi.dislikes,
                    pi.views,
                    pi.saves,
                    pi.shares,
                    pi.comments,
                    p.title,
                    p.contenttype,
                    RANK() OVER (ORDER BY pt.total_numbers DESC) AS rank
                FROM post_totals pt
                LEFT JOIN post_info pi ON pt.postid = pi.postid
                LEFT JOIN posts p ON pt.postid = p.postid
            )
            SELECT *
            FROM ranked_posts
            WHERE rank <= :limit
            ORDER BY rank ASC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':limit' => $limit]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    protected function decimalToQ64_96($value) {
        // Multiply the decimal number by 2^96 and truncate
        return bcmul($value, Q64_96_SCALE, 0); // Scale factor with no decimals
    }

    protected function q64_96ToDecimal($qValue) {
        // Divide the Q64.96 number by 2^96
        return bcdiv($qValue, Q64_96_SCALE, 15); // Precision up to 15 decimal places
    }

    protected function addQ64_96($qValue1, $qValue2) {
        // Add two Q64.96 integers
        return bcadd($qValue1, $qValue2);
    }

    public function deleteAllInWallet(): array
    {
        try {
            $this->logger->info('Deleting all records from wallet table.');
            $sql = "DELETE FROM wallet";
            $this->db->query($sql);
            $success = ['status' => 'success', 'ResponseCode' => 'All records deleted from wallet.'];
			return $success;
        } catch (\Exception $e) {
            $this->logger->error('Error deleting all records from wallet', ['exception' => $e]);
            return $this->respondWithError($e->getMessage());
        }
    }

    public function deleteAllInGems(): array
    {
        try {
            $this->logger->info('Deleting all records from gems table and resetting gemid.');
            $sql = "TRUNCATE TABLE gems RESTART IDENTITY";
            $this->db->query($sql);
            $success = ['status' => 'success', 'ResponseCode' => 'All records deleted from gems and gemid reset.'];
			return $success;
        } catch (\Exception $e) {
            $this->logger->error('Error deleting all records from gems and resetting gemid', ['exception' => $e]);
            return $this->respondWithError($e->getMessage());
        }
    }

    public function deleteAllInLogWins(): array
    {
        try {
            $this->logger->info('Deleting all records from logwins table and resetting gemid.');
            $sql = "TRUNCATE TABLE logwins RESTART IDENTITY";
            $this->db->query($sql);
            $success = ['status' => 'success', 'ResponseCode' => 'All records deleted from logwins and logid reset.'];
			return $success;
        } catch (\Exception $e) {
            $this->logger->error('Error deleting all records from logwins and resetting logid', ['exception' => $e]);
            return $this->respondWithError($e->getMessage());
        }
    }

    public function resetAllCollected(): array
    {
        $tables = ['user_post_comments', 'user_post_saves', 'user_post_views', 'user_post_reports', 'user_post_shares', 'user_post_likes', 'user_post_dislikes', 'users_info', 'gems'];
        $responses = [];

        foreach ($tables as $table) {
            try {
                $this->logger->info("Resetting collected field to 0 for $table where collected = 1.");
                $sql = "UPDATE $table SET collected = 0 WHERE collected = 1";
                $stmt = $this->db->query($sql);
                $affectedRows = $stmt->rowCount();
                $responses[$table] = "Collected field reset for $affectedRows rows in $table.";
                //return ['status' => 'success', 'ResponseCode' => 'Successfully Reseted'];
            } catch (\Exception $e) {
                $this->logger->error("Error resetting collected field for $table", ['exception' => $e]);
                $responses[$table] = 'Error: ' . $e->getMessage();
                //return $this->respondWithError($e->getMessage());
            }
        }

        $success = ['status' => 'success', 'ResponseCode' => 'Successfully Reseted'];
		return $success;
    }

}
