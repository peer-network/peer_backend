<?php
namespace Fawaz\Database;

use PDO;
use Fawaz\App\Post;
use Fawaz\App\PostAdvanced;
use Fawaz\App\PostMedia;
use Fawaz\App\Role;
use Fawaz\App\Status;
use Fawaz\App\User;
use Fawaz\config\constants\ConstantsConfig;
use Fawaz\Database\Interfaces\PeerMapper;
use Fawaz\Services\ContentFiltering\ContentFilterServiceImpl;
use Fawaz\Services\ContentFiltering\ContentReplacementPattern;
use Fawaz\Services\ContentFiltering\Strategies\GetProfileContentFilteringStrategy;
use Fawaz\Services\ContentFiltering\Strategies\ListPostsContentFilteringStrategy;
use Fawaz\Services\ContentFiltering\Types\ContentFilteringAction;
use Fawaz\Services\ContentFiltering\Types\ContentType;

class PostMapper extends PeerMapper
{
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

    public function postExistsById(string $postId): bool
    {
        $this->logger->info("PostMapper.postExistsById started");

        $stmt = $this->db->prepare("SELECT COUNT(*) FROM posts WHERE postid = :postId");
        $stmt->bindValue(':postId', $postId, \PDO::PARAM_STR);
        $stmt->execute();

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
        $postNumCutter = ($limit < 10) ? $limit : 10;

        $post_report_amount_to_hide = ConstantsConfig::contentFiltering()['REPORTS_COUNT_TO_HIDE_FROM_IOS']['POST'];
        $post_dismiss_moderation_amount = ConstantsConfig::contentFiltering()['DISMISSING_MODERATION_COUNT_TO_RESTORE_TO_IOS']['POST'];

        $trenddays = 7;

        $contentFilterBy = $args['contentFilterBy'] ?? null;
        $from = $args['from'] ?? null;
        $to = $args['to'] ?? null;
        $filterBy = $args['filterBy'] ?? [];
        $Ignorlist = $args['IgnorList'] ?? 'NO';
        $sortBy = $args['sortBy'] ?? null;
        $title = $args['title'] ?? null;
        $tag = $args['tag'] ?? null;
        $tags = $args['tags'] ?? [];
        $postId = $args['postid'] ?? null;
        $userId = $args['userid'] ?? null;

        $contentFilterService = new ContentFilterServiceImpl(
            new ListPostsContentFilteringStrategy(),
            null,
            $contentFilterBy
        );

        $whereClauses = ["p.feedid IS NULL"];
        $params = ['currentUserId' => $currentUserId];

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
            $whereClauses[] = "t.name = :tag";
            $params['tag'] = $tag;
        }
        if (!empty($tags)) { // im zukunft ob wir array von tags erstellen möchten bei der suche
            $tagPlaceholders = [];
            foreach ($tags as $i => $tg) {
                $tagPlaceholders[] = ":tag$i";
                $params["tag$i"] = $tg;
            }
            $whereClauses[] = "t.name IN (" . implode(", ", $tagPlaceholders) . ")";
        }

        // Allow Only User's & Admin's Mode Posts
        $whereClauses[] = 'u.status = :nrmal AND u.roles_mask IN (:users, :admin)';
        $params['nrmal'] = Status::NORMAL;
        $params['users'] = Role::USER;
        $params['admin'] = Role::ADMIN;

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
                'FRIENDS' => "EXISTS (SELECT 1 FROM follows f1 WHERE f1.followerid = :currentUserId AND f1.followedid = p.userid AND EXISTS (SELECT 1 FROM follows f2 WHERE f2.followerid = p.userid AND f2.followedid = :currentUserId))"
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

        if ($Ignorlist === 'YES') {
            $whereClauses[] = "p.userid NOT IN (
                SELECT blockedid FROM user_block_user WHERE blockerid = :currentUserId
                UNION
                SELECT blockerid FROM user_block_user WHERE blockedid = :currentUserId
            )";
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

        $orderByClause = match ($sortBy) {
            'FOR_ME' => "ORDER BY isfriend DESC, isfollowed DESC, friendoffriends DESC, createdat DESC",
            'NEWEST' => "ORDER BY createdat DESC",
            'OLDEST' => "ORDER BY createdat ASC",
            'TRENDING' => "ORDER BY amounttrending DESC, createdat DESC",
            'LIKES' => "ORDER BY amountlikes DESC, createdat DESC",
            'DISLIKES' => "ORDER BY amountdislikes DESC, createdat DESC",
            'VIEWS' => "ORDER BY amountviews DESC, createdat DESC",
            'COMMENTS' => "ORDER BY amountcomments DESC, createdat DESC",
            'FOLLOWER' => "ORDER BY isfollowing DESC, createdat DESC",
            'FOLLOWED' => "ORDER BY isfollowed DESC, createdat DESC",
            'RELEVANT' => "ORDER BY isfollowed DESC, isliked DESC, amountcomments DESC, createdat DESC",
            'FRIENDS' => "ORDER BY isfriend DESC, createdat DESC",
            default => "ORDER BY createdat DESC",
        };

        $baseSelect = "
            SELECT 
                p.postid, p.userid, p.contenttype, p.title, p.media, p.cover, 
                p.mediadescription, p.createdat, a.timestart AS ad_order, a.timeend AS end_order,
                u.username, u.slug, u.img AS userimg, u.status AS userstatus,

                MAX(u.status) AS user_status,
                MAX(ui.count_content_moderation_dismissed) AS user_count_content_moderation_dismissed,
                MAX(pi.count_content_moderation_dismissed) AS post_count_content_moderation_dismissed,
                MAX(ui.reports) AS user_reports,
                MAX(pi.reports) AS post_reports,

                COALESCE(JSON_AGG(t.name) FILTER (WHERE t.name IS NOT NULL), '[]') AS tags,
                (SELECT COUNT(*) FROM user_post_likes WHERE postid = p.postid) AS amountlikes,
                (SELECT COUNT(*) FROM user_post_dislikes WHERE postid = p.postid) AS amountdislikes,
                (SELECT COUNT(*) FROM user_post_views WHERE postid = p.postid) AS amountviews,
                (SELECT COUNT(*) FROM comments WHERE postid = p.postid) AS amountcomments,
                COALESCE((SELECT SUM(numbers) FROM logwins WHERE postid = p.postid AND createdat >= NOW() - INTERVAL '$trenddays days'), 0) AS amounttrending,
                EXISTS (SELECT 1 FROM user_post_likes WHERE postid = p.postid AND userid = :currentUserId) AS isliked,
                EXISTS (SELECT 1 FROM user_post_views WHERE postid = p.postid AND userid = :currentUserId) AS isviewed,
                EXISTS (SELECT 1 FROM user_post_reports WHERE postid = p.postid AND userid = :currentUserId) AS isreported,
                EXISTS (SELECT 1 FROM user_post_dislikes WHERE postid = p.postid AND userid = :currentUserId) AS isdisliked,
                EXISTS (SELECT 1 FROM user_post_saves WHERE postid = p.postid AND userid = :currentUserId) AS issaved,
                EXISTS (SELECT 1 FROM follows WHERE followedid = p.userid AND followerid = :currentUserId) AS isfollowed,
                EXISTS (SELECT 1 FROM follows WHERE followerid = p.userid AND followedid = :currentUserId) AS isfollowing,
                EXISTS (
                    SELECT 1 FROM follows f1
                    WHERE f1.followerid = :currentUserId AND f1.followedid = p.userid
                    AND EXISTS (
                        SELECT 1 FROM follows f2
                        WHERE f2.followerid = p.userid AND f2.followedid = :currentUserId
                    )
                ) AS isfriend,
                EXISTS (
                    SELECT 1 FROM follows f1
                    WHERE f1.followerid IN (
                        SELECT followedid FROM follows WHERE followerid = :currentUserId
                    ) AND f1.followedid = p.userid
                ) AS friendoffriends,
                a.status AS ad_type
            FROM posts p
            JOIN users u ON p.userid = u.uid
            LEFT JOIN post_tags pt ON p.postid = pt.postid
            LEFT JOIN tags t ON pt.tagid = t.tagid
            LEFT JOIN post_info pi ON p.postid = pi.postid AND pi.userid = p.userid
            LEFT JOIN users_info ui ON p.userid = ui.userid
            LEFT JOIN advertisements a ON p.postid = a.postid
            WHERE " . implode(" AND ", $whereClauses) . "
            GROUP BY p.postid, u.username, u.slug, userimg, userstatus, a.status, a.timeend, a.timestart
        ";

        $sqlPinnedPosts = "WITH base_posts AS ($baseSelect)
        SELECT * FROM base_posts
        WHERE ad_type = 'pinned'
          AND ad_order <= NOW()
          AND end_order > NOW()
        ORDER BY ad_order DESC";

        $sqlNormalPosts = "WITH base_posts AS ($baseSelect)
        SELECT bp.*, NULL AS ad_type FROM base_posts bp
          $orderByClause
        LIMIT :limit OFFSET :offset";

        $sqlBasicAds = "WITH base_posts AS ($baseSelect)
        SELECT * FROM base_posts
        WHERE ad_type = 'basic'
          AND ad_order <= NOW()
          AND end_order > NOW()
        ORDER BY ad_order ASC";

        try {
            $normalStmt = $this->db->prepare($sqlNormalPosts);
            foreach ($params as $key => $val) {
                $normalStmt->bindValue(":" . $key, $val);
            }
            $normalStmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
            $normalStmt->bindValue(':offset', $offset, \PDO::PARAM_INT);
            $normalStmt->execute();

            $basicStmt = $this->db->prepare($sqlBasicAds);
            foreach ($params as $key => $val) {
                $basicStmt->bindValue(":" . $key, $val);
            }
            $basicStmt->execute();

            $normal = $normalStmt->fetchAll(\PDO::FETCH_ASSOC);
            $basic = $basicStmt->fetchAll(\PDO::FETCH_ASSOC);

            $finalPosts = [];
            $pinned = [];
            $adsInserted = 0;

            if ((int)$offset === 0) {
                $pinnedStmt = $this->db->prepare($sqlPinnedPosts);
                foreach ($params as $key => $val) {
                    $pinnedStmt->bindValue(":" . $key, $val);
                }
                $pinnedStmt->execute();
                $pinned = $pinnedStmt->fetchAll(\PDO::FETCH_ASSOC);

                if (!empty($pinned)) {
                    $finalPosts = $pinned;
                } else {
                    if (!empty($basic)) {
                        $finalPosts[] = array_shift($basic);
                        $adsInserted++;
                    }
                }
            }

            $basicCount = count($basic);
            $normalCount = count($normal);

            for ($i = 0; $i < $normalCount; $i++) {
                $finalPosts[] = $normal[$i];

                $isFullBlock = (($i + 1) % $postNumCutter === 0);
                if ($isFullBlock && $adsInserted < $basicCount) {
                    $finalPosts[] = $basic[$adsInserted];
                    $adsInserted++;
                }
            }

            while ($adsInserted < $basicCount) {
                $finalPosts[] = $basic[$adsInserted];
                $adsInserted++;
            }

            return array_map(function ($row) use($contentFilterService, $currentUserId) {
                $row['tags'] = json_decode($row['tags'], true) ?? [];

                $user_reports = (int)$row['user_reports'];
                $user_dismiss_moderation_amount = (int)$row['user_count_content_moderation_dismissed'];

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

                return self::mapRowToPost($row);
            }, $finalPosts);

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
            'amountviews' => (int)$row['amountviews'],
            'amountcomments' => (int)$row['amountcomments'],
            'amountdislikes' => (int)$row['amountdislikes'],
            'amounttrending' => (int)$row['amounttrending'],
            'isliked' => (bool)$row['isliked'],
            'isviewed' => (bool)$row['isviewed'],
            'isreported' => (bool)$row['isreported'],
            'isdisliked' => (bool)$row['isdisliked'],
            'issaved' => (bool)$row['issaved'],
            'type' => (string)$row['ad_type'],
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
}
