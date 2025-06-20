<?php

namespace Fawaz\Database;

use PDO;
use Fawaz\App\Advertisements;
use Psr\Log\LoggerInterface;

class AdvertisementMapper
{
    public function __construct(protected LoggerInterface $logger, protected PDO $db)
    {
    }

    // Check if userId and currentUserId are same
    public function isSameUser(string $userId, string $currentUserId): bool
    {
        return $userId === $currentUserId;
    }

    // Get all data cols from advertisements table.
    public function fetchAll(int $offset, int $limit): array
    {
        $this->logger->info("AdvertisementMapper.fetchAll started");

        $sql = "SELECT advertisementid, userid, status, timestart, timeend FROM advertisements_log ORDER BY status DESC, createdat DESC LIMIT :limit OFFSET :offset";

        try {
            $stmt = $this->db->prepare($sql);
            $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, \PDO::PARAM_INT);
            $stmt->execute();

            $results = array_map(fn($row) => new Advertisements($row), $stmt->fetchAll(\PDO::FETCH_ASSOC));

            $this->logger->info(
                $results ? "Fetched advertisements successfully" : "No advertisements found",
                ['count' => count($results)]
            );

            return $results;
        } catch (\Throwable $e) {
            $this->logger->error("Error fetching advertisements from database", [
                'error' => $e->getMessage(),
            ]);
            return [];
        }
    }

	// Check if the Post exist & userId is the same as the post owner.
	public function isCreator(string $advertisementId, string $currentUserId): bool
	{
		$this->logger->info("AdvertisementMapper.isCreator started");

		$sql = "SELECT 1 FROM posts WHERE postid = :advertisementId AND userid = :currentUserId LIMIT 1";
		$stmt = $this->db->prepare($sql);
		$stmt->bindValue(':advertisementId', $advertisementId, \PDO::PARAM_STR);
		$stmt->bindValue(':currentUserId', $currentUserId, \PDO::PARAM_STR);
		$stmt->execute();

		return (bool) $stmt->fetchColumn();
	}

	// Check if the post advertisement exists in the advertisements table.
	public function isAdvertisementIdExist(string $advertisementId): bool
	{
		$this->logger->info("AdvertisementMapper.isAdvertisementIdExist started");

		$sql = "SELECT 1 FROM advertisements WHERE advertisementid = :advertisementId LIMIT 1";
		$stmt = $this->db->prepare($sql);
		$stmt->bindValue(':advertisementId', $advertisementId, \PDO::PARAM_STR);
		$stmt->execute();

		return (bool) $stmt->fetchColumn();
	}

    // Create a Post Advertisement with Loging
	public function insert(Advertisements $post): Advertisements
	{
		$this->logger->info("AdvertisementMapper.insert started");

		$data = $post->getArrayCopy();

		// SQL-Statements für beide Tabellen
		$query1 = "INSERT INTO advertisements 
				   (advertisementid, userid, status, timestart, timeend)
				   VALUES 
				   (:advertisementid, :userid, :status, :timestart, :timeend)";

		$query2 = "INSERT INTO advertisements_log 
				   (advertisementid, userid, status, timestart, timeend)
				   VALUES 
				   (:advertisementid, :userid, :status, :timestart, :timeend)";

		try {
			$this->db->beginTransaction();

			// Statement 1
			$stmt1 = $this->db->prepare($query1);
			if (!$stmt1) {
				throw new \RuntimeException("SQL prepare() failed: " . implode(", ", $this->db->errorInfo()));
			}

			foreach (['advertisementid', 'userid', 'status', 'timestart', 'timeend'] as $key) {
				$stmt1->bindValue(':' . $key, $data[$key], \PDO::PARAM_STR);
			}

			$stmt1->execute();

			// Statement 2
			$stmt2 = $this->db->prepare($query2);
			if (!$stmt2) {
				throw new \RuntimeException("SQL prepare() failed: " . implode(", ", $this->db->errorInfo()));
			}

			foreach (['advertisementid', 'userid', 'status', 'timestart', 'timeend'] as $key) {
				$stmt2->bindValue(':' . $key, $data[$key], \PDO::PARAM_STR);
			}

			$stmt2->execute();

			$this->db->commit();

			$this->logger->info("Inserted new PostAdvertisement into both tables");
			return new Advertisements($data);

		} catch (\Throwable $e) {
			$this->db->rollBack();

			$this->logger->error(
				"PostMapper.insertAdvertisement: Exception occurred while inserting",
				['error' => $e->getMessage()]
			);

			throw new \RuntimeException("Failed to insert PostAdvertisement: " . $e->getMessage());
		}
	}

	public function convertEuroToTokens(float $euroAmount): array
	{
		$this->logger->info('AdvertisementMapper.convertEuroToTokens started', ['euroAmount' => $euroAmount]);

		$tokenPrice = 0.01; // Fixed price: 1 cent
		$tokens = $euroAmount / $tokenPrice;

		$response = [
			'status' => 'success',
			'ResponseCode' => 11400,
			'affectedRows' => [
				'InputEUR' => round($euroAmount, 2),
				'TokenPriceFixedEUR' => $tokenPrice,
				'TokenAmount' => floor($tokens),
			]
		];

		$this->logger->info('convertEuroToTokens response', ['response' => $response]);
		return $response;
	}

	public function convertTokensToEuro(int $tokenAmount): array
	{
		$this->logger->info('AdvertisementMapper.convertTokensToEuro started', ['tokenAmount' => $tokenAmount]);

		$tokenPrice = 0.01; // Fixed price: 1 cent
		$euroValue = $tokenAmount * $tokenPrice;

		$response = [
			'status' => 'success',
			'ResponseCode' => 11410,
			'affectedRows' => [
				'TokenAmount' => $tokenAmount,
				'TokenPriceFixedEUR' => $tokenPrice,
				'TotalEUR' => round($euroValue, 2),
			]
		];

		$this->logger->info('convertTokensToEuro response:', ['response' => $response]);
		return $response;
	}
}
