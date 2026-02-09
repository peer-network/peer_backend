<?php

declare(strict_types=1);

namespace Fawaz\App;

use Fawaz\App\Post;
use Fawaz\App\Comment;
use Fawaz\App\Interfaces\ProfileService;
use Fawaz\App\Profile;
use Fawaz\App\Models\MultipartPost;
use Fawaz\Database\Interfaces\InteractionsPermissionsMapper;
use Fawaz\Services\ContentFiltering\Specs\SpecTypes\HiddenContent\HiddenContentFilterSpec;
use Fawaz\Services\ContentFiltering\Specs\SpecTypes\HiddenContent\NormalVisibilityStatusSpec;
use Fawaz\Services\ContentFiltering\Specs\SpecTypes\IllegalContent\IllegalContentFilterSpec;
use Fawaz\Services\ContentFiltering\Specs\SpecTypes\User\DeletedUserSpec;
use Fawaz\config\constants\PeerUUID;
use Fawaz\Database\CommentMapper;
use Fawaz\Database\PostInfoMapper;
use Fawaz\Database\PostMapper;
use Fawaz\Database\TagMapper;
use Fawaz\Database\TagPostMapper;
use Fawaz\Services\ContentFiltering\Replacers\ContentReplacer;
use Fawaz\Services\ContentFiltering\Specs\SpecTypes\User\SystemUserSpec;
use Fawaz\Services\ContentFiltering\Types\ContentFilteringCases;
use Fawaz\Services\ContentFiltering\Types\ContentType;
use Fawaz\Services\FileUploadDispatcher;
use Fawaz\Services\TokenTransfer\Strategies\PaidCommentTransferStrategy;
use Fawaz\Services\TokenTransfer\Strategies\PaidDislikeTransferStrategy;
use Fawaz\Services\TokenTransfer\Strategies\PaidLikeTransferStrategy;
use Fawaz\Services\TokenTransfer\Strategies\PaidPostTransferStrategy;
use Fawaz\Services\VideoCoverGenerator;
use Fawaz\Services\Base64FileHandler;
use Fawaz\Utils\ResponseHelper;
use Fawaz\Utils\PeerLoggerInterface;
use Fawaz\config\ContentLimitsPerPost;
use Fawaz\Services\JWTService;
use Fawaz\config\constants\ConstantsConfig;
use Fawaz\Database\Interfaces\TransactionManager;
use Fawaz\Database\Interfaces\ProfileRepository;
use Fawaz\Database\UserMapper;
use Fawaz\Services\ContentFiltering\Specs\SpecTypes\Advertisements\ExcludeAdvertisementsForNormalFeedSpec;

use function grapheme_strlen;

class PostService
{
    use ResponseHelper;

    protected ?string $currentUserId = null;

    public function __construct(
        protected PeerLoggerInterface $logger,
        protected PostMapper $postMapper,
        protected CommentMapper $commentMapper,
        protected CommentService $commentService,
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
        protected ProfileRepository $profileRepository,
        protected InteractionsPermissionsMapper $interactionsPermissionsMapper,
        protected PostInfoService $postInfoService
    ) {
    }

    public function setCurrentUserId(string $userid): void
    {
        $this->currentUserId = $userid;
    }

    private static function validateDate($date, $format = 'Y-m-d')
    {
        $d = \DateTime::createFromFormat($format, $date);
        return $d && $d->format($format) === $date;
    }

    private function checkAuthentication(): bool
    {
        if ($this->currentUserId === null) {
            $this->logger->error('PostService.checkAuthentication: Unauthorized action attempted');
            return false;
        }
        return true;
    }

    private function argsToJsString($args)
    {
        return json_encode($args);
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
    public function resolveActionPost(?array $args = []): ?array
    {
        $tokenomicsConfig = ConstantsConfig::tokenomics();
        $dailyfreeConfig = ConstantsConfig::dailyFree();
        $actions = ConstantsConfig::wallet()['ACTIONS'];
        if (!$this->checkAuthentication()) {
            $this->logger->error('PostService.resolveActionPost: Authentication failed');
            return $this::respondWithError(60501);
        }

        $this->logger->debug('Query.resolveActionPost started');

        $postId = $args['postid'] ?? null;
        $action = $args['action'] = strtolower($args['action'] ?? 'LIKE');

        $freeActions = ['report', 'save', 'share', 'view'];

        if (!empty($postId) && !self::isValidUUID($postId)) {
            $this->logger->error('PostService.resolveActionPost: Invalid postId', ['postId' => $postId]);
            return $this::respondWithError(30209, ['postid' => $postId]);
        }

        if ($postId) {
            $contentFilterCase = ContentFilteringCases::searchById;

            $deletedUserSpec = new DeletedUserSpec(
                $contentFilterCase,
                ContentType::post
            );
            $systemUserSpec = new SystemUserSpec(
                $contentFilterCase,
                ContentType::post
            );

            $illegalContentSpec = new IllegalContentFilterSpec(
                $contentFilterCase,
                ContentType::post
            );

            $specs = [
                $illegalContentSpec,
                $systemUserSpec,
                $deletedUserSpec,
            ];

            if ($this->interactionsPermissionsMapper->isInteractionAllowed(
                $specs,
                $postId
            ) === false) {
                $this->logger->error('PostService.resolveActionPost: Interaction not allowed', ['postId' => $postId]);
                return $this::respondWithError(31513, ['postid' => $postId]);
            }
        }

        if (in_array($action, $freeActions, true)) {
            $response = $this->postInfoService->{$action . 'Post'}($postId);
            return $response;
        }

        $paidActions = ['like', 'dislike', 'comment', 'post'];

        if (!in_array($action, $paidActions, true)) {
            $this->logger->error('PostService.resolveActionPost: Invalid paid action', ['action' => $action]);
            return $this::respondWithError(30105);
        }

        $dailyLimits = [
            'like' => $dailyfreeConfig['DAILY_FREE_ACTIONS']['like'],
            'comment' => $dailyfreeConfig['DAILY_FREE_ACTIONS']['comment'],
            'post' => $dailyfreeConfig['DAILY_FREE_ACTIONS']['post'],
            'dislike' => $dailyfreeConfig['DAILY_FREE_ACTIONS']['dislike'],
        ];

        $actionPrices = [
            'like' => $tokenomicsConfig['ACTION_TOKEN_PRICES']['like'],
            'comment' => $tokenomicsConfig['ACTION_TOKEN_PRICES']['comment'],
            'post' => $tokenomicsConfig['ACTION_TOKEN_PRICES']['post'],
            'dislike' => $tokenomicsConfig['ACTION_TOKEN_PRICES']['dislike'],
        ];

        $actionMaps = [
            'like' => $actions['LIKE'],
            'comment' => $actions['COMMENT'],
            'post' => $actions['POST'],
            'dislike' => $actions['DISLIKE'],
        ];

        // Validations
        if (!isset($dailyLimits[$action]) || !isset($actionPrices[$action])) {
            $this->logger->error('PostService.resolveActionPost: Invalid action parameter', ['action' => $action]);
            return $this::respondWithError(30105);
        }

        $limit = $dailyLimits[$action];
        $price = $actionPrices[$action];
        $actionMap = $args['art'] = $actionMaps[$action];

        $this->transactionManager->beginTransaction();
        try {
            if ($limit > 0) {
                $DailyUsage = $this->dailyFreeService->getUserDailyUsage($this->currentUserId, $actionMap);

                // Return ResponseCode with Daily Free Code
                if ($DailyUsage < $limit) {
                    if ($action === 'comment') {
                        $response = $this->commentService->createComment($args);
                        if (isset($response['status']) && $response['status'] === 'error') {
                            $this->transactionManager->rollback();
                            return $response;
                        }
                        $response['ResponseCode'] = "11608";

                    } elseif ($action === 'post') {
                        $response = $this->createPost($args['input']);
                        if (isset($response['status']) && $response['status'] === 'error') {
                            $this->transactionManager->rollback();
                            return $response;
                        }
                        $response['ResponseCode'] = "11513";
                    } elseif ($action === 'like') {
                        $response = $this->postInfoService->likePost($postId);
                        if (isset($response['status']) && $response['status'] === 'error') {
                            $this->transactionManager->rollback();
                            return $response;
                        }

                        $response['ResponseCode'] = "11514";
                    } else {
                        $this->transactionManager->rollback();
                        $this->logger->error('PostService.resolveActionPost: Unsupported action in free flow', ['action' => $action]);
                        return $this::respondWithError(30105);
                    }

                    if (isset($response['status']) && $response['status'] === 'success') {
                        $incrementResult = $this->dailyFreeService->incrementUserDailyUsage($this->currentUserId, $actionMap);

                        if ($incrementResult === false) {
                            $this->logger->error('PostService.resolveActionPost: Failed to increment daily usage', ['userId' => $this->currentUserId]);
                            $this->transactionManager->rollback();
                            return $this::respondWithError(40301);
                        }

                        $this->transactionManager->commit();
                        return $response;
                    }

                    $this->logger->error("{$action}Post failed", ['response' => $response]);
                    $response['affectedRows'] = $args;
                    $this->transactionManager->rollback();
                    return $response;
                }
            }
            $balance = $this->walletService->getUserWalletBalance($this->currentUserId);

            // Return ResponseCode with Daily Free Code

            if ($balance < $price) {
                $this->logger->error('PostService.resolveActionPost: Insufficient wallet balance', ['userId' => $this->currentUserId, 'price' => $price]);
                $this->transactionManager->rollback();
                return $this::respondWithError(51301);
            }


            if ($action === 'comment') {
                $response = $this->commentService->createComment($args);
                if (isset($response['status']) && $response['status'] === 'error') {
                    $this->transactionManager->rollback();
                    return $response;
                }
                $response['ResponseCode'] = "11605";
            } elseif ($action === 'post') {
                $response = $this->createPost($args['input']);
                if (isset($response['status']) && $response['status'] === 'error') {
                    $this->transactionManager->rollback();
                    return $response;
                }
                $response['ResponseCode'] = "11508";

                if (isset($response['affectedRows']['postid']) && !empty($response['affectedRows']['postid'])) {
                    unset($args['input'], $args['action']);
                    $args['postid'] = $response['affectedRows']['postid'];
                }
            } elseif ($action === 'like') {
                $response = $this->postInfoService->likePost($postId);
                if (isset($response['status']) && $response['status'] === 'error') {
                    $this->transactionManager->rollback();
                    return $response;
                }
                $response['ResponseCode'] = "11503";
            } elseif ($action === 'dislike') {
                $response = $this->postInfoService->dislikePost($postId);
                if (isset($response['status']) && $response['status'] === 'error') {
                    $this->transactionManager->rollback();
                    return $response;
                }
                $response['ResponseCode'] = "11504";
            } else {
                $this->transactionManager->rollback();
                $this->logger->error('PostService.resolveActionPost: Unsupported action in paid flow', ['action' => $action]);
                return $this::respondWithError(30105);
            }

            if (isset($response['status']) && $response['status'] === 'success') {
                assert(in_array($action, ['post', 'like', 'comment', 'dislike'], true));

                $transferStrategy = match($action) {
                    'post' => new PaidPostTransferStrategy(),
                    'like' => new PaidLikeTransferStrategy(),
                    'comment' => new PaidCommentTransferStrategy(),
                    'dislike' => new PaidDislikeTransferStrategy(),
                };

                $deducted = $this->walletService->performPayment($this->currentUserId, $transferStrategy, $args);
                if (isset($deducted['status']) && $deducted['status'] === 'error') {
                    $this->transactionManager->rollback();
                    return $deducted;
                }

                if (!$deducted) {
                    $this->logger->error('PostService.resolveActionPost: Failed to perform payment', ['userId' => $this->currentUserId, 'action' => $action]);
                    $this->transactionManager->rollback();
                    return $this::respondWithError(40301);
                }
                $this->transactionManager->commit();
                return $response;
            }

            $this->logger->error("{$action}Post failed after wallet deduction", ['response' => $response]);
            $response['affectedRows'] = $args;
            $this->transactionManager->rollback();
            return $response;
        } catch (\Throwable $e) {
            $this->logger->error('PostService.resolveActionPost: Unexpected error', [
                'exception' => $e->getMessage(),
                'args' => $args,
            ]);
            $this->transactionManager->rollback();
            return $this::respondWithError(40301);
        }
    }

    public function createPost(array $args = []): array
    {
        if (!$this->checkAuthentication()) {
            $this->logger->error('PostService.createPost: Authentication failed');
            return $this::respondWithError(60501);
        }

        if (empty($args)) {
            $this->logger->error('PostService.createPost: Empty arguments provided');
            return $this::respondWithError(30101);
        }

        foreach (['title', 'contenttype'] as $field) {
            if (empty($args[$field])) {
                $this->logger->error('PostService.createPost: Missing required field', ['field' => $field]);
                return $this::respondWithError(30210);
            }
        }

        $this->logger->debug('PostService.createPost started');

        $postId = self::generateUUID();

        $createdAt = new \DateTime()->format('Y-m-d H:i:s.u');

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
            'visibility_status' => 'normal',
        ];

        try {
            // Media Upload
            if (isset($args['media']) && $this->isValidMedia($args['media'])) {
                $validateContentCountResult = $this->validateContentCount($args);
                if (isset($validateContentCountResult['error'])) {
                    $this->logger->error('PostService.createPost: Invalid content count', ['error' => $validateContentCountResult['error']]);
                    return $this::respondWithError($validateContentCountResult['error']);
                }

                $mediaPath = $this->base64filehandler->handleUploads($args['media'], $args['contenttype'], $postId);
                $this->logger->info('PostService.createPost mediaPath', ['mediaPath' => $mediaPath]);

                if (!empty($mediaPath['error'])) {
                    $this->logger->error('PostService.createPost: Media upload error', ['error' => $mediaPath['error']]);
                    return $this::respondWithError(30251);
                }

                if (!empty($mediaPath['path'])) {
                    $postData['media'] = $this->argsToJsString($mediaPath['path']);
                } else {
                    $this->logger->error('PostService.createPost: Media path missing after upload', ['mediaPath' => $mediaPath]);
                    return $this::respondWithError(30251);
                }
            } elseif (isset($args['uploadedFiles']) && !empty($args['uploadedFiles'])) {

                try {

                    $validateSameMediaType = new MultipartPost(['media' => explode(',', $args['uploadedFiles'])], [], false);

                    if (!$validateSameMediaType->isFilesExists()) {
                        $this->logger->error('PostService.createPost: Uploaded files do not exist', ['uploadedFiles' => $args['uploadedFiles']]);
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
                            $this->logger->error('PostService.createPost: Uploaded files missing after move', ['uploadedFiles' => $args['uploadedFiles']]);
                            return $this::respondWithError(30101);
                        }
                    } else {
                        if (isset($args['uploadedFiles'])) {
                            $this->postMapper->revertFileToTmp($args['uploadedFiles']);
                        }
                        $this->logger->error('PostService.createPost: Uploaded files do not share type', ['uploadedFiles' => $args['uploadedFiles']]);
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
                $this->logger->error('PostService.createPost: Missing media input');
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
                    $this->logger->error('PostService.createPost: Cover upload failed', ['coverPath' => $coverPath]);
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
                $this->logger->error('PostService.createPost: Failed to create post', [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
                return $this::respondWithError(30263);
            }
            $this->postMapper->insert(post: $post);

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
                if (isset($args['uploadedFiles'])) {
                    $this->postMapper->revertFileToTmp($args['uploadedFiles']);
                }
                $this->logger->error('PostService.createPost: Failed to handle tags', ['exception' => $e]);
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
            return $this::createSuccessResponse(11513, $data);

        } catch (\Throwable $e) {
            if (isset($args['uploadedFiles'])) {
                $this->postMapper->revertFileToTmp($args['uploadedFiles']);
            }
            $this->logger->error('PostService.createPost: Failed to create post', ['exception' => $e]);
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
        $minLength = (int) $tagNameConfig['MIN_LENGTH'];
        $maxLength = (int) $tagNameConfig['MAX_LENGTH'];
        if (count($tags) > $maxTags) {
            throw new \Exception('Maximum tag limit exceeded');
        }

        $seenTags = [];
        foreach ($tags as $tagName) {
            $tagName = strtolower(trim((string) $tagName));

            if (isset($seenTags[$tagName])) {
                continue;
            }
            $seenTags[$tagName] = true;

            if (strlen($tagName) < $minLength || strlen($tagName) > $maxLength || !preg_match('/' . $tagNameConfig['PATTERN'] . '/u', $tagName)) {
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
        $tagName = strtolower(trim($tagName));
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
            $this->logger->error('PostService.fetchAll: Authentication failed');
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
            $this->logger->error('PostService.fetchAll: Error fetching posts', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return $this::respondWithError(41513);
        }
    }

    public function findPostser(?array $args = []): array|false
    {
        if (!$this->checkAuthentication()) {
            $this->logger->error('PostService.findPostser: Authentication failed');
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
        $inputConfig  = ConstantsConfig::input();
        $controlPattern = '/'.$inputConfig['FORBID_CONTROL_CHARS_PATTERN'].'/u';
        $contentFilterBy = $args['contentFilterBy'] ?? null;
        $commentOffset = max((int)($args['commentOffset'] ?? 0), 0);
        $commentLimit = min(max((int)($args['commentLimit'] ?? 10), 1), 20);

        if ($postId !== null && !self::isValidUUID($postId)) {
            $this->logger->error('PostService.findPostser: Invalid postId', ['postId' => $postId]);
            return $this::respondWithError(30209);
        }

        if ($userId !== null && !self::isValidUUID($userId)) {
            $this->logger->error('PostService.findPostser: Invalid userId', ['userId' => $userId]);
            return $this::respondWithError(30201);
        }

        if ($title !== null && (grapheme_strlen((string)$title) < $titleConfig['MIN_LENGTH'] || grapheme_strlen((string)$title) > $titleConfig['MAX_LENGTH'])) {
            $this->logger->error('PostService.findPostser: Invalid title length', ['title' => $title]);
            return $this::respondWithError(30210);
        }

        if ($from !== null && !self::validateDate($from)) {
            $this->logger->error('PostService.findPostser: Invalid from date', ['from' => $from]);
            return $this::respondWithError(30212);
        }

        if ($to !== null && !self::validateDate($to)) {
            $this->logger->error('PostService.findPostser: Invalid to date', ['to' => $to]);
            return $this::respondWithError(30213);
        }

        if ($tag !== null) {
            if (preg_match($controlPattern, $tag) === 1) {
                $this->logger->error('PostService.findPostser: Invalid tag format provided', ['tag' => $tag]);
                return $this->respondWithError(30211);
            }
        }

        if (!empty($filterBy) && is_array($filterBy)) {
            $allowedTypes = ['IMAGE', 'AUDIO', 'VIDEO', 'TEXT', 'FOLLOWED', 'FOLLOWER', 'VIEWED', 'FRIENDS'];

            $invalidTypes = array_diff(array_map('strtoupper', $filterBy), $allowedTypes);

            if (!empty($invalidTypes)) {
                $this->logger->error('PostService.findPostser: Invalid filterBy types', ['filterBy' => $filterBy]);
                return $this::respondWithError(30103);
            }
        }

        if ($Ignorlist !== null) {
            $Ignorlisten = ['YES', 'NO'];
            if (!in_array($Ignorlist, $Ignorlisten, true)) {
                $this->logger->error('PostService.findPostser: Invalid IgnorList value', ['IgnorList' => $Ignorlist]);
                return $this::respondWithError(30103);
            }
        }

        $this->logger->debug("PostService.findPostser started");
        $contentFilterCase = ContentFilteringCases::postFeed;

        if ($title || $tag) {
            $contentFilterCase = ContentFilteringCases::searchByMeta;
        }
        if ($userId || $postId) {
            $contentFilterCase = ContentFilteringCases::searchById;
        }
        if ($userId && $userId === $this->currentUserId) {
            $contentFilterCase = ContentFilteringCases::myprofile;
        }

        $deletedUserSpec = new DeletedUserSpec(
            $contentFilterCase,
            ContentType::post
        );
        $systemUserSpec = new SystemUserSpec(
            $contentFilterCase,
            ContentType::post
        );

        $hiddenContentFilterSpec = new HiddenContentFilterSpec(
            $contentFilterCase,
            $contentFilterBy,
            ContentType::post,
            $this->currentUserId,
        );

        $illegalContentSpec = new IllegalContentFilterSpec(
            $contentFilterCase,
            ContentType::post
        );

        $excludeAdvertisementsForNormalFeedSpec = new ExcludeAdvertisementsForNormalFeedSpec($postId);
        $normalVisibilityStatusSpec = new NormalVisibilityStatusSpec($contentFilterBy);

        $specs = [
            $excludeAdvertisementsForNormalFeedSpec,
            $illegalContentSpec,
            $systemUserSpec,
            $deletedUserSpec,
            $hiddenContentFilterSpec,
            $normalVisibilityStatusSpec
        ];

        try {
            $results = $this->postMapper->findPostser($this->currentUserId, $specs, $args);
            if (empty($results) && $postId != null) {
                $this->logger->error('PostService.findPostser: Post not found', ['postId' => $postId]);
                return $this::respondWithError(31510);
            }
            $postsEnriched = $this->enrichWithProfileAndComment(
                $results,
                $specs,
                $this->currentUserId,
                $commentOffset,
                $commentLimit
            );

            foreach ($postsEnriched as $post) {
                ContentReplacer::placeholderPost($post, $specs);
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


    /**
     * Check for If user eligibile to make a post or not
     *
     * @returns with Suggested PostId, JWT which will be valid for certain time
     */
    public function postEligibility(bool $isTokenGenerationRequired = true): ?array
    {
        if (!$this->checkAuthentication()) {
            $this->logger->error('PostService.postEligibility: Authentication failed');
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
            $this->transactionManager->beginTransaction();

            $DailyUsage = $this->dailyFreeService->getUserDailyUsage($this->currentUserId, $actionMap);
            if ($DailyUsage < $limit) {
                // generate PostId and JWT
                $hasFreeDaily = true;
            }

            $balance = $this->walletService->getUserWalletBalance($this->currentUserId);
            // Return ResponseCode with Daily Free Code
            if ($balance < $price && !$hasFreeDaily) {
                $this->logger->error('PostService.postEligibility: Insufficient wallet balance', ['userId' => $this->currentUserId, 'price' => $price]);
                $this->transactionManager->rollback();
                return $this::respondWithError(51301);
            }

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
            $this->logger->error('PostService.postEligibility: Limit exceeded', ['error' => $e->getMessage(), 'mess' => $e->getErrors()]);
            return self::respondWithError($e->getErrors()[0]);
        } catch (\Throwable $e) {
            $this->transactionManager->rollback();
            $this->logger->error('PostService.postEligibility: Exception', [
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
            $this->logger->error('PostService.postInteractions: Authentication failed');
            return $this::respondWithError(60501);
        }

        $this->logger->debug("PostService.postInteractions started");

        $offset = max((int)($args['offset'] ?? 0), 0);
        $limit = min(max((int)($args['limit'] ?? 10), 1), 20);

        $getOnly = $args['getOnly'] ?? null;
        $postOrCommentId = $args['postOrCommentId'] ?? null;
        $contentFilterBy = $args['contentFilterBy'] ?? null;


        if ($getOnly == null || $postOrCommentId == null || !in_array($getOnly, ['VIEW', 'LIKE', 'DISLIKE', 'COMMENTLIKE'])) {
            $this->logger->error('PostService.postInteractions: Invalid arguments', ['getOnly' => $getOnly, 'postOrCommentId' => $postOrCommentId]);
            return $this::respondWithError(30103);
        }

        if (!self::isValidUUID($postOrCommentId)) {
            $this->logger->error('PostService.postInteractions: Invalid postOrCommentId', ['postOrCommentId' => $postOrCommentId]);
            return $this::respondWithError(30201);
        }

        $contentFilterCase = ContentFilteringCases::searchById;

        $deletedUserSpec = new DeletedUserSpec(
            $contentFilterCase,
            ContentType::user
        );
        $systemUserSpec = new SystemUserSpec(
            $contentFilterCase,
            ContentType::user
        );

        $hiddenContentFilterSpec = new HiddenContentFilterSpec(
            $contentFilterCase,
            $contentFilterBy,
            ContentType::user,
            $this->currentUserId,
        );

        $illegalContentSpec = new IllegalContentFilterSpec(
            $contentFilterCase,
            ContentType::user
        );
        $normalVisibilityStatusSpec = new NormalVisibilityStatusSpec($contentFilterBy);

        $specs = [
            $illegalContentSpec,
            $systemUserSpec,
            $deletedUserSpec,
            $hiddenContentFilterSpec,
            $normalVisibilityStatusSpec
        ];

        try {
            $result = $this->postMapper->getInteractions(
                $specs,
                $getOnly,
                $postOrCommentId,
                $this->currentUserId,
                $offset,
                $limit
            );

            $usersArray = [];

            foreach ($result as $user) {
                ContentReplacer::placeholderProfile($user, $specs);
                $usersArray[] = $user->getArrayCopy();
            }
            $this->logger->info("Interaction fetched successfully", ['count' => count($result)]);
            return $this::createSuccessResponse(11205, $usersArray);

        } catch (\Throwable $e) {
            $this->logger->error('PostService.postInteractions: Error fetching interactions', [
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
            $this->logger->error('PostService.getGuestListPost: Invalid postId', ['postId' => $postId]);
            return $this::respondWithError(30209);
        }

        $this->logger->debug("PostService.getGuestListPost started");

        $contentFilterCase = ContentFilteringCases::searchById;

        $deletedUserSpec = new DeletedUserSpec(
            $contentFilterCase,
            ContentType::post
        );
        $systemUserSpec = new SystemUserSpec(
            $contentFilterCase,
            ContentType::post
        );

        $illegalContentSpec = new IllegalContentFilterSpec(
            $contentFilterCase,
            ContentType::post
        );
        $excludeAdvertisementsForNormalFeedSpec = new ExcludeAdvertisementsForNormalFeedSpec($postId);
        $normalVisibilityStatusSpec = new NormalVisibilityStatusSpec(null);

        $specs = [
            $illegalContentSpec,
            $systemUserSpec,
            $deletedUserSpec,
            $excludeAdvertisementsForNormalFeedSpec,
            $normalVisibilityStatusSpec
        ];

        try {
            $results = $this->postMapper->findPostser(
                PeerUUID::empty->value,
                $specs,
                $args
            );

            if (empty($results)) {
                $this->logger->error('PostService.getGuestListPost: Post not found', ['postId' => $postId]);
                return $this::respondWithError(31510);
            }
            $postsEnriched = $this->enrichWithProfileAndComment(
                $results,
                $specs,
                PeerUUID::empty->value,
                $commentOffset,
                $commentLimit
            );
            foreach ($postsEnriched as $post) {
                ContentReplacer::placeholderPost($post, $specs);
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
     * @param \Fawaz\Services\ContentFiltering\Specs\Specification[] $specs Content filtering specs
     * @param string $currentUserId Current/guest user id for profile fetch
     * @param int $commentOffset
     * @param int $commentLimit
     * @return PostAdvanced[]
     */
    private function enrichWithProfileAndComment(
        array $posts,
        array $specs,
        string $currentUserId,
        int $commentOffset,
        int $commentLimit
    ): array {

        $userIdsFromPosts = array_values(
            array_unique(
                array_filter(
                    array_map(fn (PostAdvanced $post) => $post->getUserId(), $posts)
                )
            )
        );

        if (empty($userIdsFromPosts)) {
            return $posts;
        }

        $profiles = $this->profileRepository->fetchByIds($userIdsFromPosts, $currentUserId, $specs);

        $enriched = [];
        foreach ($posts as $post) {
            $data = $post->getArrayCopy();
            $enrichedWithProfiles = $this->enrichAndPlaceholderWithProfile($data, $profiles[$post->getUserId()], $specs);
            $enrichedWithCommentsAndProfiles = $this->enrichAndPlaceholderWithComments(
                $enrichedWithProfiles,
                $specs,
                $commentOffset,
                $commentLimit,
                $currentUserId
            );
            $post = new PostAdvanced($enrichedWithCommentsAndProfiles, [], false);
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

    private function enrichAndPlaceholderWithComments(array $data, array $specs, int $commentOffset, int $commentLimit, string $currentUserId): array
    {
        $comments = $this->commentMapper->fetchAllByPostIdetaild($data['postid'], $specs, $currentUserId, $commentOffset, $commentLimit);
        if (empty($comments)) {
            return $data;
        }

        $userIdsFromComments = array_values(
            array_unique(
                array_filter(
                    array_map(fn (CommentAdvanced $c) => $c->getUserId(), $comments)
                )
            )
        );

        if (empty($userIdsFromComments)) {
            return $comments;
        }

        $profiles = $this->profileRepository->fetchByIds($userIdsFromComments, $currentUserId, $specs);
        $commentsArray = [];

        foreach ($comments as $comment) {
            if ($comment instanceof CommentAdvanced) {
                ContentReplacer::placeholderComments($comment, $specs);
                $dataComment = $comment->getArrayCopy();
                $enrichedWithProfiles = $this->enrichAndPlaceholderWithProfile($dataComment, $profiles[$comment->getUserId()], $specs);
                $commentsArray[] = $enrichedWithProfiles;
            }
        }
        $data['comments'] = $commentsArray;
        return $data;
    }

    public function postExistsById(string $postId): bool|array
    {
        if (!$this->checkAuthentication()) {
            $this->logger->error('PostService.postExistsById: Authentication failed');
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
