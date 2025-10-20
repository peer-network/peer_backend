<?php

declare(strict_types=1);

namespace Fawaz\Database;

use DateTime;
use Fawaz\Services\ContentFiltering\Types\ContentFilteringStrategies;
use PDO;
use Fawaz\App\Models\MultipartPost;
use Fawaz\App\Post;
use Fawaz\App\PostAdvanced;
use Fawaz\App\PostMedia;
use Fawaz\App\Specs\Specification;
use Fawaz\App\Specs\SpecificationSQLData;
use Fawaz\App\Role;
use Fawaz\App\Status;
use Fawaz\App\User;
use Fawaz\config\constants\ConstantsConfig;
use Fawaz\Services\ContentFiltering\ContentFilterServiceImpl;
use Fawaz\config\ContentReplacementPattern;
use Fawaz\Services\ContentFiltering\Types\ContentFilteringAction;
use Fawaz\Services\ContentFiltering\Types\ContentType;
use Fawaz\App\ValidationException;
use Fawaz\Utils\PeerLoggerInterface;

class PostMapper
{
    public function __construct(protected PeerLoggerInterface $logger, protected PDO $db)
    {
    }

    public function isSameUser(string $userid, string $currentUserId): bool
    {
        return $userid === $currentUserId;
    }

    public function fetchAll(int $offset, int $limit): array
    {
        $this->logger->debug("PostMapper.fetchAll started");

        $sql = "SELECT * FROM posts WHERE feedid IS NULL ORDER BY createdat DESC LIMIT :limit OFFSET :offset";

        try {
            $stmt = $this->db->prepare($sql);
            $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, \PDO::PARAM_INT);
            $stmt->execute();

            $results = array_map(fn ($row) => new Post($row), $stmt->fetchAll(\PDO::FETCH_ASSOC));

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
        } catch (\Exception $e) {
            $this->logger->error("Error fetching posts from database", [
                'error' => $e->getMessage(),
            ]);
            return [];
        }
    }

    public function isCreator(string $postid, string $currentUserId): bool
    {
        $this->logger->debug("PostMapper.isCreator started");

        $sql = "SELECT COUNT(*) FROM posts WHERE postid = :postid AND userid = :currentUserId";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['postid' => $postid, 'currentUserId' => $currentUserId]);

        return (bool) $stmt->fetchColumn();
    }

    public function postExistsById(string $postId): bool
    {
        $this->logger->debug("PostMapper.postExistsById started");

        $stmt = $this->db->prepare("SELECT COUNT(*) FROM posts WHERE postid = :postId");
        $stmt->bindValue(':postId', $postId, \PDO::PARAM_STR);
        $stmt->execute();

        return (bool) $stmt->fetchColumn();
    }

    public function isNewsFeedExist(string $feedid): bool
    {
        $this->logger->debug("PostMapper.isNewsFeedExist started");

        $sql = "SELECT COUNT(*) FROM newsfeed WHERE feedid = :feedid";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['feedid' => $feedid]);
        return (bool) $stmt->fetchColumn();
    }

    public function isHasAccessInNewsFeed(string $chatid, string $currentUserId): bool
    {
        $this->logger->debug("PostMapper.isHasAccessInNewsFeed started");

        $sql = "SELECT COUNT(*) FROM chatparticipants WHERE chatid = :chatid AND userid = :currentUserId";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['chatid' => $chatid, 'currentUserId' => $currentUserId]);

        return (bool) $stmt->fetchColumn();
    }

    public function getChatFeedsByID(string $feedid): array
    {
        $this->logger->debug("PostMapper.getChatFeedsByID started");

        $sql = "SELECT * FROM posts WHERE feedid = :feedid";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['feedid' => $feedid]);

        $results = [];
        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $results[] = new Post($row, [], false);
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
        $this->logger->debug("PostMapper.loadByTitle started");

        $sql = "SELECT * FROM posts WHERE title LIKE :title AND feedid IS NULL";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['title' => '%' . $title . '%']);
        $data = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        if (!empty($data)) {
            return array_map(fn ($row) => new Post($row, [], false), $data);
        }

        $this->logger->warning("No posts found with title", ['title' => $title]);
        return [];
    }

    public function loadById(string $id): Post|false
    {
        $this->logger->debug("PostMapper.loadById started");

        $sql = "SELECT * FROM posts WHERE postid = :postid AND feedid IS NULL";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['postid' => $id]);
        $data = $stmt->fetch(\PDO::FETCH_ASSOC);

        if ($data !== false) {
            return new Post($data, [], false);
        }

        $this->logger->warning("No post found with id", ['id' => $id]);
        return false;
    }

    public function fetchPostsByType(
        string $currentUserId, 
        string $userid, 
        array $specifications,
        int $limitPerType = 5, 
        ?string $contentFilterBy = null
    ): array {        
        $specsSQL = array_map(fn(Specification $spec) => $spec->toSql(), $specifications);

        $allSpecs = SpecificationSQLData::merge($specsSQL);

        $whereClauses = $allSpecs->whereClauses;

        $whereClauses[] = "sub.row_num <= :limit";

        $whereClausesString = implode(" AND ", $whereClauses);

        $contentFilterService = new ContentFilterServiceImpl(
            ContentFilteringStrategies::searchById,
            $contentFilterBy
        );

        $sql = sprintf(
            "SELECT 
                sub.postid, 
                sub.userid, 
                sub.contenttype, 
                sub.title, 
                sub.media, 
                sub.createdat,
                pi.reports AS post_reports,
                p.visibility_status as post_visibility_status
            FROM (
                SELECT p.*, ROW_NUMBER() OVER (PARTITION BY p.contenttype ORDER BY p.createdat DESC) AS row_num
                FROM posts p
                JOIN users u ON p.userid = u.uid
                WHERE p.userid = :userid 
                AND p.feedid IS NULL
            ) sub
            LEFT JOIN post_info pi ON sub.postid = pi.postid AND pi.userid = sub.userid
            WHERE %s
            ORDER BY sub.contenttype, sub.createdat DESC",
            $whereClausesString
        );

        $stmt = $this->db->prepare($sql);
        $params = $allSpecs->paramsToPrepare;
        $params['userid'] = $userid;
        $params['limit'] = $limitPerType;

        $stmt->execute($params);

        $unfidtered_result = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        $result = [];

        foreach ($unfidtered_result as $row) {
            $post_reports = (int)$row['post_reports'];

            if ($contentFilterService->getContentFilterAction(
                ContentType::post,
                ContentType::post,
                $post_reports,
                $currentUserId,
                $row['userid'],
                $row['post_visibility_status']
            ) == ContentFilteringAction::replaceWithPlaceholder) {
                $replacer = ContentReplacementPattern::hidden;
                $row['title'] = $replacer->postTitle();
                $row['media'] = $replacer->postMedia();
            }
            $result[] = $row;
        }
        return $result;
    }

    public function fetchComments(string $postid): array
    {
        $this->logger->debug("PostMapper.fetchComments started");

        $sql = "SELECT * FROM comments WHERE postid = :postid";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['postid' => $postid]);

        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function fetchLikes(string $postid): array
    {
        $this->logger->debug("PostMapper.fetchLikes started");

        $sql = "SELECT * FROM user_post_likes WHERE postid = :postid";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['postid' => $postid]);

        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function fetchDislikes(string $postid): array
    {
        $this->logger->debug("PostMapper.fetchDislikes started");

        $sql = "SELECT * FROM user_post_dislikes WHERE postid = :postid";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['postid' => $postid]);

        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function fetchSaves(string $postid): array
    {
        $this->logger->debug("PostMapper.fetchSaves started");

        $sql = "SELECT * FROM user_post_saves WHERE postid = :postid";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['postid' => $postid]);

        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function fetchViews(string $postid): array
    {
        $this->logger->debug("PostMapper.fetchViews started");

        $sql = "SELECT * FROM user_post_views WHERE postid = :postid";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['postid' => $postid]);

        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function fetchReports(string $postid): array
    {
        $this->logger->debug("PostMapper.fetchReports started");

        $sql = "SELECT * FROM user_post_reports WHERE postid = :postid";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['postid' => $postid]);

        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function countLikes(string $postid): int
    {
        $this->logger->debug("PostMapper.countLikes started");

        $sql = "SELECT COUNT(*) FROM user_post_likes WHERE postid = :postid";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['postid' => $postid]);
        return (int) $stmt->fetchColumn();
    }

    public function countDisLikes(string $postid): int
    {
        $this->logger->debug("PostMapper.countLikes started");

        $sql = "SELECT COUNT(*) FROM user_post_dislikes WHERE postid = :postid";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['postid' => $postid]);
        return (int) $stmt->fetchColumn();
    }

    public function countViews(string $postid): int
    {
        $this->logger->debug("PostMapper.countViews started");

        $sql = "SELECT COUNT(*) FROM user_post_views WHERE postid = :postid";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['postid' => $postid]);
        return (int) $stmt->fetchColumn();
    }

    public function countComments(string $postid): int
    {
        $this->logger->debug("PostMapper.countComments started");

        $sql = "SELECT COUNT(*) FROM comments WHERE postid = :postid";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['postid' => $postid]);
        return (int) $stmt->fetchColumn();
    }

    public function isLiked(string $postid, string $userid): bool
    {
        $this->logger->debug("PostMapper.isLiked started");

        $sql = "SELECT COUNT(*) FROM user_post_likes WHERE postid = :postid AND userid = :userid";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['postid' => $postid, 'userid' => $userid]);
        return (bool) $stmt->fetchColumn();
    }

    public function isViewed(string $postid, string $userid): bool
    {
        $this->logger->debug("PostMapper.isViewed started");

        $sql = "SELECT COUNT(*) FROM user_post_views WHERE postid = :postid AND userid = :userid";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['postid' => $postid, 'userid' => $userid]);
        return (bool) $stmt->fetchColumn();
    }

    public function isReported(string $postid, string $userid): bool
    {
        $this->logger->debug("PostMapper.isReported started");

        $sql = "SELECT COUNT(*) FROM user_post_reports WHERE postid = :postid AND userid = :userid";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['postid' => $postid, 'userid' => $userid]);
        return (bool) $stmt->fetchColumn();
    }

    public function isDisliked(string $postid, string $userid): bool
    {
        $this->logger->debug("PostMapper.isDisliked started");

        $sql = "SELECT COUNT(*) FROM user_post_dislikes WHERE postid = :postid AND userid = :userid";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['postid' => $postid, 'userid' => $userid]);
        return (bool) $stmt->fetchColumn();
    }

    public function isSaved(string $postid, string $userid): bool
    {
        $this->logger->debug("PostMapper.isSaved started");

        $sql = "SELECT COUNT(*) FROM user_post_saves WHERE postid = :postid AND userid = :userid";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['postid' => $postid, 'userid' => $userid]);
        return (bool) $stmt->fetchColumn();
    }

    public function isFollowing(string $userid, string $currentUserId): bool
    {
        $this->logger->debug("PostMapper.isFollowing started");

        $sql = "SELECT COUNT(*) FROM follows WHERE followedId = :userid AND followerId = :currentUserId";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['userid' => $userid, 'currentUserId' => $currentUserId]);

        return (bool) $stmt->fetchColumn();
    }

    public function userInfoForPosts(string $id): array
    {
        $this->logger->debug("PostMapper.userInfoForPosts started");

        $sql = "SELECT * FROM users WHERE uid = :id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['id' => $id]);
        $data = $stmt->fetch(\PDO::FETCH_ASSOC);

        if ($data === false) {
            $this->logger->warning("No user found with id: " . $id);
            return [];
        }

        return (new User($data, [], false))->getArrayCopy();
    }

    // Create a post
    public function insert(Post $post): Post
    {
        $this->logger->debug("PostMapper.insert started");

        $data = $post->getArrayCopy();

        $query = "INSERT INTO posts 
                  (postid, userid, feedid, contenttype, title, mediadescription, media, cover, createdat)
                  VALUES 
                  (:postid, :userid, :feedid, :contenttype, :title, :mediadescription, :media, :cover, :createdat)";

        try {
            $stmt = $this->db->prepare($query);

            // Explicitly bind each value
            $stmt->bindValue(':postid', $data['postid'], \PDO::PARAM_STR);
            $stmt->bindValue(':userid', $data['userid'], \PDO::PARAM_STR);
            $stmt->bindValue(':feedid', $data['feedid'], \PDO::PARAM_STR);
            $stmt->bindValue(':contenttype', $data['contenttype'], \PDO::PARAM_STR);
            $stmt->bindValue(':title', $data['title'], \PDO::PARAM_STR);
            $stmt->bindValue(':mediadescription', $data['mediadescription'], \PDO::PARAM_STR);
            $stmt->bindValue(':media', $data['media'], \PDO::PARAM_STR);
            //$stmt->bindValue(':cover', $data['cover'], \PDO::PARAM_STR);
            $stmt->bindValue(':cover', $data['cover'] ?? null, $data['cover'] !== null ? \PDO::PARAM_STR : \PDO::PARAM_NULL);
            $stmt->bindValue(':createdat', $data['createdat'], \PDO::PARAM_STR);

            $stmt->execute();

            $queryUpdateProfile = "UPDATE users_info SET amountposts = amountposts + 1 WHERE userid = :userid";
            $stmt = $this->db->prepare($queryUpdateProfile);
            $stmt->bindValue(':userid', $data['userid'], \PDO::PARAM_STR);
            $stmt->execute();

            $this->logger->info("Inserted new post into database");

            return new Post($data);
        } catch (\PDOException $e) {
            $this->logger->error(
                "PostMapper.insert: Exception occurred while inserting post",
                [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]
            );

            throw new \RuntimeException("Failed to insert post into database: " . $e->getMessage());
        } catch (\Exception $e) {
            $this->logger->error(
                "PostMapper.insert: Exception occurred while inserting post",
                [
                    'error' => $e->getMessage()
                ]
            );

            throw new \RuntimeException("Failed to insert post into database: " . $e->getMessage());
        }
    }

    // Create a post Media
    public function insertmed(PostMedia $post): PostMedia
    {
        $this->logger->debug("PostMapper.insertmed started");

        $data = $post->getArrayCopy();

        $query = "INSERT INTO posts_media 
                  (postid, contenttype, media, options)
                  VALUES 
                  (:postid, :contenttype, :media, :options)";

        try {
            $stmt = $this->db->prepare($query);
            if (!$stmt) {
                throw new \RuntimeException("SQL prepare() failed: " . implode(", ", $this->db->errorInfo()));
            }

            $stmt->bindValue(':postid', $data['postid'], \PDO::PARAM_STR);
            $stmt->bindValue(':contenttype', $data['contenttype'], \PDO::PARAM_STR);
            $stmt->bindValue(':media', $data['media'], \PDO::PARAM_STR);
            $stmt->bindValue(':options', $data['options'] ?? null, $data['options'] !== null ? \PDO::PARAM_STR : \PDO::PARAM_NULL);

            $stmt->execute();

            $this->logger->info("Inserted new PostMedia into database");

            return new PostMedia($data);
        } catch (\PDOException $e) {
            $this->logger->error(
                "PostMapper.insertmed: Exception occurred while inserting PostMedia",
                [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]
            );

            throw new \RuntimeException("Failed to insert PostMedia into database: " . $e->getMessage());
        } catch (\Exception $e) {
            $this->logger->error(
                "PostMapper.insertmed: Exception occurred while inserting PostMedia",
                [
                    'error' => $e->getMessage()
                ]
            );

            throw new \RuntimeException("Failed to insert PostMedia into database: " . $e->getMessage());
        }
    } 

    public function findPostser(string $currentUserId, ?array $args = []): array
    {
        $this->logger->debug("PostMapper.findPostser started");

        $offset = max((int)($args['offset'] ?? 0), 0);
        $limit  = min(max((int)($args['limit']  ?? 10), 1), 20);

        $post_report_amount_to_hide = ConstantsConfig::contentFiltering()['REPORTS_COUNT_TO_HIDE_FROM_IOS']['POST'];

        $trenddays = 7;

        $contentFilterBy = $args['contentFilterBy'] ?? null;
        $from     = $args['from']     ?? null;
        $to       = $args['to']       ?? null;
        $filterBy = $args['filterBy'] ?? [];
        $Ignorlist = $args['IgnorList'] ?? 'NO';
        $sortBy   = $args['sortBy']   ?? null;
        $title    = $args['title']    ?? null;
        $tag      = $args['tag']      ?? null;
        $tags     = $args['tags']     ?? [];
        $postId   = $args['postid']   ?? null;
        $userId   = $args['userid']   ?? null;
        $contentFilterService = new ContentFilterServiceImpl(
            ContentFilteringStrategies::postFeed,
            $contentFilterBy
        );

        $params = ['currentUserId' => $currentUserId];
        $whereClauses = ["p.feedid IS NULL"];

        // Show only Valid Content
        $whereClauses[] = "p.status = :postStatus";
        $params['postStatus'] = ConstantsConfig::post()['STATUS']['PUBLISHED'];

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
                $this->logger->warning('Invalid tag format provided', ['tag' => $tag]);
                return [];
            }
            $whereClauses[] = "t.name ILIKE :tag";
            $params['tag'] = $tag;
        }
        if (!empty($tags)) {
            $tagPlaceholders = [];
            foreach ($tags as $i => $tg) {
                $ph = "tag$i";
                $tagPlaceholders[] = ':' . $ph;
                $params[$ph] = $tg;
            }
            $whereClauses[] = "t.name IN (" . implode(", ", $tagPlaceholders) . ")";
        }

        // Nur normale User & Admin-Accounts
        $whereClauses[] = 'u.status = :stNormal AND u.roles_mask IN (:roleUser, :roleAdmin)';
        $params['stNormal']  = Status::NORMAL;
        $params['roleUser']  = Role::USER;
        $params['roleAdmin'] = Role::ADMIN;

        // Content Filtering: eigene Posts immer sichtbar
        if ($contentFilterService->getContentFilterAction(
            ContentType::post,
            ContentType::post
        ) === ContentFilteringAction::hideContent) {
            $params['post_report_amount_to_hide'] = $post_report_amount_to_hide;
            $whereClauses[] = "NOT EXISTS (
                    SELECT 1
                    FROM posts HiddenContentFiltering_posts
                    LEFT JOIN post_info HiddenContentFiltering_post_info ON HiddenContentFiltering_post_info.postid = HiddenContentFiltering_posts.postid
                    WHERE HiddenContentFiltering_posts.postid = p.postid
                    AND (
                        (HiddenContentFiltering_post_info.reports >= :post_report_amount_to_hide AND HiddenContentFiltering_posts.visibility_status = 'normal')
                        OR 
                        HiddenContentFiltering_posts.visibility_status = 'hidden'
                    )
                )";
            }

        // Filter: Content-Typ / Beziehungen / Viewed
        if (!empty($filterBy) && is_array($filterBy)) {
            $validTypes  = [];
            $userFilters = [];

            $mapping = [
                'IMAGE' => 'image',
                'AUDIO' => 'audio',
                'VIDEO' => 'video',
                'TEXT'  => 'text',
            ];
            $userMapping = [
                'FOLLOWED' => "p.userid IN (SELECT followedid FROM follows WHERE followerid = :currentUserId)",
                'FOLLOWER' => "p.userid IN (SELECT followerid FROM follows WHERE followedid = :currentUserId)",
                'FRIENDS'  => "EXISTS (
                    SELECT 1 FROM follows f1
                    WHERE f1.followerid = :currentUserId AND f1.followedid = p.userid
                    AND EXISTS (
                        SELECT 1 FROM follows f2
                        WHERE f2.followerid = p.userid AND f2.followedid = :currentUserId
                    )
                )",
            ];

            foreach ($filterBy as $type) {
                if (isset($mapping[$type])) {
                    $validTypes[] = $mapping[$type];
                } elseif (isset($userMapping[$type])) {
                    $userFilters[] = $userMapping[$type];
                }
            }

            // eigene Posts auch bei FOLLOWED/FOLLOWER einschließen
            if (in_array('FOLLOWED', $filterBy, true) || in_array('FOLLOWER', $filterBy, true)) {
                $userFilters[] = "p.userid = :currentUserId";
            }

            if (in_array('VIEWED', $filterBy, true)) {
                $whereClauses[] = "EXISTS (
                    SELECT 1 FROM user_post_views upv
                    WHERE upv.postid = p.postid
                      AND upv.userid = :currentUserId
                )";
            }

            if (!empty($validTypes)) {
                $placeholders = implode(", ", array_map(fn ($k) => ":filter$k", array_keys($validTypes)));
                $whereClauses[] = "p.contenttype IN ($placeholders)";
                foreach ($validTypes as $key => $value) {
                    $params["filter$key"] = $value;
                }
            }

            if (!empty($userFilters)) {
                $whereClauses[] = "(" . implode(" OR ", $userFilters) . ")";
            }
        }

        // Blockliste
        if ($Ignorlist === 'YES') {
            $whereClauses[] = "p.userid NOT IN (
                SELECT blockedid FROM user_block_user WHERE blockerid = :currentUserId
                UNION
                SELECT blockerid FROM user_block_user WHERE blockedid = :currentUserId
            )";
        }

        // FOR_ME: nicht eigene & optional noch nicht gesehene
        if ($sortBy === 'FOR_ME') {
            $whereClauses[] = "p.userid != :currentUserId";

            if (!is_array($filterBy) || !in_array('VIEWED', $filterBy, true)) {
                $whereClauses[] = "NOT EXISTS (
                    SELECT 1 FROM user_post_views upv
                    WHERE upv.postid = p.postid
                      AND upv.userid = :currentUserId
                )";
            }
        }

        $orderByClause = match ($sortBy) {
            'FOR_ME'   => "ORDER BY isfriend DESC, isfollowed DESC, friendoffriends DESC, createdat DESC",
            'NEWEST'   => "ORDER BY createdat DESC",
            'OLDEST'   => "ORDER BY createdat ASC",
            'TRENDING' => "ORDER BY amounttrending DESC, createdat DESC",
            'LIKES'    => "ORDER BY amountlikes DESC, createdat DESC",
            'DISLIKES' => "ORDER BY amountdislikes DESC, createdat DESC",
            'VIEWS'    => "ORDER BY amountviews DESC, createdat DESC",
            'COMMENTS' => "ORDER BY amountcomments DESC, createdat DESC",
            'FOLLOWER' => "ORDER BY isfollowing DESC, createdat DESC",
            'FOLLOWED' => "ORDER BY isfollowed DESC, createdat DESC",
            'RELEVANT' => "ORDER BY isfollowed DESC, isliked DESC, amountcomments DESC, createdat DESC",
            'FRIENDS'  => "ORDER BY isfriend DESC, createdat DESC",
            default    => "ORDER BY createdat DESC",
        };

        // --- WICHTIG: KEINE aktiven Ads (NOT EXISTS)
        $sql = "
            WITH base_posts AS (
                SELECT 
                    p.postid,
                    p.userid,
                    p.contenttype,
                    p.title,
                    p.media,
                    p.cover,
                    p.mediadescription,
                    p.visibility_status as post_visibility_status,
                    p.createdat AS createdat,
                    u.username,
                    u.slug,
                    u.img AS userimg,
                    u.status AS userstatus,
                    u.visibility_status as user_visibility_status,

                    -- Moderations-/Report-Daten
                    MAX(ui.reports) AS user_reports,
                    MAX(pi.reports) AS post_reports,

                    COALESCE(JSON_AGG(t.name) FILTER (WHERE t.name IS NOT NULL), '[]') AS tags,

                    (SELECT COUNT(*) FROM user_post_likes    WHERE postid = p.postid) AS amountlikes,
                    (SELECT COUNT(*) FROM user_post_dislikes WHERE postid = p.postid) AS amountdislikes,
                    (SELECT COUNT(*) FROM user_post_views    WHERE postid = p.postid) AS amountviews,
                    (SELECT COUNT(*) FROM comments           WHERE postid = p.postid) AS amountcomments,

                    COALESCE((
                        SELECT SUM(numbers)
                        FROM logwins
                        WHERE postid = p.postid
                          AND createdat >= NOW() - INTERVAL '$trenddays days'
                    ), 0) AS amounttrending,

                    EXISTS (SELECT 1 FROM user_post_likes    WHERE postid = p.postid AND userid = :currentUserId) AS isliked,
                    EXISTS (SELECT 1 FROM user_post_views    WHERE postid = p.postid AND userid = :currentUserId) AS isviewed,
                    EXISTS (SELECT 1 FROM user_post_reports  WHERE postid = p.postid AND userid = :currentUserId) AS isreported,
                    EXISTS (SELECT 1 FROM user_post_dislikes WHERE postid = p.postid AND userid = :currentUserId) AS isdisliked,
                    EXISTS (SELECT 1 FROM user_post_saves    WHERE postid = p.postid AND userid = :currentUserId) AS issaved,

                    EXISTS (SELECT 1 FROM follows WHERE followedid = p.userid AND followerid = :currentUserId) AS isfollowed,
                    EXISTS (SELECT 1 FROM follows WHERE followerid = p.userid AND followedid = :currentUserId) AS isfollowing,

                    EXISTS (
                        SELECT 1 FROM follows f1
                        WHERE f1.followerid = :currentUserId
                          AND f1.followedid = p.userid
                          AND EXISTS (
                              SELECT 1 FROM follows f2
                              WHERE f2.followerid = p.userid
                                AND f2.followedid = :currentUserId
                          )
                    ) AS isfriend,

                    EXISTS (
                        SELECT 1 FROM follows f1
                        WHERE f1.followerid IN (
                            SELECT followedid FROM follows WHERE followerid = :currentUserId
                        ) AND f1.followedid = p.userid
                    ) AS friendoffriends

                FROM posts p
                JOIN users u           ON p.userid = u.uid
                LEFT JOIN post_info pi ON pi.postid = p.postid AND pi.userid = p.userid
                LEFT JOIN users_info ui ON ui.userid = p.userid
                LEFT JOIN post_tags pt  ON pt.postid = p.postid
                LEFT JOIN tags t        ON t.tagid = pt.tagid
                WHERE " . implode(" AND ", $whereClauses) . "
                AND NOT EXISTS (
                  SELECT 1
                  FROM advertisements a
                  WHERE a.postid = p.postid
                    AND a.timestart <= NOW()
                    AND a.timeend > NOW()
                )
                GROUP BY
                    p.postid, p.userid, p.contenttype, p.title, p.media, p.cover,
                    p.mediadescription, p.createdat, p.visibility_status,
                    u.username, u.slug, userimg, userstatus, u.visibility_status
            )
            SELECT * FROM base_posts
            $orderByClause
            LIMIT :limit OFFSET :offset
        ";

        try {
            $stmt = $this->db->prepare($sql);
            foreach ($params as $k => $v) {
                $stmt->bindValue(':' . ltrim($k, ':'), $v);
            }
            $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, \PDO::PARAM_INT);
            $stmt->execute();

            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            $results = array_map(function (array $row) use ($contentFilterService, $currentUserId) {
                $row['tags'] = json_decode($row['tags'], true) ?? [];

                // User-Placeholder anwenden, falls nötig
                $user_reports = (int)$row['user_reports'];

                if ($contentFilterService->getContentFilterAction(
                    ContentType::post,
                    ContentType::user,
                    $user_reports,
                    $currentUserId,
                    $row['userid'],
                    $row['user_visibility_status']
                ) === ContentFilteringAction::replaceWithPlaceholder) {
                    $replacer       = ContentReplacementPattern::hidden;
                    $row['username'] = $replacer->username();
                    $row['userimg'] = $replacer->profilePicturePath();
                }

                return self::mapRowToPost($row);
            }, $rows);

            //$this->logger->info('findPostser.results', ['postArray' => array_map(fn(PostAdvanced $p) => $p->getArrayCopy(), $results)]);

            return $results;

        } catch (\Throwable $e) {
            $this->logger->error('General error in findPostser', [
                'error' => $e->getMessage(),
            ]);
            return [];
        }
    }

    private static function mapRowToPost(array $row): PostAdvanced
    {
        return new PostAdvanced([
            'postid' => (string)$row['postid'],
            'userid' => (string)$row['userid'],
            'contenttype' => (string)$row['contenttype'],
            'title' => (string)$row['title'],
            'media' => (string)$row['media'],
            'cover' => (string)$row['cover'],
            'mediadescription' => (string)$row['mediadescription'],
            'createdat' => (string)$row['createdat'],
            'amountlikes' => (int)$row['amountlikes'],
            'amountreports' => (int)$row['post_reports'],
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
                'uid' => (string)$row['userid'],
                'username' => (string)$row['username'],
                'slug' => (int)$row['slug'],
                'img' => (string)$row['userimg'],
                'isfollowed' => (bool)$row['isfollowed'],
                'isfollowing' => (bool)$row['isfollowing'],
                'isfriend' => (bool)$row['isfriend'],
            ],
        ]);
    }

    /**
     * Move Uploaded File to Media Folder
     */
    public function handelFileMoveToMedia(string $uploadedFiles): array
    {
        $fileObjs = explode(',', $uploadedFiles);

        $uploadedFilesObj = [];
        try {
            if (is_array($fileObjs) && !empty($fileObjs)) {
                $multipartPost = new MultipartPost(['media' => $fileObjs], [], false);
                $uploadedFilesObj = $multipartPost->moveFileTmpToMedia();
            }
        } catch (\Exception $e) {
            $this->logger->info("PostMapper.handelFileMoveToMedia Error". $e->getMessage());
        }

        return $uploadedFilesObj;
    }

    /**
     * Expire Token
     *
     */
    public function updateTokenStatus(string $userId): void
    {
        $this->logger->debug("PostMapper.updateTokenStatus started");

        try {
            $updateSql = "
                    UPDATE eligibility_token
                    SET status = :status
                    WHERE userid = :userid AND status = 'FILE_UPLOADED'
                ";
            $updateStmt = $this->db->prepare($updateSql);
            $updateStmt->bindValue(':status', 'POST_CREATED', \PDO::PARAM_STR);
            $updateStmt->bindValue(':userid', $userId, \PDO::PARAM_STR);
            $updateStmt->execute();

            $this->logger->info("PostMapper.updateTokenStatus updated successfully with POST_CREATED status");

        } catch (\PDOException $e) {
            $this->logger->error("PostMapper.updateTokenStatus: Exception occurred while update token status", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

        } catch (\Throwable $e) {
            $this->logger->error("PostMapper.updateTokenStatus: Exception occurred while update token status", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }

    /**
     * Revert Moved File to /tmp Folder
     */
    public function revertFileToTmp(string $uploadedFiles): void
    {
        $fileObjs = explode(',', $uploadedFiles);
        try {
            if (is_array($fileObjs) && !empty($fileObjs)) {
                $multipartPost = new MultipartPost(['media' => $fileObjs], [], false);
                $multipartPost->revertFileToTmp();
            }
        } catch (\Exception $e) {
            $this->logger->info("PostMapper.revertFileToTmp Error". $e->getMessage());
        }
    }

    /**
     * Get Interactions based on Filter
     */
    public function getInteractions(string $getOnly, string $postOrCommentId, string $currentUserId, int $offset, int $limit, ?string $contentFilterBy = null): array
    {
        $this->logger->debug("PostMapper.getInteractions started");

        try {
            $this->logger->debug("PostMapper.fetchViews started");

            $needleTable = 'user_post_likes';
            $needleColumn = 'postid';

            if ($getOnly == 'VIEW') {
                $needleTable = 'user_post_views';
            } elseif ($getOnly == 'LIKE') {
                $needleTable = 'user_post_likes';
            } elseif ($getOnly == 'DISLIKE') {
                $needleTable = 'user_post_dislikes';
            } elseif ($getOnly == 'COMMENTLIKE') {
                $needleTable = 'user_comment_likes';
                $needleColumn = 'commentid';
            }

            $sql = "SELECT 
                        u.uid, 
                        u.username, 
                        u.slug, 
                        u.img, 
                        u.status, 
                        u.visibility_status as user_visibility_status,
                        (f1.followerid IS NOT NULL) AS isfollowing,
                        (f2.followerid IS NOT NULL) AS isfollowed,
                        COALESCE(ui.reports, 0) AS user_reports
                    FROM $needleTable uv 
                    LEFT JOIN users u ON u.uid = uv.userid
                    LEFT JOIN users_info ui ON ui.userid = u.uid  
                    LEFT JOIN 
                        follows f1 
                        ON u.uid = f1.followerid AND f1.followedid = :currentUserId 
                    LEFT JOIN 
                        follows f2 
                        ON u.uid = f2.followedid AND f2.followerid = :currentUserId
                    WHERE $needleColumn = :postid
                    LIMIT :limit OFFSET :offset";

            $stmt = $this->db->prepare($sql);
            $stmt->bindParam(':postid', $postOrCommentId, \PDO::PARAM_STR);
            $stmt->bindParam(':currentUserId', $currentUserId, \PDO::PARAM_STR);
            $stmt->bindParam(':limit', $limit, \PDO::PARAM_INT);
            $stmt->bindParam(':offset', $offset, \PDO::PARAM_INT);

            $stmt->execute();

            $userResults =  $stmt->fetchAll(\PDO::FETCH_ASSOC);

            $contentFilterService = null;
            if ($contentFilterBy !== null) {
                $contentFilterService = new ContentFilterServiceImpl(
                    ContentFilteringStrategies::postFeed,
                    $contentFilterBy
                );
            }
            $userResultObj = [];
            foreach ($userResults as $key => $prt) {
                if ($contentFilterService !== null) {
                    $user_reports = (int)($prt['user_reports'] ?? 0);

                    $action = $contentFilterService->getContentFilterAction(
                        ContentType::post,
                        ContentType::user,
                        $user_reports,
                        $currentUserId,
                        $prt['uid'],
                        $prt['user_visibility_status']
                    );

                    if ($action === ContentFilteringAction::replaceWithPlaceholder) {
                        $replacer = ContentReplacementPattern::hidden;
                        $prt['username'] = $replacer->username();
                        $prt['img']      = $replacer->profilePicturePath();
                    }
                }
                $userResultObj[$key] = (new User($prt, [], false))->getArrayCopy();
                $userResultObj[$key]['isfollowed'] = $prt['isfollowed'];
                $userResultObj[$key]['isfollowing'] = $prt['isfollowing'];
            }

            return $userResultObj;
        } catch (\PDOException $e) {
            $this->logger->error("Error fetching posts from database", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return [];
        } catch (\Exception $e) {
            $this->logger->error("Error fetching posts from database", [
                'error' => $e->getMessage(),
            ]);
            return [];
        }
    }

    /**
     * Get GuestListPost based on Filter
     */
    public function getGuestListPost(array $args = []): array
    {
        $this->logger->debug("PostMapper.getGuestListPost started");

        $offset = 0;
        $limit = 1;

        $postId = $args['postid'] ?? null;

        $whereClauses = ["p.feedid IS NULL"];
        $joinClausesString = "
            users u ON p.userid = u.uid
            LEFT JOIN post_tags pt ON p.postid = pt.postid
            LEFT JOIN tags t ON pt.tagid = t.tagid
            LEFT JOIN post_info pi ON p.postid = pi.postid AND pi.userid = p.userid
            LEFT JOIN users_info ui ON p.userid = ui.userid
        ";

        if ($postId !== null) {
            $whereClauses[] = "p.postid = :postId";
            $params['postId'] = $postId;
        }

        $whereClauses[] = 'u.status = 0 AND (u.roles_mask = 0 OR u.roles_mask = 16)';

        $orderBy = "p.createdat DESC";

        $whereClausesString = implode(" AND ", $whereClauses);

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
                u.slug,
                u.img AS userimg,
                MAX(u.status) AS user_status,
                MAX(ui.count_content_moderation_dismissed) AS user_count_content_moderation_dismissed,
                MAX(pi.count_content_moderation_dismissed) AS post_count_content_moderation_dismissed,
                MAX(ui.reports) AS user_reports,
                MAX(pi.reports) AS post_reports,
                COALESCE(JSON_AGG(t.name) FILTER (WHERE t.name IS NOT NULL), '[]') AS tags,
                (SELECT COUNT(*) FROM user_post_likes WHERE postid = p.postid) as amountlikes,
                (SELECT COUNT(*) FROM user_post_dislikes WHERE postid = p.postid) as amountdislikes,
                (SELECT COUNT(*) FROM user_post_views WHERE postid = p.postid) as amountviews,
                (SELECT COUNT(*) FROM comments WHERE postid = p.postid) as amountcomments
            FROM posts p
            JOIN %s
            WHERE %s
            GROUP BY p.postid, u.username, u.slug, u.img
            ORDER BY %s
            LIMIT :limit OFFSET :offset",
            $joinClausesString,
            $whereClausesString,
            $orderBy
        );

        $params['limit'] = $limit;
        $params['offset'] = $offset;

        try {
            $stmt = $this->db->prepare($sql);

            $results = [];
            $stmt->execute($params);
            $row = $stmt->fetch(\PDO::FETCH_ASSOC);

            if (!$row) {
                $this->logger->warning("PostMapper.getGuestListPost No posts found for the given criteria");
                return [];
            }
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
                'amounttrending' => 0,
                'isliked' => false,
                'isviewed' => false,
                'isreported' => false,
                'isdisliked' => false,
                'issaved' => false,
                'tags' => $row['tags'],
                'user' => [
                    'uid' => $row['userid'],
                    'username' => $row['username'],
                    'slug' => $row['slug'],
                    'img' => $row['userimg'],
                    'isfollowed' => false,
                    'isfollowing' => false,
                ],
            ], [], false);

            return (!empty($results) ? $results : []);
        } catch (\PDOException $e) {
            $this->logger->error("Database error in PostMapper.getGuestListPost", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return [];
        } catch (\Exception $e) {
            $this->logger->error('Database error in PostMapper.getGuestListPost', [
                'error' => $e->getMessage(),
            ]);
            return [];
        }
    }

    /*
    * Add or Update Eligibility Token for post
    */
    public function addOrUpdateEligibilityToken01(string $userId, string $eligibilityToken, string $status): void
    {

        try {
            $this->logger->debug("PostMapper.addOrUpdateEligibilityToken started");
            $query = "SELECT COUNT(*) FROM eligibility_token WHERE userid = :userid AND token = :token";
            $stmt = $this->db->prepare($query);
            $stmt->bindValue(':userid', $userId, \PDO::PARAM_STR);
            $stmt->bindValue(':token', $eligibilityToken, \PDO::PARAM_STR);
            $stmt->execute();

            $existingToken = $stmt->fetch(\PDO::FETCH_ASSOC);

            if ($existingToken) {
                // Token exists, update it
                $this->logger->info("Updating existing eligibility token for user: " . $userId);
                $query = "UPDATE eligibility_token 
                        SET token = :token, status = :status
                        WHERE userid = :userid";
            } else {
                // Token does not exist, insert a new one
                $this->logger->info("Inserting new eligibility token for user: " . $userId);
                $query = "INSERT INTO eligibility_token 
                        (userid, token, expiresat) 
                        VALUES (:userid, :token, :expiresat)";

                $stmt = $this->db->prepare($query);
                $stmt->bindValue(':userid', $userId, \PDO::PARAM_STR);
                $stmt->bindValue(':token', $eligibilityToken, \PDO::PARAM_STR);
                $stmt->bindValue(':expiresat', date('Y-m-d H:i:s', strtotime('+5 minutes')), \PDO::PARAM_STR);

                $stmt->execute();
            }

        } catch (\Throwable $e) {
            $this->logger->error("PostMapper.addOrUpdateEligibilityToken: Exception occurred while inserting token", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }

    }

    /**
     * Add or Update Eligibility Token for post
     * Enforces: A user must NOT have more than 5 records in the past hour
     *           with status IN ('NO_FILE', 'FILE_UPLOADED').
     */
    public function addOrUpdateEligibilityToken(string $userId, string $eligibilityToken, string $status): void
    {
        try {
            $this->logger->debug("PostMapper.addOrUpdateEligibilityToken started", [
                'userId' => $userId,
                'token'  => $eligibilityToken,
                'status' => $status,
            ]);

            $now       = (new \DateTime())->format('Y-m-d H:i:s.u');
            $expiresAt = (new \DateTime('+5 minutes'))->format('Y-m-d H:i:s.u');
            $oneHourAgo = (new \DateTime('-1 hour'))->format('Y-m-d H:i:s.u');

            // Only enforce cap if status is one of the restricted ones
            $restrictedStatuses = ['NO_FILE', 'FILE_UPLOADED'];
            if (in_array($status, $restrictedStatuses, true)) {
                $capSql = "
                    SELECT COUNT(*) 
                    FROM eligibility_token
                    WHERE userid = :userid
                    AND status IN ('NO_FILE', 'FILE_UPLOADED')
                    AND createdat >= :oneHourAgo
                ";
                $capStmt = $this->db->prepare($capSql);
                $capStmt->bindValue(':userid', $userId, \PDO::PARAM_STR);
                $capStmt->bindValue(':oneHourAgo', $oneHourAgo, \PDO::PARAM_STR);
                $capStmt->execute();
                $cnt = (int) $capStmt->fetchColumn();

                if ($cnt >= 5) {
                    $this->logger->warning("Cap reached: user has {$cnt} records in last hour with restricted statuses", [
                        'userId' => $userId,
                    ]);
                    throw new ValidationException('Limit exceeded: You can only create 5 records within 1 hour while status is NO_FILE or FILE_UPLOADED.', [31512]); // Limit exceeded: You can only create 5 records within 1 hour while status is NO_FILE or FILE_UPLOADED
                }
            }

            $this->db->beginTransaction();

            // Check if (userId, token) exists
            $existsSql = "
                SELECT 1
                FROM eligibility_token
                WHERE userid = :userid AND token = :token
                LIMIT 1
            ";
            $existsStmt = $this->db->prepare($existsSql);
            $existsStmt->bindValue(':userid', $userId, \PDO::PARAM_STR);
            $existsStmt->bindValue(':token', $eligibilityToken, \PDO::PARAM_STR);
            $existsStmt->execute();
            $exists = (bool) $existsStmt->fetchColumn();

            if ($exists) {
                $updateSql = "
                    UPDATE eligibility_token
                    SET status = :status,
                        expiresat = :expiresat
                    WHERE userid = :userid
                    AND token  = :token
                ";
                $updateStmt = $this->db->prepare($updateSql);
                $updateStmt->bindValue(':status', $status, \PDO::PARAM_STR);
                $updateStmt->bindValue(':expiresat', $expiresAt, \PDO::PARAM_STR);
                $updateStmt->bindValue(':now', $now, \PDO::PARAM_STR);
                $updateStmt->bindValue(':userid', $userId, \PDO::PARAM_STR);
                $updateStmt->bindValue(':token', $eligibilityToken, \PDO::PARAM_STR);
                $updateStmt->execute();
            } else {
                $insertSql = "
                    INSERT INTO eligibility_token
                        (userid, token, status, expiresat, createdat)
                    VALUES
                        (:userid, :token, :status, :expiresat, :now)
                ";
                $insertStmt = $this->db->prepare($insertSql);
                $insertStmt->bindValue(':userid', $userId, \PDO::PARAM_STR);
                $insertStmt->bindValue(':token', $eligibilityToken, \PDO::PARAM_STR);
                $insertStmt->bindValue(':status', $status, \PDO::PARAM_STR);
                $insertStmt->bindValue(':expiresat', $expiresAt, \PDO::PARAM_STR);
                $insertStmt->bindValue(':now', $now, \PDO::PARAM_STR);
                $insertStmt->execute();
            }

            $this->db->commit();
            $this->logger->info("PostMapper.addOrUpdateEligibilityToken completed", ['userid' => $userId]);

        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            $this->logger->error("PostMapper.addOrUpdateEligibilityToken failed", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'userid' => $userId,
            ]);
            throw $e;
        }
    }
}
