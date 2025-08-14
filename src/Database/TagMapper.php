<?php

namespace Fawaz\Database;

use PDO;
use Fawaz\App\Tag;
use Psr\Log\LoggerInterface;

class TagMapper
{
    public function __construct(protected LoggerInterface $logger, protected PDO $db)
    {
    }

    public function isSameUser(string $userid, string $currentUserId): bool
    {
        return $userid === $currentUserId;
    }

    public function fetchAll(int $offset, int $limit): array
    {
        $this->logger->info("TagMapper.fetchAll started");

        $sql = "SELECT * FROM tags ORDER BY name ASC LIMIT :limit OFFSET :offset";

        try {
            $stmt = $this->db->prepare($sql);
            $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, \PDO::PARAM_INT);
            $stmt->execute();

            $results = array_map(fn($row) => new Tag($row), $stmt->fetchAll(PDO::FETCH_ASSOC));

            $this->logger->info(
                $results ? "Fetched tags successfully" : "No tags found",
                ['count' => count($results)]
            );

            return $results;
        } catch (\PDOException $e) {
            $this->logger->error("Error fetching tags from database", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return [];
        }
    }

    public function loadById(int $id): Tag|false
    {
        $this->logger->info("TagMapper.loadById started");

        $sql = "SELECT * FROM tags WHERE tagid = :id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['id' => $id]);
        $data = $stmt->fetch(\PDO::FETCH_ASSOC);

        if ($data !== false) {
            return new Tag($data);
        }

        $this->logger->warning("No tag found with id", ['id' => $id]);

        return false;
    }

    public function loadByName(string $name): Tag|false
    {
        $this->logger->info("TagMapper.loadByName started");

        $sql = "SELECT * FROM tags WHERE name = :name";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['name' => $name]);
        $data = $stmt->fetch(\PDO::FETCH_ASSOC);

        if ($data !== false) {
            $this->logger->info("tag found with name", ['data' => $data]);
            return new Tag($data);
        }

        $this->logger->info("No tag found with name", ['name' => $name]);

        return false;
    }

    public function searchByName(?array $args = []): array|false
    {
        $this->logger->info("TagMapper.loadByName started");

        $offset = max((int)($args['offset'] ?? 0), 0);
        $limit = min(max((int)($args['limit'] ?? 10), 1), 20);


        $name = strtolower($args['tagName']);
        $sql = "SELECT * FROM tags WHERE name ILIKE :name ORDER BY name ASC LIMIT :limit OFFSET :offset";

        try {
            $stmt = $this->db->prepare($sql);
            
            $searchTerm = '%' . $name . '%';
            $stmt->bindValue(':name', $searchTerm, \PDO::PARAM_STR);
            $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, \PDO::PARAM_INT);

            $stmt->execute();
            $data = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            if (!empty($data)) {
                $this->logger->info("Tags found with name", ['data' => $data]);

                $tags = [];
                foreach ($data as $row) {
                    $tags[] = new Tag($row);
                }

                return $tags;
            }

            return false;

        } catch (\PDOException $e) {
            $this->logger->error("Error fetching tags from database", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return false;
        }
    }

    public function insert(Tag $tag): Tag|false
    {
        $this->logger->info("TagMapper.insert started");

        try {
            $data = $tag->getArrayCopy();
            $query = "INSERT INTO tags (name) VALUES (:name) RETURNING tagid";

            $stmt = $this->db->prepare($query);
            $stmt->bindValue(':name', $data['name'], \PDO::PARAM_STR);
            $stmt->execute();

            // Get the auto-generated tagid from RETURNING
            $generatedTagId = $stmt->fetchColumn();

            $data['tagid'] = (int) $generatedTagId;
            $this->logger->info("Inserted new tag into database", ['tag' => $data]);

            return new Tag($data);

        } catch (\Throwable $e) {
            $this->logger->error("Error inserting tag into database", [
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    public function update(Tag $tag): Tag|false
    {
        $this->logger->info("TagMapper.update started");

        try {
            $data = $tag->getArrayCopy();

            $query = "UPDATE tags SET name = :name WHERE tagid = :tagid";
            $stmt = $this->db->prepare($query);
            $stmt->execute($data);

            $this->logger->info("Updated tag in database", ['tag' => $data]);
            return new Tag($data);

        } catch (\Throwable $e) {
            $this->logger->error("Error updating tag in database", [
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    public function delete(string $id): bool
    {
        $this->logger->info("TagMapper.delete started");

        try {
            $query = "DELETE FROM tags WHERE tagid = :id";
            $stmt = $this->db->prepare($query);
            $stmt->execute(['id' => $id]);

            $deleted = (bool)$stmt->rowCount();
            if ($deleted) {
                $this->logger->info("Deleted tag from database", ['id' => $id]);
            } else {
                $this->logger->warning("No tag found to delete in database for id", ['id' => $id]);
            }

            return $deleted;

        } catch (\Throwable $e) {
            $this->logger->error("Error deleting tag from database", [
                'error' => $e->getMessage(),
                'id' => $id,
            ]);
            throw $e;
        }
    }
}
