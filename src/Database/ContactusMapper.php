<?php

namespace Fawaz\Database;

use PDO;
use Fawaz\App\Contactus;
use Psr\Log\LoggerInterface;

class ContactusMapper
{
    public function __construct(protected LoggerInterface $logger, protected PDO $db)
    {
    }

    private function respondWithError(string $message): array
    {
        return ['status' => 'error', 'ResponseCode' => $message];
    }

    public function checkLimit(string $email): bool
    {
        try {
            $query = "SELECT COUNT(*) FROM contactus WHERE email = :email AND createdat > NOW() - INTERVAL '1 HOUR'";
            $stmt = $this->db->prepare($query);
            $stmt->bindValue(':email', $email, \PDO::PARAM_STR);
            $stmt->execute();

            $count = (int) $stmt->fetchColumn();

            if ($count >= 3) {
                $this->logger->warning('Submission limit reached for email.', ['email' => $email, 'count' => $count]);
                return false;
            }

            return true; // Limit not reached
        } catch (\PDOException $e) {
            $this->logger->error('Database error during limit check.', ['error' => $e->getMessage(), 'email' => $email]);
            throw new \RuntimeException('An error occurred while checking submission limits.');
        } catch (\Throwable $e) {
            $this->logger->error('Unexpected error during limit check.', ['error' => $e->getMessage(), 'email' => $email]);
            throw new \RuntimeException('An unexpected error occurred while checking submission limits.');
        }
    }

    public function checkRateLimit(string $ip): bool
    {
        $this->logger->info("ContactusMapper.Rate limit check started for IP: {$ip}");

        $query = "SELECT request_count, last_request FROM contactus_rate_limit WHERE ip = :ip";
        $stmt = $this->db->prepare($query);
        $stmt->bindValue(':ip', $ip, \PDO::PARAM_STR);
        $stmt->execute();

        $result = $stmt->fetch(\PDO::FETCH_ASSOC);
        $now = new \DateTime();

        if ($result) {
            $lastRequest = new \DateTime($result['last_request']);
            $interval = $now->diff($lastRequest);

            if ($interval->i < 1 && $result['request_count'] >= 5) {
                $this->logger->warning("Rate limit exceeded for IP: {$ip}");
                return false;
            }

            $query = "UPDATE contactus_rate_limit
                      SET request_count = request_count + 1, last_request = :now
                      WHERE ip = :ip";
            $stmt = $this->db->prepare($query);
            $stmt->bindValue(':ip', $ip, \PDO::PARAM_STR);
            $stmt->bindValue(':now', $now->format('Y-m-d H:i:s.u'), \PDO::PARAM_STR);
            $stmt->execute();
        } else {
            $query = "INSERT INTO contactus_rate_limit (ip, request_count, last_request)
                      VALUES (:ip, 1, :now)";
            $stmt = $this->db->prepare($query);
            $stmt->bindValue(':ip', $ip, \PDO::PARAM_STR);
            $stmt->bindValue(':now', $now->format('Y-m-d H:i:s.u'), \PDO::PARAM_STR);
            $stmt->execute();
        }

        return true;
    }

    public function fetchAll(?array $args = []): array
    {
        $this->logger->info("ContactusMapper.fetchAll started");

        $offset = max((int)($args['offset'] ?? 0), 0);
        $limit = min(max((int)($args['limit'] ?? 10), 1), 20);

        $sql = "SELECT * FROM contactus ORDER BY name ASC LIMIT :limit OFFSET :offset";

        try {
            $stmt = $this->db->prepare($sql);
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
            $stmt->execute();

            $results = array_map(fn($row) => new Contactus($row), $stmt->fetchAll(PDO::FETCH_ASSOC));

            $this->logger->info(
                $results ? "Fetched tags successfully" : "No tags found",
                ['count' => count($results)]
            );

            return $results;
        } catch (\PDOException $e) {
            $this->logger->error("Error fetching contact from database", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return $this->respondWithError('Error fetching contact from database');
        }
    }

    public function loadById(int $id): Contactus|false
    {
        $this->logger->info("ContactusMapper.loadById started");

        $sql = "SELECT * FROM contactus WHERE tagid = :id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['id' => $id]);
        $data = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($data !== false) {
            return new Contactus($data);
        }

        $this->logger->warning("No contact found with id", ['id' => $id]);

        return false;
    }

    public function loadByName(string $name): Contactus|false
    {
        $this->logger->info("ContactusMapper.loadByName started");

        $sql = "SELECT * FROM contactus WHERE name = :name";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['name' => $name]);
        $data = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($data !== false) {
            $this->logger->info("contact found with name", ['data' => $data]);
            return new Contactus($data);
        }

        $this->logger->warning("No contact found with name", ['name' => $name]);

        return false;
    }

    public function insert(Contactus $contact): Contactus|false
    {
        $this->logger->info("ContactusMapper.insert started");

        try {
            $data = $contact->getArrayCopy();

            unset($data['msgid']);

            $query = "INSERT INTO contactus (name, email, message, ip, createdat) VALUES (:name, :email, :message, :ip, :createdat) RETURNING msgid";

            $stmt = $this->db->prepare($query);
            $stmt->bindValue(':name', $data['name'], \PDO::PARAM_STR);
            $stmt->bindValue(':email', $data['email'], \PDO::PARAM_STR);
            $stmt->bindValue(':message', $data['message'], \PDO::PARAM_STR);
            $stmt->bindValue(':ip', $data['ip'], \PDO::PARAM_STR);
            $stmt->bindValue(':createdat', $data['createdat'], \PDO::PARAM_STR);
            $stmt->execute();

            $generatedContactId = $stmt->fetchColumn();

            if (!$generatedContactId) {
                throw new \RuntimeException("Failed to retrieve generated msgid");
            }

            $data['msgid'] = (int) $generatedContactId;
            $this->logger->info("Inserted new contact into database", ['contact' => $data]);

            return new Contactus($data);
        } catch (\PDOException $e) {
            if ($e->getCode() === '23505') { // Unique constraint violation
                $this->logger->warning("Duplicate email detected", [
                    'email' => $contact->getArrayCopy()['email'],
                    'error' => $e->getMessage(),
                ]);
                return false; // Or return a specific error response
            }

            $this->logger->error("Error inserting contact into database", [
                'error' => $e->getMessage(),
                'data' => $contact->getArrayCopy(),
            ]);
            return false;
        } catch (\Throwable $e) {
            $this->logger->error("Unexpected error during contact creation", [
                'error' => $e->getMessage(),
                'data' => $contact->getArrayCopy(),
            ]);
            return false;
        }
    }

    public function update(Contactus $contact): Contactus|false
    {
        $this->logger->info("ContactusMapper.update started");

        try {
            $data = $contact->getArrayCopy();

            $query = "UPDATE contactus SET name = :name WHERE msgid = :msgid";
            $stmt = $this->db->prepare($query);
            $stmt->execute($data);

            $this->logger->info("Updated contact in database", ['contact' => $data]);
            return new Contactus($data);

        } catch (\Throwable $e) {
            $this->logger->error("Error updating contact in database", [
                'error' => $e->getMessage(),
                'data' => $contact->getArrayCopy(),
            ]);
            return false;
        }
    }

    public function delete(string $id): bool
    {
        $this->logger->info("ContactusMapper.delete started");

        try {
            $query = "DELETE FROM contactus WHERE msgid = :id";
            $stmt = $this->db->prepare($query);
            $stmt->execute(['id' => $id]);

            $deleted = (bool)$stmt->rowCount();
            if ($deleted) {
                $this->logger->info("Deleted contact from database", ['id' => $id]);
            } else {
                $this->logger->warning("No contact found to delete in database for id", ['id' => $id]);
            }

            return $deleted;

        } catch (\Throwable $e) {
            $this->logger->error("Error deleting contact from database", [
                'error' => $e->getMessage(),
                'id' => $id,
            ]);
            throw $e;
        }
    }
}
