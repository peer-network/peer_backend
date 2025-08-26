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

    public function checkLimit(string $email): bool
    {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->logger->warning('Invalid email format in checkLimit', ['email' => $email]);
            throw new \InvalidArgumentException('Invalid email format.');
        }

        try {
            $query = "SELECT EXISTS (
                          SELECT 1 FROM contactus 
                          WHERE email = :email 
                          AND createdat > NOW() - INTERVAL '1 HOUR'
                          LIMIT 1
                      ) AS exists_flag";
            $stmt = $this->db->prepare($query);
            $stmt->bindValue(':email', $email, \PDO::PARAM_STR);
            $stmt->execute();

            $exists = (bool) $stmt->fetchColumn();

            if ($exists) {
                $this->logger->warning('Submission limit reached for email.', ['email' => $email]);
                return false;
            }

            return true;
        } catch (\Throwable $e) {
            $this->logger->error('Database error in checkLimit', [
                'error' => $e->getMessage(),
                'email' => $email,
                'query' => $query // Hilft bei Debugging
            ]);

            throw new \RuntimeException('An unexpected error occurred while checking submission limits.');
        }
    }

    public function checkRateLimit(string $ip): bool
    {
        $this->logger->info("ContactusMapper.Rate limit check started for IP: {$ip}");

        try {
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
                $stmt->bindValue(':now', $now->format('Y-m-d H:i:s'), \PDO::PARAM_STR); 
                $stmt->execute();
            } else {
                $query = "INSERT INTO contactus_rate_limit (ip, request_count, last_request)
                          VALUES (:ip, 1, :now)";
                $stmt = $this->db->prepare($query);
                $stmt->bindValue(':ip', $ip, \PDO::PARAM_STR);
                $stmt->bindValue(':now', $now->format('Y-m-d H:i:s'), \PDO::PARAM_STR); 
                $stmt->execute();
            }

            return true;
        } catch (\Throwable $e) {
            $this->logger->error("Unexpected error in checkRateLimit: " . $e->getMessage());
            return false;
        }
    }

    public function fetchAll(?array $args = []): array
    {
        $this->logger->info("ContactusMapper.fetchAll started");

        $offset = max((int)($args['offset'] ?? 0), 0);
        $limit = min(max((int)($args['limit'] ?? 10), 1), 20);

        $sql = "SELECT * FROM contactus ORDER BY name ASC LIMIT :limit OFFSET :offset";

        try {
            $stmt = $this->db->prepare($sql);
            $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, \PDO::PARAM_INT);
            $stmt->execute();

            $results = array_map(fn($row) => new Contactus($row), $stmt->fetchAll(PDO::FETCH_ASSOC));

            $this->logger->info(
                $results ? "Fetched contacts successfully" : "No contacts found",
                ['count' => count($results), 'limit' => $limit, 'offset' => $offset]
            );

            return $results;
        } catch (\Throwable $e) { 
            $this->logger->error("Error fetching contacts", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'limit' => $limit,
                'offset' => $offset,
            ]);
            return [];
        }
    }

    public function loadById(int $id): ?Contactus
    {
        $this->logger->info("ContactusMapper.loadById started", ['id' => $id]);

        try {
            $sql = "SELECT * FROM contactus WHERE msgid = :id";
            $stmt = $this->db->prepare($sql);
            $stmt->bindValue(':id', $id, \PDO::PARAM_INT);
            $stmt->execute();

            $data = $stmt->fetch(\PDO::FETCH_ASSOC);

            if ($data !== false) {
                return new Contactus($data);
            }

            $this->logger->info("No contact found with id", ['id' => $id]);
            return null;
        } catch (\Throwable $e) {
            $this->logger->error("Database error in loadById", [
                'error' => $e->getMessage(),
                'id' => $id
            ]);

            throw new \RuntimeException("An error occurred while loading the contact.");
        }
    }

    public function loadByName(string $name): ?Contactus
    {
        $this->logger->info("ContactusMapper.loadByName started", ['name' => $name]);

        try {
            $sql = "SELECT * FROM contactus WHERE name = :name";
            $stmt = $this->db->prepare($sql);
            $stmt->bindValue(':name', $name, \PDO::PARAM_STR);
            $stmt->execute();

            $data = $stmt->fetch(\PDO::FETCH_ASSOC);

            if ($data !== false) {
                $this->logger->info("Contact found with name", ['data' => $data]);
                return new Contactus($data);
            }

            $this->logger->info("No contact found with name", ['name' => $name]);
            return null; 
        } catch (\Throwable $e) {
            $this->logger->error("Database error in loadByName", [
                'error' => $e->getMessage(),
                'name' => $name,
            ]);
            throw new \RuntimeException("An error occurred while loading the contact by name.");
        }
    }

    public function insert(Contactus $contact): ?Contactus
    {
        $this->logger->info("ContactusMapper.insert started");

        try {
            $data = $contact->getArrayCopy();

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
        } catch (\Throwable $e) {
            $this->logger->error("Unexpected error during contact creation", [
                'error' => $e->getMessage(),
                'data' => $contact->getArrayCopy(),
            ]);
            throw new \RuntimeException("Unexpected error during contact creation");
            return null;
        }
    }

    public function update(Contactus $contact): ?Contactus
    {
        $this->logger->info("ContactusMapper.update started", ['contact' => $contact]);

        try {
            $data = $contact->getArrayCopy();
            $data['collected'] = 1;

            $query = "UPDATE contactus SET collected = :collected WHERE msgid = :msgid";
            $stmt = $this->db->prepare($query);

            $stmt->bindValue(':collected', $data['collected'], \PDO::PARAM_INT);
            $stmt->bindValue(':msgid', $data['msgid'], \PDO::PARAM_INT);

            $stmt->execute();

            if ($stmt->rowCount() > 0) {
                $this->logger->info("Updated contact in database", ['contact' => $data]);
                return new Contactus($data);
            } else {
                $this->logger->info("No changes made to the contact", ['contact' => $data]);
                return null;  
            }
        } catch (\Throwable $e) {
            $this->logger->error("Error updating contact in database", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'data' => $contact->getArrayCopy(),
            ]);
            return null; 
        }
    }

    public function delete(int $id): bool
    {
        $this->logger->info("ContactusMapper.delete started", ['id' => $id]);

        try {
            $query = "DELETE FROM contactus WHERE msgid = :id";
            $stmt = $this->db->prepare($query);
            $stmt->bindValue(':id', $id, \PDO::PARAM_INT); // Explizite Parameterbindung

            $stmt->execute();

            $deleted = (bool) $stmt->rowCount();
            if ($deleted) {
                $this->logger->info("Deleted contact from database", ['id' => $id]);
            } else {
                $this->logger->info("No contact found to delete in database for id", ['id' => $id]);
            }

            return $deleted;
        } catch (\Throwable $e) {
            $this->logger->error("Error deleting contact from database", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'id' => $id,
            ]);
            throw new \RuntimeException("An error occurred while deleting the contact.");
        }
    }
}
