<?php

namespace Fawaz\Database;

use DateTime;
use Fawaz\App\Models\MultipartPost;
use PDO;
use Fawaz\App\Post;
use Fawaz\App\PostAdvanced;
use Fawaz\App\PostMedia;
use Fawaz\App\Status;
use Fawaz\Database\Interfaces\PeerMapper;
use Fawaz\config\constants\ConstantsConfig;
use Fawaz\Services\ContentFiltering\ContentFilterServiceImpl;
use Fawaz\Services\ContentFiltering\ContentReplacementPattern;
use Fawaz\Services\ContentFiltering\Strategies\GetProfileContentFilteringStrategy;
use Fawaz\Services\ContentFiltering\Strategies\ListPostsContentFilteringStrategy;
use Fawaz\Services\ContentFiltering\Types\ContentFilteringAction;
use Fawaz\Services\ContentFiltering\Types\ContentType;
use Fawaz\App\User;
use Fawaz\App\ValidationException;
use Fawaz\Database\Interfaces\TransactionManager;
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
            $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, \PDO::PARAM_INT);
            $stmt->execute();

            $results = array_map(fn($row) => new Post($row), $stmt->fetchAll(\PDO::FETCH_ASSOC));

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
        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $results[] = new Post($row,[],false);
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
        $data = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        if (!empty($data)) {
            return array_map(fn($row) => new Post($row, [],false), $data);
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
        $data = $stmt->fetch(\PDO::FETCH_ASSOC);

        if ($data !== false) {
            return new Post($data,[],false);
        }

        $this->logger->warning("No post found with id", ['id' => $id]);
        return false;
    }

    public function fetchPostsByType(string $currentUserId, string $userid, int $limitPerType = 5, ?string $contentFilterBy = null): array
    {        
        $whereClauses = ["sub.row_num <= :limit"];
        $whereClausesString = implode(" AND ", $whereClauses);

        $contentFilterService = new ContentFilterServiceImpl(
            new GetProfileContentFilteringStrategy(),
            null,
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
                pi.count_content_moderation_dismissed AS post_count_content_moderation_dismissed
            FROM (
                SELECT p.*, ROW_NUMBER() OVER (PARTITION BY p.contenttype ORDER BY p.createdat DESC) AS row_num
                FROM posts p
                JOIN users u ON p.userid = u.uid
                WHERE p.userid = :userid 
                AND p.feedid IS NULL
                AND u.status != :status
            ) sub
            LEFT JOIN users_info ui ON sub.userid = ui.userid
            LEFT JOIN post_info pi ON sub.postid = pi.postid AND pi.userid = sub.userid
            WHERE %s
            ORDER BY sub.contenttype, sub.createdat DESC",
            $whereClausesString
        );

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue('status', Status::DELETED, \PDO::PARAM_INT);
        $stmt->bindValue('userid', $userid, \PDO::PARAM_STR);
        $stmt->bindValue('limit', $limitPerType, \PDO::PARAM_INT);
        $stmt->execute();
        $unfidtered_result = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        $result = [];

        foreach ($unfidtered_result as $row) {
            $post_reports = (int)$row['post_reports'];
            $post_dismiss_moderation_amount = (int)$row['post_count_content_moderation_dismissed'];
            
            if ($contentFilterService->getContentFilterAction(
                ContentType::post,
                ContentType::post,
                $post_reports,
                $post_dismiss_moderation_amount,
                $currentUserId,$row['userid']
            ) == ContentFilteringAction::replaceWithPlaceholder) {
                $replacer = ContentReplacementPattern::flagged;
                $row['title'] = $replacer->postTitle($row['title']);
                $row['media'] = $replacer->postMedia($row['media']);
            }
            $result[] = $row;
        }
        return $result;
    }

    public function fetchComments(string $postid): array
    {
        $this->logger->info("PostMapper.fetchComments started");

        $sql = "SELECT * FROM comments WHERE postid = :postid";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['postid' => $postid]);

        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function fetchLikes(string $postid): array
    {
        $this->logger->info("PostMapper.fetchLikes started");

        $sql = "SELECT * FROM user_post_likes WHERE postid = :postid";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['postid' => $postid]);

        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function fetchDislikes(string $postid): array
    {
        $this->logger->info("PostMapper.fetchDislikes started");

        $sql = "SELECT * FROM user_post_dislikes WHERE postid = :postid";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['postid' => $postid]);

        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function fetchSaves(string $postid): array
    {
        $this->logger->info("PostMapper.fetchSaves started");

        $sql = "SELECT * FROM user_post_saves WHERE postid = :postid";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['postid' => $postid]);

        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function fetchViews(string $postid): array
    {
        $this->logger->info("PostMapper.fetchViews started");

        $sql = "SELECT * FROM user_post_views WHERE postid = :postid";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['postid' => $postid]);

        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function fetchReports(string $postid): array
    {
        $this->logger->info("PostMapper.fetchReports started");

        $sql = "SELECT * FROM user_post_reports WHERE postid = :postid";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['postid' => $postid]);

        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
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
        $this->logger->info("PostMapper.insert started");

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
        $this->logger->info("PostMapper.insertmed started");

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
            //$stmt->bindValue(':options', $data['options'], \PDO::PARAM_STR);
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

    // public function delete(string $postid): bool
    // {
    //     $this->logger->info("PostMapper.delete started");

    //     try {
    //         $this->db->beginTransaction();

    //         $tables = [
    //             'user_post_likes',
    //             'user_post_dislikes',
    //             'user_post_reports',
    //             'user_post_saves',
    //             'user_post_shares',
    //             'user_post_views',
    //             'post_info',
    //             'posts'
    //         ];

    //         foreach ($tables as $table) {
    //             $sql = "DELETE FROM $table WHERE postid = :postid";
    //             $stmt = $this->db->prepare($sql);
    //             $stmt->bindValue(':postid', $postid, \PDO::PARAM_STR);
    //             $stmt->execute();
    //         }

    //         $this->db->commit();
    //         $this->logger->info("Deleted post and related user activities successfully", ['postid' => $postid]);
    //         return true;
    //     } catch (\Exception $e) {
    //         $this->db->rollBack();
    //         $this->logger->error("Failed to delete post and related user activities", [
    //             'postid' => $postid,
    //             'exception' => $e->getMessage()
    //         ]);
    //         return false;
    //     }
    // }
    
    public function findPostser(string $currentUserId, ?array $args = []): array
    {
        $this->logger->info("PostMapper.findPostser started");

        $offset = max((int)($args['offset'] ?? 0), 0);
        $limit = min(max((int)($args['limit'] ?? 10), 1), 20);

        $post_report_amount_to_hide = ConstantsConfig::contentFiltering()['REPORTS_COUNT_TO_HIDE_FROM_IOS']['POST'];
        $post_dismiss_moderation_amount = ConstantsConfig::contentFiltering()['DISMISSING_MODERATION_COUNT_TO_RESTORE_TO_IOS']['POST'];

        $contentFilterBy = $args['contentFilterBy'] ?? null;
        $from = $args['from'] ?? null;
        $to = $args['to'] ?? null;
        $filterBy = $args['filterBy'] ?? [];
        $Ignorlist = $args['IgnorList'] ?? 'NO';
        $sortBy = $args['sortBy'] ?? null;
        $title = $args['title'] ?? null;
        $tag = $args['tag'] ?? null; 
        $postId = $args['postid'] ?? null;
        $userId = $args['userid'] ?? null;

        $whereClauses = ["p.feedid IS NULL"];
        $joinClausesString = "
            users u ON p.userid = u.uid
            LEFT JOIN post_tags pt ON p.postid = pt.postid
            LEFT JOIN tags t ON pt.tagid = t.tagid
            LEFT JOIN post_info pi ON p.postid = pi.postid AND pi.userid = p.userid
            LEFT JOIN users_info ui ON p.userid = ui.userid
        ";
        
        $contentFilterService = new ContentFilterServiceImpl(
            new ListPostsContentFilteringStrategy(),
            null,
            $contentFilterBy
        );

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
        // Remove DELETED User's post
        $whereClauses[] = "u.status != :status";
        $params['status'] = self::STATUS_DELETED;   

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

        // dont show posts from not active accounts
        $whereClauses[] = 'u.status = 0 AND (u.roles_mask = 0 OR u.roles_mask = 16)';

        // here to decide whether to hide post ot not from feed
        // send callback with post query changes to filtering object????
        if ($contentFilterService->getContentFilterAction(
            ContentType::post,
            ContentType::post
        ) === ContentFilteringAction::hideContent) {
            $whereClauses[] = '((pi.reports < :post_report_amount_to_hide OR pi.count_content_moderation_dismissed > :post_dismiss_moderation_amount) OR p.userid = :currentUserId)';
            $params['post_report_amount_to_hide'] = $post_report_amount_to_hide;
            $params['post_dismiss_moderation_amount'] = $post_dismiss_moderation_amount;
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
            if (in_array('VIEWED', $filterBy, true)) {
                $whereClauses[] = "EXISTS (
                    SELECT 1 FROM user_post_views upv
                    WHERE upv.postid = p.postid
                    AND upv.userid = :currentUserId
                )";
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

        if (!empty($Ignorlist) && $Ignorlist === 'YES') {
            $whereClauses[] = "p.userid NOT IN (SELECT blockedid FROM user_block_user WHERE blockerid = :currentUserId)";
        }
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

        $orderBy = match ($sortBy) {
            'FOR_ME' => "CASE
                            WHEN EXISTS (
                                SELECT 1 FROM follows f1
                                WHERE f1.followerid = :currentUserId
                                AND f1.followedid = p.userid
                                AND EXISTS (
                                    SELECT 1 FROM follows f2
                                    WHERE f2.followerid = p.userid
                                    AND f2.followedid = :currentUserId
                                )
                            ) THEN 1
                            WHEN EXISTS (
                                SELECT 1 FROM follows f1
                                WHERE f1.followerid = :currentUserId
                                AND f1.followedid = p.userid
                            ) THEN 2
                            WHEN EXISTS (
                                SELECT 1 FROM follows f1
                                WHERE f1.followerid IN (
                                SELECT followedid FROM follows WHERE followerid = :currentUserId
                                )
                                AND f1.followedid = p.userid
                            ) THEN 3
                            ELSE 4
                            END,
                            p.createdat DESC",
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
                (SELECT COUNT(*) FROM comments WHERE postid = p.postid) as amountcomments,
                COALESCE((
                    SELECT SUM(w.numbers)
                    FROM logwins w
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
			if ($stmt->execute($params)) {
				//$this->logger->info("Get post successfully");
				while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
					$row['tags'] = json_decode($row['tags'], true) ?? [];

                    // here to decide if to replace post/user content or not
                    // send callback with user object changes???? 
                    $user_reports = (int)$row['user_reports'];
                    $user_dismiss_moderation_amount = (int)$row['user_count_content_moderation_dismissed'];

                    if ($row['user_status'] != 0) {
                        $replacer = ContentReplacementPattern::suspended;
                        $row['username'] = $replacer->username($row['username']);
                        $row['img'] = $replacer->profilePicturePath($row['img']);
                    }

                    if ($contentFilterService->getContentFilterAction(
                        ContentType::post,
                        ContentType::user,
                        $user_reports,$user_dismiss_moderation_amount,
                        $currentUserId, $row['userid']
                    ) == ContentFilteringAction::replaceWithPlaceholder) {
                        $replacer = ContentReplacementPattern::flagged;
                        $row['username'] = $replacer->username($row['username']);
                        $row['userimg'] = $replacer->profilePicturePath($row['userimg']);
                    }

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
							'slug' => $row['slug'],
							'img' => $row['userimg'],
							'isfollowed' => (bool)$row['isfollowed'],
							'isfollowing' => (bool)$row['isfollowing'],
						],
					],[],false);
				}
            } else {
                //$this->logger->warning("Failed to Get post info"]);
            }

            return $results;
        } catch (\PDOException $e) {
            $this->logger->error("Database error in findPostser", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return [];
        } catch (\Exception $e) {
            $this->logger->error('Database error in findPostser', [
                'error' => $e->getMessage(),
            ]);
            return [];
        }
    }

    /**
     * Move Uploaded File to Media Folder
     */
    public function handelFileMoveToMedia(string $uploadedFiles): array 
    {
        $fileObjs = explode(',', $uploadedFiles);

        $uploadedFilesObj = [];
        try{
            if(is_array($fileObjs) && !empty($fileObjs)){
                $multipartPost = new MultipartPost(['media' => $fileObjs], [], false);
                $uploadedFilesObj = $multipartPost->moveFileTmpToMedia();
            }
        }catch(\Exception $e){
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
        $this->logger->info("PostMapper.updateTokenStatus started");

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
        try{
            if(is_array($fileObjs) && !empty($fileObjs)){
                $multipartPost = new MultipartPost(['media' => $fileObjs], [], false);
                $multipartPost->revertFileToTmp();
            }
        }catch(\Exception $e){
            $this->logger->info("PostMapper.revertFileToTmp Error". $e->getMessage());
        }
    }
    
    /**
     * Get Interactions based on Filter 
     */
    public function getInteractions(string $getOnly, string $postOrCommentId, string $currentUserId, int $offset, int $limit): array
    {
        $this->logger->info("PostMapper.getInteractions started");

        try {
            $this->logger->info("PostMapper.fetchViews started");

            $needleTable = 'user_post_likes';
            $needleColumn = 'postid';

            if($getOnly == 'VIEW'){
                $needleTable = 'user_post_views';
            }elseif($getOnly == 'LIKE'){
                $needleTable = 'user_post_likes';
            }elseif($getOnly == 'DISLIKE'){
                $needleTable = 'user_post_dislikes';
            }elseif($getOnly == 'COMMENTLIKE'){
                $needleTable = 'user_comment_likes';
                $needleColumn = 'commentid';
            }

            $sql = "SELECT 
                        u.uid, 
                        u.username, 
                        u.slug, 
                        u.img, 
                        u.status, 
                        (f1.followerid IS NOT NULL) AS isfollowing,
                        (f2.followerid IS NOT NULL) AS isfollowed
                    FROM $needleTable uv 
                    LEFT JOIN users u ON u.uid = uv.userid  
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

            $userResultObj = [];
            foreach($userResults as $key => $prt){
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
     * Add or Update Eligibility Token for post
     */
    public function addOrUpdateEligibilityToken01(string $userId, string $eligibilityToken, string $status): void
    {

        try {
            $this->logger->info("PostMapper.addOrUpdateEligibilityToken started");
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
            var_dump($existingToken); exit;

            $this->logger->info("PostMapper.addOrUpdateEligibilityToken: Inserted new token into database", ['userid' => $userId]);

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
            $this->logger->info("PostMapper.addOrUpdateEligibilityToken started", [
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
                    throw new ValidationException('Limit exceeded: You can only create 5 records within 1 hour while status is NO_FILE or FILE_UPLOADED.', [40301]); // Limit exceeded: You can only create 5 records within 1 hour while status is NO_FILE or FILE_UPLOADED
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
