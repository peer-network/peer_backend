<?php

namespace Fawaz\App;

use Fawaz\App\Post;
use Fawaz\App\Comment;
use Fawaz\Database\CommentMapper;
use Fawaz\Database\PostInfoMapper;
use Fawaz\Database\PostMapper;
use Fawaz\Database\TagMapper;
use Fawaz\Database\TagPostMapper;
use Fawaz\Services\FileUploadDispatcher;
use Fawaz\Utils\ResponseHelper;
use Psr\Log\LoggerInterface;
use Fawaz\config\ContentLimitsPerPost;

class PostService
{
	use ResponseHelper;
    protected ?string $currentUserId = null;

    public function __construct(
        protected LoggerInterface $logger,
        protected PostMapper $postMapper,
        protected CommentMapper $commentMapper,
        protected PostInfoMapper $postInfoMapper,
        protected TagMapper $tagMapper,
        protected TagPostMapper $tagPostMapper,
        protected FileUploadDispatcher $base64filehandler,
    ) {}

    public function setCurrentUserId(string $userid): void
    {
        $this->currentUserId = $userid;
    }

    public static function isValidUUID(string $uuid): bool
    {
        return preg_match('/^[a-fA-F0-9]{8}-[a-fA-F0-9]{4}-[a-fA-F0-9]{4}-[a-fA-F0-9]{4}-[a-fA-F0-9]{12}$/', $uuid) === 1;
    }

    private static function validateDate($date, $format = 'Y-m-d') {
        $d = \DateTime::createFromFormat($format, $date);
        return $d && $d->format($format) === $date;
    }

    private function respondWithError(string $responseCode): array
    {
        return ['status' => 'error', 'ResponseCode' => $responseCode];
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


    private function validateCoverCount(array $args, string $contenttype): array {
        if (!is_array($args['cover'])) {
                return ['success' => false, 'error' => '30102'];
        }

        $covers = $args['cover'];
        $coversCount = count($covers);

        try {
            $limitObj = ContentLimitsPerPost::from($contenttype);
            if (!$limitObj) {
                return ['success' => false, 'error' => '40301'];    
            }
            $coverLimit = $limitObj->coverLimit();

        } catch (\Throwable $e) {
            echo($e->getMessage());
            return ['success' => false, 'error' => '40301'];
        }

        if ($coversCount > $coverLimit) {
            return ['success' => false, 'error' => '30268'];
        } else {
            return ['success' => true, 'error' => null];
        }
    }


    private function validateContentCount(array $args): array {
        if (!isset($args['contenttype']) && empty($args['contenttype']) && !is_string($args['contenttype'])) {
            return ['success' => false, 'error' => '30206'];
        }
        $contenttype = strval($args['contenttype']);
        if (!isset($args['media']) && empty($args['media']) && !is_array($args['media'])) {
            return ['success' => false, 'error' => '30102'];
        }
        if (isset($args['cover']) && !empty($args['cover'])) {
             return $this->validateCoverCount($args,$contenttype);
        }

        $media = $args['media'];
        $mediaCount = count($media);

        try {
            $mediaLimitObj = ContentLimitsPerPost::from($contenttype);
            if (!$mediaLimitObj) {
                return ['success' => false, 'error' => '40301'];    
            }
            $mediaLimit = $mediaLimitObj->mediaLimit();
        } catch (\Throwable $e) {
            echo($e->getMessage());
            return ['success' => false, 'error' => '40301'];
        }

        if ($mediaCount > $mediaLimit) {
            return ['success' => false, 'error' => '30267'];
        } else {
            return ['success' => true, 'error' => null];
        }
    }

    public function createPost(array $args = []): array
    {
        if (!$this->checkAuthentication()) {
            return $this->respondWithError(60501);
        }

        if (empty($args)) {
            return $this->respondWithError(30101);
        }

        foreach (['title', 'media', 'contenttype'] as $field) {
            if (empty($args[$field])) {
                return $this->respondWithError(30210);
            }
        }

        $this->logger->info('PostService.createPost started');

        $postId = self::generateUUID();
        if (empty($postId)) {
            $this->logger->critical('Failed to generate post ID');
            return $this->respondWithError(41511);
        }

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
            'createdat' => $createdAt,
        ];

        if ($postData['feedid']) {
            if (!$this->postMapper->isNewsFeedExist($postData['feedid'])) {
                return $this->respondWithError(41512);
            }

            if (!$this->postMapper->isHasAccessInNewsFeed($postData['feedid'], $this->currentUserId)) {
                return $this->respondWithError(31801);
            }
        }

        try {
            $this->postMapper->beginTransaction();
            // Media Upload
            if ($this->isValidMedia($args['media'])) {
                $validateContentCountResult = $this->validateContentCount($args);
                if (isset($validateContentCountResult['error'])) {
                    return $this->respondWithError($validateContentCountResult['error']);
                }

                $mediaPath = $this->base64filehandler->handleUploads($args['media'], $args['contenttype'], $postId);
                $this->logger->info('PostService.createPost mediaPath', ['mediaPath' => $mediaPath]);

                if (!empty($mediaPath['error'])) {
                    return $this->respondWithError(30251);
                }

                if (!empty($mediaPath['path'])) {
                    $postData['media'] = $this->argsToJsString($mediaPath['path']);
                } else {
                    return $this->respondWithError(30251);
                }
            } else {
                return $this->respondWithError(30101);
            }

            // Cover Upload Nur (Audio & Video)
            if ($this->isValidCover($args)) {
                $coverPath = $this->base64filehandler->handleUploads($args['cover'], 'cover', $postId);
                $this->logger->info('PostService.createPost coverPath', ['coverPath' => $coverPath]);

                if (!empty($coverPath['path'])) {
                    $postData['cover'] = $this->argsToJsString($coverPath['path']);
                } else {
                    return $this->respondWithError(40306);
                }
            }
            try {
                // Post speichern
                $post = new Post($postData);
            } catch (\Throwable $e) {
                $this->postMapper->rollback();
                return $this->respondWithError($e->getMessage());
            }
            $this->postMapper->insert($post);

            if (isset($mediaPath['path']) && !empty($mediaPath['path'])) {
                // Media Posts_media
                foreach ($mediaPath['path'] as $media) {
                    $postMed = [
                        'postid' => $postId,
                        'contenttype' => $args['contenttype'],
                        'media' => $media['path'],
                        'options' => $this->argsToJsString($media['options']),
                    ];

                    $postMedia = new PostMedia($postMed);
                    $this->postMapper->insertmed($postMedia);
                }
            }

            if (isset($coverPath['path']) && !empty($coverPath['path'])) {
                // Cover Posts_media
                $coverDecoded = $coverPath['path'] ?? null;
                $coverMed = [
                    'postid' => $postId,
                    'contenttype' => 'cover',
                    'media' => $coverDecoded[0]['path'],
                    'options' => $this->argsToJsString($coverDecoded[0]['options']),
                ];

                $coverMedia = new PostMedia($coverMed);
                $this->postMapper->insertmed($coverMedia);
            }

            // Tags speichern
            try {
                if (!empty($args['tags']) && is_array($args['tags'])) {
                    $this->handleTags($args['tags'], $postId, $createdAt);
                } 
            } catch (\Throwable $e) {
                $this->postMapper->rollback();
                return $this->respondWithError(30262);
            }

            // Metadaten für eigene Posts (kein Feed)
            if (!$postData['feedid']) {
                $this->insertPostMetadata($postId, $this->currentUserId);
            }

            $tagPosts = $this->tagPostMapper->loadByPostId($postId);
            $tagNames = [];

            foreach ($tagPosts as $tp) {
                $tag = $this->tagMapper->loadById($tp->getTagId());
                if ($tag) {
                    $tagNames[] = $tag->getName();
                }
            }

            $data = $post->getArrayCopy();
            $data['tags'] = $tagNames;
            $this->postMapper->commit();
            return $this->createSuccessResponse(11513, $data);

        } catch (\Throwable $e) {
            $this->postMapper->rollback();
            $this->logger->error('Failed to create post', ['exception' => $e]);
            return $this->respondWithError(41508);
        }
    }

    private function isValidMedia($media): bool
    {
        return isset($media) && is_array($media) && !empty($media);
    }

    private function isValidCover(array $args): bool
    {
        return isset($args['cover']) && is_array($args['cover']) && !empty($args['cover'])
            && in_array($args['contenttype'], ['audio', 'video'], true);
    }

    private function handleTags(array $tags, string $postId, string $createdAt): void
    {
        $maxTags = 10;
        if (count($tags) > $maxTags) {
            throw new \Throwable('Maximum tag limit exceeded');
        }

        foreach ($tags as $tagName) {
            $tagName = !empty($tagName) ? trim((string) $tagName) : '';
            
            if (strlen($tagName) < 2 || strlen($tagName) > 53 || !preg_match('/^[a-zA-Z0-9_-]+$/', $tagName)) {
                throw new \Throwable('Invalid tag name');
            }

            $tag = $this->tagMapper->loadByName($tagName);
            
            if (!$tag) {
                $this->logger->info('get tag name', ['tagName' => $tagName]);
                unset($tag);
                $tag = $this->createTag($tagName);
            }
            
            if (!$tag) {
                $this->logger->error('Failed to load or create tag', ['tagName' => $tagName]);
                throw new \Throwable('Failed to load or create tag: ' . $tagName);
            }

            $tagPost = new TagPost([
                'postid' => $postId,
                'tagid' => $tag->getTagId(), 
                'createdat' => $createdAt,
            ]);

            try {
                $this->tagPostMapper->insert($tagPost);
            } catch (\Throwable $e) {
                $this->logger->error('Failed to insert tag-post relationship', [
                    'postid' => $postId,
                    'tagName' => $tagName,
                    'exception' => $e->getMessage(),
                ]);
                throw new \Throwable('Failed to insert tag-post relationship: ' . $tagName);
            }
        }
    }

    private function createTag(string $tagName): Tag|false|array
    {
        $tagId = 0;
        $tagData = ['tagid' => $tagId, 'name' => $tagName];

        $tag = new Tag($tagData);

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
            return $this->respondWithError(60501);
        }

        $this->logger->info("PostService.fetchAll started");

        $offset = max((int)($args['offset'] ?? 0), 0);
        $limit = min(max((int)($args['limit'] ?? 10), 1), 20);

        try {
            $posts = $this->postMapper->fetchAll($offset, $limit);
            $result = array_map(fn(Post $post) => $post->getArrayCopy(), $posts);

            $this->logger->info("Posts fetched successfully", ['count' => count($result)]);
            return $this->createSuccessResponse(11502, [$result]);

        } catch (\Throwable $e) {
            $this->logger->error("Error fetching Posts", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return $this->respondWithError(41513);
        }
    }

    public function findPostser(?array $args = []): array|false
    {
        if (!$this->checkAuthentication()) {
            return $this->respondWithError(60501);
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
            return $this->respondWithError(30209);
        }

        if ($userId !== null && !self::isValidUUID($userId)) {
            return $this->respondWithError(30201);
        }

        if ($title !== null && strlen((string)$title) < 2 || strlen((string)$title) > 33) {
            return $this->respondWithError(30210);
        }

        if ($from !== null && !self::validateDate($from)) {
            return $this->respondWithError(30212);
        }

        if ($to !== null && !self::validateDate($to)) {
            return $this->respondWithError(30213);
        }

        if ($tag !== null) {
            if (!preg_match('/^[a-zA-Z0-9_]+$/', $tag)) {
                $this->logger->error('Invalid tag format provided', ['tag' => $tag]);
                return $this->respondWithError(30211);
            }
        }

        if (!empty($filterBy) && is_array($filterBy)) {
            $allowedTypes = ['IMAGE', 'AUDIO', 'VIDEO', 'TEXT', 'FOLLOWED', 'FOLLOWER'];

            $invalidTypes = array_diff(array_map('strtoupper', $filterBy), $allowedTypes);

            if (!empty($invalidTypes)) {
                return $this->respondWithError(30103);
            }
        }

        if ($Ignorlist !== null) {
            $Ignorlisten = ['YES', 'NO'];
            if (!in_array($Ignorlist, $Ignorlisten, true)) {
                return $this->respondWithError(30103);
            }
        }

        $this->logger->info("PostService.findPostser started");

        $results = $this->postMapper->findPostser($this->currentUserId, $args);
        if (empty($results) && $postId != null) {
            return $this->respondWithError(31510); 
        }

        return $results;
    }

    public function getChatFeedsByID(string $feedid): ?array
    {
        if (!$this->checkAuthentication() || !self::isValidUUID($feedid)) {
            return $this->respondWithError(30103);
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
                'ResponseCode' => 11808,
                'affectedRows' => $result,
            ];
        } catch (\Throwable $e) {
            $this->logger->error('Failed to fetch chat feeds', ['feedid' => $feedid, 'exception' => $e]);
            return $this->respondWithError(41807);
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
            return $this->respondWithError(30209);
        }

        $this->logger->info('PostService.deletePost started');

        $posts = $this->postMapper->loadById($id);
        if (!$posts) {
            return $this->createSuccessResponse(21516);
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
                    'ResponseCode' => 11510,
                ];
            }
        } catch (\Throwable $e) {
            return $this->respondWithError(41510);
        }

        return $this->respondWithError(41510);
    }
}
