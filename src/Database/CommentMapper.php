<?php

declare(strict_types=1);

namespace Fawaz\Database;

use Fawaz\App\Profile;
use Fawaz\Services\ContentFiltering\Specs\Specification;
use Fawaz\Services\ContentFiltering\Specs\SpecificationSQLData;
use PDO;
use Fawaz\App\Comment;
use Fawaz\App\CommentAdvanced;
use Fawaz\App\Commented;
use Fawaz\Services\ContentFiltering\Types\ContentType;
use Fawaz\Utils\PeerLoggerInterface;
use Fawaz\App\User;
use Fawaz\Utils\ResponseHelper;

class CommentMapper
{
    use ResponseHelper;
    public function __construct(protected PeerLoggerInterface $logger, protected PDO $db)
    {
    }



    public function isSameUser(string $userid, string $currentUserId): bool
    {
        return $userid === $currentUserId;
    }

    public function isCreator(string $commentid, string $userid): bool
    {
        $this->logger->debug("CommentMapper.isCreator started");

        $sql = "SELECT COUNT(*) FROM comments WHERE commentid = :commentid AND userid = :userid";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['commentid' => $commentid, 'userid' => $userid]);
        return (bool) $stmt->fetchColumn();
    }

    public function insert(Comment $comment): Comment
    {
        $this->logger->debug("CommentMapper.insert started");

        $daten = $data = $comment->getArrayCopy();

        $query = "INSERT INTO comments (commentid, userid, postid, parentid, content, createdat, visibility_status)
            VALUES (:commentid, :userid, :postid, :parentid, :content, :createdat,:visibility_status)";
        $stmt = $this->db->prepare($query);
        $stmt->execute($data);

        $this->logger->info("Inserted new comment into database", ['comment' => $data]);

        $queryUserPostComments = "INSERT INTO user_post_comments (userid, postid, commentid, collected)
            VALUES (:userid, :postid, :commentid, 0)";
        $stmtUserPostComments = $this->db->prepare($queryUserPostComments);
        $stmtUserPostComments->execute([
            'userid' => $data['userid'],
            'postid' => $data['postid'],
            'commentid' => $data['commentid']
        ]);

        $this->logger->info("Inserted new entry into user_post_comments table", [
            'userid' => $data['userid'],
            'postid' => $data['postid'],
            'commentid' => $data['commentid']
        ]);

        return new Comment($daten);
    }

    public function delete(string $commentid): bool
    {
        $this->logger->debug("CommentMapper.delete started");

        $query = "DELETE FROM comments WHERE commentid = :commentid";

        $stmt = $this->db->prepare($query);
        $stmt->execute(['commentid' => $commentid]);

        $deleted = (bool)$stmt->rowCount();

        if ($deleted) {
            $this->logger->info("Deleted comment from database", ['commentid' => $commentid]);
        } else {
            $this->logger->warning("No comment found to delete in database for", ['commentid' => $commentid]);
        }

        return $deleted;
    }

    public function fetchAllByPostIdetaild(string $postId, array $specifications, string $currentUserId, int $offset = 0, int $limit = 10): array
    {
        $this->logger->debug("CommentMapper.fetchAllByPostIdetaild started");

        $specsSQL = array_map(fn (Specification $spec) => $spec->toSql(ContentType::comment), $specifications);
        $allSpecs = SpecificationSQLData::merge($specsSQL);
        $whereClauses = $allSpecs->whereClauses;
        $params = $allSpecs->paramsToPrepare;

        $whereClauses[] = "c.postid = :postId AND c.parentid IS NULL";

        $joinClausesString = "
                users u ON c.userid = u.uid
            LEFT JOIN 
                (SELECT commentid, COUNT(*) AS like_count FROM user_comment_likes GROUP BY commentid) like_counts 
                ON c.commentid = like_counts.commentid
			LEFT JOIN 
				(SELECT commentid, SUM(comments) AS comment_count FROM comment_info GROUP BY commentid) comment_counts 
				ON c.commentid = comment_counts.commentid
            LEFT JOIN 
                user_comment_likes ul 
                ON c.commentid = ul.commentid AND ul.userid = :currentUserId
            LEFT JOIN 
                follows f1 
                ON u.uid = f1.followerid AND f1.followedid = :currentUserId -- Is the author following the current user?
            LEFT JOIN 
                follows f2 
                ON u.uid = f2.followedid AND f2.followerid = :currentUserId -- Is the current user following the author?
            LEFT JOIN comment_info ci
                ON c.commentid = ci.commentid AND ci.userid = c.userid
            LEFT JOIN users_info ui 
                ON c.userid = ui.userid
        ";

        $whereClausesString = implode(" AND ", $whereClauses);

        $sql = sprintf(
            "
            SELECT 
                c.*,
                COALESCE(like_counts.like_count, 0) AS amountlikes,
				COALESCE(comment_counts.comment_count, 0) AS amountreplies,
                CASE WHEN ul.userid IS NOT NULL THEN TRUE ELSE FALSE END AS isliked,
                u.uid,
                u.username,
				u.slug,
                u.status,
                u.img,
                CASE WHEN f1.followerid IS NOT NULL THEN TRUE ELSE FALSE END AS isfollowing,
                CASE WHEN f2.followerid IS NOT NULL THEN TRUE ELSE FALSE END AS isfollowed,
                ui.reports AS user_reports,
                u.status AS user_status,
                c.visibility_status as visibility_status,
                ci.reports AS comment_reports,
                ci.totalreports AS comment_total_reports,
                EXISTS (SELECT 1 FROM user_reports  WHERE targetid = c.commentid AND reporter_userid = :currentUserId) AS isreported
                FROM comments c
            LEFT JOIN %s
            WHERE %s
            ORDER BY 
                c.createdat ASC
            LIMIT :limit OFFSET :offset;",
            $joinClausesString,
            $whereClausesString
        );

        $stmt = $this->db->prepare($sql);
        $params['postId'] = $postId;
        $params['currentUserId'] = $currentUserId;
        $params['limit'] = $limit;
        $params['offset'] = $offset;

        $stmt->execute($params);

        $results = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $this->logger->debug("Fetched comments for post counter", ['row' => $row]);
            // here to decide if to replace comment/user content or not

            $results[] = new CommentAdvanced([
                'commentid' => $row['commentid'],
                'userid' => $row['userid'],
                'postid' => $row['postid'],
                'parentid' => $row['parentid'],
                'content' => $row['content'],
                'amountlikes' => (int) $row['amountlikes'],
                'amountreplies' => (int) $row['amountreplies'],
                'amountreports' => (int) $row['comment_total_reports'],
                'isreported' => (bool) ($row['isreported'] ?? false),
                'isliked' => (bool) $row['isliked'],
                'createdat' => $row['createdat'],
                'visibility_status' => $row['visibility_status'],
                'reports' => $row['comment_reports']
            ], [], false);
        }

        $this->logger->debug("Fetched comments for post", ['count' => count($results)]);

        return $results;
    }

    public function fetchAllByPostId(string $postId, string $currentUserId, int $offset = 0, int $limit = 10): array
    {
        $this->logger->debug("CommentMapper.fetchAllByPostId started");

        $sql = "SELECT 
            c.*, 
            u.uid,
            u.username,
            u.status,
            u.slug,
            u.img,
            u.biography,
            u.updatedat
            u.visibility_status
        FROM 
            comments c
        LEFT JOIN 
            users u ON c.userid = u.uid
        WHERE
            c.postid = :postid AND c.parentid IS NULL  
        ORDER BY 
            c.createdat ASC
        LIMIT 
            :limit
        OFFSET
            :offset";

        $stmt = $this->db->prepare($sql);
        $stmt->bindParam(':postid', $postId, PDO::PARAM_STR);
        $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        $comments = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {

            $userObj = [
                        'uid' => $row['userid'],
                        'status' => $row['status'],
                        'username' => $row['username'],
                        'slug' => $row['slug'],
                        'img' => $row['img'],
                        'biography' => $row['biography'],
                        'updatedat' => $row['updatedat'],
                    ];
            $userObj = new Profile($userObj, [], false)->getArrayCopy();

            $row['user'] = [
                'uid' => $userObj['uid'] ?? '',
                'username' => $userObj['username'] ?? '',
                'slug' => $userObj['slug'] ?? 0,
                'img' =>  $userObj['img'] ?? '',
                'biography' =>  $userObj['biography'] ?? '',
                'updatedat' =>  $userObj['updatedat'] ?? '',
            ];
            $row['userstatus'] = $row['status'];
            $comment = new Commented($row);
            // echo($comment['user']['id']);
            $commentArray = $comment->getArrayCopy();
            $commentArray['subcomments'] = $this->fetchSubComments($comment->getId());
            $comments[] = $commentArray;
        }

        return $comments;
    }

    private function fetchSubComments(string $parentId): array
    {
        $sql = "SELECT 
            c.*, 
            u.*
        FROM 
            comments c
        LEFT JOIN 
            users u ON c.userid = u.uid
        WHERE
            c.parentid = :parentid
        ORDER BY 
            c.createdat ASC";

        $stmt = $this->db->prepare($sql);
        $stmt->bindParam(':parentid', $parentId, PDO::PARAM_STR);
        $stmt->execute();

        $subComments = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {

            $userObj = [
                'uid' => $row['userid'],
                'status' => $row['status'],
                'username' => $row['username'],
                'slug' => $row['slug'],
                'img' => $row['img'],
                'biography' => $row['biography'],
                'updatedat' => $row['updatedat'],
                'visibility_status' => $row['visibility_status'],
            ];
            $userObj = new User($userObj, [], false)->getArrayCopy();

            $row['user'] = [
                'uid' => $userObj['uid'] ?? '',
                'username' => $userObj['username'] ?? '',
                'slug' => $userObj['slug'] ?? 0,
                'img' =>  $userObj['img'] ?? '',
                'biography' =>  $userObj['biography'] ?? '',
                'updatedat' =>  $userObj['updatedat'] ?? '',
                'visibility_status' => $row['visibility_status'],
            ];
            $row['userstatus'] = $row['status'];
            $subComment = new CommentAdvanced($row, [], false);
            $subComments[] = $subComment->getArrayCopy();
        }

        return $subComments;
    }

    public function fetchByParentId(string $parentId, string $currentUserId, int $offset = 0, int $limit = 10): array
    {
        try {
            $this->logger->debug("CommentMapper.fetchByParentId started");

            // Check if the parent ID exists
            $parentCheckSql = "SELECT 1 FROM comments WHERE commentid = :parentId LIMIT 1";
            $parentStmt = $this->db->prepare($parentCheckSql);
            $parentStmt->bindParam(':parentId', $parentId, PDO::PARAM_STR);
            $parentStmt->execute();
            $parentExists = $parentStmt->fetchColumn();

            if (!$parentExists) {
                return $this::respondWithError(31601);
            }

            // Fetch child comments
            $sql = "
				SELECT 
					c.commentid,
					c.userid,
					c.postid,
					c.parentid,
					c.content,
					c.createdat,
                    c.visibility_status as comment_visibility_status,
					COALESCE(like_counts.like_count, 0) AS amountlikes,
					COALESCE(comment_counts.comment_count, 0) AS amountreplies,
                    ci.reports AS amountreports,
					(ul.userid IS NOT NULL) AS isliked,
					u.uid,
					u.username,
					u.slug,
					u.status,
					u.img,
                    u.visibility_status as user_visibility_status,
					(f1.followerid IS NOT NULL) AS isfollowing,
					(f2.followerid IS NOT NULL) AS isfollowed,
                    EXISTS (SELECT 1 FROM user_reports  WHERE targetid = c.commentid AND reporter_userid = :currentUserId) AS isreported
				FROM 
					comments c
				LEFT JOIN 
					users u ON c.userid = u.uid
                LEFT JOIN 
                    comment_info ci
                    ON c.commentid = ci.commentid
				LEFT JOIN 
					(SELECT commentid, COUNT(*) AS like_count FROM user_comment_likes GROUP BY commentid) like_counts 
					ON c.commentid = like_counts.commentid
				LEFT JOIN 
					(SELECT commentid, COUNT(*) AS comment_count FROM user_post_comments GROUP BY commentid) comment_counts 
					ON c.commentid = comment_counts.commentid
				LEFT JOIN 
					user_comment_likes ul 
					ON c.commentid = ul.commentid AND ul.userid = :currentUserId
				LEFT JOIN 
					follows f1 
					ON u.uid = f1.followerid AND f1.followedid = :currentUserId 
				LEFT JOIN 
					follows f2 
					ON u.uid = f2.followedid AND f2.followerid = :currentUserId 
				WHERE 
					c.parentid = :parentId
				ORDER BY 
					c.createdat DESC
				LIMIT :limit OFFSET :offset;
			";

            $stmt = $this->db->prepare($sql);
            $stmt->bindParam(':parentId', $parentId, PDO::PARAM_STR);
            $stmt->bindParam(':currentUserId', $currentUserId, PDO::PARAM_STR);
            $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
            $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);

            $stmt->execute();

            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $comments = array_map(function ($row) {

                $userObj = [
                        'uid' => $row['uid'],
                        'status' => $row['status'],
                        'username' => $row['username'],
                        'slug' => $row['slug'],
                        'img' => $row['img'],
                        'visibility_status' => $row['user_visibility_status'],
                    ];
                $userObj = new User($userObj, [], false)->getArrayCopy();

                return new CommentAdvanced([
                    'commentid' => $row['commentid'],
                    'userid' => $row['userid'],
                    'postid' => $row['postid'],
                    'parentid' => $row['parentid'],
                    'content' => $row['content'],
                    'amountlikes' => (int) $row['amountlikes'],
                    'amountreplies' => (int) $row['amountreplies'],
                    'amountreports' => (int) $row['amountreports'],
                    'isreported' => (bool) ($row['isreported'] ?? false),
                    'isliked' => (bool) $row['isliked'],
                    'createdat' => $row['createdat'],
                    'userstatus' => $userObj['status'],
                    'visibility_status' => $row['comment_visibility_status'],
                    'user' => [
                        'uid' => $userObj['uid'],
                        'username' => $userObj['username'],
                        'status' => $userObj['status'],
                        'slug' => $userObj['slug'],
                        'img' => $userObj['img'],
                        'isfollowed' => (bool) $row['isfollowed'],
                        'isfollowing' => (bool) $row['isfollowing'],
                        'visibility_status' => $row['user_visibility_status'],
                    ],
                ], [], false);
            }, $results);

            $this->logger->debug("Fetched comments for post", ['count' => count($comments)]);

            if (empty($results)) {
                return $results;
            }

            return $comments;
        } catch (\Throwable $e) {
            $this->logger->error("Error in fetchByParentId", ['message' => $e->getMessage()]);
            return $this::respondWithError(41606);
        }
    }

    public function fetchByParentIdd(string $parentId, string $currentUserId, int $offset = 0, int $limit = 10): array
    {
        $this->logger->debug("CommentMapper.fetchByParentId started");

        $sql = "SELECT c.*, u.status FROM comments c LEFT JOIN users u ON c.userid = u.uid WHERE c.parentid = :parentid ORDER BY c.createdat ASC LIMIT :limit OFFSET :offset";
        $params = [
            'parentid' => $parentId,
            'limit' => $limit,
            'offset' => $offset,
        ];

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        $comments = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $row['userstatus'] = $row['status'];
            $comments[] = new Comment($row);
        }

        return $comments;
    }

    public function loadById(string $commentid): Comment|false
    {
        $this->logger->debug("CommentMapper.loadById started");

        $sql = "SELECT c.*, u.status FROM comments c LEFT JOIN users u ON c.userid = u.uid WHERE c.commentid = :id";

        $stmt = $this->db->prepare($sql);
        $stmt->execute(
            ['id' => $commentid]
        );
        $data = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($data !== false) {
            $data['userstatus'] = $data['status'];
            return new Comment($data);
        }

        $this->logger->warning("No comment found with commentid", ['id' => $commentid]);
        return false;
    }

    public function isParentTopLevel(string $commentId): bool
    {
        $this->logger->debug("CommentMapper.isParentTopLevel started");

        $sql = "SELECT COUNT(*) FROM comments WHERE commentid = :commentId AND parentid IS NULL";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['commentId' => $commentId]);
        return (bool) $stmt->fetchColumn();
    }


    public function fetchAllByParentId(string $parentId, int $offset = 0, int $limit = 10): array
    {
        $this->logger->debug("CommentMapper.fetchAllByParentId started");

        $sql = "
            SELECT 
                commentid,
                userid,
                postid,
                parentid,
                content,
                createdat,
                visibility_status
            FROM comments
            WHERE parentid = :parentId
            ORDER BY createdat ASC
            LIMIT :limit OFFSET :offset
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->bindParam(':parentId', $parentId, PDO::PARAM_STR);
        $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return array_map(fn ($row) => new Comment($row), $rows);
    }
}
