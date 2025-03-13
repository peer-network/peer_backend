<?php

namespace Fawaz\App;

use Fawaz\App\Post;
use Fawaz\App\Comment;
use Fawaz\Database\CommentMapper;
use Fawaz\Database\PostInfoMapper;
use Fawaz\Database\PostMapper;
use Fawaz\Database\TagMapper;
use Fawaz\Database\TagPostMapper;
use Fawaz\Services\Base64FileHandler;
use Psr\Log\LoggerInterface;

class PostService
{
    protected ?string $currentUserId = null;
    private Base64FileHandler $base64filehandler;

    public function __construct(
        protected LoggerInterface $logger,
        protected PostMapper $postMapper,
        protected CommentMapper $commentMapper,
        protected PostInfoMapper $postInfoMapper,
        protected TagMapper $tagMapper,
        protected TagPostMapper $tagPostMapper,
    ) {
		$this->base64filehandler = new Base64FileHandler();
    }

    public function setCurrentUserId(string $userid): void
    {
        $this->currentUserId = $userid;
    }

    private function generateUUID(): string
    {
        return sprintf(
            '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff), mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        );
    }

    public static function isValidUUID(string $uuid): bool
    {
        return preg_match('/^[a-fA-F0-9]{8}-[a-fA-F0-9]{4}-[a-fA-F0-9]{4}-[a-fA-F0-9]{4}-[a-fA-F0-9]{12}$/', $uuid) === 1;
    }

	private static function validateDate($date, $format = 'Y-m-d') {
		$d = \DateTime::createFromFormat($format, $date);
		return $d && $d->format($format) === $date;
	}

    private function respondWithError(string $responseCode, array $extraData = []): array
    {
        return array_merge(['status' => 'error', 'ResponseCode' => $responseCode], $extraData);
    }

    private function createSuccessResponse(string $message, array $data = []): array
    {
        return ['status' => 'success', 'counter' => count($data), 'ResponseCode' => $message, 'affectedRows' => $data];
    }

    private function checkAuthentication(): bool
    {
        if ($this->currentUserId === null) {
            $this->logger->warning('Unauthorized action attempted.');
            return false;
        }
        return true;
    }

	private function argsToJsString($args) {
		return json_encode($args);
	}

    private function argsToString($args) {
        return serialize($args);
    }

    public function createPost(array $args): array
    {
        if (!$this->checkAuthentication()) {
            return $this->respondWithError('Unauthorized');
        }

        if (empty($args)) {
            return $this->respondWithError('No arguments provided. Please provide valid input parameters.');
        }

        $requiredFields = ['title', 'media', 'contenttype'];
        foreach ($requiredFields as $field) {
            if (empty($args[$field])) {
                return $this->respondWithError("$field is required");
            }
        }

        $this->logger->info('PostService.createPost started');
        $postId = $this->generateUUID();
        $createdAt = (new \DateTime())->format('Y-m-d H:i:s.u');

        $postData = [
            'postid' => $postId,
            'userid' => $this->currentUserId,
            'feedid' => $args['feedid'] ?? null,
            'contenttype' => $args['contenttype'],
            'title' => $args['title'],
            'media' => null,
            'cover' => null,
            'mediadescription' => $args['mediadescription'] ?? null,
            'options' => null,
            'createdat' => $createdAt,
        ];

        if ($postData['feedid'] && !$this->postMapper->isNewsFeedExist($postData['feedid'])) {
            return $this->respondWithError('Invalid newsfeed ID');
        }

        if ($postData['feedid'] && !$this->postMapper->isHasAccessInNewsFeed($postData['feedid'], $this->currentUserId)) {
            return $this->respondWithError('No access to the newsfeed');
        }

        try {
            if (!empty($args['media'])) {
                $mediaPath = $this->base64filehandler->handleFileUpload($args['media'], $args['contenttype'], $postId);
                $this->logger->info('PostService.createPost mediaPath', ['mediaPath' => $mediaPath]);

                if ($mediaPath === '') {
                    return $this->respondWithError('Media upload failed');
                }

                if (isset($mediaPath['options'])) {
                    $postData['options'] = $this->argsToJsString($mediaPath['options']);
                }

                if (!empty($mediaPath['path'])) {
					$postData['media'] = $mediaPath['path'];
                } else {
					return $this->respondWithError('Media path necessary for upload');
				}

            } else {
                return $this->respondWithError('Media necessary for upload');
            }

            if (!empty($args['cover'])) {
                $coverPath = $this->base64filehandler->handleFileUpload($args['cover'], 'image', $postId . '_cover');
				$this->logger->info('PostService.createPost coverPath', ['coverPath' => $coverPath]);

                if ($coverPath === '') {
                    return $this->respondWithError('Cover upload failed');
                }

                if (!empty($coverPath['path'])) {
					$postData['cover'] = $coverPath['path'];
                } else {
					return $this->respondWithError('Cover path necessary for upload');
				}

            }

            $post = new Post($postData);
            $this->postMapper->insert($post);

            if (isset($args['tags']) && is_array($args['tags'])) {
                $this->handleTags($args['tags'], $postId, $createdAt);
            }

            if (!$postData['feedid']) {
                $this->insertPostMetadata($postId, $this->currentUserId);
            }

            return $this->createSuccessResponse('Post created successfully', $post->getArrayCopy());
        } catch (\Exception $e) {
            $this->logger->error('Failed to create post', ['exception' => $e]);
            return $this->respondWithError('Failed to create post');
        }
    }

    private function handleTags(array $tags, string $postId, string $createdAt): void
    {
        $maxTags = 10;
        if (count($tags) > $maxTags) {
            throw new \Exception('Maximum tag limit exceeded');
        }

        foreach ($tags as $tagName) {
            $tagName = trim($tagName);
            
            if (strlen($tagName) < 2 || strlen($tagName) > 53 || !preg_match('/^[a-zA-Z0-9-]+$/', $tagName)) {
                throw new \Exception('Invalid tag name');
            }

            $tag = $this->tagMapper->loadByName($tagName);
            
            if (!$tag) {
                $this->logger->info('get tag name', ['tagName' => $tagName]);
                unset($tag);
                $tag = $this->createTag($tagName);
            }
            
            if (!$tag) {
                $this->logger->error('Failed to load or create tag', ['tagName' => $tagName]);
                throw new \Exception('Failed to load or create tag: ' . $tagName);
            }

            $tagPost = new TagPost([
                'postid' => $postId,
                'tagid' => $tag->getTagId(), // This is now guaranteed to be an object
                'createdat' => $createdAt,
            ]);

            try {
                $this->tagPostMapper->insert($tagPost);
            } catch (\Exception $e) {
                $this->logger->error('Failed to insert tag-post relationship', [
                    'postid' => $postId,
                    'tagName' => $tagName,
                    'exception' => $e->getMessage(),
                ]);
                throw new \Exception('Failed to insert tag-post relationship: ' . $tagName);
            }
        }
    }

    private function createTag(string $tagName): Tag|false
    {
        $tagId = 0;
        $tag = new Tag(['tagid' => $tagId, 'name' => $tagName]);
        $tag = $this->tagMapper->insert($tag);
        return $tag;
    }

    private function insertPostMetadata(string $postId, string $userId): void
    {
        $postInfo = new PostInfo([
            'postid' => $postId,
            'userid' => $userId,
            'likes' => 0,
            'dislikes' => 0,
            'reports' => 0,
            'views' => 0,
            'saves' => 0,
            'shares' => 0,
            'comments' => 0,
        ]);
        $this->postInfoMapper->insert($postInfo);
    }

    public function fetchAll(?array $args = []): array
    {
        if (!$this->checkAuthentication()) {
            return $this->respondWithError('Unauthorized');
        }

        $this->logger->info("PostService.fetchAll started");

        // Sanitize and validate offset and limit
        $offset = max((int)($args['offset'] ?? 0), 0);
        $limit = min(max((int)($args['limit'] ?? 10), 1), 20);

        try {
            // Fetch posts and map them to array format
            $posts = $this->postMapper->fetchAll($offset, $limit);
            $result = array_map(fn(Post $post) => $post->getArrayCopy(), $posts);

            $this->logger->info("Posts fetched successfully", ['count' => count($result)]);
            return $this->createSuccessResponse('Posts fetched successfully', [$result]);

        } catch (\Throwable $e) {
            $this->logger->error("Error fetching Posts", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return $this->respondWithError('Failed to fetch posts');
        }
    }

    public function findPostser(?array $args = []): array|false
    {
        if (!$this->checkAuthentication()) {
            return $this->respondWithError('Unauthorized');
        }

        $from = $args['from'] ?? null;
        $to = $args['to'] ?? null;
        $filterBy = $args['filterBy'] ?? [];
        $Ignorlist = $args['IgnorList'] ?? null;
        $sortBy = $args['sortBy'] ?? null;
        $title = $args['title'] ?? null;
        $tag = $args['tag'] ?? null; 
        $postId = $args['postid'] ?? null;
        $userId = $args['userid'] ?? null;

        if ($postId !== null && !self::isValidUUID($postId)) {
			return $this->respondWithError('Invalid postid format provided');
        }

        if ($userId !== null && !self::isValidUUID($userId)) {
			return $this->respondWithError('Invalid userid format provided');
        }

        if ($title !== null && strlen($title) < 2 || strlen($title) > 33) {
            return $this->respondWithError('Title must be between 2 and 30 characters.');
        }

		if ($from !== null && !self::validateDate($from)) {
			return $this->respondWithError('Invalid from date format provided');
		}

		if ($to !== null && !self::validateDate($to)) {
			return $this->respondWithError('Invalid to date format provided');
		}

        if ($tag !== null) {
            if (!preg_match('/^[a-zA-Z0-9_]+$/', $tag)) {
                $this->logger->error('Invalid tag format provided', ['tag' => $tag]);
				return $this->respondWithError('Invalid tag format provided');
            }
        }

		if (!empty($filterBy) && is_array($filterBy)) {
			$allowedTypes = ['IMAGE', 'AUDIO', 'VIDEO', 'TEXT', 'FOLLOWED', 'FOLLOWER'];

			$invalidTypes = array_diff(array_map('strtoupper', $filterBy), $allowedTypes);

			if (!empty($invalidTypes)) {
				return $this->respondWithError('Invalid type parameter(s) provided');
			}
		}

		if ($Ignorlist !== null) {
			$Ignorlisten = ['YES', 'NO'];
			if (!in_array($Ignorlist, $Ignorlisten, true)) {
				return $this->respondWithError('Invalid Ignorlist parameter provided.');
			}
		}

        $this->logger->info("PostService.findPostser started");

        $results = $this->postMapper->findPostser($args, $this->currentUserId);

        return $results;
    }

    public function getChatFeedsByID(string $feedid): ?array
    {
        if (!$this->checkAuthentication() || !self::isValidUUID($feedid)) {
            return $this->respondWithError('Invalid feed ID');
        }

        $this->logger->info("PostService.getChatFeedsByID started");

        try {
            $posts = $this->postMapper->getChatFeedsByID($feedid);

            $result = array_map(
                fn(Post $post) => $this->mapFeedsWithComments($post),
                $posts
            );

            return [
                'status' => 'success',
                'ResponseCode' => 'Chat feeds fetched successfully',
                'affectedRows' => $result,
            ];
        } catch (\Exception $e) {
            $this->logger->error('Failed to fetch chat feeds', ['feedid' => $feedid, 'exception' => $e]);
            return $this->respondWithError('Failed to fetch chat feeds');
        }
    }

    public function mapFeedsWithComments(Post $post): array
    {
        $postArray = $post->getArrayCopy();

        $comments = $this->commentMapper->fetchAllByPostId($post->getPostId());
        $postArray['comments'] = $this->mapCommentsWithReplies($comments);

        return $postArray;
    }

    private function mapCommentsWithReplies(array $comments): array
    {
        return array_map(
            function (Comment $comment) {
                $commentArray = $comment->getArrayCopy();
                $replies = $this->commentMapper->fetchAllByParentId($comment->getId());
                $commentArray['replies'] = $this->mapCommentsWithReplies($replies);
                return $commentArray;
            },
            $comments
        );
    }

    public function deletePost(string $id): array
    {
        if (!$this->checkAuthentication() || !self::isValidUUID($feedid)) {
            return $this->respondWithError('Invalid feed ID');
        }

        if (!self::isValidUUID($id)) {
            return $this->respondWithError('Invalid postId');
        }

        $this->logger->info('PostService.deletePost started');

        $posts = $this->postMapper->loadById($id);
        if (!$posts) {
            return $this->respondWithError('PostId not found.');
        }

        $post = $posts->getArrayCopy();

        if ($post['userid'] !== $this->currentUserId && !$this->postMapper->isCreator($id, $this->currentUserId)) {
            return $this->respondWithError('Unauthorized: You can only delete your own posts.');
        }

        try {
            $postid = $this->postMapper->delete($id);

            if ($postid) {
                $this->logger->info('Post deleted successfully', ['postid' => $postid]);
                return [
                    'status' => 'success',
                    'ResponseCode' => 'Post deleted successfully',
                ];
            }
        } catch (\Exception $e) {
            return $this->respondWithError('Failed to delete post.');
        }

        return $this->respondWithError('Failed to delete post.');
    }
}
