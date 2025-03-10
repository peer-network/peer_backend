<?php
namespace Fawaz\Database;

use PDO;
use Fawaz\App\Post;
use Fawaz\App\PostAdvanced;
use Psr\Log\LoggerInterface;

class PostMapper
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
        $this->logger->info("PostMapper.fetchAll started");

        $sql = "SELECT * FROM posts WHERE feedid IS NULL ORDER BY createdat DESC LIMIT :limit OFFSET :offset";

        try {
            $stmt = $this->db->prepare($sql);
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
            $stmt->execute();

            $results = array_map(fn($row) => new Post($row), $stmt->fetchAll(PDO::FETCH_ASSOC));

            $this->logger->info(
                $results ? "Fetched posts successfully" : "No posts found",
                ['count' => count($results)]
            );

            return $results;
        } catch (\PDOException $e) {
            $this->logger->error("Error fetching posts from database", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return [];
        }
    }

    public function isCreator(string $postid, string $currentUserId): bool
    {
        $this->logger->info("PostMapper.isCreator started");

        $sql = "SELECT COUNT(*) FROM posts WHERE postid = :postid AND userid = :currentUserId";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['postid' => $postid, 'currentUserId' => $currentUserId]);

        return (bool) $stmt->fetchColumn();
    }

    public function isNewsFeedExist(string $feedid): bool
    {
        $this->logger->info("PostMapper.isNewsFeedExist started");

        $sql = "SELECT COUNT(*) FROM newsfeed WHERE feedid = :feedid";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['feedid' => $feedid]);
        return (bool) $stmt->fetchColumn();
    }

    public function isHasAccessInNewsFeed(string $chatid, string $currentUserId): bool
    {
        $this->logger->info("PostMapper.isHasAccessInNewsFeed started");

        $sql = "SELECT COUNT(*) FROM chatparticipants WHERE chatid = :chatid AND userid = :currentUserId";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['chatid' => $chatid, 'currentUserId' => $currentUserId]);

        return (bool) $stmt->fetchColumn();
    }

    public function getChatFeedsByID(string $feedid): array
    {
        $this->logger->info("PostMapper.getChatFeedsByID started");

        $sql = "SELECT * FROM posts WHERE feedid = :feedid";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['feedid' => $feedid]);

        $results = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $results[] = new Post($row);
        }

        if (empty($results)) {
            $this->logger->warning("No posts found with feedid", ['feedid' => $feedid]);
            return [];
        }

        $this->logger->info("Fetched all posts from database", ['count' => count($results)]);

        return $results;
    }

    public function loadByTitle(string $title): array
    {
        $this->logger->info("PostMapper.loadByTitle started");

        $sql = "SELECT * FROM posts WHERE title LIKE :title AND feedid IS NULL";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['title' => '%' . $title . '%']);
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (!empty($data)) {
            return array_map(fn($row) => new Post($row), $data);
        }

        $this->logger->warning("No posts found with title", ['title' => $title]);
        return [];
    }

    public function loadById(string $id): Post|false
    {
        $this->logger->info("PostMapper.loadById started");

        $sql = "SELECT * FROM posts WHERE postid = :postid AND feedid IS NULL";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['postid' => $id]);
        $data = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($data !== false) {
            return new Post($data);
        }

        $this->logger->warning("No post found with id", ['id' => $id]);
        return false;
    }

    public function fetchPostsByType(string $userid, int $limitPerType = 5): array
    {
        $sql = "
            SELECT postid, contenttype, title, media, createdat
            FROM (
                SELECT *, ROW_NUMBER() OVER (PARTITION BY contenttype ORDER BY createdat DESC) AS row_num
                FROM posts
                WHERE userid = :userid AND feedid IS NULL
            ) subquery
            WHERE row_num <= :limit
            ORDER BY contenttype, createdat DESC
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue('userid', $userid, PDO::PARAM_STR);
        $stmt->bindValue('limit', $limitPerType, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function fetchComments(string $postid): array
    {
        $this->logger->info("PostMapper.fetchComments started");

        $sql = "SELECT * FROM comments WHERE postid = :postid";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['postid' => $postid]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function fetchLikes(string $postid): array
    {
        $this->logger->info("PostMapper.fetchLikes started");

        $sql = "SELECT * FROM user_post_likes WHERE postid = :postid";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['postid' => $postid]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function fetchDislikes(string $postid): array
    {
        $this->logger->info("PostMapper.fetchDislikes started");

        $sql = "SELECT * FROM user_post_dislikes WHERE postid = :postid";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['postid' => $postid]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function fetchSaves(string $postid): array
    {
        $this->logger->info("PostMapper.fetchSaves started");

        $sql = "SELECT * FROM user_post_saves WHERE postid = :postid";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['postid' => $postid]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function fetchViews(string $postid): array
    {
        $this->logger->info("PostMapper.fetchViews started");

        $sql = "SELECT * FROM user_post_views WHERE postid = :postid";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['postid' => $postid]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function fetchReports(string $postid): array
    {
        $this->logger->info("PostMapper.fetchReports started");

        $sql = "SELECT * FROM user_post_reports WHERE postid = :postid";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['postid' => $postid]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function countLikes(string $postid): int
    {
        $this->logger->info("PostMapper.countLikes started");

        $sql = "SELECT COUNT(*) FROM user_post_likes WHERE postid = :postid";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['postid' => $postid]);
        return (int) $stmt->fetchColumn();
    }

    public function countDisLikes(string $postid): int
    {
        $this->logger->info("PostMapper.countLikes started");

        $sql = "SELECT COUNT(*) FROM user_post_dislikes WHERE postid = :postid";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['postid' => $postid]);
        return (int) $stmt->fetchColumn();
    }

    public function countViews(string $postid): int
    {
        $this->logger->info("PostMapper.countViews started");

        $sql = "SELECT COUNT(*) FROM user_post_views WHERE postid = :postid";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['postid' => $postid]);
        return (int) $stmt->fetchColumn();
    }

    public function countComments(string $postid): int
    {
        $this->logger->info("PostMapper.countComments started");

        $sql = "SELECT COUNT(*) FROM comments WHERE postid = :postid";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['postid' => $postid]);
        return (int) $stmt->fetchColumn();
    }

    public function isLiked(string $postid, string $userid): bool
    {
        $this->logger->info("PostMapper.isLiked started");

        $sql = "SELECT COUNT(*) FROM user_post_likes WHERE postid = :postid AND userid = :userid";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['postid' => $postid, 'userid' => $userid]);
        return (bool) $stmt->fetchColumn();
    }

    public function isViewed(string $postid, string $userid): bool
    {
        $this->logger->info("PostMapper.isViewed started");

        $sql = "SELECT COUNT(*) FROM user_post_views WHERE postid = :postid AND userid = :userid";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['postid' => $postid, 'userid' => $userid]);
        return (bool) $stmt->fetchColumn();
    }

    public function isReported(string $postid, string $userid): bool
    {
        $this->logger->info("PostMapper.isReported started");

        $sql = "SELECT COUNT(*) FROM user_post_reports WHERE postid = :postid AND userid = :userid";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['postid' => $postid, 'userid' => $userid]);
        return (bool) $stmt->fetchColumn();
    }

    public function isDisliked(string $postid, string $userid): bool
    {
        $this->logger->info("PostMapper.isDisliked started");

        $sql = "SELECT COUNT(*) FROM user_post_dislikes WHERE postid = :postid AND userid = :userid";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['postid' => $postid, 'userid' => $userid]);
        return (bool) $stmt->fetchColumn();
    }

    public function isSaved(string $postid, string $userid): bool
    {
        $this->logger->info("PostMapper.isSaved started");

        $sql = "SELECT COUNT(*) FROM user_post_saves WHERE postid = :postid AND userid = :userid";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['postid' => $postid, 'userid' => $userid]);
        return (bool) $stmt->fetchColumn();
    }

    public function isFollowing(string $userid, string $currentUserId): bool
    {
        $this->logger->info("PostMapper.isFollowing started");

        $sql = "SELECT COUNT(*) FROM follows WHERE followedId = :userid AND followerId = :currentUserId";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['userid' => $userid, 'currentUserId' => $currentUserId]);

        return (bool) $stmt->fetchColumn();
    }

    public function userInfoForPosts(string $id): array
    {
        $this->logger->info("PostMapper.userInfoForPosts started");

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

    // Create a post
    public function insert(Post $post): Post
    {
        $this->logger->info("PostMapper.insert started");

        $data = $post->getArrayCopy();

        $query = "INSERT INTO posts 
                  (postid, userid, feedid, title, media, cover, mediadescription, contenttype, createdat)
                  VALUES 
                  (:postid, :userid, :feedid, :title, :media, :cover, :mediadescription, :contenttype, :createdat)";

        try {
            $stmt = $this->db->prepare($query);

            // Explicitly bind each value
            $stmt->bindValue(':postid', $data['postid'], \PDO::PARAM_STR);
            $stmt->bindValue(':userid', $data['userid'], \PDO::PARAM_STR);
            $stmt->bindValue(':feedid', $data['feedid'], \PDO::PARAM_STR);
            $stmt->bindValue(':title', $data['title'], \PDO::PARAM_STR);
            $stmt->bindValue(':media', $data['media'], \PDO::PARAM_STR);
            $stmt->bindValue(':cover', $data['cover'], \PDO::PARAM_STR);
            $stmt->bindValue(':mediadescription', $data['mediadescription'], \PDO::PARAM_STR);
            $stmt->bindValue(':contenttype', $data['contenttype'], \PDO::PARAM_STR);
            $stmt->bindValue(':createdat', $data['createdat'], \PDO::PARAM_STR);

            $stmt->execute();

            $this->logger->info("Inserted new post into database", ['post' => $data]);

            return new Post($data);
        } catch (\PDOException $e) {
            $this->logger->error(
                "PostMapper.insert: Exception occurred while inserting post",
                [
                    'data' => $data,
                    'exception' => $e->getMessage(),
                ]
            );

            throw new \RuntimeException("Failed to insert post into database: " . $e->getMessage());
        }
    }

    public function findPostser(?array $args = [], string $currentUserId): array
    {
        $this->logger->info("PostMapper.findPostser started");

        $offset = max((int)($args['postOffset'] ?? 0), 0);
        $limit = min(max((int)($args['postLimit'] ?? 10), 1), 20);

        $from = $args['from'] ?? null;
        $to = $args['to'] ?? null;
        $filterBy = $args['filterBy'] ?? [];
        $sortBy = $args['sortBy'] ?? null;
        $title = $args['title'] ?? null;
        $tag = $args['tag'] ?? null; 
        $postId = $args['postid'] ?? null;
        $userId = $args['userid'] ?? null;

        $whereClauses = ["p.feedid IS NULL"];
        $params = ['currentUserId' => $currentUserId];

        $trendlimit = 4;
        $trenddays = 17;

        if ($postId !== null) {
            $whereClauses[] = "p.postid = :postId";
            $params['postId'] = $postId;
        }

        if ($userId !== null) {
            $whereClauses[] = "p.userid = :userId";
            $params['userId'] = $userId;
        }

        if ($title !== null) {
            $whereClauses[] = "p.title ILIKE :title";
            $params['title'] = '%' . $title . '%';
        }

        if ($from !== null) {
            $whereClauses[] = "p.createdat >= :from";
            $params['from'] = $from;
        }

        if ($to !== null) {
            $whereClauses[] = "p.createdat <= :to";
            $params['to'] = $to;
        }

        if ($tag !== null) {
            if (!preg_match('/^[a-zA-Z0-9_]+$/', $tag)) {
                $this->logger->error('Invalid tag format provided', ['tag' => $tag]);
                return [];
            }
            $whereClauses[] = "t.name = :tag";
            $params['tag'] = $tag;
        }

        if (!empty($filterBy) && is_array($filterBy)) {
            $validTypes = [];
            $userFilters = [];

            $mapping = [
                'IMAGE' => 'image',
                'AUDIO' => 'audio',
                'VIDEO' => 'video',
                'TEXT' => 'text',
            ];

            $userMapping = [
                'FOLLOWED' => "p.userid IN (SELECT followedid FROM follows WHERE followerid = :currentUserId)",
                'FOLLOWER' => "p.userid IN (SELECT followerid FROM follows WHERE followedid = :currentUserId)",
            ];

            foreach ($filterBy as $type) {
                if (isset($mapping[$type])) {
                    $validTypes[] = $mapping[$type]; 
                } elseif (isset($userMapping[$type])) {
                    $userFilters[] = $userMapping[$type]; 
                }
            }

            if (!empty($validTypes)) {
                $placeholders = implode(", ", array_map(fn($k) => ":filter$k", array_keys($validTypes)));
                $whereClauses[] = "p.contenttype IN ($placeholders)";

                foreach ($validTypes as $key => $value) {
                    $params["filter$key"] = $value;
                }
            }

            if (!empty($userFilters)) {
                $whereClauses[] = "(" . implode(" OR ", $userFilters) . ")";
            }
        }

        $orderBy = match ($sortBy) {
            'NEWEST' => "p.createdat DESC",
            'FOLLOWER' => "isfollowing DESC, p.createdat DESC",
            'FOLLOWED' => "isfollowed DESC, p.createdat DESC",
            'TRENDING' => "amounttrending DESC, p.createdat DESC",
            'LIKES' => "amountlikes DESC, p.createdat DESC",
            'DISLIKES' => "amountdislikes DESC, p.createdat DESC",
            'VIEWS' => "amountviews DESC, p.createdat DESC",
            'COMMENTS' => "amountcomments DESC, p.createdat DESC",
            default => "p.createdat DESC",
        };

        $sql = sprintf(
            "SELECT 
                p.postid, 
                p.userid, 
                p.contenttype, 
                p.title, 
                p.media, 
                p.cover, 
                p.mediadescription, 
                p.createdat, 
                u.username, 
                u.img AS userimg,
                COALESCE(JSON_AGG(t.name) FILTER (WHERE t.name IS NOT NULL), '[]') AS tags,
                (SELECT COUNT(*) FROM user_post_likes WHERE postid = p.postid) as amountlikes,
                (SELECT COUNT(*) FROM user_post_dislikes WHERE postid = p.postid) as amountdislikes,
                (SELECT COUNT(*) FROM user_post_views WHERE postid = p.postid) as amountviews,
                (SELECT COUNT(*) FROM comments WHERE postid = p.postid) as amountcomments,
                COALESCE((
                    SELECT SUM(w.numbers)
                    FROM wallet w
                    WHERE w.postid = p.postid
                      AND w.createdat >= NOW() - INTERVAL '$trenddays days'
                ), 0) AS amounttrending,
                EXISTS (SELECT 1 FROM user_post_likes WHERE postid = p.postid AND userid = :currentUserId) as isliked,
                EXISTS (SELECT 1 FROM user_post_views WHERE postid = p.postid AND userid = :currentUserId) as isviewed,
                EXISTS (SELECT 1 FROM user_post_reports WHERE postid = p.postid AND userid = :currentUserId) as isreported,
                EXISTS (SELECT 1 FROM user_post_dislikes WHERE postid = p.postid AND userid = :currentUserId) as isdisliked,
                EXISTS (SELECT 1 FROM user_post_saves WHERE postid = p.postid AND userid = :currentUserId) as issaved,
                EXISTS (SELECT 1 FROM follows WHERE followedid = p.userid AND followerid = :currentUserId) as isfollowed,
                EXISTS (SELECT 1 FROM follows WHERE followerid = p.userid AND followedid = :currentUserId) as isfollowing
            FROM posts p
            JOIN users u ON p.userid = u.uid
            LEFT JOIN post_tags pt ON p.postid = pt.postid
            LEFT JOIN tags t ON pt.tagid = t.tagid
            WHERE %s
            GROUP BY p.postid, u.username, u.img
            ORDER BY %s
            LIMIT :limit OFFSET :offset",
            implode(" AND ", $whereClauses),
            $orderBy
        );

        $params['limit'] = $limit;
        $params['offset'] = $offset;

        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);

            $results = [];
            while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
                $row['tags'] = json_decode($row['tags'], true) ?? [];
                $results[] = new PostAdvanced([
                    'postid' => $row['postid'],
                    'userid' => $row['userid'],
                    'contenttype' => $row['contenttype'],
                    'title' => $row['title'],
                    'media' => $row['media'],
                    'cover' => $row['cover'],
                    'mediadescription' => $row['mediadescription'],
                    'createdat' => $row['createdat'],
                    'amountlikes' => (int)$row['amountlikes'],
                    'amountviews' => (int)$row['amountviews'],
                    'amountcomments' => (int)$row['amountcomments'],
                    'amountdislikes' => (int)$row['amountdislikes'],
                    'amounttrending' => (int)$row['amounttrending'],
                    'isliked' => (bool)$row['isliked'],
                    'isviewed' => (bool)$row['isviewed'],
                    'isreported' => (bool)$row['isreported'],
                    'isdisliked' => (bool)$row['isdisliked'],
                    'issaved' => (bool)$row['issaved'],
                    'tags' => $row['tags'],
                    'user' => [
                        'uid' => $row['userid'],
                        'username' => $row['username'],
                        'img' => $row['userimg'],
                        'isfollowed' => (bool)$row['isfollowed'],
                        'isfollowing' => (bool)$row['isfollowing'],
                    ],
                ]);
            }

            return $results;
        } catch (\PDOException $e) {
            $this->logger->error("Database error in findPostser", [
                'error' => $e->getMessage(),
                'sql' => $sql,
                'params' => $params,
            ]);
            return [];
        }
    }
}
