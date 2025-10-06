<?php
declare(strict_types=1);

namespace Fawaz\Database;

use PDO;
use Fawaz\App\Mcap;
use Fawaz\Utils\ResponseHelper;
use Psr\Log\LoggerInterface;

class McapMapper
{
    use ResponseHelper;
    
    public function __construct(protected LoggerInterface $logger, protected PDO $db)
    {
    }

    public function fetchAll(int $offset, int $limit): array
    {
        $this->logger->debug("McapMapper.fetchAll started");

        $sql = "SELECT * FROM mcap ORDER BY capid DESC LIMIT :limit OFFSET :offset";

        try {
            $stmt = $this->db->prepare($sql);
            $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, \PDO::PARAM_INT);
            $stmt->execute();

            $results = array_map(fn($row) => new Mcap($row), $stmt->fetchAll(\PDO::FETCH_ASSOC));

            $this->logger->info(
                $results ? "Fetched capid successfully" : "No capid found",
                ['count' => count($results)]
            );

            return $results;
        } catch (\PDOException $e) {
            $this->logger->error("Error fetching capid from database", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return [];
        }
    }

    public function loadLastId(): Mcap|false
    {
        $this->logger->debug("McapMapper.loadLastId started");

        try {
            $sql = "SELECT * FROM mcap ORDER BY createdat DESC LIMIT 1";
            $stmt = $this->db->query($sql);

            $data = $stmt->fetch(\PDO::FETCH_ASSOC);
            $this->logger->info("McapMapper.mcap found", ['data' => $data]);

            if ($data !== false) {
                return new Mcap($data);
            }

            $this->logger->warning("No mcap found", ['data' => $data]);
            return false;
        } catch (\PDOException $e) {
            $this->logger->error("Database Exception in loadLastId", ['exception' => $e]);
            return false;
        }
    }

    public function insert(Mcap $mcap): Mcap
    {
        $this->logger->debug("McapMapper.insert started");

        $send = $data = $mcap->getArrayCopy();
        unset($data['capid']);

        $query = "INSERT INTO mcap (coverage, tokenprice, gemprice, daygems, daytokens, totaltokens, createdat) VALUES (:coverage, :tokenprice, :gemprice, :daygems, :daytokens, :totaltokens, :createdat)";
        $stmt = $this->db->prepare($query);
        $stmt->execute($data);

        $this->logger->info("Inserted new mcap into database", ['data' => $data]);

        return new Mcap($send);
    }

    public function update(Mcap $mcap): Mcap
    {
        $this->logger->debug("McapMapper.update started");

        $data = $mcap->getArrayCopy();

        $query = "UPDATE mcap SET coverage = :coverage, tokenprice = :tokenprice, gemprice = :gemprice, daygems = :daygems, daytokens = :daytokens, totaltokens = :totaltokens, createdat = :createdat WHERE capid = :capid";

        $stmt = $this->db->prepare($query);
        $stmt->execute($data);

        $this->logger->info("Updated mcap in database", ['data' => $data]);

        return new Mcap($data);
    }

    public function delete(int $capid): bool
    {
        $this->logger->debug("McapMapper.delete started");

        $query = "DELETE FROM mcap WHERE capid = :capid";
        $stmt = $this->db->prepare($query);
        $stmt->execute(['capid' => $capid]);

        $deleted = (bool)$stmt->rowCount();
        if ($deleted) {
            $this->logger->info("Deleted capid from database", ['capid' => $capid]);
        } else {
            $this->logger->warning("No capid found to delete in database", ['capid' => $capid]);
        }

        return $deleted;
    }

    protected function getLastPrice(): ?array
    {
        $this->logger->debug('McapMapper.getLastPrice started');

        try {
            $sql = "SELECT coverage, daytokens, createdat FROM mcap ORDER BY createdat DESC LIMIT 1";
            $stmt = $this->db->query($sql);
            $data = $stmt->fetch(\PDO::FETCH_ASSOC);
            return $data !== false ? (array) $data : [];
        } catch (\PDOException $e) {
            return [];
        }
    }

    public function fetchAndUpdateMarketPrices(): array
    {
        $this->logger->debug('McapMapper.fetchAndUpdateMarketPrices started');

        try {
            $numberoftokens = (float) $this->db->query('SELECT SUM(liquidity) FROM wallett')->fetchColumn() ?: 0;
            $numberofgems = (float) $this->db->query('SELECT SUM(gems) FROM gems WHERE collected = 0 AND createdat = NOW()')->fetchColumn() ?: 0;

            if ($numberoftokens === 0 || $numberofgems === 0) {
                $this->logger->info("numberoftokens or numberofgems is empty.", ['numberoftokens' => $numberoftokens, 'numberofgems' => $numberofgems]);
                return $this::createSuccessResponse(21207);
            }

            $resultLastData = $this->refreshMarketData();
            if ($resultLastData['status'] !== 'success') {
                $this->logger->info($resultLastData['ResponseCode'], ['resultLastData' => $resultLastData]);
                return $this::respondWithError(41217);
            }

            $insertedId = $resultLastData['affectedRows']['insertedId'] ?? null;
            $coverage = $resultLastData['affectedRows']['coverage'] ?? 0.0;
            $daytokens = $resultLastData['affectedRows']['daytokens'] ?? 0.0;

            if ($insertedId === null) {
                $this->logger->info("Inserted ID is missing from refreshMarketData response.", ['insertedId' => $insertedId]);
                return $this::respondWithError(41218);
            }

            $numberoftokens += $daytokens;
            $oneTokenPrice = $coverage / $numberoftokens;
            $oneGemsPrice = Rechnen::calculate_gems_preis($daytokens, $numberofgems, $oneTokenPrice);

            try {
                $sql = 'UPDATE mcap 
                        SET tokenprice = :tokenprice, gemprice = :gemprice, daygems = :daygems, totaltokens = :totaltokens 
                        WHERE capid = :capid';
                $stmt = $this->db->prepare($sql);
                $stmt->execute([
                    ':tokenprice' => $oneTokenPrice,
                    ':gemprice' => $oneGemsPrice,
                    ':daygems' => $numberofgems,
                    ':totaltokens' => $numberoftokens,
                    ':capid' => $insertedId
                ]);
            } catch (\PDOException $e) {
                return $this::respondWithError(40301);
            }

            $result = [
                'NumberOfTokens' => $numberoftokens,
                'NumberOfGems' => $numberofgems,
                'coverage' => $coverage,
                'TokenPrice' => $oneTokenPrice,
                'GemsPrice' => $oneGemsPrice
            ];

            return [
                'status' => $resultLastData['status'],
                'ResponseCode' => $resultLastData['ResponseCode'],
                'affectedRows' => $result
            ];
        } catch (\PDOException $e) {
            return $this::respondWithError(40301);
        }
    }

    public function refreshMarketData(): array
    {
        $this->logger->debug('McapMapper.refreshMarketData started.');

        try {
            $url = 'https://exchange-api.lcx.com/market/tickers';
            $priceInfo = @file_get_contents($url);

            if ($priceInfo === false) {
                return $this::respondWithError(41219);
            }

            $array = json_decode($priceInfo, true);
            if (json_last_error() !== JSON_ERROR_NONE || $array === null) {
                return $this::respondWithError(41220);
            }

            if (empty($array['data']['ETH/EUR']['bestAsk'])) {
                return $this::createSuccessResponse(21208);
            }

            $coverage = (float) $array['data']['ETH/EUR']['bestAsk'];
            $daytokens = 5000;

            $this->db->beginTransaction();
            try {
                $sql = 'INSERT INTO mcap (coverage, daytokens) VALUES(:coverage, :daytokens)';
                $stmt = $this->db->prepare($sql);
                $stmt->execute([':coverage' => $coverage, ':daytokens' => $daytokens]);

                $insertedId = $this->db->lastInsertId();
                $this->db->commit();
            } catch (\PDOException $e) {
                $this->db->rollBack();
                $this->logger->error('Database Exception.', ['exception' => $e]);
                return $this::respondWithError(40302);
            }

            return $this::createSuccessResponse(11210, ['coverage' => $coverage, 'daytokens' => $daytokens, 'insertedId' => $insertedId], false);
        } catch (\Exception $e) {
            $this->logger->error('Connection Exception.', ['exception' => $e]);
            return $this::respondWithError(40301);
        }
    }
}
