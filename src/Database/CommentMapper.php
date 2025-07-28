<?php

namespace Fawaz\Database;

use PDO;
use Fawaz\App\Comment;
use Fawaz\App\CommentAdvanced;
use Fawaz\App\Commented;
use Fawaz\config\constants\ConstantsConfig;
use Fawaz\Services\ContentFiltering\ContentFilterServiceImpl;
use Fawaz\Services\ContentFiltering\ContentReplacementPattern;
use Fawaz\Services\ContentFiltering\Strategies\ListPostsContentFilteringStrategy;
use Fawaz\Services\ContentFiltering\Types\ContentFilteringAction;
use Fawaz\Services\ContentFiltering\Types\ContentType;
use Psr\Log\LoggerInterface;
use Fawaz\App\User;

class CommentMapper
{
    public function __construct(protected LoggerInterface $logger, protected PDO $db)
    {
    }

    protected function respondWithError(int $message): array
    {
        return ['status' => 'error', 'ResponseCode' => $message];
    }

    public function isSameUser(string $userid, string $currentUserId): bool
    {
        return $userid === $currentUserId;
    }

    public function isCreator(string $commentid, string $userid): bool
    {
        $this->logger->info("CommentMapper.isCreator started");

        $sql = "SELECT COUNT(*) FROM comments WHERE commentid = :commentid AND userid = :userid";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['commentid' => $commentid, 'userid' => $userid]);
        return (bool) $stmt->fetchColumn();
    }

    public function insert(Comment $comment): Comment
    {
        $this->logger->info("CommentMapper.insert started");

        $daten = $data = $comment->getArrayCopy();

        $query = "INSERT INTO comments (commentid, userid, postid, parentid, content, createdat)
            VALUES (:commentid, :userid, :postid, :parentid, :content, :createdat)";
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
        $this->logger->info("CommentMapper.delete started");

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

    public function fetchAllByPostIdetaild(string $postId, string $currentUserId, int $offset = 0, int $limit = 10,?string $contentFilterBy = null): array
    {
        $this->logger->info("CommentMapper.fetchAllByPostIdetaild started");

        $contentFilterService = new ContentFilterServiceImpl(
            new ListPostsContentFilteringStrategy(),
            null,
            $contentFilterBy
        );
        $whereClauses = ["c.postid = :postId AND c.parentid IS NULL"];
        // $whereClauses[] = 'u.status = 0 AND (u.roles_mask = 0 OR u.roles_mask = 16)';

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

        $sql = sprintf("
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
                ui.count_content_moderation_dismissed AS user_count_content_moderation_dismissed,
                ci.count_content_moderation_dismissed AS comment_count_content_moderation_dismissed,
                ui.reports AS user_reports,
                u.status AS user_status,
                ci.reports AS comment_reports
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
        $stmt->bindValue(':postId', $postId, PDO::PARAM_STR);
        $stmt->bindValue(':currentUserId', $currentUserId, PDO::PARAM_STR);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        $results = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
			$this->logger->info("Fetched comments for post counter", ['row' => $row]);
            // here to decide if to replace comment/user content or not
            $user_reports = (int)$row['user_reports'];
            $user_dismiss_moderation_amount = (int)$row['user_count_content_moderation_dismissed'];
            $comment_reports = (int)$row['comment_reports'];
            $comment_dismiss_moderation_amount = (int)$row['comment_count_content_moderation_dismissed'];

            
            if ($row['user_status'] != 0) {
                $replacer = ContentReplacementPattern::suspended;
                $row['username'] = $replacer->username($row['username']);
                $row['img'] = $replacer->profilePicturePath($row['img']);
            }

            if ($contentFilterService->getContentFilterAction(
                ContentType::comment,
                ContentType::user,
                $user_reports,$user_dismiss_moderation_amount,
                $currentUserId,$row['uid']
            ) == ContentFilteringAction::replaceWithPlaceholder) {
                $replacer = ContentReplacementPattern::flagged;
                $row['username'] = $replacer->username($row['username']);
                $row['img'] = $replacer->profilePicturePath($row['img']);
            }

            if ($contentFilterService->getContentFilterAction(
                ContentType::comment,
                ContentType::comment,
                $comment_reports,$comment_dismiss_moderation_amount,
                $currentUserId,$row['uid']
            ) == ContentFilteringAction::replaceWithPlaceholder) {
                $replacer = ContentReplacementPattern::flagged;
                $row['content'] = $replacer->commentContent($row['content']);
            }

            
            $userObj = [
                        'uid' => $row['uid'],
                        'status' => $row['status'],
                        'username' => $row['username'],
                        'slug' => $row['slug'],
                        'img' => $row['img'],
                    ];
            $userObj = (new User($userObj, [], false))->getArrayCopy();

            $results[] = new CommentAdvanced([
                'commentid' => $row['commentid'],
                'userid' => $row['userid'],
                'postid' => $row['postid'],
                'parentid' => $row['parentid'],
                'content' => $row['content'],
                'amountlikes' => (int) $row['amountlikes'],
                'amountreplies' => (int) $row['amountreplies'],
                'isliked' => (bool) $row['isliked'],
                'createdat' => $row['createdat'],
                'userstatus' => $userObj['status'],
                'user' => [
                    'uid' => $userObj['uid'],
                    'username' => $userObj['username'],
                    'status' => $userObj['status'],
                    'slug' => $userObj['slug'],
                    'img' => $userObj['img'],
                    'isfollowed' => (bool) $row['isfollowed'],
                    'isfollowing' => (bool) $row['isfollowing'],
                ],
            ]);
        }

        $this->logger->info("Fetched comments for post", ['count' => count($results)]);

        return $results;
    }

    public function fetchAllByPostIdd(string $postId, string $currentUserId, int $offset = 0, int $limit = 10): array
    {
        $this->logger->info("CommentMapper.fetchAllByPostId started");

        $sql = "SELECT c.*, u.status FROM comments c LEFT JOIN users u ON c.userid = u.uid WHERE c.postid = :postId AND c.parentid IS NULL ORDER BY c.createdat ASC LIMIT :limit OFFSET :offset";
        $params = [
            'postId' => $postId,
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

    public function fetchAllByPostId(string $postId, string $currentUserId, int $offset = 0, int $limit = 10): array
    {
        $this->logger->info("CommentMapper.fetchAllByPostId started");

        $sql = "SELECT 
            c.*, 
            u.uid,
            u.username,
            u.status,
            u.slug,
            u.img,
            u.biography,
            u.updatedat
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
            $userObj = (new User($userObj, [], false))->getArrayCopy();

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
        // $sql = "SELECT * FROM comments WHERE parentid = :parentid ORDER BY createdat ASC";
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
                    ];
            $userObj = (new User($userObj, [], false))->getArrayCopy();

            $row['user'] = [
                'uid' => $userObj['uid'] ?? '',
                'username' => $userObj['username'] ?? '',
                'slug' => $userObj['slug'] ?? 0,
                'img' =>  $userObj['img'] ?? '',
                'biography' =>  $userObj['biography'] ?? '',
                'updatedat' =>  $userObj['updatedat'] ?? '',
            ];
            $row['userstatus'] = $row['status'];
            $subComment = new CommentAdvanced($row);
            $subComments[] = $subComment->getArrayCopy();
        }
        
        return $subComments;
    }

	public function fetchByParentId(string $parentId, string $currentUserId, int $offset = 0, int $limit = 10): array
	{
		try {
			$this->logger->info("CommentMapper.fetchByParentId started");

			// Check if the parent ID exists
			$parentCheckSql = "SELECT 1 FROM comments WHERE commentid = :parentId LIMIT 1";
			$parentStmt = $this->db->prepare($parentCheckSql);
			$parentStmt->bindParam(':parentId', $parentId, \PDO::PARAM_STR);
			$parentStmt->execute();
			$parentExists = $parentStmt->fetchColumn();

			if (!$parentExists) {
				return $this->respondWithError(31601);
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
					COALESCE(like_counts.like_count, 0) AS amountlikes,
					COALESCE(comment_counts.comment_count, 0) AS amountreplies,
					(ul.userid IS NOT NULL) AS isliked,
					u.uid,
					u.username,
					u.slug,
					u.status,
					u.img,
					(f1.followerid IS NOT NULL) AS isfollowing,
					(f2.followerid IS NOT NULL) AS isfollowed
				FROM 
					comments c
				LEFT JOIN 
					users u ON c.userid = u.uid
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
			$stmt->bindParam(':parentId', $parentId, \PDO::PARAM_STR);
			$stmt->bindParam(':currentUserId', $currentUserId, \PDO::PARAM_STR);
			$stmt->bindParam(':limit', $limit, \PDO::PARAM_INT);
			$stmt->bindParam(':offset', $offset, \PDO::PARAM_INT);

			$stmt->execute();

			$results = $stmt->fetchAll(PDO::FETCH_ASSOC);

			$comments = array_map(function($row) {

                $userObj = [
                        'uid' => $row['uid'],
                        'status' => $row['status'],
                        'username' => $row['username'],
                        'slug' => $row['slug'],
                        'img' => $row['img'],
                    ];
                $userObj = (new User($userObj, [], false))->getArrayCopy();

				return new CommentAdvanced([
					'commentid' => $row['commentid'],
					'userid' => $row['userid'],
					'postid' => $row['postid'],
					'parentid' => $row['parentid'],
					'content' => $row['content'],
					'amountlikes' => (int) $row['amountlikes'],
					'amountreplies' => (int) $row['amountreplies'],
					'isliked' => (bool) $row['isliked'],
					'createdat' => $row['createdat'],
                    'userstatus' => $userObj['status'],
					'user' => [
						'uid' => $userObj['uid'],
						'username' => $userObj['username'],
                        'status' => $userObj['status'],
						'slug' => $userObj['slug'],
						'img' => $userObj['img'],
						'isfollowed' => (bool) $row['isfollowed'],
						'isfollowing' => (bool) $row['isfollowing'],
					],
				]);
			}, $results);

			$this->logger->info("Fetched comments for post", ['count' => count($comments)]);

			if (empty($results)) {
				return $results;
			}

			return $comments;
		} catch (\Throwable $e) {
			$this->logger->error("Error in fetchByParentId", ['message' => $e->getMessage()]);
			return $this->respondWithError(41606);
		}
	}

    public function fetchByParentIdd(string $parentId, string $currentUserId, int $offset = 0, int $limit = 10): array
    {
        $this->logger->info("CommentMapper.fetchByParentId started");

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
        $this->logger->info("CommentMapper.loadById started");
        
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
        $this->logger->info("CommentMapper.isParentTopLevel started");

        $sql = "SELECT COUNT(*) FROM comments WHERE commentid = :commentId AND parentid IS NULL";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['commentId' => $commentId]);
        return (bool) $stmt->fetchColumn();
    }
    
    public function fetchAllByParentId(string $parentId, int $offset = 0, int $limit = 10): array
    {
        $this->logger->info("CommentMapper.fetchAllByParentId started");

        $sql = "
            SELECT 
                commentid,
                userid,
                postid,
                parentid,
                content,
                createdat
            FROM comments
            WHERE parentid = :parentId
            ORDER BY createdat ASC
            LIMIT :limit OFFSET :offset
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->bindParam(':parentId', $parentId, \PDO::PARAM_STR);
        $stmt->bindParam(':limit', $limit, \PDO::PARAM_INT);
        $stmt->bindParam(':offset', $offset, \PDO::PARAM_INT);
        $stmt->execute();

        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        return array_map(fn($row) => new Comment($row), $rows);
    }
}
