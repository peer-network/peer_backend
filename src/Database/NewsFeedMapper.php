<?php
namespace Fawaz\Database;

use PDO;
use Fawaz\App\Post;
use Psr\Log\LoggerInterface;

class NewsFeedMapper
{
    public function __construct(protected LoggerInterface $logger, protected PDO $db)
    {
    }

    public function isSameUser(string $userid, string $currentUserId): bool
    {
        return $userid === $currentUserId;
    }

    public function isCreator(string $feedid, string $currentUserId): bool
    {
        $this->logger->info("NewsFeedMapper.isCreator started");

        $sql = "SELECT COUNT(*) FROM posts WHERE feedid = :feedid AND userid = :currentUserId";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['feedid' => $feedid, 'currentUserId' => $currentUserId]);

        return (bool) $stmt->fetchColumn();
    }

    public function loadById(string $id): Post|false
    {
        $this->logger->info("NewsFeedMapper.loadById started");

        $sql = "SELECT * FROM posts WHERE feedid = :feedid";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['feedid' => $id]);
        $data = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($data !== false) {
            return new NewsFeed($data);
        }

        $this->logger->warning("No feed found with id", ['id' => $id]);
        return false;
    }

    public function userInfoForFeeds(string $id): array
    {
        $this->logger->info("NewsFeedMapper.userInfoForFeeds started");

        $sql = "SELECT uid AS id, username, img FROM users WHERE uid = :id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['id' => $id]);
        $data = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($data === false) {
            $this->logger->warning("No user found with id: " . $id);
            return [];
        }

        return $data;
    }

    // Create a NewsFeedM
    public function insert(Post $post): Post
    {
        $this->logger->info("NewsFeedMapper.insert started");

        $data = $post->getArrayCopy();

        $query = "INSERT INTO posts (postid, userid, feedid, title, media, mediadescription, contenttype, amountlikes, amountdislikes, amountviews, amountcomments, createdat)
            VALUES (:postid, :userid, :feedid, :title, :media, :mediadescription, :contenttype, :amountlikes, :amountdislikes, :amountviews, :amountcomments, :createdat)";
        $stmt = $this->db->prepare($query);
        $stmt->execute($data);

        $this->logger->info("Inserted new post into database", ['post' => $data]);

        return new Post($data);
    }

    public function delete(string $id): bool
    {
        $this->logger->info("NewsFeedMapper.delete started");

        $query = "DELETE FROM posts WHERE feedid = :feedid";

        $stmt = $this->db->prepare($query);
        $stmt->execute(['feedid' => $id]);

        $deleted = (bool)$stmt->rowCount();

        if ($deleted) {
            $this->logger->info("Deleted feed from database", ['id' => $id]);
        } else {
            $this->logger->warning("No feed found to delete in database for id", ['id' => $id]);
        }

        return $deleted;
    }

    public function getAllPosts(?string $feedid): array
    {
        $this->logger->info("NewsFeedMapper.getAllPosts started");

        $sql = "SELECT * FROM posts WHERE feedid = :feedid";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['feedid' => $feedid]);

        $results = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $results[] = new NewsFeed($row);
        }

        if ($results) {
            $this->logger->info("Fetched all feeds from database", ['count' => count($results)]);
        } else {
            $this->logger->warning("No feeds found in database");
        }

        try {

            foreach ($results as &$feed) {

                $feed = $feed->getArrayCopy();
                $userid = $feed['userid'];
                $userInfo = $this->userInfoForFeeds($userid);
                $feed['user'] = $userInfo;
            }

            return $results;
        } catch (\Exception $e) {
            return [];
        }
    }
}
