<?php

declare(strict_types=1);

namespace Fawaz\App;

use Fawaz\App\Post;
use Fawaz\App\Comment;
use Fawaz\App\Profile;
use Fawaz\App\Models\MultipartPost;
use Fawaz\App\Specs\SpecTypes\User\BasicUserSpec;
use Fawaz\App\Specs\SpecTypes\HiddenContent\HiddenContentFilterSpec;
use Fawaz\App\Specs\SpecTypes\IllegalContent\IllegalContentFilterSpec;
use Fawaz\App\Specs\SpecTypes\User\HideInactiveUserPostSpec;
use Fawaz\App\Specs\SpecTypes\User\InactiveUserPostSpec;
use Fawaz\App\Specs\SpecTypes\User\InactiveUserSpec;
use Fawaz\App\Specs\SpecTypes\IllegalContent\PlaceholderIllegalContentFilterSpec;
use Fawaz\config\constants\PeerUUID;
use Fawaz\Database\CommentMapper;
use Fawaz\Database\PostInfoMapper;
use Fawaz\Database\PostMapper;
use Fawaz\Database\TagMapper;
use Fawaz\Database\TagPostMapper;
use Fawaz\Services\ContentFiltering\Replacers\ContentReplacer;
use Fawaz\Services\ContentFiltering\Types\ContentFilteringAction;
use Fawaz\Services\ContentFiltering\Types\ContentFilteringStrategies;
use Fawaz\Services\ContentFiltering\Types\ContentType;
use Fawaz\Services\FileUploadDispatcher;
use Fawaz\Services\VideoCoverGenerator;
use Fawaz\Services\Base64FileHandler;
use Fawaz\Utils\ResponseHelper;
use Fawaz\Utils\PeerLoggerInterface;
use Fawaz\config\ContentLimitsPerPost;
use Fawaz\Services\JWTService;

use Fawaz\config\constants\ConstantsConfig;
use Fawaz\Database\Interfaces\TransactionManager;
use Fawaz\Database\Interfaces\ProfileRepository;

class PostService
{
    use ResponseHelper;

    protected ?string $currentUserId = null;

    public function __construct(
        protected PeerLoggerInterface $logger,
        protected PostMapper $postMapper,
        protected CommentMapper $commentMapper,
        protected PostInfoMapper $postInfoMapper,
        protected TagMapper $tagMapper,
        protected TagPostMapper $tagPostMapper,
        protected FileUploadDispatcher $base64filehandler,
        protected VideoCoverGenerator $videoCoverGenerator,
        protected Base64FileHandler $base64Encoder,
        protected DailyFreeService $dailyFreeService,
        protected WalletService $walletService,
        protected JWTService $tokenService,
        protected TransactionManager $transactionManager,
        protected ProfileRepository $profileRepository
    ) {
    }

    public function setCurrentUserId(string $userid): void
    {
        $this->currentUserId = $userid;
    }

    public static function isValidUUID(string $uuid): bool
    {
        return preg_match('/^[a-fA-F0-9]{8}-[a-fA-F0-9]{4}-[a-fA-F0-9]{4}-[a-fA-F0-9]{4}-[a-fA-F0-9]{12}$/', $uuid) === 1;
    }

    private static function validateDate($date, $format = 'Y-m-d')
    {
        $d = \DateTime::createFromFormat($format, $date);
        return $d && $d->format($format) === $date;
    }

    private function checkAuthentication(): bool
    {
        if ($this->currentUserId === null) {
            $this->logger->warning('Unauthorized action attempted.');
            return false;
        }
        return true;
    }

    private function argsToJsString($args)
    {
        return json_encode($args);
    }

    private function argsToString($args)
    {
        return serialize($args);
    }

    private function validateCoverCount(array $args, string $contenttype): array
    {
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
            return ['success' => false, 'error' => 30268 ];
        } else {
            return ['success' => true, 'error' => null];
        }
    }


    private function validateContentCount(array $args): array
    {
        if (!isset($args['contenttype']) && empty($args['contenttype']) && !is_string($args['contenttype'])) {
            return ['success' => false, 'error' => 30206];
        }
        $contenttype = strval($args['contenttype']);
        if (!isset($args['media']) && empty($args['media']) && !is_array($args['media'])) {
            return ['success' => false, 'error' => 30102 ];
        }
        if (isset($args['cover']) && !empty($args['cover'])) {
            return $this->validateCoverCount($args, $contenttype);
        }

        $media = $args['media'];
        $mediaCount = count($media);

        try {
            $mediaLimitObj = ContentLimitsPerPost::from($contenttype);
            if (!$mediaLimitObj) {
                return ['success' => false, 'error' => 40301 ];
            }
            $mediaLimit = $mediaLimitObj->mediaLimit();
        } catch (\Throwable $e) {
            echo($e->getMessage());
            return ['success' => false, 'error' => 40301];
        }

        if ($mediaCount > $mediaLimit) {
            return ['success' => false, 'error' => 30267 ];
        } else {
            return ['success' => true, 'error' => null];
        }
    }

    public function createPost(array $args = []): array
    {
        if (!$this->checkAuthentication()) {
            return $this::respondWithError(60501);
        }

        if (empty($args)) {
            return $this::respondWithError(30101);
        }

        foreach (['title', 'contenttype'] as $field) {
            if (empty($args[$field])) {
                return $this::respondWithError(30210);
            }
        }

        $this->logger->debug('PostService.createPost started');

        $postId = self::generateUUID();

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

        try {
            $this->transactionManager->beginTransaction();
            // Media Upload
            if (isset($args['media']) && $this->isValidMedia($args['media'])) {
                $validateContentCountResult = $this->validateContentCount($args);
                if (isset($validateContentCountResult['error'])) {
                    return $this::respondWithError($validateContentCountResult['error']);
                }

                $mediaPath = $this->base64filehandler->handleUploads($args['media'], $args['contenttype'], $postId);
                $this->logger->info('PostService.createPost mediaPath', ['mediaPath' => $mediaPath]);

                if (!empty($mediaPath['error'])) {
                    return $this::respondWithError(30251);
                }

                if (!empty($mediaPath['path'])) {
                    $postData['media'] = $this->argsToJsString($mediaPath['path']);
                } else {
                    return $this::respondWithError(30251);
                }
            } elseif (isset($args['uploadedFiles']) && !empty($args['uploadedFiles'])) {

                try {

                    $validateSameMediaType = new MultipartPost(['media' => explode(',', $args['uploadedFiles'])], [], false);

                    if (!$validateSameMediaType->isFilesExists()) {
                        return $this::respondWithError(31511);
                    }

                    $hasSameMediaType = $validateSameMediaType->validateSameContentTypes();

                    if ($hasSameMediaType) {
                        $postData['contenttype'] = $hasSameMediaType;
                        $args['contenttype'] = $hasSameMediaType;
                        $uploadedFileArray = $this->postMapper->handelFileMoveToMedia($args['uploadedFiles']);
                        $this->postMapper->updateTokenStatus($this->currentUserId);

                        $mediaPath['path'] = $uploadedFileArray;

                        if (!empty($mediaPath['path'])) {
                            $postData['media'] = $this->argsToJsString($mediaPath['path']);
                        } else {
                            if (isset($args['uploadedFiles'])) {
                                $this->postMapper->revertFileToTmp($args['uploadedFiles']);
                            }
                            return $this::respondWithError(30101);
                        }
                    } else {
                        if (isset($args['uploadedFiles'])) {
                            $this->postMapper->revertFileToTmp($args['uploadedFiles']);
                        }
                        return $this::respondWithError(30266); // Provided files should have same type
                    }
                } catch (\Exception $e) {
                    if (isset($args['uploadedFiles'])) {
                        $this->postMapper->revertFileToTmp($args['uploadedFiles']);
                    }
                    $this->logger->error('PostService.createPost Unexpected error occurred', [
                        'message' => $e->getMessage(),
                        'trace'   => $e->getTraceAsString(),
                    ]);
                    return $this::respondWithError(40301); // Unexpected error occurred
                }


            } else {
                return $this::respondWithError(30101);
            }

            // Cover Upload Nur (Audio & Video)
            if ($this->isValidCover($args)) {
                $coverPath = $this->base64filehandler->handleUploads($args['cover'], 'cover', $postId);
                $this->logger->info('PostService.createPost coverPath', ['coverPath' => $coverPath]);

                if (!empty($coverPath['path'])) {
                    $postData['cover'] = $this->argsToJsString($coverPath['path']);
                } else {
                    if (isset($args['uploadedFiles'])) {
                        $this->postMapper->revertFileToTmp($args['uploadedFiles']);
                    }
                    return $this::respondWithError(40306);
                }
            } elseif ($args['contenttype'] === 'video') {
                $videoRelativePath = $mediaPath['path'][0]['path'];
                $videoFilePath = __DIR__ . '/../../runtime-data/media' . $videoRelativePath;

                $coverResult = $this->generateCoverFromVideo($videoFilePath, $postId);

                if (!empty($coverResult['path'])) {
                    $postData['cover'] = $this->argsToJsString($coverResult['path']);
                    $coverPath = ['path' => $coverResult['path']];
                }
            }
            try {
                // Post speichern
                $post = new Post($postData);
            } catch (\Throwable $e) {
                $this->transactionManager->rollback();
                $this->logger->error('Failed to create post', [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
                return $this::respondWithError(30263);
                if (isset($args['uploadedFiles'])) {
                    $this->postMapper->revertFileToTmp($args['uploadedFiles']);
                }
                return $this::respondWithError($e->getMessage());
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
                $coverDecoded = $coverPath['path'];
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
                $this->transactionManager->rollback();
                if (isset($args['uploadedFiles'])) {
                    $this->postMapper->revertFileToTmp($args['uploadedFiles']);
                }
                return $this::respondWithError(30262);
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
            $this->transactionManager->commit();
            return $this::createSuccessResponse(11513, $data);

        } catch (\Throwable $e) {
            $this->transactionManager->rollback();
            if (isset($args['uploadedFiles'])) {
                $this->postMapper->revertFileToTmp($args['uploadedFiles']);
            }
            $this->logger->error('Failed to create post', ['exception' => $e]);
            return $this::respondWithError(41508);
        }
    }

    private function generateCoverFromVideo(string $videoFilePath, string $postId): ?array
    {
        $generatedCoverPath = null;

        try {
            $generatedCoverPath = $this->videoCoverGenerator->generate($videoFilePath);
            $base64String = $this->base64Encoder->encodeFileToBase64($generatedCoverPath, 'image/jpeg');

            $coverResult = $this->base64filehandler->handleUploads([$base64String], 'cover', $postId);

            if (!empty($coverResult['path'])) {
                $this->logger->info('Autogenerated cover used', ['coverPath' => $coverResult['path']]);

                $this->videoCoverGenerator->deleteTemporaryFile($generatedCoverPath);
                $this->logger->debug('Temporary cover file deleted after success', ['path' => $generatedCoverPath]);

                return $coverResult;
            } else {
                $this->logger->error('Autogenerated cover upload failed', ['error' => $coverResult['error'] ?? 'unknown']);

                $this->videoCoverGenerator->deleteTemporaryFile($generatedCoverPath);
                $this->logger->debug('Temporary cover file deleted after upload failure', ['path' => $generatedCoverPath]);
            }

        } catch (\Throwable $e) {
            $this->logger->error('Autogenerated cover generation error', ['exception' => $e]);

            $this->videoCoverGenerator->deleteTemporaryFile($generatedCoverPath);
            $this->logger->debug('Temporary cover file deleted after exception', ['path' => $generatedCoverPath]);
        }

        return null;
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
        $tagNameConfig = ConstantsConfig::post()['TAG'];
        if (count($tags) > $maxTags) {
            throw new \Exception('Maximum tag limit exceeded');
        }

        foreach ($tags as $tagName) {
            $tagName = !empty($tagName) ? trim((string) $tagName) : '';

            if (strlen($tagName) < 2 || strlen($tagName) > 53 || !preg_match('/^[a-zA-Z0-9_-]+$/', $tagName)) {
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
                throw new \Exception('Failed to insert tag-post relationship: ' . $tagName);
            }
        }
    }

    private function createTag(string $tagName): Tag|false
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
            return $this::respondWithError(60501);
        }

        $this->logger->debug("PostService.fetchAll started");

        $offset = max((int)($args['offset'] ?? 0), 0);
        $limit = min(max((int)($args['limit'] ?? 10), 1), 20);

        try {
            $posts = $this->postMapper->fetchAll($offset, $limit);
            $result = array_map(fn (Post $post) => $post->getArrayCopy(), $posts);

            $this->logger->info("Posts fetched successfully", ['count' => count($result)]);
            return $this::createSuccessResponse(11502, [$result]);

        } catch (\Throwable $e) {
            $this->logger->error("Error fetching Posts", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return $this::respondWithError(41513);
        }
    }

    public function findPostser(?array $args = []): array|false
    {
        if (!$this->checkAuthentication()) {
            return $this::respondWithError(60501);
        }
        $userId = $args['userid'] ?? null;

        $from = $args['from'] ?? null;
        $to = $args['to'] ?? null;
        $filterBy = $args['filterBy'] ?? [];
        $Ignorlist = $args['IgnorList'] ?? null;
        $sortBy = $args['sortBy'] ?? null;
        $title = $args['title'] ?? null;
        $tag = $args['tag'] ?? null;
        $postId = $args['postid'] ?? null;
        $titleConfig = ConstantsConfig::post()['TITLE'];
        $contentFilterBy = $args['contentFilterBy'] ?? null;
        $commentOffset = max((int)($args['commentOffset'] ?? 0), 0);
        $commentLimit = min(max((int)($args['commentLimit'] ?? 10), 1), 20);
        
        $contentFilterStrategy = ContentFilteringStrategies::postFeed;
        $illegalPostContentAction = ContentFilteringAction::hideContent;

        if ($postId !== null && !self::isValidUUID($postId)) {
            return $this::respondWithError(30209);
        }

        if ($userId !== null && !self::isValidUUID($userId)) {
            return $this::respondWithError(30201);
        }

        if ($title !== null && (strlen((string)$title) < $titleConfig['MIN_LENGTH'] || strlen((string)$title) > $titleConfig['MAX_LENGTH'])) {
            return $this::respondWithError(30210);
        }

        if ($from !== null && !self::validateDate($from)) {
            return $this::respondWithError(30212);
        }

        if ($to !== null && !self::validateDate($to)) {
            return $this::respondWithError(30213);
        }

        if ($tag !== null) {
            if (!preg_match('/' . $titleConfig['PATTERN'] . '/u', $tag)) {
                $this->logger->warning('Invalid tag format provided', ['tag' => $tag]);
                return $this->respondWithError(30211);
            }
        }

        if (!empty($filterBy) && is_array($filterBy)) {
            $allowedTypes = ['IMAGE', 'AUDIO', 'VIDEO', 'TEXT', 'FOLLOWED', 'FOLLOWER', 'VIEWED', 'FRIENDS'];

            $invalidTypes = array_diff(array_map('strtoupper', $filterBy), $allowedTypes);

            if (!empty($invalidTypes)) {
                return $this::respondWithError(30103);
            }
        }

        if ($Ignorlist !== null) {
            $Ignorlisten = ['YES', 'NO'];
            if (!in_array($Ignorlist, $Ignorlisten, true)) {
                return $this::respondWithError(30103);
            }
        }

        $this->logger->debug("PostService.findPostser started");
        
        if ($title || $tag) {
            $contentFilterStrategy = ContentFilteringStrategies::searchByMeta;
            $illegalPostContentAction = ContentFilteringAction::hideContent;
        }
        if ($userId || $postId) {
            $contentFilterStrategy = ContentFilteringStrategies::searchById;
            $illegalPostContentAction = ContentFilteringAction::replaceWithPlaceholder;
        }
        if ($userId && $userId === $this->currentUserId) { 
            $contentFilterStrategy = ContentFilteringStrategies::myprofile;
            $illegalPostContentAction = ContentFilteringAction::replaceWithPlaceholder;
        }
        
        $inactiveUserSpec = new InactiveUserSpec(
            ContentFilteringAction::replaceWithPlaceholder
        );
        $hideInactiveUserPostSpec = new HideInactiveUserPostSpec();
        
        $basicUserSpec = new BasicUserSpec(
            ContentFilteringAction::replaceWithPlaceholder
        );
        
        $placeholderIllegalPostContentFilterSpec = new IllegalContentFilterSpec(
            $illegalPostContentAction,
            ContentType::post
        );

        $placeholderIllegalUserContentFilterSpec = new IllegalContentFilterSpec(
            $illegalPostContentAction,
            ContentType::user
        );

        $placeholderIllegalCommentContentFilterSpec = new IllegalContentFilterSpec(
            $illegalPostContentAction,
            ContentType::comment
        );

        $postsHiddenContentFilterSpec = new HiddenContentFilterSpec(
            $contentFilterStrategy,
            $contentFilterBy,
            $this->currentUserId,
            $userId,
            ContentType::post,
            ContentType::post
        );

        $userHiddenContentFilterSpec = new HiddenContentFilterSpec(
            $contentFilterStrategy,
            $contentFilterBy,
            $this->currentUserId,
            $userId,
            ContentType::post,
            ContentType::user
        );

        $commentsHiddenContentFilterSpec = new HiddenContentFilterSpec(
            $contentFilterStrategy,
            $contentFilterBy,
            $this->currentUserId,
            $userId,
            ContentType::post,
            ContentType::comment
        );

        $postSpecs = [
            $inactiveUserSpec,
            $basicUserSpec,
            $hideInactiveUserPostSpec,
            $placeholderIllegalPostContentFilterSpec,
            $postsHiddenContentFilterSpec,
        ];

        $userSpecs = [
            $inactiveUserSpec,
            $basicUserSpec,
            $placeholderIllegalUserContentFilterSpec,
            $userHiddenContentFilterSpec
        ];

        $commentSpecs = [
            $inactiveUserSpec,
            $basicUserSpec,
            $placeholderIllegalCommentContentFilterSpec,
            $commentsHiddenContentFilterSpec
        ];

        try {
            $results = $this->postMapper->findPostser($this->currentUserId,$postSpecs, $args);
            if (empty($results) && $postId != null) {
                return $this::respondWithError(31510);
            }
            $postsEnriched = $this->enrichWithProfileAndComment(
                $results, 
                $userSpecs, 
                $commentSpecs, 
                $this->currentUserId,
                $commentOffset,
                $commentLimit
            );


            foreach($postsEnriched as $post) {
                ContentReplacer::placeholderPost($post, $postSpecs);
            }
            return $postsEnriched;
        } catch (\Throwable $e) {
            // Log and fall back to original results
            $this->logger->error('Failed to load list post', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return false;
        }
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

    // public function deletePost(string $id): array
    // {
    //     if (!$this->checkAuthentication() || !self::isValidUUID($id)) {
    //         return $this::respondWithError('Invalid feed ID');
    //     }

    //     if (!self::isValidUUID($id)) {
    //         return $this::respondWithError(30209);
    //     }

    //     $this->logger->debug('PostService.deletePost started');

    //     $posts = $this->postMapper->loadById($id);
    //     if (!$posts) {
    //         return $this::createSuccessResponse(21516);
    //     }

    //     $post = $posts->getArrayCopy();

    //     if ($post['userid'] !== $this->currentUserId && !$this->postMapper->isCreator($id, $this->currentUserId)) {
    //         return $this::respondWithError('Unauthorized: You can only delete your own posts.');
    //     }

    //     try {
    //         $postid = $this->postMapper->delete($id);

    //         if ($postid) {
    //             $this->logger->info('Post deleted successfully', ['postid' => $postid]);
    //             return [
    //                 'status' => 'success',
    //                 'ResponseCode' => "11510",
    //             ];
    //         }
    //     } catch (\Throwable $e) {
    //         return $this::respondWithError(41510);
    //     }

    //     return $this::respondWithError(41510);
    // }


    /**
     * Check for If user eligibile to make a post or not
     *
     * @returns with Suggested PostId, JWT which will be valid for certain time
     */
    public function postEligibility(bool $isTokenGenerationRequired = true): ?array
    {
        if (!$this->checkAuthentication()) {
            return $this::respondWithError(60501);
        }

        $this->logger->debug('GraphQLSchemaBuilder.postEligibility started');

        $dailyFree = ConstantsConfig::dailyFree()['DAILY_FREE_ACTIONS'];
        $prices    = ConstantsConfig::tokenomics()['ACTION_TOKEN_PRICES'];
        $actions = ConstantsConfig::wallet()['ACTIONS'];

        try {
            $dailyLimits = [
                'like' => $dailyFree['like'],
                'comment' => $dailyFree['comment'],
                'post' => $dailyFree['post'],
                'dislike' => $dailyFree['dislike'],
            ];

            $actionPrices = [
                'like' => $prices['like'],
                'comment' => $prices['comment'],
                'post' => $prices['post'],
                'dislike' => $prices['dislike'],
            ];

            $actionMaps = [
                'like' => $actions['LIKE'],
                'comment' => $actions['COMMENT'],
                'post' => $actions['POST'],
                'dislike' => $actions['DISLIKE'],
            ];

            $limit = $dailyLimits['post'];
            $price = $actionPrices['post'];
            $actionMap = $actionMaps['post'];

            $response = [
                        'status' => 'error',
                        'ResponseCode' => "40301", // Not eligible for upload for post
                    ];
            $hasFreeDaily = false;

            $DailyUsage = $this->dailyFreeService->getUserDailyUsage($this->currentUserId, $actionMap);
            if ($DailyUsage < $limit) {
                // generate PostId and JWT
                $hasFreeDaily = true;
            }

            $balance = $this->walletService->getUserWalletBalance($this->currentUserId);
            // Return ResponseCode with Daily Free Code
            if ($balance < $price && !$hasFreeDaily) {
                $this->logger->warning('Insufficient wallet balance', ['userId' => $this->currentUserId, 'balance' => $balance, 'price' => $price]);
                return $this::respondWithError(51301);
            }

            $this->transactionManager->beginTransaction();
            // generate PostId and JWT
            $eligibilityToken = $this->tokenService->createAccessTokenWithCustomExpriy($this->currentUserId, 300);

            if ($isTokenGenerationRequired) {
                // Add Eligibility Token to DB table eligibility_token
                $this->postMapper->addOrUpdateEligibilityToken($this->currentUserId, $eligibilityToken, 'NO_FILE');
            }
            $this->transactionManager->commit();
            $response = [
                        'status' => 'success',
                        'ResponseCode' => "10901", // You are eligible for post upload
                    ];
            $response['eligibilityToken'] = $eligibilityToken;

            return $response;

        } catch (ValidationException $e) {
            $this->transactionManager->rollback();
            $this->logger->warning("PostService.postEligibility Limit exceeded: You can only create 5 records within 1 hour while status is NO_FILE or FILE_UPLOADED", ['error' => $e->getMessage(), 'mess' => $e->getErrors()]);
            return self::respondWithError($e->getErrors()[0]);
        } catch (\Throwable $e) {
            $this->transactionManager->rollback();
            $this->logger->error('PostService.postEligibility exception', [
                'message' => $e->getMessage(),
                'trace'   => $e->getTraceAsString(),
            ]);
            return $this::respondWithError(40301);
        }
    }

    /**
     * Get Interaction with post or comment
     */
    public function postInteractions(?array $args = []): array|false
    {
        if (!$this->checkAuthentication()) {
            $this->logger->info("PostService.postInteractions failed due to authentication");
            return $this::respondWithError(60501);
        }

        $this->logger->debug("PostService.postInteractions started");

        $offset = max((int)($args['offset'] ?? 0), 0);
        $limit = min(max((int)($args['limit'] ?? 10), 1), 20);

        $getOnly = $args['getOnly'] ?? null;
        $postOrCommentId = $args['postOrCommentId'] ?? null;
        $contentFilterBy = $args['contentFilterBy'] ?? null;


        if ($getOnly == null || $postOrCommentId == null || !in_array($getOnly, ['VIEW', 'LIKE', 'DISLIKE', 'COMMENTLIKE'])) {
            $this->logger->info("PostService.postInteractions failed due to empty or invalid arguments");
            return $this::respondWithError(30103);
        }

        if (!self::isValidUUID($postOrCommentId)) {
            $this->logger->info("PostService.postInteractions failed due to invalid postOrCommentId");
            return $this::respondWithError(30201);
        }

        try {
            $result = $this->postMapper->getInteractions(
                $getOnly,
                $postOrCommentId,
                $this->currentUserId,
                $offset,
                $limit,
                $contentFilterBy
            );

            $this->logger->info("Interaction fetched successfully", ['count' => count($result)]);
            return $this::createSuccessResponse(11205, $result);

        } catch (\Throwable $e) {
            $this->logger->error("Error fetching Posts", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return $this::respondWithError(41513);
        }
    }
    /**
     * Get Guest List Post
     */
    public function getGuestListPost(?array $args = []): array|false
    {
        $commentOffset = max((int)($args['commentOffset'] ?? 0), 0);
        $commentLimit = min(max((int)($args['commentLimit'] ?? 10), 1), 20);
        $postId = $args['postid'] ?? null;

        if (!self::isValidUUID($postId)) {
            return $this::respondWithError(30209);
        }

        $this->logger->debug("PostService.getGuestListPost started");
        
        $inactiveUserSpec = new InactiveUserSpec(
            ContentFilteringAction::replaceWithPlaceholder
        );

        $hideInactiveUserPostSpec = new HideInactiveUserPostSpec();

        $basicUserSpec = new BasicUserSpec(
            ContentFilteringAction::replaceWithPlaceholder
        );

        $placeholderIllegalContentFilterSpec = new IllegalContentFilterSpec(
            ContentFilteringAction::replaceWithPlaceholder,
            ContentType::comment
        );
        
        
        $postSpecs = [
            $hideInactiveUserPostSpec,
            $placeholderIllegalContentFilterSpec,
        ];

        $userSpecs = [
            $inactiveUserSpec,
            $basicUserSpec,
            $placeholderIllegalContentFilterSpec,
        ];

        $commentsSpecs = [
            $placeholderIllegalContentFilterSpec,
        ];

        try {
            $results = $this->postMapper->findPostser(
                PeerUUID::empty->value,
                $postSpecs,
                $args
            );
            
            if (empty($results)) {
                return $this::respondWithError(31510);
            }
            $postsEnriched = $this->enrichWithProfileAndComment(
                $results, 
                $userSpecs, 
                $commentsSpecs,
                PeerUUID::empty->value,
                $commentOffset,
                $commentLimit
            );
            foreach($postsEnriched as $post) {
                ContentReplacer::placeholderPost($post, $postSpecs);
            }
            return $postsEnriched;
        } catch (\Throwable $e) {
            // Log and fall back to original results
            $this->logger->error('Failed to load guest list post', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return false;
        }
    }

    /**
     * Enrich a list of PostAdvanced with user profiles and return PostAdvancedWithUser objects.
     * Falls back gracefully if no profiles found.
     *
     * @param PostAdvanced[] $posts Array of PostAdvanced
     * @param \Fawaz\App\Specs\Specification[] $userSpecs Content filtering specs
     * @param \Fawaz\App\Specs\Specification[] $commentSpecs Content filtering specs
     * @param string $currentUserId Current/guest user id for profile fetch
     * @param int $commentOffset
     * @param int $commentLimit
     * @return PostAdvanced[]
     */
    private function enrichWithProfileAndComment(
        array $posts, 
        array $userSpecs, 
        array $commentSpecs, 
        string $currentUserId, 
        int $commentOffset, 
        int $commentLimit
    ): array {
        $userIdsFromPosts = array_values(
            array_unique(
                array_filter(
                    array_map(fn(PostAdvanced $post) => $post->getUserId(),$posts)
                )
            )
        );

        if (empty($userIdsFromPosts)) {
            return $posts;
        }

        $profiles = $this->profileRepository->fetchByIds($userIdsFromPosts, $currentUserId, $userSpecs);

        $enriched = [];
        foreach ($posts as $post) {
            $data = $post->getArrayCopy();
            $enrichedWithProfiles = $this->enrichAndPlaceholderWithProfile($data, $profiles[$post->getUserId()], $userSpecs);
            $enrichedWithCommentsAndProfiles = $this->enrichAndPlaceholderWithComments(
                $enrichedWithProfiles,
                $commentSpecs,
                $userSpecs,
                $commentOffset,
                $commentLimit,
                $currentUserId
            );
            $post = new PostAdvanced($enrichedWithCommentsAndProfiles, [],false);
            $enriched[] = $post;
        }

        return $enriched;
    }

    /**
     * Enrich a single PostAdvanced with a Profile and return PostAdvancedWithUser.
     */
    private function enrichAndPlaceholderWithProfile(array $data, ?Profile $profile, array $specs): array
    {
        if ($profile instanceof Profile) {
            ContentReplacer::placeholderProfile($profile, $specs);
            $data['user'] = $profile->getArrayCopy();
        }
        return $data;
    }

    private function enrichAndPlaceholderWithComments(array $data, array $commentSpecs, array $userSpecs, int $commentOffset, int $commentLimit, string $currentUserId): array
    {
        $comments = $this->commentMapper->fetchAllByPostIdetaild($data['postid'],$currentUserId, $commentOffset, $commentLimit);
        
        if (empty($comments)) {
            return $data;
        }
        // add here userids
        $userIdsFromComments = array_values(
            array_unique(
                array_filter(
                    array_map(fn(CommentAdvanced $c) => $c->getUserId(),$comments)
                )
            )
        );

        if (empty($userIdsFromComments)) {
            return $comments;
        }
        $profiles = $this->profileRepository->fetchByIds($userIdsFromComments, $currentUserId, $userSpecs);
        $commentsArray = [];
        foreach($comments as $comment) {
            if ($comment instanceof CommentAdvanced) {
                ContentReplacer::placeholderComments($comment, $commentSpecs);
                $dataComment = $comment->getArrayCopy();
                $enrichedWithProfiles = $this->enrichAndPlaceholderWithProfile($dataComment, $profiles[$comment->getUserId()], $userSpecs);
                // var_dump($enrichedWithProfiles);
                $commentsArray[] = $enrichedWithProfiles;
            }
        }
        $data['comments'] = $commentsArray;
        return $data;
    }

    public function postExistsById(string $postId): bool|array
    {
        if (!$this->checkAuthentication()) {
            return $this->respondWithError(60501);
        }

        $this->logger->debug('PostService.postExistsById started');

        try {
            return $this->postMapper->postExistsById($postId);

        } catch (\Throwable $e) {
            $this->logger->error('Failed fetch Post', ['postId' => $postId, 'exception' => $e]);
            return false;
        }
    }
}
