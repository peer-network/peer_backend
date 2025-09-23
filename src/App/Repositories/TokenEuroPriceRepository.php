<?php

namespace Fawaz\App\Repositories;

use Fawaz\App\Models\TokenEuroPrice;
use PDO;
use Psr\Log\LoggerInterface;

class TokenEuroPriceRepository
{
    public function __construct(
        protected LoggerInterface $logger,
        protected PDO $db
    ) {}

    /**
     * Fetches TokenEuroPrice by token.
     */
    public function getTokenEuroPrice(TokenEuroPrice $tokenPrice): ?TokenEuroPrice
    {
        $this->logger->info("Fetching token price for token: {$tokenPrice->getToken()}");

        try {
            $stmt = $this->db->prepare("SELECT * FROM token_euro_price WHERE token = :token");
            $stmt->bindValue(':token', $tokenPrice->getToken(), PDO::PARAM_STR);
            $stmt->execute();

            $data = $stmt->fetch(PDO::FETCH_ASSOC);

            return is_array($data) ? $this->mapArrayToTokenEuroPrice($data) : null;

        } catch (\Throwable $e) {
            $this->handleException("getTokenEuroPrice", $e);
        }
    }

    /**
     * Updates TokenEuroPrice for a given token.
     */
    public function updateTokenEuroPrice(TokenEuroPrice $tokenPrice): TokenEuroPrice
    {
        $this->logger->info("Updating token price for token: {$tokenPrice->getToken()}");

        try {
            $stmt = $this->db->prepare("
                UPDATE token_euro_price 
                SET europrice = :europrice, updatedat = :updatedat 
                WHERE token = :token
            ");

            $stmt->bindValue(':token', $tokenPrice->getToken(), PDO::PARAM_STR);
            $stmt->bindValue(':europrice', $tokenPrice->getEuroPrice(), PDO::PARAM_STR);
            $stmt->bindValue(':updatedat', $tokenPrice->getUpdatedat(), PDO::PARAM_STR);
            $stmt->execute();

            return $tokenPrice;

        } catch (\Throwable $e) {
            $this->handleException("updateTokenEuroPrice", $e);
        }
    }

    /**
     * Inserts new TokenEuroPrice entry.
     */
    public function saveTokenEuroPrice(TokenEuroPrice $tokenPrice): TokenEuroPrice
    {
        $this->logger->info("Saving new token price for token: {$tokenPrice->getToken()}");

        try {
            $stmt = $this->db->prepare("
                INSERT INTO token_euro_price (token, europrice, updatedat)
                VALUES (:token, :europrice, :updatedat)
            ");

            $stmt->bindValue(':token', $tokenPrice->getToken(), PDO::PARAM_STR);
            $stmt->bindValue(':europrice', $tokenPrice->getEuroPrice(), PDO::PARAM_STR);
            $stmt->bindValue(':updatedat', $tokenPrice->getUpdatedat(), PDO::PARAM_STR);
            $stmt->execute();

            return $tokenPrice;

        } catch (\Throwable $e) {
            $this->handleException("saveTokenEuroPrice", $e);
        }
    }

    /**
     * Map associative array to TokenEuroPrice.
     */
    protected function mapArrayToTokenEuroPrice(array $data): TokenEuroPrice
    {
        return new TokenEuroPrice([
            'token'     => $data['token'],
            'europrice' => $data['europrice'],
            'updatedat' => $data['updatedat'],
        ]);
    }

    /**
     * Central exception handler for repository methods.
     */
    protected function handleException(string $method, \Throwable $e): never
    {
        $this->logger->error("TokenEuroPriceRepository.{$method}: Exception occurred", [
            'error' => $e->getMessage(),
            'trace' => $e instanceof \Exception ? $e->getTraceAsString() : null,
        ]);

        throw new \RuntimeException("Failed in {$method}: " . $e->getMessage(), 0, $e);
    }
}
