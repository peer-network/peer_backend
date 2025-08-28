<?php
declare(strict_types=1);

namespace Fawaz\Database;

use PDO;
use Fawaz\App\TagPost;
use Psr\Log\LoggerInterface;

class TagPostMapper
{
    public function __construct(protected LoggerInterface $logger, protected PDO $db)
    {
    }

    public function fetchAll(int $offset, int $limit): array
    {
        $this->logger->info("TagPostMapper.fetchAll started");

        $sql = "SELECT * FROM post_tags ORDER BY postid ASC LIMIT :limit OFFSET :offset";

        try {
            $stmt = $this->db->prepare($sql);
            $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, \PDO::PARAM_INT);
            $stmt->execute();

            $results = array_map(fn($row) => new TagPost($row), $stmt->fetchAll(\PDO::FETCH_ASSOC));

            $this->logger->info(
                $results ? "Fetched postags successfully" : "No postags found",
                ['count' => count($results)]
            );

            return $results;
        } catch (\PDOException $e) {
            $this->logger->error("Error fetching postags from database", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return [];
        }
    }

    public function loadByPostId(string $postid): array
    {
        $this->logger->info("TagPostMapper.loadByPostId started");

        $sql = "SELECT * FROM post_tags WHERE postid = :postid";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['postid' => $postid]);
        $results = [];

        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $results[] = new TagPost($row);
        }

        if ($results) {
            $this->logger->info("Fetched post_tags by postid", ['postid' => $postid, 'count' => count($results)]);
        } else {
            $this->logger->warning("No post_tags found for postid", ['postid' => $postid]);
        }

        return $results;
    }

    public function loadByTagId(string $tagid): array
    {
        $this->logger->info("TagPostMapper.loadByTagId started");

        $sql = "SELECT * FROM post_tags WHERE tagid = :tagid";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['tagid' => $tagid]);
        $results = [];

        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $results[] = new TagPost($row);
        }

        if ($results) {
            $this->logger->info("Fetched post_tags by tagid", ['tagid' => $tagid, 'count' => count($results)]);
        } else {
            $this->logger->warning("No post_tags found for tagid", ['tagid' => $tagid]);
        }

        return $results;
    }

    public function insert(TagPost $tagPost): TagPost
    {
        $this->logger->info("TagPostMapper.insert started");

        $data = $tagPost->getArrayCopy();

        $query = "INSERT INTO post_tags (tagid, postid, createdat) VALUES (:tagid, :postid, :createdat)";
        $stmt = $this->db->prepare($query);
        $stmt->execute($data);

        $this->logger->info("Inserted new post_tags into database", ['post_tags' => $data]);

        return new TagPost($data);
    }

    public function deleteByPostId(string $postid): bool
    {
        $this->logger->info("TagPostMapper.deleteByPostId started");

        $query = "DELETE FROM post_tags WHERE postid = :postid";
        $stmt = $this->db->prepare($query);
        $stmt->execute(['postid' => $postid]);

        $deleted = (bool) $stmt->rowCount();
        if ($deleted) {
            $this->logger->info("Deleted post_tags by postid from database", ['postid' => $postid]);
        } else {
            $this->logger->warning("No post_tags found to delete for postid", ['postid' => $postid]);
        }

        return $deleted;
    }

    public function deleteByTagId(string $tagid): bool
    {
        $this->logger->info("TagPostMapper.deleteByTagId started");

        $query = "DELETE FROM post_tags WHERE tagid = :tagid";
        $stmt = $this->db->prepare($query);
        $stmt->execute(['tagid' => $tagid]);

        $deleted = (bool) $stmt->rowCount();
        if ($deleted) {
            $this->logger->info("Deleted post_tags by tagid from database", ['tagid' => $tagid]);
        } else {
            $this->logger->warning("No post_tags found to delete for tagid", ['tagid' => $tagid]);
        }

        return $deleted;
    }
}
