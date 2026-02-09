<?php

namespace Fawaz;

const INT32_MAX = 2147483647;

const BASIC = 50;
const PINNED = 200;

use Fawaz\App\Advertisements;
use Fawaz\App\AdvertisementService;
use Fawaz\App\CommentAdvanced;
use Fawaz\App\CommentInfoService;
use Fawaz\App\CommentService;
use Fawaz\App\ContactusService;
use Fawaz\App\DailyFreeService;
use Fawaz\App\Helpers\FeesAccountHelper;
use Fawaz\App\Interfaces\ProfileService;
use Fawaz\App\PoolService;
use Fawaz\App\Interfaces\GemsService;
use Fawaz\App\PostAdvanced;
use Fawaz\App\PostInfoService;
use Fawaz\App\PostService;
use Fawaz\App\UserInfoService;
use Fawaz\App\UserService;
use Fawaz\App\TagService;
use Fawaz\App\WalletService;
use Fawaz\App\MintService;
use Fawaz\Database\CommentMapper;
use Fawaz\Database\UserMapper;
use Fawaz\Services\JWTService;
use GraphQL\Executor\Executor;
use GraphQL\Type\Definition\ResolveInfo;
use GraphQL\Type\Schema;
use GraphQL\Utils\BuildSchema;
use Fawaz\App\PeerTokenService;
use Fawaz\config\constants\ConstantsConfig;
use Fawaz\Utils\ResponseHelper;
use Fawaz\Utils\PeerLoggerInterface;
use Fawaz\Utils\ResponseMessagesProvider;
use Fawaz\App\Errors\ErrorMapper;
use Fawaz\Utils\ArrayNormalizer;
use Fawaz\App\ValidationException;
use Fawaz\App\ModerationService;
use Fawaz\App\Status;
use Fawaz\App\Validation\RequestValidator;
use Fawaz\App\Validation\ValidatorErrors;
use Fawaz\Utils\ErrorResponse;
use Fawaz\App\Role;
use Fawaz\Services\ContentFiltering\Specs\SpecTypes\User\DeletedUserSpec;
use Fawaz\Services\ContentFiltering\Specs\SpecTypes\User\SystemUserSpec;
use Fawaz\Services\ContentFiltering\Specs\SpecTypes\HiddenContent\HiddenContentFilterSpec;
use Fawaz\Services\ContentFiltering\Replacers\ContentReplacer;
use Fawaz\Services\ContentFiltering\Types\ContentFilteringCases;
use Fawaz\Services\ContentFiltering\Types\ContentType;
use Fawaz\Services\ContentFiltering\Specs\SpecTypes\IllegalContent\IllegalContentFilterSpec;
use Fawaz\Services\ContentFiltering\Replaceables\ProfileReplaceable;
use Fawaz\Database\Interfaces\InteractionsPermissionsMapper;
use Fawaz\App\Models\TransactionHistoryItem;
use Fawaz\App\AlphaMintService;
use Fawaz\App\LeaderBoardService;
use Fawaz\Database\Interfaces\TransactionManager;
use Fawaz\App\PeerShopService;
use Fawaz\Utils\DateService;
use Fawaz\Utils\AppVersion;
use PDOException;

use function grapheme_strlen;

class GraphQLSchemaBuilder
{
    use ResponseHelper;
    protected array $resolvers = [];
    protected ?string $currentUserId = null;
    protected ?int $userRoles = 0;

    public function __construct(
        protected PeerLoggerInterface $logger,
        protected UserMapper $userMapper,
        protected TagService $tagService,
        protected CommentMapper $commentMapper,
        protected ContactusService $contactusService,
        protected DailyFreeService $dailyFreeService,
        protected ProfileService $profileService,
        protected UserService $userService,
        protected UserInfoService $userInfoService,
        protected PoolService $poolService,
        protected GemsService $gemsService,
        protected PostInfoService $postInfoService,
        protected PostService $postService,
        protected CommentService $commentService,
        protected CommentInfoService $commentInfoService,
        protected WalletService $walletService,
        protected PeerTokenService $peerTokenService,
        protected PeerShopService $peerShopService,
        protected LeaderBoardService $leaderBoardService,
        protected AdvertisementService $advertisementService,
        protected MintService $mintService,
        protected JWTService $tokenService,
        protected ModerationService $moderationService,
        protected ResponseMessagesProvider $responseMessagesProvider,
        protected InteractionsPermissionsMapper $interactionsPermissionsMapper,
        protected AlphaMintService $alphaMintService,
        protected TransactionManager $transactionManager
    ) {
        $this->resolvers = $this->buildResolvers();
    }

    public function getQueriesDependingOnRole(): ?string
    {
        $graphqlPath = "Graphql/schema/";

        $baseQueries = \file_get_contents(__DIR__ . '/' . $graphqlPath . 'schema.graphql');
        $guestOnlyQueries =  \file_get_contents(__DIR__ . '/' . $graphqlPath . 'schemaguest.graphql');
        $adminOnlyQueries = \file_get_contents(__DIR__ . '/' . $graphqlPath . 'admin_schema.graphql');
        $bridgeOnlyQueries = \file_get_contents(__DIR__ . '/' . $graphqlPath . 'bridge_schema.graphql');
        $moderatorOnlyQueries = \file_get_contents(__DIR__ . '/' . $graphqlPath . 'moderator_schema.graphql');

        $adminSchema = $baseQueries . $adminOnlyQueries;
        $moderatorSchema = $baseQueries . $moderatorOnlyQueries;
        $guestSchema = $guestOnlyQueries;
        $userSchema = $baseQueries;
        $bridgeSchema = $bridgeOnlyQueries;

        $schema = $guestSchema;

        if ($this->currentUserId !== null) {
            if ($this->userRoles === Role::USER) {
                $schema = $userSchema;
            } elseif ($this->userRoles === Role::WEB3_BRIDGE_USER) {
                $schema = $bridgeSchema;
            } elseif ($this->userRoles === Role::ADMIN) {
                $schema = $adminSchema;
            } elseif ($this->userRoles === Role::MODERATOR) { // Role::MODERATOR
                $schema = $moderatorSchema;
            }if ($this->userRoles === Role::PEER_SHOP) {
                $schema = $userSchema;
            }
        }

        return $schema;
    }

    public function build(): Schema|array
    {
        $graphqlPath = "Graphql/schema/";
        $typesPath   = "types/";

        $scalars = \file_get_contents(__DIR__ . '/' . $graphqlPath . $typesPath . "scalars.graphql");
        $response = \file_get_contents(__DIR__ . '/' . $graphqlPath . $typesPath . "response.graphql");
        $inputs = \file_get_contents(__DIR__ . '/' . $graphqlPath . $typesPath . "inputs.graphql");
        $enum = \file_get_contents(__DIR__ . '/' . $graphqlPath . $typesPath . "enums.graphql");
        $types = \file_get_contents(__DIR__ . '/' . $graphqlPath . $typesPath . "types.graphql");

        $schema = $this->getQueriesDependingOnRole();
        if (empty($schema)) {
            $this->logger->error('Invalid schema', ['schema' => $schema]);
            return $this::respondWithError(40301);
        }

        $schemaSource = $scalars . $enum . $inputs . $types . $response . $schema;

        try {
            $resultSchema = BuildSchema::build($schemaSource);
            Executor::setDefaultFieldResolver($this->fieldResolver(...));
            return $resultSchema;
        } catch (\Throwable $e) {
            $this->logger->error('Invalid schema', ['schema' => $schema, 'exception' => $e->getMessage()]);
            return $this::respondWithError(40301);
        }
    }

    // true - if token is empty or valid
    // false - if not-empty and invalid
    public function setCurrentUserId(?string $bearerToken): bool
    {
        if ($bearerToken !== null && $bearerToken !== '') {
            try {
                $decodedToken = $this->tokenService->validateToken($bearerToken);
                // Validate that the provided bearer access token exists in DB and is not expired
                // if (!$this->userMapper->accessTokenValidForUser($decodedToken->uid, $bearerToken)) {
                //     $this->logger->warning('Access token not found or expired for user', [
                //         'userId' => $decodedToken->uid,
                //     ]);
                //     $this->currentUserId = null;
                //     return;
                // }

                $user = $this->userMapper->loadByIdMAin($decodedToken->uid, $decodedToken->rol);
                if ($user) {
                    $this->currentUserId = $decodedToken->uid;
                    $this->userRoles = $decodedToken->rol;
                    $this->setCurrentUserIdForServices($this->currentUserId);
                    $this->logger->debug('Query.setCurrentUserId started');
                }

                $user = $this->userMapper->loadByIdMAin($decodedToken->uid, $decodedToken->rol);
                if ($user) {
                    $this->currentUserId = $decodedToken->uid;
                    $this->userRoles = $decodedToken->rol;
                    $this->setCurrentUserIdForServices($this->currentUserId);
                    $this->logger->debug('Query.setCurrentUserId started');
                    return true;
                }
                $this->logger->error('Query.setCurrentUserId: user not found');
                return false;
            } catch (\Throwable $e) {
                $this->logger->error('Invalid token', ['exception' => $e]);
                $this->currentUserId = null;
                return false;
            }
        } else {
            $this->currentUserId = null;
            return true;
        }
    }

    protected function setCurrentUserIdForServices(string $userid): void
    {
        $this->alphaMintService->setCurrentUserId($userid);
        $this->moderationService->setCurrentUserId($userid);
        $this->userService->setCurrentUserId($userid);
        $this->profileService->setCurrentUserId($userid);
        $this->userInfoService->setCurrentUserId($userid);
        $this->poolService->setCurrentUserId($userid);
        $this->gemsService->setCurrentUserId($userid);
        $this->postService->setCurrentUserId($userid);
        $this->postInfoService->setCurrentUserId($userid);
        $this->commentService->setCurrentUserId($userid);
        $this->commentInfoService->setCurrentUserId($userid);
        $this->dailyFreeService->setCurrentUserId($userid);
        $this->walletService->setCurrentUserId($userid);
        $this->peerTokenService->setCurrentUserId($userid);
        $this->peerShopService->setCurrentUserId($userid);
        $this->leaderBoardService->setCurrentUserId($userid);
        $this->tagService->setCurrentUserId($userid);
        $this->advertisementService->setCurrentUserId($userid);
        $this->mintService->setCurrentUserId($userid);
    }

    protected function getStatusNameByID(int $status): ?string
    {
        $statusCode = $status;
        $statusMap = Status::getMap();

        return $statusMap[$statusCode] ?? null;
    }

    public function buildResolvers(): array
    {
        return [
            'Query' => $this->buildQueryResolvers(),
            'Mutation' => $this->buildMutationResolvers(),
            'Subscription' => $this->buildSubscriptionResolvers(),
            'UserPreferencesResponse' => [
                'meta' => fn (array $root): array => [
                    'status' => $root['status'] ?? '',
                    'ResponseCode' => isset($root['ResponseCode']) ? (string)$root['ResponseCode'] : '',
                    'ResponseMessage' => $this->responseMessagesProvider->getMessage($root['ResponseCode'] ?? '') ?? '',
                    'RequestId' => $this->logger->getRequestUid()
                ],
                'status' => function (array $root): string {
                    $this->logger->debug('Query.DefaultResponse Resolvers');
                    return $root['status'] ?? '';
                },
                'ResponseCode' => fn (array $root): string => $root['ResponseCode'] ?? "",
                'affectedRows' => fn (array $root): array => $root['affectedRows'] ?? [],
            ],
            'UserPreferences' => [
                'contentFilteringSeverityLevel' => function (array $root): ?string {
                    $this->logger->debug('Query.UserPreferences Resolvers');
                    return $root['contentFilteringSeverityLevel'];
                },
                'onboardingsWereShown' => function (array $root): array {
                    $this->logger->info('Query.UserPreferences.onboardingsWereShown Resolver');
                    return $root['onboardingsWereShown'] ?? [];
                },
            ],
            'TodaysInteractionsData' => [
                'totalInteractions' => function (array $root): int {
                    $this->logger->debug('Query.TodaysInteractionsData Resolvers');
                    return $root['totalInteractions'] ?? 0;
                },
                'totalScore' => fn (array $root): int => $root['totalScore'] ?? 0,
                'totalDetails' => fn (array $root): array => $root['totalDetails'] ?? [],
            ],
            'TodaysInteractionsDetailsData' => [
                'views' => function (array $root): int {
                    $this->logger->debug('Query.TodaysInteractionsDetailsData Resolvers');
                    return $root['msgid'] ?? 0;
                },
                'likes' => fn (array $root): int => $root['likes'] ?? 0,
                'dislikes' => fn (array $root): int => $root['dislikes'] ?? 0,
                'comments' => fn (array $root): int => $root['comments'] ?? 0,
                'viewsScore' => fn (array $root): int => $root['viewsScore'] ?? 0,
                'likesScore' => fn (array $root): int => $root['likesScore'] ?? 0,
                'dislikesScore' => fn (array $root): int => $root['dislikesScore'] ?? 0,
                'commentsScore' => fn (array $root): int => $root['commentsScore'] ?? 0
            ],
            'ContactusResponsePayload' => [
                'msgid' => function (array $root): int {
                    $this->logger->debug('Query.ContactusResponsePayload Resolvers');
                    return $root['msgid'] ?? 0;
                },
                'email' => fn (array $root): string => $root['email'] ?? '',
                'name' => fn (array $root): string => $root['name'] ?? '',
                'message' => fn (array $root): string => $root['message'] ?? '',
                'ip' => fn (array $root): string => $root['ip'] ?? '',
                'createdat' => fn (array $root): string => $root['createdat'] ?? '',
            ],
            'HelloResponse' => [
                'currentuserid' => function (array $root): string {
                    $this->logger->debug('Query.HelloResponse Resolvers');
                    return $root['currentuserid'] ?? '';
                },
                'userroles' => fn (array $root): int => $root['userroles'] ?? 0,
                'userRoleString' => fn (array $root): string => $root['userRoleString'] ?? '',
                'currentVersion' => fn (array $root): string => $root['currentVersion'] ?? '',
                'wikiLink' => fn (array $root): string => $root['wikiLink'] ?? '',
                'lastMergedPullRequestNumber' => fn (array $root): string => $root['lastMergedPullRequestNumber'] ?? '',
                'companyAccountId' => fn (array $root): string => $root['companyAccountId'] ?? '',
            ],
            'RegisterResponse' => [
                'meta' => fn (array $root): array => [
                    'status' => $root['status'] ?? '',
                    'ResponseCode' => isset($root['ResponseCode']) ? (string)$root['ResponseCode'] : '',
                    'ResponseMessage' => $this->responseMessagesProvider->getMessage($root['ResponseCode'] ?? '') ?? '',
                    'RequestId' => $this->logger->getRequestUid(),
                ],
                'status' => function (array $root): string {
                    $this->logger->debug('Query.RegisterResponse Resolvers');
                    return $root['status'] ?? '';
                },
                'ResponseCode' => fn (array $root): string => $root['ResponseCode'] ?? "",
                'userid' => fn (array $root): string => $root['userid'] ?? '',
            ],
            'ReferralResponse' => [
                'meta' => fn (array $root): array => [
                    'status' => $root['status'] ?? '',
                    'ResponseCode' => isset($root['ResponseCode']) ? (string)$root['ResponseCode'] : '',
                    'ResponseMessage' => $this->responseMessagesProvider->getMessage($root['ResponseCode'] ?? '') ?? '',
                    'RequestId' => $this->logger->getRequestUid(),
                ],
                'status' => function (array $root): string {
                    $this->logger->debug('Query.ReferralResponse Resolvers');
                    return $root['status'] ?? '';
                },
                'ResponseCode' => fn (array $root): string => $root['ResponseCode'] ?? "",
                'affectedRows' => fn (array $root): array => $root['affectedRows'] ?? [],
            ],
            'MintAccountResponse' => [
                'meta' => function (array $root): array {
                    return [
                        'status' => $root['status'] ?? '',
                        'ResponseCode' => isset($root['ResponseCode']) ? (string)$root['ResponseCode'] : '',
                        'ResponseMessage' => $this->responseMessagesProvider->getMessage($root['ResponseCode'] ?? '') ?? '',
                        'RequestId' => $this->logger->getRequestUid(),
                    ];
                },
                'mintAccount' => function (array $root): array {
                    return $root['affectedRows'];
                },
            ],
            'ReferralInfo' => [
                'uid' => function (array $root): string {
                    $this->logger->debug('Query.ReferralInfo Resolvers');
                    return $root['uid'] ?? '';
                },
                'username' => fn (array $root): string => $root['username'] ?? '',
                'slug' => fn (array $root): int => $root['slug'] ?? 0,
                'img' => fn (array $root): string => $root['img'] ?? '',
            ],
            'User' => [
                'id' => function (array $root): string {
                    $this->logger->debug('Query.User Resolvers');
                    return $root['uid'] ?? '';
                },
                'visibilityStatus' => fn (array $root): string => strtoupper($root['visibility_status'] ?? 'NORMAL'),
                'hasActiveReports' => function (array $root): bool {
                    $reports = $root['reports'] ?? 0;
                    return (int)$reports > 0;
                },
                'isHiddenForUsers' => fn (array $root): bool => isset($root['isHiddenForUsers']) ? (bool)$root['isHiddenForUsers'] : false,
                'situation' => function (array $root): string {
                    $status = $root['status'] ?? 0;
                    return $this->getStatusNameByID($status) ?? '';
                },
                'email' => fn (array $root): string => $root['email'] ?? '',
                'username' => fn (array $root): string => $root['username'] ?? '',
                'password' => fn (array $root): string => $root['password'] ?? '',
                'status' => fn (array $root): int => $root['status'] ?? 0,
                'verified' => fn (array $root): int => $root['verified'] ?? 0,
                'slug' => fn (array $root): int => $root['slug'] ?? 0,
                'roles_mask' => fn (array $root): int => $root['roles_mask'] ?? 0,
                'ip' => fn (array $root): string => $root['ip'] ?? '',
                'img' => fn (array $root): string => $root['img'] ?? '',
                'biography' => fn (array $root): string => $root['biography'] ?? '',
                'liquidity' => fn (array $root): float => $root['liquidity'] ?? 0.0,
                'createdat' => fn (array $root): string => $root['createdat'] ?? '',
                'updatedat' => fn (array $root): string => $root['updatedat'] ?? '',
            ],
            'UserInfoResponse' => [
                'meta' => fn (array $root): array => [
                    'status' => $root['status'] ?? '',
                    'ResponseCode' => isset($root['ResponseCode']) ? (string)$root['ResponseCode'] : '',
                    'ResponseMessage' => $this->responseMessagesProvider->getMessage($root['ResponseCode'] ?? '') ?? '',
                    'RequestId' => $this->logger->getRequestUid(),
                ],
                'status' => function (array $root): string {
                    $this->logger->debug('Query.UserInfoResponse Resolvers');
                    return $root['status'] ?? '';
                },
                'ResponseCode' => fn (array $root): string => $root['ResponseCode'] ?? "",
                'affectedRows' => fn (array $root): array => $root['affectedRows'] ?? [],
            ],
            'UserListResponse' => [
                'meta' => fn (array $root): array => [
                    'status' => $root['status'] ?? '',
                    'ResponseCode' => isset($root['ResponseCode']) ? (string)$root['ResponseCode'] : '',
                    'ResponseMessage' => $this->responseMessagesProvider->getMessage($root['ResponseCode'] ?? '') ?? '',
                    'RequestId' => $this->logger->getRequestUid(),
                ],
                'status' => function (array $root): string {
                    $this->logger->debug('Query.UserListResponse Resolvers');
                    return $root['status'] ?? '';
                },
                'counter' => fn (array $root): int => $root['counter'] ?? 0,
                'ResponseCode' => fn (array $root): string => $root['ResponseCode'] ?? "",
                'affectedRows' => fn (array $root): array => $root['affectedRows'] ?? [],
            ],
            'Profile' => [
                'id' => function (array $root): string {
                    $this->logger->debug('Query.User Resolvers');
                    return $root['uid'] ?? '';
                },
                'visibilityStatus' => fn (array $root): string => strtoupper($root['visibility_status'] ?? 'NORMAL'),
                'hasActiveReports' => function (array $root): bool {
                    $reports = $root['reports'] ?? 0;
                    return (int)$reports > 0;
                },
                'isHiddenForUsers' => fn (array $root): bool => isset($root['isHiddenForUsers']) ? (bool)$root['isHiddenForUsers'] : false,
                'situation' => function (array $root): string {
                    $status = $root['status'] ?? 0;
                    return $this->getStatusNameByID(0) ?? '';
                },
                'username' => fn (array $root): string => $root['username'] ?? '',
                'status' => fn (array $root): int => $root['status'] ?? 0,
                'slug' => fn (array $root): int => $root['slug'] ?? 0,
                'img' => fn (array $root): string => $root['img'] ?? '',
                'biography' => fn (array $root): string => $root['biography'] ?? '',
                'amountposts' => fn (array $root): int => $root['amountposts'] ?? 0,
                'amounttrending' => fn (array $root): int => $root['amounttrending'] ?? 0,
                'amountfollower' => fn (array $root): int => $root['amountfollower'] ?? 0,
                'amountfollowed' => fn (array $root): int => $root['amountfollowed'] ?? 0,
                'amountfriends' => fn (array $root): int => $root['amountfriends'] ?? 0,
                'amountblocked' => fn (array $root): int => $root['amountblocked'] ?? 0,
                'amountreports' => fn (array $root): int => $root['amountreports'] ?? 0,
                'isfollowed' => fn (array $root): bool => $root['isfollowed'] ?? false,
                'isfollowing' => fn (array $root): bool => $root['isfollowing'] ?? false,
                'iFollowThisUser' => fn (array $root): bool => $root['iFollowThisUser'] ?? false,
                'thisUserFollowsMe' => fn (array $root): bool => $root['thisUserFollowsMe'] ?? false,
                'isreported' => fn (array $root): bool => $root['isreported'] ?? false,
                'imageposts' => fn (array $root): array => [],
                'textposts' => fn (array $root): array => [],
                'videoposts' => fn (array $root): array => [],
                'audioposts' => fn (array $root): array => [],
            ],
            'ProfileInfo' => [
                'meta' => fn (array $root): array => [
                    'status' => $root['status'] ?? '',
                    'ResponseCode' => isset($root['ResponseCode']) ? (string)$root['ResponseCode'] : '',
                    'ResponseMessage' => $this->responseMessagesProvider->getMessage($root['ResponseCode'] ?? '') ?? '',
                    'RequestId' => $this->logger->getRequestUid(),
                ],
                'status' => function (array $root): string {
                    $this->logger->debug('Query.ProfileInfo Resolvers');
                    return $root['status'] ?? '';
                },
                'ResponseCode' => fn (array $root): string => $root['ResponseCode'] ?? "",
                'affectedRows' => fn (array $root): array => $root['affectedRows'] ?? [],
            ],
            'ProfilePostMedia' => [
                'id' => function (array $root): string {
                    $this->logger->debug('Query.ProfilePostMedia Resolvers');
                    return $root['postid'] ?? '';
                },
                'title' => fn (array $root): string => $root['title'] ?? '',
                'contenttype' => fn (array $root): string => $root['contenttype'] ?? '',
                'media' => fn (array $root): string => $root['media'] ?? '',
                'createdat' => fn (array $root): string => $root['createdat'] ?? '',
            ],
            'ProfileUser' => [
                'id' => function (array $root): string {
                    $this->logger->debug('Query.ProfileUser Resolvers');
                    return $root['uid'] ?? '';
                },
                'visibilityStatus' => fn (array $root): string => strtoupper($root['visibility_status'] ?? 'NORMAL'),
                'hasActiveReports' => function (array $root): bool {
                    $reports = $root['reports'] ?? 0;
                    return (int)$reports > 0;
                },
                'isHiddenForUsers' => fn (array $root): bool => isset($root['isHiddenForUsers']) ? (bool)$root['isHiddenForUsers'] : false,
                'username' => fn (array $root): string => $root['username'] ?? '',
                'slug' => fn (array $root): int => $root['slug'] ?? 0,
                'img' => fn (array $root): string => $root['img'] ?? '',
                'isfollowed' => fn (array $root): bool => $root['isfollowed'] ?? false,
                'isfollowing' => fn (array $root): bool => $root['isfollowing'] ?? false,
                'iFollowThisUser' => fn (array $root): bool => $root['iFollowThisUser'] ?? false,
                'thisUserFollowsMe' => fn (array $root): bool => $root['thisUserFollowsMe'] ?? false,
                'isfriend' => fn (array $root): bool => $root['isfriend'] ?? false,
                'isreported' => fn (array $root): bool => $root['isreported'] ?? false,
            ],
            'BasicUserInfo' => [
                'userid' => function (array $root): string {
                    $this->logger->debug('Query.BasicUserInfo Resolvers');
                    return $root['uid'] ?? '';
                },
                'visibilityStatus' => fn (array $root): string => strtoupper($root['visibility_status'] ?? 'NORMAL'),
                'hasActiveReports' => function (array $root): bool {
                    $reports = $root['reports'] ?? 0;
                    return (int)$reports > 0;
                },
                'isHiddenForUsers' => fn (array $root): bool => isset($root['isHiddenForUsers']) ? (bool)$root['isHiddenForUsers'] : false,
                'img' => fn (array $root): string => $root['img'] ?? '',
                'username' => fn (array $root): string => $root['username'] ?? '',
                'slug' => fn (array $root): int => $root['slug'] ?? 0,
                'biography' => fn (array $root): string => $root['biography'] ?? '',
                'updatedat' => fn (array $root): string => $root['updatedat'] ?? '',
            ],
            'BlockedUser' => [
                'userid' => function (array $root): string {
                    $this->logger->debug('Query.BlockedUser Resolvers');
                    return $root['uid'] ?? '';
                },
                'img' => fn (array $root): string => $root['img'] ?? '',
                'username' => fn (array $root): string => $root['username'] ?? '',
                'slug' => fn (array $root): int => $root['slug'] ?? 0,
                'visibilityStatus' => fn (array $root): string => strtoupper($root['visibility_status'] ?? 'NORMAL'),
                'hasActiveReports' => function (array $root): bool {
                    $reports = $root['reports'] ?? 0;
                    return (int)$reports > 0;
                },
                'isHiddenForUsers' => fn (array $root): bool => isset($root['isHiddenForUsers']) ? (bool)$root['isHiddenForUsers'] : false,
            ],
            'BlockedUsers' => [
                'iBlocked' => function (array $root): array {
                    $this->logger->debug('Query.BlockedUsers Resolvers');
                    return $root['iBlocked'] ?? [];
                },
                'blockedBy' => fn (array $root): array => $root['blockedBy'] ?? [],
            ],
            'BlockedUsersResponse' => [
                'meta' => fn (array $root): array => [
                    'status' => $root['status'] ?? '',
                    'ResponseCode' => isset($root['ResponseCode']) ? (string)$root['ResponseCode'] : '',
                    'ResponseMessage' => $this->responseMessagesProvider->getMessage($root['ResponseCode'] ?? '') ?? '',
                    'RequestId' => $this->logger->getRequestUid(),
                ],
                'status' => function (array $root): string {
                    $this->logger->debug('Query.BlockedUsersResponse Resolvers');
                    return $root['status'] ?? '';
                },
                'counter' => fn (array $root): int => $root['counter'] ?? 0,
                'ResponseCode' => fn (array $root): string => $root['ResponseCode'] ?? "",
                'affectedRows' => fn (array $root): array => $root['affectedRows'] ?? [],
            ],
            'FollowRelations' => [
                'followers' => function (array $root): array {
                    $this->logger->debug('Query.FollowRelations Resolvers');
                    return $root['followers'] ?? [];
                },
                'following' => fn (array $root): array => $root['following'] ?? [],
            ],
            'FollowRelationsResponse' => [
                'meta' => fn (array $root): array => [
                    'status' => $root['status'] ?? '',
                    'ResponseCode' => isset($root['ResponseCode']) ? (string)$root['ResponseCode'] : '',
                    'ResponseMessage' => $this->responseMessagesProvider->getMessage($root['ResponseCode'] ?? '') ?? '',
                    'RequestId' => $this->logger->getRequestUid(),
                ],
                'status' => function (array $root): string {
                    $this->logger->debug('Query.FollowRelationsResponse Resolvers');
                    return $root['status'] ?? '';
                },
                'counter' => fn (array $root): int => $root['counter'] ?? 0,
                'ResponseCode' => fn (array $root): string => $root['ResponseCode'] ?? "",
                'affectedRows' => fn (array $root): array => $root['affectedRows'] ?? [],
            ],
            'UserFriendsResponse' => [
                'meta' => fn (array $root): array => [
                    'status' => $root['status'] ?? '',
                    'ResponseCode' => isset($root['ResponseCode']) ? (string)$root['ResponseCode'] : '',
                    'ResponseMessage' => $this->responseMessagesProvider->getMessage($root['ResponseCode'] ?? '') ?? '',
                    'RequestId' => $this->logger->getRequestUid(),
                ],
                'status' => function (array $root): string {
                    $this->logger->debug('Query.UserFriendsResponse Resolvers');
                    return $root['status'] ?? '';
                },
                'counter' => fn (array $root): int => $root['counter'] ?? 0,
                'ResponseCode' => fn (array $root): string => $root['ResponseCode'] ?? "",
                'affectedRows' => fn (array $root): array => $root['affectedRows'] ?? [],
            ],
            'BasicUserInfoResponse' => [
                'meta' => fn (array $root): array => [
                    'status' => $root['status'] ?? '',
                    'ResponseCode' => isset($root['ResponseCode']) ? (string)$root['ResponseCode'] : '',
                    'ResponseMessage' => $this->responseMessagesProvider->getMessage($root['ResponseCode'] ?? '') ?? '',
                    'RequestId' => $this->logger->getRequestUid(),
                ],
                'status' => function (array $root): string {
                    $this->logger->debug('Query.BasicUserInfoResponse Resolvers');
                    return $root['status'] ?? '';
                },
                'ResponseCode' => fn (array $root): string => $root['ResponseCode'] ?? "",
                'affectedRows' => fn (array $root): array => $root['affectedRows'] ?? [],
            ],
            'FollowStatusResponse' => [
                'meta' => fn (array $root): array => [
                    'status' => $root['status'] ?? '',
                    'ResponseCode' => isset($root['ResponseCode']) ? (string)$root['ResponseCode'] : '',
                    'ResponseMessage' => $this->responseMessagesProvider->getMessage($root['ResponseCode'] ?? '') ?? '',
                    'RequestId' => $this->logger->getRequestUid(),
                ],
                'status' => function (array $root): string {
                    $this->logger->debug('Query.FollowStatusResponse Resolvers');
                    return $root['status'] ?? '';
                },
                'ResponseCode' => fn (array $root): string => $root['ResponseCode'] ?? "",
                'isfollowing' => fn (array $root): bool => $root['isfollowing'] ?? false,
            ],
            'Post' => [
                'id' => function (array $root): string {
                    $this->logger->debug('Query.Post Resolvers');
                    return $root['postid'] ?? '';
                },
                'visibilityStatus' => fn (array $root): string => strtoupper($root['visibility_status'] ?? 'NORMAL'),
                'hasActiveReports' => function (array $root): bool {
                    $reports = $root['reports'] ?? 0;
                    return (int)$reports > 0;
                },
                'isHiddenForUsers' => fn (array $root): bool => isset($root['isHiddenForUsers']) ? (bool)$root['isHiddenForUsers'] : false,
                'contenttype' => fn (array $root): string => $root['contenttype'] ?? '',
                'title' => fn (array $root): string => $root['title'] ?? '',
                'media' => fn (array $root): string => $root['media'] ?? '',
                'cover' => fn (array $root): string => $root['cover'] ?? '',
                'url' => fn (array $root): string => $root['url'] ?? '',
                'mediadescription' => fn (array $root): string => $root['mediadescription'] ?? '',
                'amountlikes' => fn (array $root): int => $root['amountlikes'] ?? 0,
                'amountdislikes' => fn (array $root): int => $root['amountdislikes'] ?? 0,
                'amountviews' => fn (array $root): int => $root['amountviews'] ?? 0,
                'amountcomments' => fn (array $root): int => $root['amountcomments'] ?? 0,
                'amounttrending' => fn (array $root): int => $root['amounttrending'] ?? 0,
                'amountreports' => fn (array $root): int => $root['amountreports'] ?? 0,
                'isliked' => fn (array $root): bool => $root['isliked'] ?? false,
                'isviewed' => fn (array $root): bool => $root['isviewed'] ?? false,
                'isreported' => fn (array $root): bool => $root['isreported'] ?? false,
                'isdisliked' => fn (array $root): bool => $root['isdisliked'] ?? false,
                'issaved' => fn (array $root): bool => $root['issaved'] ?? false,
                'createdat' => fn (array $root): string => $root['createdat'] ?? '',
                'tags' => fn (array $root): array => $root['tags'] ?? [],
                'user' => fn (array $root): array => $root['user'] ?? [],
                'comments' => fn (array $root): array => $root['comments'] ?? [],
            ],
            'PostInfoResponse' => [
                'meta' => fn (array $root): array => [
                    'status' => $root['status'] ?? '',
                    'ResponseCode' => isset($root['ResponseCode']) ? (string)$root['ResponseCode'] : '',
                    'ResponseMessage' => $this->responseMessagesProvider->getMessage($root['ResponseCode'] ?? '') ?? '',
                    'RequestId' => $this->logger->getRequestUid(),
                ],
                'status' => function (array $root): string {
                    $this->logger->debug('Query.PostInfoResponse Resolvers');
                    return $root['status'] ?? '';
                },
                'ResponseCode' => fn (array $root): string => $root['ResponseCode'] ?? "",
                'affectedRows' => fn (array $root): array => $root['affectedRows'] ?? [],
            ],
            'PostInfo' => [
                'userid' => function (array $root): string {
                    $this->logger->debug('Query.PostInfo Resolvers');
                    return $root['userid'] ?? '';
                },
                'likes' => fn (array $root): int => $root['likes'] ?? 0,
                'dislikes' => fn (array $root): int => $root['dislikes'] ?? 0,
                'reports' => fn (array $root): int => $root['reports'] ?? 0,
                'views' => fn (array $root): int => $root['views'] ?? 0,
                'saves' => fn (array $root): int => $root['saves'] ?? 0,
                'shares' => fn (array $root): int => $root['shares'] ?? 0,
                'comments' => fn (array $root): int => $root['comments'] ?? 0,
            ],
            'PostListResponse' => [
                'meta' => fn (array $root): array => [
                    'status' => $root['status'] ?? '',
                    'ResponseCode' => isset($root['ResponseCode']) ? (string)$root['ResponseCode'] : '',
                    'ResponseMessage' => $this->responseMessagesProvider->getMessage($root['ResponseCode'] ?? '') ?? '',
                    'RequestId' => $this->logger->getRequestUid(),
                ],
                'status' => function (array $root): string {
                    $this->logger->debug('Query.PostListResponse Resolvers');
                    return $root['status'] ?? '';
                },
                'counter' => fn (array $root): int => $root['counter'] ?? 0,
                'ResponseCode' => fn (array $root): string => $root['ResponseCode'] ?? "",
                'affectedRows' => fn (array $root): array => $root['affectedRows'] ?? [],
            ],
            'PostResponse' => [
                'meta' => fn (array $root): array => [
                    'status' => $root['status'] ?? '',
                    'ResponseCode' => isset($root['ResponseCode']) ? (string)$root['ResponseCode'] : '',
                    'ResponseMessage' => $this->responseMessagesProvider->getMessage($root['ResponseCode'] ?? '') ?? '',
                    'RequestId' => $this->logger->getRequestUid(),
                ],
                'status' => function (array $root): string {
                    $this->logger->debug('Query.PostResponse Resolvers');
                    return $root['status'] ?? '';
                },
                'ResponseCode' => fn (array $root): string => $root['ResponseCode'] ?? "",
                'affectedRows' => fn (array $root): array => $root['affectedRows'] ?? [],
            ],
            'AddPostResponse' => [
                'meta' => fn (array $root): array => [
                    'status' => $root['status'] ?? '',
                    'ResponseCode' => isset($root['ResponseCode']) ? (string)$root['ResponseCode'] : '',
                    'ResponseMessage' => $this->responseMessagesProvider->getMessage($root['ResponseCode'] ?? '') ?? '',
                    'RequestId' => $this->logger->getRequestUid(),
                ],
                'status' => function (array $root): string {
                    $this->logger->debug('Query.AddPostResponse Resolvers');
                    return $root['status'] ?? '';
                },
                'ResponseCode' => fn (array $root): string => $root['ResponseCode'] ?? "",
                'affectedRows' => fn (array $root): array => $root['affectedRows'] ?? [],
            ],
            'Comment' => [
                'commentid' => function (array $root): string {
                    $this->logger->debug('Query.Comment Resolvers');
                    return $root['commentid'] ?? '';
                },
                'visibilityStatus' => fn (array $root): string => strtoupper($root['visibility_status'] ?? 'NORMAL'),
                'hasActiveReports' => function (array $root): bool {
                    $reports = $root['reports'] ?? 0;
                    return (int)$reports > 0;
                },
                'isHiddenForUsers' => fn (array $root): bool => isset($root['isHiddenForUsers']) ? (bool)$root['isHiddenForUsers'] : false,
                'userid' => fn (array $root): string => $root['userid'] ?? '',
                'postid' => fn (array $root): string => $root['postid'] ?? '',
                'parentid' => fn (array $root): string => $root['parentid'] ?? '',
                'content' => fn (array $root): string => $root['content'] ?? '',
                'createdat' => fn (array $root): string => $root['createdat'] ?? '',
                'amountlikes' => fn (array $root): int => $root['amountlikes'] ?? 0,
                'amountreplies' => fn (array $root): int => $root['amountreplies'] ?? 0,
                'amountreports' => fn (array $root): int => $root['amountreports'] ?? 0,
                'isreported' => fn (array $root): bool => $root['isreported'] ?? false,
                'isliked' => fn (array $root): bool => $root['isliked'] ?? false,
                'user' => fn (array $root): array => $root['user'] ?? [],
            ],
            'CommentInfoResponse' => [
                'meta' => fn (array $root): array => [
                    'status' => $root['status'] ?? '',
                    'ResponseCode' => isset($root['ResponseCode']) ? (string)$root['ResponseCode'] : '',
                    'ResponseMessage' => $this->responseMessagesProvider->getMessage($root['ResponseCode'] ?? '') ?? '',
                    'RequestId' => $this->logger->getRequestUid(),
                ],
                'status' => function (array $root): string {
                    $this->logger->debug('Query.CommentInfoResponse Resolvers');
                    return $root['status'] ?? '';
                },
                'ResponseCode' => fn (array $root): string => $root['ResponseCode'] ?? "",
                'affectedRows' => fn (array $root): array => $root['affectedRows'] ?? [],
            ],
            'CommentInfo' => [
                'userid' => fn (array $root): string => $root['userid'] ?? '',
                'likes' => fn (array $root): int => $root['likes'] ?? 0,
                'reports' => fn (array $root): int => $root['reports'] ?? 0,
                'comments' => fn (array $root): int => $root['comments'] ?? 0,
            ],
            'CommentResponse' => [
                'meta' => fn (array $root): array => [
                    'status' => $root['status'] ?? '',
                    'ResponseCode' => isset($root['ResponseCode']) ? (string)$root['ResponseCode'] : '',
                    'ResponseMessage' => $this->responseMessagesProvider->getMessage($root['ResponseCode'] ?? '') ?? '',
                    'RequestId' => $this->logger->getRequestUid(),
                ],
                'status' => function (array $root): string {
                    $this->logger->debug('Query.CommentResponse Resolvers');
                    return $root['status'] ?? '';
                },
                'counter' => fn (array $root): int => $root['counter'] ?? 0,
                'ResponseCode' => fn (array $root): string => $root['ResponseCode'] ?? "",
                'affectedRows' => fn (array $root): array => $root['affectedRows'] ?? [],
            ],
            'CommentListResponse' => [
                'meta' => fn (array $root): array => [
                    'status' => $root['status'] ?? '',
                    'ResponseCode' => isset($root['ResponseCode']) ? (string)$root['ResponseCode'] : '',
                    'ResponseMessage' => $this->responseMessagesProvider->getMessage($root['ResponseCode'] ?? '') ?? '',
                    'RequestId' => $this->logger->getRequestUid(),
                ],
                'counter' => function (array $root): int {
                    $this->logger->debug('Query.CommentListResponse Resolvers');
                    return $root['counter'] ?? 0;
                },
                'affectedRows' => fn (array $root): array => $root['affectedRows'] ?? [],
            ],
            'AdvCreator' => [
                'advertisementid' => function (array $root): string {
                    $this->logger->debug('Query.AdvCreator Resolvers');
                    return $root['advertisementid'] ?? '';
                },
                'postid' => fn (array $root): string => $root['postid'] ?? '',
                'advertisementtype' => fn (array $root): string => strtoupper($root['status']),
                'startdate' => fn (array $root): string => $root['timestart'] ?? '',
                'enddate' => fn (array $root): string => $root['timeend'] ?? '',
                'createdat' => fn (array $root): string => $root['createdat'] ?? '',
                'user' => fn (array $root): array => $root['user'] ?? [],
            ],
            'ListAdvertisementPostsResponse' => [
                'meta' => fn (array $root): array => [
                    'status' => $root['status'] ?? '',
                    'ResponseCode' => isset($root['ResponseCode']) ? (string)$root['ResponseCode'] : '',
                    'ResponseMessage' => $this->responseMessagesProvider->getMessage($root['ResponseCode'] ?? '') ?? '',
                    'RequestId' => $this->logger->getRequestUid(),
                ],
                'status' => function (array $root): string {
                    $this->logger->debug('Query.ListAdvertisementPostsResponse Resolvers');
                    return $root['status'] ?? '';
                },
                'counter' => fn (array $root): int => $root['counter'] ?? 0,
                'ResponseCode' => fn (array $root): string => $root['ResponseCode'] ?? '',
                'affectedRows' => fn (array $root): array => $root['affectedRows'] ?? [],
            ],
            'AdvertisementPost' => [
                'post' => function (array $root): array {
                    $this->logger->debug('Query.AdvertisementPost Resolvers');
                    return $root['post'] ?? [];
                },
                'advertisement' => fn (array $root): array => $root['advertisement'] ?? [],
            ],
            'DefaultResponse' => [
                'status' => function (array $root): string {
                    $this->logger->debug('Query.DefaultResponse Resolvers');
                    return $root['status'] ?? '';
                },
                'ResponseCode' => fn (array $root): string => $root['ResponseCode'] ?? "",
                'ResponseMessage' => fn (array $root): string => $this->responseMessagesProvider->getMessage($root['ResponseCode']) ?? '',
                'RequestId' => fn (array $root): string => $this->logger->getRequestUid(),
            ],
            'AuthPayload' => [
                'meta' => fn (array $root): array => [
                    'status' => $root['status'] ?? '',
                    'ResponseCode' => isset($root['ResponseCode']) ? (string)$root['ResponseCode'] : '',
                    'ResponseMessage' => $this->responseMessagesProvider->getMessage($root['ResponseCode'] ?? '') ?? '',
                    'RequestId' => $this->logger->getRequestUid(),
                ],
                'status' => function (array $root): string {
                    $this->logger->debug('Query.AuthPayload Resolvers');
                    return $root['status'] ?? '';
                },
                'ResponseCode' => fn (array $root): string => $root['ResponseCode'] ?? "",
                'accessToken' => fn (array $root): string => $root['accessToken'] ?? '',
                'refreshToken' => fn (array $root): string => $root['refreshToken'] ?? '',
            ],
            'TagSearchResponse' => [
                'meta' => fn (array $root): array => [
                    'status' => $root['status'] ?? '',
                    'ResponseCode' => isset($root['ResponseCode']) ? (string)$root['ResponseCode'] : '',
                    'ResponseMessage' => $this->responseMessagesProvider->getMessage($root['ResponseCode'] ?? '') ?? '',
                    'RequestId' => $this->logger->getRequestUid(),
                ],
                'status' => function (array $root): string {
                    $this->logger->debug('Query.TagSearchResponse Resolvers');
                    return $root['status'] ?? '';
                },
                'counter' => fn (array $root): int => $root['counter'] ?? 0,
                'ResponseCode' => fn (array $root): string => $root['ResponseCode'] ?? "",
                'affectedRows' => fn (array $root): array => $root['affectedRows'] ?? [],
            ],
            'Tag' => [
                'tagid' => function (array $root): int {
                    $this->logger->debug('Query.Tag Resolvers');
                    return $root['tagid'] ?? 0;
                },
                'name' => fn (array $root): string => $root['name'] ?? '',
            ],
            'GetDailyResponse' => [
                'meta' => fn (array $root): array => [
                    'status' => $root['status'] ?? '',
                    'ResponseCode' => isset($root['ResponseCode']) ? (string)$root['ResponseCode'] : '',
                    'ResponseMessage' => $this->responseMessagesProvider->getMessage($root['ResponseCode'] ?? '') ?? '',
                    'RequestId' => $this->logger->getRequestUid(),
                ],
                'status' => function (array $root): string {
                    $this->logger->debug('Query.GetDailyResponse Resolvers');
                    return $root['status'] ?? '';
                },
                'ResponseCode' => fn (array $root): string => $root['ResponseCode'] ?? "",
                'affectedRows' => fn (array $root): array => $root['affectedRows'] ?? [],
            ],
            'DailyFreeResponse' => [
                'name' => function (array $root): string {
                    $this->logger->debug('Query.DailyFreeResponse Resolvers');
                    return $root['name'] ?? '';
                },
                'used' => fn (array $root): int => $root['used'] ?? 0,
                'available' => fn (array $root): int => $root['available'] ?? 0,
            ],
            'CurrentLiquidity' => [
                'meta' => fn (array $root): array => [
                    'status' => $root['status'] ?? '',
                    'ResponseCode' => isset($root['ResponseCode']) ? (string)$root['ResponseCode'] : '',
                    'ResponseMessage' => $this->responseMessagesProvider->getMessage($root['ResponseCode'] ?? '') ?? '',
                    'RequestId' => $this->logger->getRequestUid(),
                ],
                'status' => function (array $root): string {
                    $this->logger->debug('Query.CurrentLiquidity Resolvers');
                    return $root['status'] ?? '';
                },
                'ResponseCode' => fn (array $root): string => $root['ResponseCode'] ?? "",
                'currentliquidity' => function (array $root): float {
                    $this->logger->debug('Query.currentliquidity Resolvers');
                    return $root['currentliquidity'] ?? 0.0;
                },
            ],
            'MintAccount' => [
                'accountid' => function (array $root): string {
                    $this->logger->debug('Query.MintAccount Resolvers');
                    return $root['accountid'] ?? '';
                },
                'initialBalance' => function (array $root): string {
                    return (string)$root['initial_balance'];
                },
                'currentBalance' => function (array $root): string {
                    return (string)$root['current_balance'];
                },
                'updatedat' => function (array $root): string {
                    return $root['updatedat'] ?? '';
                },
            ],
            'UserInfo' => [
                'userid' => function (array $root): string {
                    $this->logger->debug('Query.UserInfo Resolvers');
                    return $root['userid'] ?? '';
                },
                'liquidity' => fn (array $root): float => $root['liquidity'] ?? 0.0,
                'isfollowed' => fn (array $root): bool => $root['isfollowed'] ?? false,
                'isfollowing' => fn (array $root): bool => $root['isfollowing'] ?? false,
                'iFollowThisUser' => fn (array $root): bool => $root['iFollowThisUser'] ?? false,
                'thisUserFollowsMe' => fn (array $root): bool => $root['thisUserFollowsMe'] ?? false,
                'isreported' => fn (array $root): bool => $root['isreported'] ?? false,
                'amountreports' => fn (array $root): int => $root['reports'] ?? 0,
                'amountposts' => fn (array $root): int => $root['amountposts'] ?? 0,
                'amountblocked' => fn (array $root): int => $root['amountblocked'] ?? 0,
                'amountfollowed' => fn (array $root): int => $root['amountfollowed'] ?? 0,
                'amountfollower' => fn (array $root): int => $root['amountfollower'] ?? 0,
                'amountfriends' => fn (array $root): int => $root['amountfriends'] ?? 0,
                'invited' => fn (array $root): string => $root['invited'] ?? '',
                'updatedat' => fn (array $root): string => $root['updatedat'] ?? '',
                'userPreferences' => fn (array $root): array => $root['userPreferences'] ?? [],
            ],
            'StandardResponse' => [
                'meta' => fn (array $root): array => [
                    'status' => $root['status'] ?? '',
                    'ResponseCode' => isset($root['ResponseCode']) ? (string)$root['ResponseCode'] : '',
                    'ResponseMessage' => $this->responseMessagesProvider->getMessage($root['ResponseCode'] ?? '') ?? '',
                    'RequestId' => $this->logger->getRequestUid(),
                ],
                'status' => function (array $root): string {
                    $this->logger->debug('Query.StandardResponse Resolvers');
                    return $root['status'] ?? '';
                },
                'ResponseCode' => fn (array $root): string => $root['ResponseCode'] ?? "",
                'affectedRows' => fn (array $root): array => $root['affectedRows'] ?? [],
            ],
            'ListTodaysInteractionsResponse' => [
                'meta' => fn (array $root): array => [
                    'status' => $root['status'] ?? '',
                    'ResponseCode' => isset($root['ResponseCode']) ? (string)$root['ResponseCode'] : '',
                    'ResponseMessage' => $this->responseMessagesProvider->getMessage($root['ResponseCode'] ?? '') ?? '',
                    'RequestId' => $this->logger->getRequestUid(),
                ],
                'status' => function (array $root): string {
                    $this->logger->debug('Query.StandardResponse Resolvers');
                    return $root['status'] ?? '';
                },
                'ResponseCode' => fn (array $root): string => $root['ResponseCode'] ?? "",
                'affectedRows' => fn (array $root): array => $root['affectedRows'] ?? [],
            ],
            'PercentBeforeTransactionResponse' => [
                'meta' => fn (array $root): array => [
                    'status' => $root['status'] ?? '',
                    'ResponseCode' => isset($root['ResponseCode']) ? (string)$root['ResponseCode'] : '',
                    'ResponseMessage' => $this->responseMessagesProvider->getMessage($root['ResponseCode'] ?? '') ?? '',
                    'RequestId' => $this->logger->getRequestUid(),
                ],
                'status' => function (array $root): string {
                    $this->logger->debug('Query.StandardResponse Resolvers');
                    return $root['status'] ?? '';
                },
                'ResponseCode' => fn (array $root): string => $root['ResponseCode'] ?? "",
                'affectedRows' => fn (array $root): array => $root['affectedRows'] ?? [],
            ],
            'PercentBeforeTransactionData' => [
                'inviterId' => function (array $root): string {
                    $this->logger->debug('Query.PercentBeforeTransactionResponse Resolvers');
                    return $root['inviterId'] ?? '';
                },
                'tosend' => fn (array $root): float => $root['tosend'] ?? 0.0,
                'percentTransferred' => fn (array $root): float => $root['percentTransferred'] ?? 0.0,
            ],
            'GemsterResponse' => [
                'meta' => fn (array $root): array => [
                    'status' => $root['status'] ?? '',
                    'ResponseCode' => isset($root['ResponseCode']) ? (string)$root['ResponseCode'] : '',
                    'ResponseMessage' => $this->responseMessagesProvider->getMessage($root['ResponseCode'] ?? '') ?? '',
                    'RequestId' => $this->logger->getRequestUid(),
                ],
                'status' => function (array $root): string {
                    $this->logger->debug('Query.GemsterResponse Resolvers');
                    return $root['status'] ?? '';
                },
                'ResponseCode' => fn (array $root): string => $root['ResponseCode'] ?? "",
                'affectedRows' => fn (array $root): array => $root['affectedRows'] ?? [],
            ],
            'DailyGemStatusResponse' => [
                'meta' => fn (array $root): array => [
                    'status' => $root['status'] ?? '',
                    'ResponseCode' => isset($root['ResponseCode']) ? (string)$root['ResponseCode'] : '',
                    'ResponseMessage' => $this->responseMessagesProvider->getMessage($root['ResponseCode'] ?? '') ?? '',
                    'RequestId' => $this->logger->getRequestUid(),
                ],
                'status' => function (array $root): string {
                    $this->logger->debug('Query.DailyGemStatusResponse Resolvers');
                    return $root['status'] ?? '';
                },
                'ResponseCode' => fn (array $root): string => $root['ResponseCode'] ?? "",
                'affectedRows' => fn (array $root): array => $root['affectedRows'] ?? [],
            ],
            'DailyGemsResultsResponse' => [
                'meta' => fn (array $root): array => [
                    'status' => $root['status'] ?? '',
                    'ResponseCode' => isset($root['ResponseCode']) ? (string)$root['ResponseCode'] : '',
                    'ResponseMessage' => $this->responseMessagesProvider->getMessage($root['ResponseCode'] ?? '') ?? '',
                    'RequestId' => $this->logger->getRequestUid(),
                ],
                'status' => function (array $root): string {
                    $this->logger->debug('Query.DailyGemsResultsResponse Resolvers');
                    return $root['status'] ?? '';
                },
                'ResponseCode' => fn (array $root): string => $root['ResponseCode'] ?? "",
                'affectedRows' => fn (array $root): array => $root['affectedRows'] ?? [],
            ],
            'DailyGemStatusData' => [
                'd0' => function (array $root): int {
                    $this->logger->debug('Query.DailyGemStatusData Resolvers');
                    return $root['d0'] ?? 0;
                },
                'd1' => fn (array $root): int => $root['d1'] ?? 0,
                'd2' => fn (array $root): int => $root['d2'] ?? 0,
                'd3' => fn (array $root): int => $root['d3'] ?? 0,
                'd4' => fn (array $root): int => $root['d4'] ?? 0,
                'd5' => fn (array $root): int => $root['d5'] ?? 0,
                'd6' => fn (array $root): int => $root['d6'] ?? 0,
                'd7' => fn (array $root): int => $root['d7'] ?? 0,
                'w0' => fn (array $root): int => $root['q0'] ?? 0,
                'm0' => fn (array $root): int => $root['m0'] ?? 0,
                'y0' => fn (array $root): int => $root['y0'] ?? 0,
            ],
            'DailyGemsResultsData' => [
                'data' => function (array $root): array {
                    $this->logger->debug('Query.DailyGemsResultsData Resolvers');
                    return $root['data'] ?? [];
                },
                'totalGems' => fn (array $root): float => $root['totalGems'] ?? 0.0,
            ],
            'DailyGemsResultsUserData' => [
                'userid' => function (array $root): string {
                    $this->logger->debug('Query.DailyGemsResultsUserData Resolvers');
                    return $root['userid'] ?? '';
                },
                'gems' => fn (array $root): float => $root['gems'] ?? 0.0,
                'pkey' => fn (array $root): string => $root['pkey'] ?? '',
            ],
            'ContactusResponse' => [
                'meta' => fn (array $root): array => [
                    'status' => $root['status'] ?? '',
                    'ResponseCode' => isset($root['ResponseCode']) ? (string)$root['ResponseCode'] : '',
                    'ResponseMessage' => $this->responseMessagesProvider->getMessage($root['ResponseCode'] ?? '') ?? '',
                    'RequestId' => $this->logger->getRequestUid(),
                ],
                'status' => function (array $root): string {
                    $this->logger->debug('Query.StandardResponse Resolvers');
                    return $root['status'] ?? '';
                },
                'ResponseCode' => fn (array $root): string => $root['ResponseCode'] ?? "",
                'affectedRows' => fn (array $root): array => $root['affectedRows'] ?? [],
            ],
            'GenericResponse' => [
                'meta' => fn (array $root): array => [
                    'status' => $root['status'] ?? '',
                    'ResponseCode' => isset($root['ResponseCode']) ? (string)$root['ResponseCode'] : '',
                    'ResponseMessage' => $this->responseMessagesProvider->getMessage($root['ResponseCode'] ?? '') ?? '',
                    'RequestId' => $this->logger->getRequestUid(),
                ],
                'status' => function (array $root): string {
                    $this->logger->debug('Query.GenericResponse Resolvers');
                    return $root['status'] ?? '';
                },
                'counter' => fn (array $root): int => $root['counter'] ?? 0,
                'ResponseCode' => fn (array $root): string => $root['ResponseCode'] ?? "",
                'affectedRows' => fn (array $root): array => $root['affectedRows'] ?? [],
            ],
            'GemstersResponse' => [
                'meta' => fn (array $root): array => [
                    'status' => $root['status'] ?? '',
                    'ResponseCode' => isset($root['ResponseCode']) ? (string)$root['ResponseCode'] : '',
                    'ResponseMessage' => $this->responseMessagesProvider->getMessage($root['ResponseCode'] ?? '') ?? '',
                    'RequestId' => $this->logger->getRequestUid(),
                ],
                'status' => function (array $root): string {
                    $this->logger->debug('Query.GenericResponse Resolvers');
                    return $root['status'] ?? '';
                },
                'counter' => fn (array $root): int => $root['counter'] ?? 0,
                'ResponseCode' => fn (array $root): string => $root['ResponseCode'] ?? "",
                'affectedRows' => fn (array $root): array => $root['affectedRows'] ?? [],
            ],
            'GemstersData' => [
                'winStatus' => function (array $root): array {
                    $this->logger->debug('Query.GemstersData Resolvers');
                    return $root['winStatus'] ?? [];
                },
                'userStatus' => fn (array $root): array => $root['userStatus'] ?? [],
            ],
            'WinStatus' => [
                'totalGems' => function (array $root): float {
                    $this->logger->debug('Query.WinStatus Resolvers');
                    return isset($root['totalGems']) ? (float)$root['totalGems'] : 0.0;
                },
                'gemsintoken' => fn (array $root): float => isset($root['gemsintoken']) ? (float)$root['gemsintoken'] : 0.0,
                'bestatigung' => fn (array $root): float => isset($root['bestatigung']) ? (float)$root['bestatigung'] : 0.0,
            ],
            'GemstersUserStatus' => [
                'userid' => function (array $root): string {
                    $this->logger->debug('Query.GemstersUserStatus Resolvers');
                    return $root['userid'] ?? '';
                },
                'gems' => fn (array $root): float => $root['gems'] ?? 0.0,
                'tokens' => fn (array $root): float => $root['tokens'] ?? 0.0,
                'percentage' => fn (array $root): float => $root['percentage'] ?? 0.0,
                'details' => fn (array $root): array => $root['details'] ?? []
            ],
            'GemstersUserStatusDetails' => [
                'gemid' => function (array $root): string {
                    $this->logger->debug('Query.GemstersUserStatusDetails Resolvers');
                    return $root['gemid'] ?? '';
                },
                'userid' => fn (array $root): string => $root['userid'] ?? '',
                'postid' => fn (array $root): string => $root['postid'] ?? '',
                'fromid' => fn (array $root): string => $root['fromid'] ?? '',
                'gems' => fn (array $root): float => $root['gems'] ?? 0.0,
                'numbers' => fn (array $root): float => $root['numbers'] ?? 0.0,
                'whereby' => fn (array $root): int => $root['whereby'] ?? 0,
                'createdat' => fn (array $root): string => $root['createdat'] ?? ''
            ],
            'TestingPoolResponse' => [
                'meta' => fn (array $root): array => [
                    'status' => $root['status'] ?? '',
                    'ResponseCode' => isset($root['ResponseCode']) ? (string)$root['ResponseCode'] : '',
                    'ResponseMessage' => $this->responseMessagesProvider->getMessage($root['ResponseCode'] ?? '') ?? '',
                    'RequestId' => $this->logger->getRequestUid(),
                ],
                'status' => function (array $root): string {
                    $this->logger->debug('Query.GenericResponse Resolvers');
                    return $root['status'] ?? '';
                },
                'counter' => fn (array $root): int => $root['counter'] ?? 0,
                'ResponseCode' => fn (array $root): string => $root['ResponseCode'] ?? "",
                'affectedRows' => fn (array $root): array => $root['affectedRows'] ?? [],
            ],
            'PostCommentsResponse' => [
                'meta' => fn (array $root): array => [
                    'status' => $root['status'] ?? '',
                    'ResponseCode' => isset($root['ResponseCode']) ? (string)$root['ResponseCode'] : '',
                    'ResponseMessage' => $this->responseMessagesProvider->getMessage($root['ResponseCode'] ?? '') ?? '',
                    'RequestId' => $this->logger->getRequestUid(),
                ],
                'status' => function (array $root): string {
                    $this->logger->debug('Query.PostCommentsResponse Resolvers');
                    return $root['status'] ?? '';
                },
                'counter' => fn (array $root): int => $root['counter'] ?? 0,
                'ResponseCode' => fn (array $root): string => $root['ResponseCode'] ?? "",
                'affectedRows' => fn (array $root): array => $root['affectedRows'] ?? [],
            ],
            'PostCommentsData' => [
                'commentid' => function (array $root): string {
                    $this->logger->debug('Query.PostCommentsData Resolvers');
                    return $root['commentid'] ?? '';
                },
                'userid' => fn (array $root): string => $root['userid'] ?? '',
                'postid' => fn (array $root): string => $root['postid'] ?? '',
                'parentid' => fn (array $root): string => $root['parentid'] ?? '',
                'content' => fn (array $root): string => $root['content'] ?? '',
                'createdat' => fn (array $root): string => $root['createdat'] ?? '',
                'amountlikes' => fn (array $root): int => $root['amountlikes'] ?? 0,
                'isliked' => fn (array $root): bool => $root['isliked'] ?? false,
                'user' => fn (array $root): array => $root['user'] ?? [],
                'subcomments' => fn (array $root): array => $root['subcomments'] ?? [],
            ],
            'PostSubCommentsData' => [
                'commentid' => function (array $root): string {
                    $this->logger->debug('Query.PostSubCommentsData Resolvers');
                    return $root['commentid'] ?? '';
                },
                'userid' => fn (array $root): string => $root['userid'] ?? '',
                'postid' => fn (array $root): string => $root['postid'] ?? '',
                'parentid' => fn (array $root): string => $root['parentid'] ?? '',
                'content' => fn (array $root): string => $root['content'] ?? '',
                'createdat' => fn (array $root): string => $root['createdat'] ?? '',
                'amountlikes' => fn (array $root): int => $root['amountlikes'] ?? 0,
                'amountreplies' => fn (array $root): int => $root['amountreplies'] ?? 0,
                'isliked' => fn (array $root): bool => $root['isliked'] ?? false,
                'user' => fn (array $root): array => $root['user'] ?? []
            ],
            'LogWins' => [
                'from' => function (array $root): string {
                    $this->logger->debug('Query.UserInfo Resolvers');
                    return $root['from'] ?? '';
                },
                'token' => fn (array $root): string => $root['token'] ?? '',
                'userid' => fn (array $root): string => $root['userid'] ?? '',
                'postid' => fn (array $root): string => $root['postid'] ?? '',
                'action' => fn (array $root): string => $root['action'] ?? '',
                'numbers' => fn (array $root): float => $root['numbers'] ?? 0.0,
                'createdat' => fn (array $root): string => $root['createdat'] ?? '',
            ],
            'UserLogWins' => [
                'meta' => fn (array $root): array => [
                    'status' => $root['status'] ?? '',
                    'ResponseCode' => isset($root['ResponseCode']) ? (string)$root['ResponseCode'] : '',
                    'ResponseMessage' => $this->responseMessagesProvider->getMessage($root['ResponseCode'] ?? '') ?? '',
                    'RequestId' => $this->logger->getRequestUid(),
                ],
                'status' => function (array $root): string {
                    $this->logger->debug('Query.UserLogWins Resolvers');
                    return $root['status'] ?? '';
                },
                'counter' => fn (array $root): int => $root['counter'] ?? 0,
                'ResponseCode' => fn (array $root): string => $root['ResponseCode'] ?? "",
                'affectedRows' => fn (array $root): array => $root['affectedRows'] ?? [],
            ],
            'AllUserInfo' => [
                'followerid' => function (array $root): string {
                    $this->logger->debug('Query.AllUserInfo Resolvers');
                    return $root['follower'] ?? '';
                },
                'followername' => fn (array $root): string => ($root['followername'] ?? '') . '.' . ($root['followerslug'] ?? ''),
                'followedid' => fn (array $root): string => $root['followed'] ?? '',
                'followedname' => fn (array $root): string => ($root['followedname'] ?? '') . '.' . ($root['followedslug'] ?? ''),
            ],
            'AllUserFriends' => [
                'meta' => fn (array $root): array => [
                    'status' => $root['status'] ?? '',
                    'ResponseCode' => isset($root['ResponseCode']) ? (string)$root['ResponseCode'] : '',
                    'ResponseMessage' => $this->responseMessagesProvider->getMessage($root['ResponseCode'] ?? '') ?? '',
                    'RequestId' => $this->logger->getRequestUid(),
                ],
                'status' => function (array $root): string {
                    $this->logger->debug('Query.AllUserFriends Resolvers');
                    return $root['status'] ?? '';
                },
                'counter' => fn (array $root): int => $root['counter'] ?? 0,
                'ResponseCode' => fn (array $root): string => $root['ResponseCode'] ?? "",
                'affectedRows' => fn (array $root): array => $root['affectedRows'] ?? [],
            ],
            'ReferralInfoResponse' => [
                'meta' => fn (array $root): array => [
                    'status' => $root['status'] ?? '',
                    'ResponseCode' => isset($root['ResponseCode']) ? (string)$root['ResponseCode'] : '',
                    'ResponseMessage' => $this->responseMessagesProvider->getMessage($root['ResponseCode'] ?? '') ?? '',
                    'RequestId' => $this->logger->getRequestUid(),
                ],
                'status' => function (array $root): string {
                    $this->logger->debug('Query.ReferralInfoResponse Resolvers');
                    return $root['status'] ?? '';
                },
                'ResponseCode' => fn (array $root): string => $root['ResponseCode'] ?? "",
                'referralUuid' => fn (array $root): string => $root['referralUuid'] ?? '',
                'referralLink' => fn (array $root): string => $root['referralLink'] ?? '',
            ],
            'ReferralListResponse' => [
                'meta' => fn (array $root): array => [
                    'status' => $root['status'] ?? '',
                    'ResponseCode' => isset($root['ResponseCode']) ? (string)$root['ResponseCode'] : '',
                    'ResponseMessage' => $this->responseMessagesProvider->getMessage($root['ResponseCode'] ?? '') ?? '',
                    'RequestId' => $this->logger->getRequestUid(),
                ],
                'status' => function (array $root): string {
                    $this->logger->debug('Query.ReferralListResponse Resolvers');
                    return $root['status'] ?? '';
                },
                'counter' => fn (array $root): int => $root['counter'] ?? 0,
                'ResponseCode' => fn (array $root): string => $root['ResponseCode'] ?? "",
                'affectedRows' => fn (array $root): array => $root['affectedRows'] ?? [],
            ],
            'ReferralUsers' => [
                'invitedBy' => fn (array $root): ?array => $root['invitedBy'] ?? null,
                'iInvited' => fn (array $root): array => $root['iInvited'] ?? [],
            ],
            'GetActionPricesResponse' => [
                'meta' => fn (array $root): array => [
                    'status' => $root['status'] ?? '',
                    'ResponseCode' => isset($root['ResponseCode']) ? (string)$root['ResponseCode'] : '',
                    'ResponseMessage' => $this->responseMessagesProvider->getMessage($root['ResponseCode'] ?? '') ?? '',
                    'RequestId' => $this->logger->getRequestUid(),
                ],
                'status' => function (array $root): string {
                    $this->logger->debug('Query.GetActionPricesResponse Resolvers');
                    return $root['status'] ?? '';
                },
                'ResponseCode' => fn (array $root): string => $root['ResponseCode'] ?? "",
                'affectedRows' => fn (array $root): ?array => $root['affectedRows'] ?? null,
            ],
            'ActionPriceResult' => [
                'postPrice' => fn (array $root): float => (float) ($root['postPrice'] ?? 0),
                'likePrice' => fn (array $root): float => (float) ($root['likePrice'] ?? 0),
                'dislikePrice' => fn (array $root): float => (float) ($root['dislikePrice'] ?? 0),
                'commentPrice' => fn (array $root): float => (float) ($root['commentPrice'] ?? 0),
            ],
            'ActionGemsReturns' => [
                'viewGemsReturn' => fn (array $root): float => (float)($root['viewGemsReturn'] ?? 0.0),
                'likeGemsReturn' => fn (array $root): float => (float)($root['likeGemsReturn'] ?? 0.0),
                'dislikeGemsReturn' => fn (array $root): float => (float)($root['dislikeGemsReturn'] ?? 0.0),
                'commentGemsReturn' => fn (array $root): float => (float)($root['commentGemsReturn'] ?? 0.0),
            ],
            'MintingData' => [
                'tokensMintedYesterday' => fn (array $root): float => (float)($root['tokensMintedYesterday'] ?? 0.0),
            ],
            'TokenomicsResponse' => [
                'meta' => fn (array $root): array => [
                    'status' => $root['status'] ?? '',
                    'ResponseCode' => isset($root['ResponseCode']) ? (string)$root['ResponseCode'] : '',
                    'ResponseMessage' => $this->responseMessagesProvider->getMessage($root['ResponseCode'] ?? '') ?? '',
                    'RequestId' => $this->logger->getRequestUid(),
                ],
                'status' => function (array $root): string {
                    $this->logger->debug('Query.TokenomicsResponse Resolvers');
                    return $root['status'] ?? '';
                },
                'ResponseCode' => fn (array $root): int => $root['ResponseCode'] ?? 0,
                'actionTokenPrices' => fn (array $root): array => $root['actionTokenPrices'] ?? [],
                'actionGemsReturns' => fn (array $root): array => $root['actionGemsReturns'] ?? [],
                'mintingData' => fn (array $root): array => $root['mintingData'] ?? [],
            ],
            'ResetPasswordRequestResponse' => [
                'meta' => fn (array $root): array => [
                    'status' => $root['status'] ?? '',
                    'ResponseCode' => isset($root['ResponseCode']) ? (string)$root['ResponseCode'] : '',
                    'ResponseMessage' => $this->responseMessagesProvider->getMessage($root['ResponseCode'] ?? '') ?? '',
                    'RequestId' => $this->logger->getRequestUid(),
                ],
                'status' => function (array $root): string {
                    $this->logger->debug('Query.ResetPasswordRequestResponse Resolvers');
                    return $root['status'] ?? '';
                },
                'ResponseCode' => fn (array $root): string => $root['ResponseCode'] ?? "",
                'nextAttemptAt' => fn (array $root): string => $root['nextAttemptAt'] ?? '',
            ],
            'PostEligibilityResponse' => [
                'meta' => fn (array $root): array => [
                    'status' => $root['status'] ?? '',
                    'ResponseCode' => isset($root['ResponseCode']) ? (string)$root['ResponseCode'] : '',
                    'ResponseMessage' => $this->responseMessagesProvider->getMessage($root['ResponseCode'] ?? '') ?? '',
                    'RequestId' => $this->logger->getRequestUid(),
                ],
                'status' => function (array $root): string {
                    $this->logger->debug('Query.PostEligibilityResponse Resolvers');
                    return $root['status'] ?? '';
                },
                'ResponseCode' => fn (array $root): string => isset($root['ResponseCode']) ? (string) $root['ResponseCode'] : '',
                'eligibilityToken' => fn (array $root): string => $root['eligibilityToken'] ?? ''
            ],
             'TransactionResponse' => [
                'meta' => fn (array $root): array => [
                    'status' => $root['status'] ?? '',
                    'ResponseCode' => isset($root['ResponseCode']) ? (string)$root['ResponseCode'] : '',
                    'ResponseMessage' => $this->responseMessagesProvider->getMessage($root['ResponseCode'] ?? '') ?? '',
                    'RequestId' => $this->logger->getRequestUid(),
                ],
                'status' => function (array $root): string {
                    $this->logger->debug('Query.TransactionResponse Resolvers');
                    return $root['status'] ?? '';
                },
                'ResponseCode' => fn (array $root): string => isset($root['ResponseCode']) ? (string) $root['ResponseCode'] : '',
                'affectedRows' => fn (array $root): array => $root['affectedRows'] ?? [],
            ],
            'TransactionHistoryResponse' => [
                'meta' => function (array $root): array {
                    return [
                        'status' => $root['status'] ?? '',
                        'ResponseCode' => isset($root['ResponseCode']) ? (string)$root['ResponseCode'] : '',
                        'ResponseMessage' => $this->responseMessagesProvider->getMessage($root['ResponseCode'] ?? '') ?? '',
                        'RequestId' => $this->logger->getRequestUid(),
                    ];
                },
                'affectedRows' => function (array $root): array {
                    return $root['affectedRows'] ?? [];
                },
            ],
            'TransactionHistoryItem' => [
                'operationid' => function (array $root): string {
                    return $root['operationid'] ?? '';
                },
                'transactionId' => function (array $root): string {
                    return $root['transactionid'] ?? '';
                },
                'transactiontype' => function (array $root): string {
                    return $root['transactiontype'] ?? '';
                },
                'tokenamount' => function (array $root): string {
                    return $root['tokenamount'] ?? '';
                },
                'netTokenAmount' => function (array $root): string {
                    return $root['netTokenAmount'] ?? '';
                },
                'message' => function (array $root): string {
                    return $root['message'] ?? '';
                },
                'createdat' => function (array $root): string {
                    return $root['createdat'] ?? '';
                },
                'sender' => function (array $root): array {
                    return $root['sender'] ?? [];
                },
                'recipient' => function (array $root): array {
                    return $root['recipient'] ?? [];
                },
                'fees' => function (array $root): ?array {
                    return $root['fees'] ?? null;
                },
                'transactionCategory' => function (array $root): ?string {
                    return $root['transactioncategory'] ?? null;
                },
            ],
            'TransactionFeeSummary' => [
                'total' => function (array $root): ?string {
                    return isset($root['total']) ? $root['total'] : null;
                },
                'burn' => function (array $root): ?string {
                    return isset($root['burn']) ? $root['burn'] : null;
                },
                'peer' => function (array $root): ?string {
                    return isset($root['peer']) ? $root['peer'] : null;
                },
                'inviter' => function (array $root): ?string {
                    return isset($root['inviter']) ? $root['inviter'] : null;
                },
            ],
            'TransferTokenResponse' => [
                'meta' => fn (array $root): array => [
                    'status' => $root['status'] ?? '',
                    'ResponseCode' => isset($root['ResponseCode']) ? (string)$root['ResponseCode'] : '',
                    'ResponseMessage' => $this->responseMessagesProvider->getMessage($root['ResponseCode'] ?? '') ?? '',
                    'RequestId' => $this->logger->getRequestUid(),
                ],
                'status' => function (array $root): string {
                    $this->logger->debug('Query.TransferTokenResponse Resolvers');
                    return $root['status'] ?? '';
                },
                'ResponseCode' => fn (array $root): string => isset($root['ResponseCode']) ? (string) $root['ResponseCode'] : '',
                'affectedRows' => fn (array $root): array => $root['affectedRows'] ?? [],
            ],
            'TransferToken' => [
                'tokenSend' => fn (array $root): float => $root['tokenSend'] ?? 0.0,
                'tokensSubstractedFromWallet' => fn (array $root): float => $root['tokensSubstractedFromWallet'] ?? 0.0,
                'tokenSendFormatted' => fn (array $root): string => (string) ($root['tokenSend'] ?? '0'),
                'tokensSubstractedFromWalletFormatted' => fn (array $root): string => (string) ($root['tokensSubstractedFromWallet'] ?? '0'),
                'createdat' => fn (array $root): string => ($root['createdat'] ?? ''),
            ],
            'Transaction' => [
                'transactionid' => fn (array $root): string => $root['transactionid'] ?? '',
                'operationid' => fn (array $root): string => $root['operationid'] ?? '',
                'transactiontype' => fn (array $root): string => $root['transactiontype'] ?? '',
                'senderid' => fn (array $root): string => $root['senderid'] ?? '',
                'recipientid' => fn (array $root): string => $root['recipientid'] ?? '',
                'tokenamount' => fn (array $root): float => (float) ($root['tokenamount'] ?? 0.0),
                'transferaction' => fn (array $root): string => $root['transferaction'] ?? '',
                'message' => fn (array $root): string => $root['message'] ?? '',
                'createdat' => fn (array $root): string => $root['createdat'] ?? '',
                'sender' => fn (array $root): array => $root['sender'] ?? [],
                'recipient' => fn (array $root): array => $root['recipient'] ?? [],
            ],
            'PostInteractionResponse' => [
                'meta' => fn (array $root): array => [
                    'status' => $root['status'] ?? '',
                    'ResponseCode' => isset($root['ResponseCode']) ? (string)$root['ResponseCode'] : '',
                    'ResponseMessage' => $this->responseMessagesProvider->getMessage($root['ResponseCode'] ?? '') ?? '',
                    'RequestId' => $this->logger->getRequestUid(),
                ],
                'status' => function (array $root): string {
                    $this->logger->debug('Query.PostInteractionResponse Resolvers');
                    return $root['status'] ?? '';
                },
                'ResponseCode' => fn (array $root): string => $root['ResponseCode'] ?? "",
                'affectedRows' => fn (array $root): array => $root['affectedRows'] ?? [],
            ],
            'ListAdvertisementData' => [
                'status' => function (array $root): string {
                    $this->logger->debug('Query.ListAdvertisementData Resolvers');
                    return $root['status'] ?? '';
                },
                'meta' => fn (array $root): array => [
                    'status' => $root['status'] ?? '',
                    'ResponseCode' => isset($root['ResponseCode']) ? (string)$root['ResponseCode'] : '',
                    'ResponseMessage' => $this->responseMessagesProvider->getMessage($root['ResponseCode'] ?? '') ?? '',
                    'RequestId' => $this->logger->getRequestUid(),
                ],
                'ResponseCode' => fn (array $root): string => $root['ResponseCode'] ?? '',
                'affectedRows' => fn (array $root): ?array => $root['affectedRows'] ?? null,
            ],
            'AdvertisementRow' => [
                'id' => function (array $root): string {
                    $this->logger->debug('Query.AdvertisementRow Resolvers');
                    return $root['advertisementid'] ?? '';
                },
                'createdAt' => fn (array $root): string => $root['createdat'] ?? '',
                'type' => fn (array $root): string => strtoupper($root['status']),
                'timeframeStart' => fn (array $root): string => $root['timestart'] ?? '',
                'timeframeEnd' => fn (array $root): string => $root['timeend'] ?? '',
                'totalTokenCost' => fn (array $root): float => $root['tokencost'] ?? 0.0,
                'totalEuroCost' => fn (array $root): float => $root['eurocost'] ?? 0.0,
            ],
            'ListedAdvertisementData' => [
                'status' => function (array $root): string {
                    $this->logger->debug('Query.ListedAdvertisementData Resolvers');
                    return $root['status'] ?? '';
                },
                'ResponseCode' => fn (array $root): string => $root['ResponseCode'] ?? '',
                'meta' => fn (array $root): array => [
                    'status' => $root['status'] ?? '',
                    'ResponseCode' => isset($root['ResponseCode']) ? (string)$root['ResponseCode'] : '',
                    'ResponseMessage' => $this->responseMessagesProvider->getMessage($root['ResponseCode'] ?? '') ?? '',
                    'RequestId' => $this->logger->getRequestUid(),
                ],
                'affectedRows' => fn (array $root): ?array => $root['affectedRows'] ?? null,
            ],
            'Advertisement' => [
                'id' => function (array $root): string {
                    $this->logger->debug('Query.Advertisement Resolvers');
                    return $root['advertisementid'] ?? '';
                },
                'creatorId' => fn (array $root): string => $root['userid'] ?? '',
                'postId' => fn (array $root): string => $root['postid'] ?? '',
                'type' => fn (array $root): string => strtoupper($root['status']),
                'timeframeStart' => fn (array $root): string => $root['timestart'] ?? '',
                'timeframeEnd' => fn (array $root): string => $root['timeend'] ?? '',
                'totalTokenCost' => fn (array $root): float => $root['tokencost'] ?? 0.0,
                'totalEuroCost' => fn (array $root): float => $root['eurocost'] ?? 0.0,
                'gemsEarned' => fn (array $root): float => $root['gemsearned'] ?? 0.0,
                'amountLikes' => fn (array $root): int => $root['amountlikes'] ?? 0,
                'amountViews' => fn (array $root): int => $root['amountviews'] ?? 0,
                'amountComments' => fn (array $root): int => $root['amountcomments'] ?? 0,
                'amountDislikes' => fn (array $root): int => $root['amountdislikes'] ?? 0,
                'amountReports' => fn (array $root): int => $root['amountreports'] ?? 0,
                'createdAt' => fn (array $root): string => $root['createdat'] ?? '',
                'user' => fn (array $root): array =>
                    // neu
                    $root['user'] ?? [],
                'post' => fn (array $root): array =>
                    // neu
                    $root['post'] ?? [],
            ],
            'TotalAdvertisementHistoryStats' => [
                'tokenSpent' => function (array $root): float {
                    $this->logger->debug('Query.TotalAdvertisementHistoryStats Resolvers');
                    return $root['tokenSpent'] ?? 0.0;
                },
                'euroSpent' => fn (array $root): float => $root['euroSpent'] ?? 0.0,
                'amountAds' => fn (array $root): int => $root['amountAds'] ?? 0,
                'gemsEarned' => fn (array $root): float => $root['gemsEarned'] ?? 0.0,
                'amountLikes' => fn (array $root): int => $root['amountLikes'] ?? 0,
                'amountViews' => fn (array $root): int => $root['amountViews'] ?? 0,
                'amountComments' => fn (array $root): int => $root['amountComments'] ?? 0,
                'amountDislikes' => fn (array $root): int => $root['amountDislikes'] ?? 0,
                'amountReports' => fn (array $root): int => $root['amountReports'] ?? 0,
            ],
            'AdvertisementHistoryResult' => [
                'stats' => function (array $root): array {
                    $this->logger->debug('Query.AdvertisementHistoryResult Resolvers');
                    return $root['stats'] ?? [];
                },
                'advertisements' => fn (array $root): array => $root['advertisements'] ?? [],
            ],
            'ModerationStatsResponse' => [
                'status' => function (array $root): string {
                    $this->logger->info('Query.ModerationStatsResponse Resolvers');
                    return $root['status'] ?? '';
                },
                'ResponseCode' => fn (array $root): string => $root['ResponseCode'] ?? '',
                'affectedRows' => fn (array $root): array => $root['affectedRows'] ?? [],
                'meta' => fn (array $root): array => [
                    'status' => $root['status'] ?? '',
                    'ResponseCode' => isset($root['ResponseCode']) ? (string)$root['ResponseCode'] : '',
                    'ResponseMessage' => $this->responseMessagesProvider->getMessage($root['ResponseCode'] ?? '') ?? '',
                    'RequestId' => $this->logger->getRequestUid(),
                ],
            ],
            'ModerationStats' => [
                'AmountAwaitingReview' => fn (array $root): int => $root['AmountAwaitingReview'] ?? 0,
                'AmountHidden' => fn (array $root): int => $root['AmountHidden'] ?? 0,
                'AmountRestored' => fn (array $root): int => $root['AmountRestored'] ?? 0,
                'AmountIllegal' => fn (array $root): int => $root['AmountIllegal'] ?? 0
            ],
            'ModerationItemListResponse' => [
                'status' => function (array $root): string {
                    $this->logger->info('Query.ModerationItemListResponse Resolvers');
                    return $root['status'] ?? '';
                },
                'ResponseCode' => fn (array $root): string => $root['ResponseCode'] ?? '',
                'affectedRows' => fn (array $root): array => $root['affectedRows'] ?? [],
                'meta' => fn (array $root): array => [
                    'status' => $root['status'] ?? '',
                    'ResponseCode' => isset($root['ResponseCode']) ? (string)$root['ResponseCode'] : '',
                    'ResponseMessage' => $this->responseMessagesProvider->getMessage($root['ResponseCode'] ?? '') ?? '',
                    'RequestId' => $this->logger->getRequestUid(),
                ],
            ],
            'ModerationItem' => [
                'moderationTicketId' => fn (array $root): string => $root['uid'] ?? '',
                'targettype' => fn (array $root): string => $root['targettype'] ?? '',
                'targetContentId' => fn (array $root): string => $root['targetcontentid'] ?? '',
                'status' => fn (array $root): string => $root['status'] ?? '',
                'reportscount' => fn (array $root): int => $root['reportscount'] ?? 1,
                'targetcontent' => fn (array $root): array => $root['targetcontent'] ?? [],
                'reporters' => fn (array $root): array => $root['reporters'] ?? [],
                'createdat' => fn (array $root): string => $root['createdat'] ?? '',
                'moderatedBy' => fn (array $root): ?array => $root['moderatedBy'] ?? null,
            ],
            'TargetContent' => [
                'post' => fn (array|null $root): ?array => $root['post'] ?? null,
                'comment' => fn (array|null $root): ?array => $root['comment'] ?? null,
                'user' => fn (array|null $root): ?array => $root['user'] ?? null,
            ],
            'ShopOrderDetailsResponse' => [
                'status' => function (array $root): string {
                    $this->logger->info('Query.ShopOrderDetailsResponse Resolvers');
                    return $root['status'] ?? '';
                },
                'ResponseCode' => fn (array $root): string => $root['ResponseCode'] ?? '',
                'affectedRows' => fn (array $root): array => $root['affectedRows'] ?? [],
                'meta' => fn (array $root): array => [
                    'status' => $root['status'] ?? '',
                    'ResponseCode' => isset($root['ResponseCode']) ? (string)$root['ResponseCode'] : '',
                    'ResponseMessage' => $this->responseMessagesProvider->getMessage($root['ResponseCode'] ?? '') ?? '',
                    'RequestId' => $this->logger->getRequestUid(),
                ],
            ],
            'ShopOrderDetails' => [
                'shopOrderId' => fn (array $root): string => $root['shopOrderId'] ?? '',
                'shopItemId' => fn (array $root): string => $root['shopItemId'] ?? '',
                'shopItemSpecs' => fn (array $root): array => $root['shopItemSpecs'] ?? [],
                'deliveryDetails' => fn (array $root): array => $root['deliveryDetails'] ?? [],
                'createdat' => fn (array $root): string => $root['createdat'] ?? '',
            ],
            'ShopItemSpecs' => [
                'size' => fn (array $root): string => $root['size'] ?? '',
            ],
            'ShopOrderDeliveryDetails' => [
                'name' => fn (array $root): string => $root['name'] ?? '',
                'email' => fn (array $root): string => $root['email'] ?? '',
                'addressline1' => fn (array $root): string => $root['addressline1'] ?? '',
                'addressline2' => fn (array $root): string => $root['addressline2'] ?? '',
                'city' => fn (array $root): string => $root['city'] ?? '',
                'zipcode' => fn (array $root): string => $root['zipcode'] ?? '',
                'country' => fn (array $root): string => $root['country'] ?? '',
            ],
            'ShopSupportedDeliveryCountry' => [
                'country' => fn (array $root): string => $root['country'] ?? '',
            ],
            'LeaderboardResponse' => [
                'meta' => fn (array $root): array => [
                    'status' => $root['status'] ?? '',
                    'ResponseCode' => isset($root['ResponseCode']) ? (string)$root['ResponseCode'] : '',
                    'ResponseMessage' => $this->responseMessagesProvider->getMessage($root['ResponseCode'] ?? '') ?? '',
                    'RequestId' => $this->logger->getRequestUid(),
                ],
                'leaderboardResultLink' => fn (array $root): string => ($root['affectedRows']['leaderboardResultLink'] ?? ''),
            ],

        ];
    }

    protected function buildSubscriptionResolvers(): array
    {
        return [];
    }
    protected function buildQueryResolvers(): array
    {

        return [
            'hello' => fn (mixed $root, array $args, mixed $context) => $this->resolveHello($root, $args, $context),
            'searchUser' => fn (mixed $root, array $args) => $this->resolveSearchUser($args),
            'searchUserAdmin' => fn (mixed $root, array $args) => $this->resolveSearchUser($args),
            'listUsersV2' => fn (mixed $root, array $args) => $this->profileService->listUsers($args),
            'listUsersAdminV2' => fn (mixed $root, array $args) => $this->profileService->listUsersAdmin($args),
            'listUsers' => fn (mixed $root, array $args) => $this->profileService->listUsers($args),
            'getProfile' => fn (mixed $root, array $args) => $this->resolveProfile($args),
            'listFollowRelations' => fn (mixed $root, array $args) => $this->resolveFollows($args),
            'listFriends' => fn (mixed $root, array $args) => $this->resolveFriends($args),
            'listPosts' => fn (mixed $root, array $args) => $this->resolvePosts($args),
            'guestListPost' => fn (mixed $root, array $args) => $this->guestListPost($args),
            'listAdvertisementPosts' => fn (mixed $root, array $args) => $this->resolveAdvertisementsPosts($args),
            'listComments' => fn (mixed $root, array $args) => $this->resolveListComments($args),
            'listChildComments' => fn (mixed $root, array $args) => $this->resolveComments($args),
            'listTags' => fn (mixed $root, array $args) => $this->resolveTags($args),
            'searchTags' => fn (mixed $root, array $args) => $this->resolveTagsearch($args),
            'getDailyFreeStatus' => fn (mixed $root, array $args) => $this->dailyFreeService->getUserDailyAvailability($this->currentUserId),
            'gemster' => fn (mixed $root, array $args) => $this->gemsService->gemsStats(),
            'balance' => fn (mixed $root, array $args) => $this->resolveLiquidity(),
            'getUserInfo' => fn (mixed $root, array $args) => $this->resolveUserInfo(),
            'listWinLogs' => fn (mixed $root, array $args) => $this->resolveFetchWinsLog($args),
            'listPaymentLogs' => fn (mixed $root, array $args) => $this->resolveFetchPaysLog($args),
            'listBlockedUsers' => fn (mixed $root, array $args) => $this->resolveBlocklist($args),
            'listTodaysInteractions' => fn (mixed $root, array $args) => $this->mintService->listTodaysInteractions(),
            'allfriends' => fn (mixed $root, array $args) => $this->resolveAllFriends($args),
            'postcomments' => fn (mixed $root, array $args) => $this->resolvePostComments($args),
            'dailygemstatus' => fn (mixed $root, array $args) => $this->poolService->gemsStats(),
            'dailygemsresults' => fn (mixed $root, array $args) => $this->gemsService->allGemsForDay($args['day']),
            'getReferralInfo' => fn (mixed $root, array $args) => $this->resolveReferralInfo(),
            'referralList' => fn (mixed $root, array $args) => $this->resolveReferralList($args),
            'getActionPrices' => fn (mixed $root, array $args) => $this->resolveActionPrices(),
            'postEligibility' => fn (mixed $root, array $args) => $this->postService->postEligibility(),
            'getTransactionHistory' => fn (mixed $root, array $args) => $this->transactionsHistory($args),
            'transactionHistory' => fn (mixed $root, array $args) => $this->transactionsHistoryItems($args),
            'postInteractions' => fn (mixed $root, array $args) => $this->postInteractions($args),
            'advertisementHistory' => fn (mixed $root, array $args) => $this->resolveAdvertisementHistory($args),
            'getTokenomics' => fn (mixed $root, array $args) => $this->resolveTokenomics(),
            'moderationStats' => fn (mixed $root, array $args) => $this->moderationStats(),
            'moderationItems' => fn (mixed $root, array $args) => $this->moderationItems($args),
            'shopOrderDetails' => fn (mixed $root, array $args) => $this->shopOrderDetails($args),
            'getMintAccount' => fn (mixed $root, array $args) => $this->mintService->getMintAccount(),
            'generateLeaderboard' => fn (mixed $root, array $args) => $this->generateLeaderboard($args)
        ];
    }

    protected function buildMutationResolvers(): array
    {
        return [
            'requestPasswordReset' => fn (mixed $root, array $args) => $this->userService->requestPasswordReset($args['email']),
            'resetPasswordTokenVerify' => fn (mixed $root, array $args) => $this->userService->resetPasswordTokenVerify($args['token']),
            'resetPassword' => fn (mixed $root, array $args) => $this->userService->resetPassword($args),
            'register' => fn (mixed $root, array $args) => $this->createUser($args['input']),
            'verifyAccount' => fn (mixed $root, array $args) => $this->verifyAccount($args['userid']),
            'login' => fn (mixed $root, array $args) => $this->login($args['email'], $args['password']),
            'refreshToken' => fn (mixed $root, array $args) => $this->refreshToken($args['refreshToken']),
            'verifyReferralString' => fn (mixed $root, array $args) => $this->resolveVerifyReferral($args),
            'updateUserPreferences' => fn (mixed $root, array $args) => $this->userService->updateUserPreferences($args),
            'updateUsername' => fn (mixed $root, array $args) => $this->userService->setUsername($args),
            'updateEmail' => fn (mixed $root, array $args) => $this->userService->setEmail($args),
            'updatePassword' => fn (mixed $root, array $args) => $this->userService->setPassword($args),
            'updateBio' => fn (mixed $root, array $args) => $this->userInfoService->updateBio($args['biography']),
            'updateProfileImage' => fn (mixed $root, array $args) => $this->userInfoService->setProfilePicture($args['img']),
            'toggleUserFollowStatus' => fn (mixed $root, array $args) => $this->userInfoService->toggleUserFollow($args['userid']),
            'toggleBlockUserStatus' => fn (mixed $root, array $args) => $this->userInfoService->toggleUserBlock($args['userid']),
            'deleteAccount' => fn (mixed $root, array $args) => $this->userService->deleteAccount($args['password']),
            'likeComment' => fn (mixed $root, array $args) => $this->commentInfoService->likeComment($args['commentid']),
            'reportComment' => fn (mixed $root, array $args) => $this->commentInfoService->reportComment($args['commentid']),
            'reportUser' => fn (mixed $root, array $args) => $this->userInfoService->reportUser($args['userid']),
            'contactus' => fn (mixed $root, array $args) => $this->ContactUs($args),
            'createComment' => fn (mixed $root, array $args) => $this->postService->resolveActionPost($args),
            'createPost' => fn (mixed $root, array $args) => $this->postService->resolveActionPost($args),
            'resolvePostAction' => fn (mixed $root, array $args) => $this->postService->resolveActionPost($args),
            'resolveTransfer' => fn (mixed $root, array $args) => $this->peerTokenService->transferToken($args),
            'resolveTransferV2' => fn (mixed $root, array $args) => $this->peerTokenService->transferToken($args),
            'globalwins' => fn (mixed $root, array $args) => $this->gemsService->generateGemsFromActions(),
            'distributeTokensForGems' => fn (mixed $root, array $args) => $this->resolveMint($args),
            'gemsters' => fn (mixed $root, array $args) => $this->resolveGemsters($args),
            'advertisePostBasic' => fn (mixed $root, array $args) => $this->advertisementService->resolveAdvertisePost($args),
            'advertisePostPinned' => fn (mixed $root, array $args) => $this->advertisementService->resolveAdvertisePost($args),
            'performModeration' => fn (mixed $root, array $args) => $this->performModerationAction($args),
            'alphaMint' => fn (mixed $root, array $args) => $this->alphaMintService->alphaMint($args),
            'performShopOrder' => fn (mixed $root, array $args) => $this->performShopOrder($args),
        ];
    }

    protected function transferTokenV2(array $args): array
    {
        return $this->peerTokenService->transferToken($args);
    }

    protected function resolveHello(mixed $root, array $args, mixed $context): array
    {
        $this->logger->debug('Query.hello started', ['args' => $args]);

        /**
         * Map Role Mask
         */
        if (Role::mapRolesMaskToNames($this->userRoles)[0]) {
            $userRole = Role::mapRolesMaskToNames($this->userRoles)[0];
        }
        $userRoleString = $userRole ?? 'USER';

        return [
            'userroles' => $this->userRoles,
            'userRoleString' => $userRoleString,
            'currentuserid' => $this->currentUserId,
            'currentVersion' => AppVersion::get(),
            'wikiLink' => 'https://github.com/peer-network/peer_backend/releases/latest',
            'lastMergedPullRequestNumber' => "thingy is replaced with 'currentVersion' field",
            'companyAccountId' => FeesAccountHelper::getAccounts()['PEER_BANK'],
        ];
    }

    // Werbeanzeige historie abrufen
    protected function resolveAdvertisementHistory(?array $args = []): ?array
    {
        // Authentifizierung prüfen
        if (!$this->checkAuthentication()) {
            return $this->respondWithError(60501);
        }

        $validationResult = $this->validateOffsetAndLimit($args);
        if (isset($validationResult['status']) && $validationResult['status'] === 'error') {
            return $validationResult;
        }

        $this->logger->info('Query.resolveAdvertisementHistory gestartet');

        try {
            // Werbeanzeige function abrufen
            $response = $this->advertisementService->fetchAll($args);

            if (isset($response['status']) && $response['status'] === 'success') {

                return $response;
            }

            return $response;

        } catch (\Throwable $e) {
            return $this->respondWithError(40301);
        }
    }

    protected function createUser(array $args): ?array
    {
        $this->logger->debug('Query.createUser started');

        $response = $this->userService->createUser($args);
        if (isset($response['status']) && $response['status'] === 'error') {
            return $response;
        }

        if (!empty($response)) {
            return $response;
        }

        $this->logger->error('Query.createUser No data found');
        return $this::respondWithError(41105);
    }

    protected function resolveBlocklist(array $args): ?array
    {
        if (!$this->checkAuthentication()) {
            return $this::respondWithError(60501);
        }

        $validation = RequestValidator::validate($args, []);

        if ($validation instanceof ValidatorErrors) {
            return $this::respondWithError(
                $validation->errors[0]
            );
        }

        $this->logger->debug('Query.resolveBlocklist started');

        $response = $this->userInfoService->loadBlocklist($args);
        if (isset($response['status']) && $response['status'] === 'error') {
            return $response;
        }

        if (empty($response['counter'])) {
            return $this::createSuccessResponse(11107, [], false);
        }

        return $response;
    }

    protected function resolveFetchWinsLog(array $args): ?array
    {
        if (!$this->checkAuthentication()) {
            return $this::respondWithError(60501);
        }

        if (empty($args)) {
            return $this::respondWithError(30101);
        }

        $validationResult = $this->validateOffsetAndLimit($args);
        if (isset($validationResult['status']) && $validationResult['status'] === 'error') {
            return $validationResult;
        }

        $this->logger->debug('Query.resolveFetchWinsLog started');

        $response = $this->walletService->callFetchWinsLog($args);
        if (isset($response['status']) && $response['status'] === 'error') {
            return $response;
        }

        if (empty($response)) {
            $this->logger->warning('Query.resolveFetchWinsLog No records found');
            return $this::createSuccessResponse(21202, [], false);
        }

        return $this::createSuccessResponse(11203, $response);
    }

    protected function resolveFetchPaysLog(array $args): ?array
    {
        if (!$this->checkAuthentication()) {
            return $this::respondWithError(60501);
        }

        if (empty($args)) {
            return $this::respondWithError(30101);
        }

        $validationResult = $this->validateOffsetAndLimit($args);
        if (isset($validationResult['status']) && $validationResult['status'] === 'error') {
            return $validationResult;
        }

        $this->logger->debug('Query.resolveFetchPaysLog started');

        $response = $this->walletService->callFetchPaysLog($args);
        if (isset($response['status']) && $response['status'] === 'error') {
            return $response;
        }

        if (empty($response)) {
            $this->logger->warning('Query.resolveFetchPaysLog No records found');
            return $this::createSuccessResponse(21202, [], false);
        }

        return $this::createSuccessResponse(11203, $response);
    }

    protected function resolveReferralInfo(): ?array
    {
        if (!$this->checkAuthentication()) {
            return $this::respondWithError(60501);
        }

        $this->logger->debug('Query.resolveReferralInfo started');

        try {
            $userId = $this->currentUserId;
            $this->logger->info('Current userId in resolveReferralInfo', [
                'userId' => $userId,
            ]);


            $info = $this->userMapper->getReferralInfoByUserId($userId);
            if (empty($info)) {
                return $this::createSuccessResponse(21002);
            }

            $response = [
                'referralUuid' => $info['referral_uuid'] ?? '',
                'referralLink' => $info['referral_link'] ?? '',
                'status' => 'success',
                'ResponseCode' => "11011"
            ];

            return $response;
        } catch (\Throwable $e) {
            $this->logger->error('Query.resolveReferralInfo exception', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return $this::respondWithError(41013);
        }
    }

    protected function resolveReferralList(array $args): ?array
    {
        if (!$this->checkAuthentication()) {
            return $this::respondWithError(60501);
        }

        $this->logger->debug('Query.resolveReferralList started');

        $userId = $this->currentUserId;
        $validationResult = $this->validateOffsetAndLimit($args);
        if (isset($validationResult['status']) && $validationResult['status'] === 'error') {
            return $validationResult;
        }
        try {
            $this->logger->info('Current userId in resolveReferralList', ['userId' => $userId]);

            $referralUsers = [
                'invitedBy' => [],
                'iInvited' => [],
            ];

            $deletedUserSpec = new DeletedUserSpec(
                ContentFilteringCases::searchById,
                ContentType::user
            );
            $systemUserSpec = new SystemUserSpec(
                ContentFilteringCases::searchById,
                ContentType::user
            );

            $illegalContentFilterSpec = new IllegalContentFilterSpec(
                ContentFilteringCases::searchById,
                ContentType::user
            );

            $specs = [
                $illegalContentFilterSpec,
                $systemUserSpec,
                $deletedUserSpec,
            ];


            $inviter = $this->userMapper->getInviterByInvitee($userId, $specs);
            $referralUsers['invitedBy'] = null;
            if (!empty($inviter)) {
                $this->logger->info('Inviter data', ['inviter' => $inviter->getUserId()]);
                ContentReplacer::placeholderProfile($inviter, $specs);
                $referralUsers['invitedBy'] = $inviter->getArrayCopy();
            }

            $offset = $args['offset'] ?? 0;
            $limit = $args['limit'] ?? 20;

            $invited = $this->userMapper->getReferralRelations($userId, $specs, $offset, $limit);

            if (!empty($invited)) {
                foreach ($invited as $user) {
                    ContentReplacer::placeholderProfile($user, $specs);
                    $referralUsers['iInvited'][] = $user->getArrayCopy();
                }
            }

            if (empty($referralUsers['invitedBy']) && empty($referralUsers['iInvited'])) {
                return $this::createSuccessResponse(21003, $referralUsers, false);
            }

            $this->logger->info('Returning final referralList response', ['referralUsers' => $referralUsers]);

            return [
                'status' => 'success',
                'ResponseCode' => "11011",
                'counter' => count($referralUsers['iInvited']),
                'affectedRows' => $referralUsers
            ];
        } catch (\Throwable $e) {
            $this->logger->error('Query.resolveReferralList exception', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return $this::respondWithError(41013);
        }
    }

    protected function resolveActionPrices(): ?array
    {
        if (!$this->checkAuthentication()) {
            return $this::respondWithError(60501);
        }

        $this->logger->debug('PoolService.getActionPrices started');

        try {
            $result = $this->poolService->getActionPrices();

            if (
                empty($result) ||
                !isset($result['post_price'], $result['like_price'], $result['dislike_price'], $result['comment_price'])
            ) {
                $this->logger->warning('resolveActionPrices: DB result missing/invalid, falling back to constants');

                $tokenomics = ConstantsConfig::tokenomics();
                $prices     = $tokenomics['ACTION_TOKEN_PRICES'];

                $affectedRows = [
                    'postPrice'    => (float)$prices['post'],
                    'likePrice'    => (float)$prices['like'],
                    'dislikePrice' => (float)$prices['dislike'],
                    'commentPrice' => (float)$prices['comment'],
                ];
            } else {
                $affectedRows = [
                    'postPrice'    => isset($result['post_price']) ? (float)$result['post_price'] : 0.0,
                    'likePrice'    => isset($result['like_price']) ? (float)$result['like_price'] : 0.0,
                    'dislikePrice' => isset($result['dislike_price']) ? (float)$result['dislike_price'] : 0.0,
                    'commentPrice' => isset($result['comment_price']) ? (float)$result['comment_price'] : 0.0,
                ];
            }
            $this->logger->info('resolveActionPrices: Successfully fetched prices', $affectedRows);
            return $this::createSuccessResponse(11304, $affectedRows, false);
        } catch (\Throwable $e) {
            $this->logger->error('Query.resolveActionPrices exception', [
                'message' => $e->getMessage(),
                'trace'   => $e->getTraceAsString(),
            ]);
            return $this::respondWithError(41301);
        }
    }

    private function resolveTokenomics(): array
    {
        $this->logger->debug('Query.getTokenomics started');

        $tokenomics = ConstantsConfig::tokenomics();
        $minting    = ConstantsConfig::minting();

        $prices = $tokenomics['ACTION_TOKEN_PRICES'];
        $gems   = $tokenomics['ACTION_GEMS_RETURNS'];

        $actionTokenPrices = [
            'postPrice'    => (float)$prices['post'],
            'likePrice'    => (float)$prices['like'],
            'dislikePrice' => (float)$prices['dislike'],
            'commentPrice' => (float)$prices['comment'],
        ];

        $actionGemsReturns = [
            'viewGemsReturn'    => (float)$gems['view'],
            'likeGemsReturn'    => (float)$gems['like'],
            'dislikeGemsReturn' => (float)$gems['dislike'],
            'commentGemsReturn' => (float)$gems['comment'],
        ];

        $mintedYesterday = (float)$minting['DAILY_NUMBER_TOKEN'];

        $payload = [
            'status'            => 'success',
            'ResponseCode'      => 11212,
            'actionTokenPrices' => $actionTokenPrices,
            'actionGemsReturns' => $actionGemsReturns,
            'mintingData'       => [
                'tokensMintedYesterday' => $mintedYesterday,
            ],
        ];

        $this->logger->info('Query.getTokenomics finished', ['payload' => $payload]);
        return $payload;
    }
    protected function resolveComments(array $args): array
    {
        if (!$this->checkAuthentication()) {
            return $this::respondWithError(60501);
        }

        if (empty($args)) {
            return $this::respondWithError(30104);
        }

        $validationResult = $this->validateOffsetAndLimit($args);
        if (isset($validationResult['status']) && $validationResult['status'] === 'error') {
            return $validationResult;
        }

        $comments = $this->commentService->fetchByParentId($args);
        if (isset($comments['status']) && $comments['status'] === 'error') {
            return $comments;
        }

        if (empty($comments)) {
            return $this::createSuccessResponse(21606, [], false);
        }

        $results = array_map(fn (CommentAdvanced $comment) => $comment->getArrayCopy(), $comments);

        return $this::createSuccessResponse(11607, $results);
    }

    protected function resolveListComments(array $args): array
    {
        $this->logger->debug('GraphQLSchemaBuilder.resolveListComments started');

        if (!$this->checkAuthentication()) {
            return $this::respondWithError(60501);
        }


        $postId = $args['postid'] ?? null;
        if (empty($postId) || !self::isValidUUID($postId)) {
            return $this::respondWithError(30209);
        }


        $validationResult = $this->validateOffsetAndLimit($args);
        if (isset($validationResult['status']) && $validationResult['status'] === 'error') {
            return $validationResult;
        }


        $contentFilterBy = $args['contentFilterBy'] ?? null;

        $contentFilterCase = ContentFilteringCases::searchById;

        $deletedUserSpec = new DeletedUserSpec(
            $contentFilterCase,
            ContentType::comment
        );
        $systemUserSpec = new SystemUserSpec(
            $contentFilterCase,
            ContentType::comment
        );
        $hiddenContentFilterSpec = new HiddenContentFilterSpec(
            $contentFilterCase,
            $contentFilterBy,
            ContentType::comment,
            $this->currentUserId
        );

        $specs = [
            $deletedUserSpec,
            $systemUserSpec,
            $hiddenContentFilterSpec,
        ];

        $commentOffset = max((int)($args['commentOffset'] ?? 0), 0);
        $commentLimit = min(max((int)($args['commentLimit'] ?? 10), 1), 20);

        try {
            $comments = $this->commentService->fetchAllByPostIdetaild(
                $postId,
                $commentOffset,
                $commentLimit,
                $specs
            );
        } catch (\Throwable $e) {
            $this->logger->error('Failed to fetch comments', [
                'postId' => $postId,
                'error' => $e->getMessage()
            ]);
            return $this::respondWithError(41601);
        }

        // Apply content filtering to each comment
        foreach ($comments as $comment) {
            ContentReplacer::placeholderComments($comment, $specs);
        }

        $results = array_map(
            fn (CommentAdvanced $comment) => $comment->getArrayCopy(),
            $comments
        );

        $this->logger->info('Query.resolveListComments successful', ['commentCount' => count($results)]);

        return [
            'status' => 'success',
            'counter' => count($results),
            'ResponseCode' => empty($results) ? "21601" : "11601",
            'affectedRows' => $results,
        ];
    }

    protected function resolvePostComments(array $args): array
    {
        if (!$this->checkAuthentication()) {
            return $this::respondWithError(60501);
        }

        if (empty($args)) {
            return $this::respondWithError(30104);
        }

        $comments = $this->commentService->fetchAllByPostId($args);
        if (isset($comments['status']) && $comments['status'] === 'error') {
            return $comments;
        }

        if (empty($comments)) {
            return $this::createSuccessResponse(21601, [], false);
        }

        $this->logger->info('Query.resolveTags successful');
        return $this::createSuccessResponse(11601, $comments);
    }

    protected function resolveTags(array $args): ?array
    {
        if (!$this->checkAuthentication()) {
            return $this::respondWithError(60501);
        }

        $validationResult = $this->validateOffsetAndLimit($args);
        if (isset($validationResult['status']) && $validationResult['status'] === 'error') {
            return $validationResult;
        }

        $this->logger->debug('Query.resolveTags started');

        $tags = $this->tagService->fetchAll($args);
        if (isset($tags['status']) && $tags['status'] === 'success') {
            $this->logger->info('Query.resolveTags successful');

            return $tags;
        }

        if (isset($tags['status']) && $tags['status'] === 'error') {
            return $tags;
        }

        return $this::createSuccessResponse(21701);
    }

    protected function resolveTagsearch(array $args): ?array
    {
        if (!$this->checkAuthentication()) {
            return $this::respondWithError(60501);
        }

        $validationResult = $this->validateOffsetAndLimit($args);
        if (isset($validationResult['status']) && $validationResult['status'] === 'error') {
            return $validationResult;
        }

        $this->logger->debug('Query.resolveTagsearch started');
        $data = $this->tagService->loadTag($args);
        if (isset($data['status']) && $data['status'] === 'success') {
            $this->logger->info('Query.resolveTagsearch successful');

            return $data;
        }

        if (isset($data['status']) && $data['status'] === 'error') {
            return $data;
        }

        return $this::createSuccessResponse(21701);
    }

    protected function resolveLiquidity(): ?array
    {
        if (!$this->checkAuthentication()) {
            return $this::respondWithError(60501);
        }

        $this->logger->debug('Query.resolveLiquidity started');

        $results = $this->walletService->loadLiquidityById($this->currentUserId);
        if (isset($results['status']) && $results['status'] === 'success') {
            $this->logger->info('Query.resolveLiquidity successful');
            return $results;
        }

        if (isset($results['status']) && $results['status'] === 'error') {
            return $results;
        }

        $this->logger->error('Query.resolveLiquidity Failed to find liquidity');
        return $this::respondWithError(41201);
    }

    protected function resolveUserInfo(): ?array
    {
        if (!$this->checkAuthentication()) {
            return $this::respondWithError(60501);
        }

        $this->logger->debug('Query.resolveUserInfo started');

        $results = $this->userInfoService->loadInfoById();
        if (isset($results['status']) && $results['status'] === 'success') {
            $this->logger->info('Query.resolveUserInfo successful');

            return $results;
        }

        if (isset($results['status']) && $results['status'] === 'error') {
            return $this::respondWithError($results['ResponseCode']);
        }

        $this->logger->error('Query.resolveUserInfo Failed to find INFO');
        return $this::respondWithError(41001);
    }

    protected function resolveSearchUser(array $args): ?array
    {
        if (!$this->checkAuthentication()) {
            return $this::respondWithError(60501);
        }

        if (empty($args)) {
            return $this::respondWithError(30101);
        }

        $validationResult = $this->validateOffsetAndLimit($args);
        if (isset($validationResult['status']) && $validationResult['status'] === 'error') {
            return $validationResult;
        }

        $username = isset($args['username']) ? trim($args['username']) : null;
        $usernameConfig = ConstantsConfig::user()['USERNAME'];
        $userId = $args['userid'] ?? null;
        $email = $args['email'] ?? null;
        $status = $args['status'] ?? null;
        $verified = $args['verified'] ?? null;
        $ip = $args['ip'] ?? null;

        if (empty($args['username']) && empty($args['userid']) && empty($args['email']) && !isset($args['status']) && !isset($args['verified']) && !isset($args['ip'])) {
            return $this::respondWithError(30102);
        }

        if (!empty($username) && !empty($userId)) {
            return $this::respondWithError(31012);
        }

        if ($userId !== null && !self::isValidUUID($userId)) {
            return $this::respondWithError(30201);
        }

        if ($username !== null && (strlen($username) < $usernameConfig['MIN_LENGTH'] || strlen($username) > $usernameConfig['MAX_LENGTH'])) {
            return $this::respondWithError(30202);
        }

        if ($username !== null && !preg_match('/' . $usernameConfig['PATTERN'] . '/u', $username)) {
            return $this::respondWithError(30202);
        }

        if (!empty($ip) && !filter_var($ip, FILTER_VALIDATE_IP)) {
            return $this::respondWithError(30257);//"The IP '$ip' is not a valid IP address."
        }

        $args['limit'] = min(max((int)($args['limit'] ?? 10), 1), 20);

        $this->logger->debug('Query.resolveSearchUser started');

        if ($this->userRoles === 16) {
            $args['includeDeleted'] = true;
        }

        $contentFilterCase = ContentFilteringCases::searchByMeta;
        if (!empty($userId)) {
            $contentFilterCase = ContentFilteringCases::searchById;
            if ($userId == $this->currentUserId) {

                $contentFilterCase = ContentFilteringCases::myprofile;
            }
            $args['uid'] = $userId;
        }

        $contentFilterBy = $args['contentFilterBy'] ?? null;

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
            $this->currentUserId
        );
        $illegalContentFilterSpec = new IllegalContentFilterSpec(
            $contentFilterCase,
            ContentType::user
        );
        $specs = [
            $illegalContentFilterSpec,
            $systemUserSpec,
            $deletedUserSpec,
            $hiddenContentFilterSpec,
        ];


        try {
            $users = $this->userMapper->fetchAll($this->currentUserId, $args, $specs);
            $usersArray = [];
            foreach ($users as $profile) {
                if ($profile instanceof ProfileReplaceable) {
                    ContentReplacer::placeholderProfile($profile, $specs);
                }
                $usersArray[] = $profile->getArrayCopy();
            }

            if (!empty($usersArray)) {
                $this->logger->info('Query.resolveSearchUser successful', ['userCount' => count($usersArray)]);
                return [
                    'status' => 'success',
                    'counter' => count($usersArray),
                    'ResponseCode' => "11009",
                    'affectedRows' => $usersArray,
                ];
            } else {
                if ($userId) {
                    return self::respondWithError(31007);
                }
                return self::createSuccessResponse(21001);
            }
        } catch (\Throwable $e) {
            return self::respondWithError(41207);
        }
    }

    protected function resolveFollows(array $args): ?array
    {
        if (!$this->checkAuthentication()) {
            return $this::respondWithError(60501);
        }

        $this->logger->debug('Query.resolveFollows started');

        $validation = RequestValidator::validate($args, []);

        if ($validation instanceof ValidatorErrors) {
            return $this::respondWithError(
                $validation->errors[0]
            );
        }

        $results = $this->userService->Follows($validation);


        if ($results instanceof ErrorResponse) {
            return $results->response;
        }

        $this->logger->info('Query.resolveProfile successful');
        return $results;
    }

    protected function resolveProfile(array $args): array
    {
        if (!$this->checkAuthentication()) {
            return $this::respondWithError(60501);
        }

        $this->logger->debug('Query.resolveProfile started');

        $validation = RequestValidator::validate($args);

        if ($validation instanceof ValidatorErrors) {
            return $this::respondWithError(
                $validation->errors[0]
            );
        }

        $result = $this->profileService->profile($validation);

        if ($result instanceof ErrorResponse) {
            return $result->response;
        }

        $this->logger->info('Query.resolveProfile successful');
        return $this::createSuccessResponse(
            11008,
            $result->getArrayCopy(),
            false
        );
    }


    protected function resolveGemsters(array $args): array
    {
        if (!$this->checkAuthentication()) {
            return $this::respondWithError(60501);
        }

        $this->logger->debug('Query.resolveGemsters started');

        $args['dateOffset'] = $args['day'];

        $validation = RequestValidator::validate($args, ['dateOffset']);

        if ($validation instanceof ValidatorErrors) {
            return $this::respondWithError(
                $validation->errors[0]
            );
        }

        $dateYYYYMMDD = DateService::dateOffsetToYYYYMMDD($validation['dateOffset']);

        $result = $this->mintService->distributeTokensFromGems($dateYYYYMMDD);

        if ($result instanceof ErrorResponse) {
            return $result->response;
        }

        $this->logger->info('Query.resolveProfile successful');
        return $result;
    }


    protected function resolveMint(array $args): array
    {
        if (!$this->checkAuthentication()) {
            return $this::respondWithError(60501);
        }

        $this->logger->debug('Query.resolveGemsters started');

        $args['dateYYYYMMDD'] = $args['date'];

        $validation = RequestValidator::validate($args, ['dateYYYYMMDD']);

        if ($validation instanceof ValidatorErrors) {
            return $this::respondWithError(
                $validation->errors[0]
            );
        }

        $dateYYYYMMDD = $validation['dateYYYYMMDD'];

        $result = $this->mintService->distributeTokensFromGems($dateYYYYMMDD);

        if ($result instanceof ErrorResponse) {
            return $result->response;
        }

        $this->logger->info('Query.resolveProfile successful');
        return $result;
    }

    protected function resolveVerifyReferral(array $args): array
    {

        $this->logger->debug('Query.resolveVerifyReferral started');
        $referralString = $args['referralString'];

        if (empty($referralString) || !self::isValidUUID($referralString)) {
            return self::respondWithError(31010); // Invalid referral string
        }

        $result = $this->userService->verifyReferral($referralString);

        return $result;
    }

    protected function resolveFriends(array $args): ?array
    {
        if (!$this->checkAuthentication()) {
            return $this::respondWithError(60501);
        }

        $validation = RequestValidator::validate($args, []);

        if ($validation instanceof ValidatorErrors) {
            return $this::respondWithError(
                $validation->errors[0]
            );
        }

        $this->logger->debug('Query.resolveFriends started');

        $results = $this->userService->getFriends($validation);
        if (isset($results['status']) && $results['status'] === 'success') {
            $this->logger->info('Query.resolveFriends successful');

            return $results;
        }

        if (isset($results['status']) && $results['status'] === 'error') {
            return $this::respondWithError($results['ResponseCode']);
        }

        $this->logger->warning('Query.resolveFriends Users not found');
        return $this::createSuccessResponse(21101);
    }

    protected function resolveAllFriends(array $args): ?array
    {
        if (!$this->checkAuthentication()) {
            return $this::respondWithError(60501);
        }

        $this->logger->debug('Query.resolveAllFriends started');

        $results = $this->userService->getAllFriends($args);
        if (isset($results['status']) && $results['status'] === 'success') {
            $this->logger->info('Query.resolveAllFriends successful');

            return $results;
        }

        if (isset($results['status']) && $results['status'] === 'error') {
            return $this::respondWithError($results['ResponseCode']);
        }

        $this->logger->warning('Query.resolveAllFriends No listFriends found');
        return $this::createSuccessResponse(21101);
    }

    protected function resolveUsers(array $args): ?array
    {
        if (!$this->checkAuthentication()) {
            return $this::respondWithError(60501);
        }

        $validationResult = $this->validateOffsetAndLimit($args);
        if (isset($validationResult['status']) && $validationResult['status'] === 'error') {
            return $validationResult;
        }

        $this->logger->debug('Query.resolveUsers started');

        if ($this->userRoles === 16) {
            $results = $this->userService->fetchAllAdvance($args);
        } else {
            $results = $this->userService->fetchAll($args);
        }

        return $results;
    }

    /**
     * Get transcation history with Filter
     *
     */
    public function transactionsHistory(array $args): array
    {
        $this->logger->debug('GraphQLSchemaBuilder.transactionsHistory started');

        if (!$this->checkAuthentication()) {
            return self::respondWithError(60501);
        }

        $validationResult = $this->validateOffsetAndLimit($args);
        if (isset($validationResult['status']) && $validationResult['status'] === 'error') {
            return $validationResult;
        }

        try {
            return $this->peerTokenService->transactionsHistory($args);
        } catch (\Throwable $e) {
            $this->logger->error("Error in GraphQLSchemaBuilder.transactionsHistory", ['exception' => $e->getMessage()]);
            return ErrorMapper::toResponse($e);
        }

    }

    public function transactionsHistoryItems(array $args): array
    {
        $this->logger->debug('GraphQLSchemaBuilder.transactionsHistory started');

        if (!$this->checkAuthentication()) {
            return self::respondWithError(60501);
        }

        $validation = RequestValidator::validate($args);

        if ($validation instanceof ValidatorErrors) {
            return $this::respondWithError(
                $validation->errors[0]
            );
        }

        try {
            // $validation['transactionCategory'] = TransactionCategory::tryFrom("hahss");
            $entitiesArray = $this->peerTokenService->transactionsHistoryItems($validation);
            $resultArray = array_map(fn (TransactionHistoryItem $item) => $item->getArrayCopy(), $entitiesArray);
            return $this::createSuccessResponse(11215, $resultArray);
        } catch (\Throwable $e) {
            $this->logger->error("Error in GraphQLSchemaBuilder.transactionsHistoryItems", ['exception' => $e->getMessage()]);
            return ErrorMapper::toResponse($e);
        }

    }


    /**
     * Get Post Interaction history with Post and Comment
     *
     */
    public function postInteractions(array $args): array
    {
        $this->logger->debug('GraphQLSchemaBuilder.postInteractions started');

        if (!$this->checkAuthentication()) {
            $this->logger->info("GraphQLSchemaBuilder.postInteractions failed due to authentication");
            return self::respondWithError(60501);
        }

        $validationResult = $this->validateOffsetAndLimit($args);
        if (isset($validationResult['status']) && $validationResult['status'] === 'error') {
            return $validationResult;
        }

        try {
            $results = $this->postService->postInteractions($args);

            return $this::createSuccessResponse(
                (int)$results['ResponseCode'],
                $results['affectedRows'] ?? [],
                false // no counter needed for existing data
            );

        } catch (\Exception $e) {
            $this->logger->error("Error in GraphQLSchemaBuilder.postInteractions", ['exception' => $e->getMessage()]);
            return self::respondWithError(41226);  // Error occurred while retrieving Post, Comment Interactions
        }

    }

    protected function resolvePosts(array $args): ?array
    {
        if (!$this->checkAuthentication()) {
            return $this::respondWithError(60501);
        }

        $validationResult = $this->validateOffsetAndLimit($args);
        if (isset($validationResult['status']) && $validationResult['status'] === 'error') {
            return $validationResult;
        }

        $this->logger->debug('Query.resolvePosts started');

        $posts = $this->postService->findPostser($args);
        if (isset($posts['status']) && $posts['status'] === 'error') {
            return $posts;
        }

        $data = array_map(
            fn (PostAdvanced $post) => $post->getArrayCopy(),
            $posts
        );
        return [
            'status' => 'success',
            'counter' => count($data),
            'ResponseCode' => empty($data) ? "21501" : "11501",
            'affectedRows' => $data,
        ];
    }

    protected function resolveAdvertisementsPosts(array $args): ?array
    {
        if (!$this->checkAuthentication()) {
            return $this->respondWithError(60501);
        }

        $validationResult = $this->validateOffsetAndLimit($args);
        if (isset($validationResult['status']) && $validationResult['status'] === 'error') {
            return $validationResult;
        }

        $this->logger->debug('Query.resolveAdvertisementsPosts started');

        $posts = $this->advertisementService->findAdvertiser($args);
        if (isset($posts['status']) && $posts['status'] === 'error') {
            return $posts;
        }

        $data = array_map(
            function (array $row) {
                $elem = [
                    // PostAdvanced Objekt
                    'post' => $row['post']->getArrayCopy(),
                    // Advertisements Objekt
                    'advertisement' => $row['advertisement']->getArrayCopy()
                ];
                return $elem;
            },
            $posts
        );
        $this->logger->info('findAdvertiser', ['data' => $data]);

        return self::createSuccessResponse(
            empty($data) ? 21501 : 11501,
            $data
        );
    }

    protected function fetchCommentWithoutReplies(CommentAdvanced $comment): ?array
    {
        return $comment->getArrayCopy();
    }

    public function fieldResolver(mixed $source, array $args, mixed $context, ResolveInfo $info): mixed
    {
        $fieldName = $info->fieldName;
        $parentTypeName = $info->parentType->name;

        try {
            if (!isset($this->resolvers[$parentTypeName])) {
                $this->logger->warning("No resolver found for parent type: {$parentTypeName}");
                throw new \RuntimeException("Resolver for type '{$parentTypeName}' is not defined.");
            }

            $resolver = $this->resolvers[$parentTypeName];

            if (is_array($resolver) && array_key_exists($fieldName, $resolver)) {
                $value = $resolver[$fieldName];
            } elseif (is_object($resolver) && isset($resolver->{$fieldName})) {
                $value = $resolver->{$fieldName};
            } else {
                $this->logger->warning("No field resolver found for '{$fieldName}' in '{$parentTypeName}'");
                throw new \RuntimeException("No resolver found for field '{$fieldName}' in type '{$parentTypeName}'.");
            }

            if (is_callable($value)) {
                $refFunc = new \ReflectionFunction($value);
                $params = $refFunc->getParameters();

                if (!empty($params)) {
                    $firstParamType = $params[0]->getType();

                    if ($firstParamType instanceof \ReflectionNamedType
                        && !$firstParamType->isBuiltin()
                        && $firstParamType->getName() !== 'mixed'
                        && !($source instanceof ($firstParamType->getName()))) {

                        throw new \TypeError("Resolver for '{$fieldName}' expected type '{$firstParamType->getName()}', but received " . gettype($source));
                    }
                }

                return $value($source, $args, $context, $info);
            }

            return $value;
        } catch (\TypeError $e) {
            $this->logger->error("Type error in resolver for '{$fieldName}': " . $e->getMessage(), ['args' => $args]);
            throw new \GraphQL\Error\UserError("Type mismatch in resolver for field '{$fieldName}': " . $e->getMessage());
        } catch (\Throwable $e) {
            $this->logger->alert("Unhandled error in resolver for '{$fieldName}': " . $e->getMessage(), ['exception' => (string)$e]);
            throw new \GraphQL\Error\UserError("An unexpected error occurred while resolving field '{$fieldName}'.");
        }
    }

    protected function validateOffsetAndLimit(array $args = []): ?array
    {
        $offset = isset($args['offset']) ? (int)$args['offset'] : null;
        $limit = isset($args['limit']) ? (int)$args['limit'] : null;
        $postOffset = isset($args['postOffset']) ? (int)$args['postOffset'] : null;
        $postLimit = isset($args['postLimit']) ? (int)$args['postLimit'] : null;
        $commentOffset = isset($args['commentOffset']) ? (int)$args['commentOffset'] : null;
        $commentLimit = isset($args['commentLimit']) ? (int)$args['commentLimit'] : null;
        $messageOffset = isset($args['messageOffset']) ? (int)$args['messageOffset'] : null;
        $messageLimit = isset($args['messageLimit']) ? (int)$args['messageLimit'] : null;

        $paging = ConstantsConfig::paging();
        $minOffset = $paging['OFFSET']['MIN'];
        $maxOffset = $paging['OFFSET']['MAX'];
        $minLimit = $paging['LIMIT']['MIN'];
        $maxLimit = $paging['LIMIT']['MAX'];

        if ($offset !== null) {
            if ($offset < $minOffset || $offset > $maxOffset) {
                return $this::respondWithError(30203);
            }
        }

        if ($limit !== null) {
            if ($limit < $minLimit || $limit > $maxLimit) {
                return $this::respondWithError(30204);
            }
        }

        if ($postOffset !== null) {
            if ($postOffset < $minOffset || $postOffset > $maxOffset) {
                return $this::respondWithError(30203);
            }
        }

        if ($postLimit !== null) {
            if ($postLimit < $minLimit || $postLimit > $maxLimit) {
                return $this::respondWithError(30204);
            }
        }

        if ($commentOffset !== null) {
            if ($commentOffset < $minOffset || $commentOffset > $maxOffset) {
                return $this::respondWithError(30215);
            }
        }

        if ($commentLimit !== null) {
            if ($commentLimit < $minLimit || $commentLimit > $maxLimit) {
                return $this::respondWithError(30216);
            }
        }

        if ($messageOffset !== null) {
            if ($messageOffset < $minOffset || $messageOffset > $maxOffset) {
                return $this::respondWithError(30219);
            }
        }

        if ($messageLimit !== null) {
            if ($messageLimit < $minLimit || $messageLimit > $maxLimit) {
                return $this::respondWithError(30220);
            }
        }

        return null;
    }

    protected function checkAuthentication(): bool
    {
        if ($this->currentUserId === null) {
            $this->logger->warning('Unauthorized access attempt');
            return false;
        }
        return true;
    }

    protected function ContactUs(?array $args = []): array
    {
        $this->logger->debug('Query.ContactUs started');

        $ip = filter_var($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0', FILTER_VALIDATE_IP) ?: '0.0.0.0';
        if ($ip === '0.0.0.0') {
            return $this::respondWithError(30257);
        }

        if (!$this->contactusService->checkRateLimit($ip)) {
            return $this::respondWithError(30302);
        }

        if (empty($args)) {
            $this->logger->warning('Mandatory args missing.');
            return $this->respondWithError(30101);
        }

        $email = isset($args['email']) ? trim($args['email']) : null;
        $name = isset($args['name']) ? trim($args['name']) : null;
        $message = isset($args['message']) ? trim($args['message']) : null;
        $args['ip'] = $ip;
        $args['createdat'] = new \DateTime()->format('Y-m-d H:i:s.u');

        if (empty($email) || empty($name) || empty($message)) {
            return $this::respondWithError(30101);
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $this::respondWithError(30224);
        }

        if (strlen($name) < 3 || strlen($name) > 33) {
            return $this::respondWithError(30202);
        }

        if (!preg_match('/^[a-zA-Z0-9_]+$/', $name)) {
            return $this::respondWithError(30202);
        }

        if (grapheme_strlen($message) < 3 || grapheme_strlen($message) > 500) {
            return $this::respondWithError(30103);
        }

        try {
            $contact = new \Fawaz\App\Contactus($args);

            $insertedContact = $this->contactusService->insert($contact);

            if (!$insertedContact) {
                return $this::respondWithError(30401);
            }

            $this->logger->info('Contact successfully created.', ['contact' => $insertedContact->getArrayCopy()]);

            return $this::createSuccessResponse(10401, $insertedContact->getArrayCopy(), false);
        } catch (\Throwable $e) {
            $this->logger->warning('Unexpected error during contact creation', [
                'error' => $e->getMessage(),
                'args' => $args,
            ]);
            return $this::respondWithError(30401);
        }
    }

    protected function verifyAccount(string $userid = ''): array
    {
        if ($userid === '') {
            return $this::respondWithError(30101);
        }

        if (!self::isValidUUID($userid)) {
            return $this::respondWithError(30201);
        }

        $this->logger->debug('Query.verifyAccount started');

        try {
            $user = $this->userService->loadAllUsersById($userid);
            if (!$user) {
                return $this::respondWithError(31007);
            }

            if ($user->getVerified() == 1) {
                $this->logger->info('Account is already verified', ['userid' => $userid]);
                return [
                    'status' => 'error',
                    'ResponseCode' => "30701"
                ];
            }

            if ($this->userMapper->verifyAccount($userid)) {
                $this->userMapper->logLoginData($userid, 'verifyAccount');
                $this->logger->info('Account verified successfully', ['userid' => $userid]);

                return [
                    'status' => 'success',
                    'ResponseCode' => "10701"
                ];
            }

        } catch (\Throwable $e) {
            return $this::respondWithError(40701);
        }

        return $this::respondWithError(40701);
    }

    protected function login(string $email, string $password): array
    {
        $this->logger->debug('Query.login started');

        try {
            if (empty($email) || empty($password)) {
                $this->logger->warning('Email and password are required');
                return $this::respondWithError(30801);
            }

            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $this->logger->warning('Invalid email format');
                return $this::respondWithError(30801);
            }

            $user = $this->userMapper->loadByEmail($email);

            if (!$user) {
                $this->logger->warning('Invalid email or password');
                return $this::respondWithError(30801);
            }

            if (!$user->getVerified()) {
                $this->logger->warning('Account not verified', ['userId' => $user->getUserId()]);
                return $this::respondWithError(60801);
            }

            if ($user->getStatus() == 6) {
                $this->logger->warning('Account has been deleted', ['userId' => $user->getUserId()]);
                return $this::respondWithError(30801);
            }

            if (!$user->verifyPassword($password)) {
                $this->logger->warning('Invalid password', ['userId' => $user->getUserId()]);
                return $this::respondWithError(30801);
            }

            $payload = [
                'iss' => 'peerapp.de',
                'aud' => 'peerapp.de',
                'rol' => $user->getRoles(),
                'uid' => $user->getUserId()
            ];
            $this->logger->info('Query.login See my payload:', ['payload' => $payload]);

            $this->userMapper->update($user);
            $accessToken = $this->tokenService->createAccessToken($payload);
            $refreshToken = $this->tokenService->createRefreshToken($payload);

            $this->userMapper->saveOrUpdateAccessToken($user->getUserId(), $accessToken);
            $this->userMapper->saveOrUpdateRefreshToken($user->getUserId(), $refreshToken);

            $this->userMapper->logLoginData($user->getUserId());

            $this->logger->info('Login successful', ['userId' => $user->getUserId()]);

            return [
                'status' => 'success',
                'ResponseCode' => "10801",
                'accessToken' => $accessToken,
                'refreshToken' => $refreshToken
            ];
        } catch (\Throwable $e) {
            $this->logger->error('Error during login process', [
                'email' => $email,
                'exception' => $e->getMessage(),
                'stackTrace' => $e->getTraceAsString()
            ]);

            return $this::respondWithError(40801);
        }
    }

    protected function refreshToken(string $refreshToken): array
    {
        $this->logger->debug('Query.refreshToken started');

        try {
            if (empty($refreshToken)) {
                return $this::respondWithError(30101);
            }

            $decodedToken = $this->tokenService->validateToken($refreshToken, true);

            if (!$decodedToken) {
                return $this::respondWithError(30901);
            }

            // // Validate that the provided refresh token exists in DB and is not expired
            // if (!$this->userMapper->refreshTokenValidForUser($decodedToken->uid, $refreshToken)) {
            //     $this->logger->warning('Refresh token not found or expired for user', [
            //         'userId' => $decodedToken->uid,
            //     ]);
            //     return $this::respondWithError(30901);
            // }

            $users = $this->userService->loadVisibleUsersById($decodedToken->uid);
            if ($users === false) {
                return $this::respondWithError(30901);
            }

            $payload = [
                'iss' => 'peerapp.de',
                'aud' => 'peerapp.de',
                'rol' => $decodedToken->rol,
                'uid' => $decodedToken->uid
            ];
            $this->logger->info('Query.refreshToken See my payload:', ['payload' => $payload]);

            $accessToken = $this->tokenService->createAccessToken($payload);
            $newRefreshToken = $this->tokenService->createRefreshToken($payload);

            $this->userMapper->saveOrUpdateAccessToken($decodedToken->uid, $accessToken);
            $this->userMapper->saveOrUpdateRefreshToken($decodedToken->uid, $newRefreshToken);

            $this->userMapper->logLoginData($decodedToken->uid, 'refreshToken');

            $this->logger->info('Token refreshed successfully', ['uid' => $decodedToken->uid]);

            return [
                'status' => 'success',
                'ResponseCode' => "10901",
                'accessToken' => $accessToken,
                'refreshToken' => $newRefreshToken
            ];
        } catch (ValidationException $e) {
            $this->logger->warning('Validation Error during refreshToken process', [
                'exception' => $e->getMessage(),
                'stackTrace' => $e->getTraceAsString()
            ]);

            return $this::respondWithError(30901);
        } catch (\Throwable $e) {
            $this->logger->error('Error during refreshToken process', [
                'exception' => $e->getMessage(),
                'stackTrace' => $e->getTraceAsString()
            ]);

            return $this::respondWithError(40901);
        }
    }

    /**
     * Guest List Post
     *
     */
    protected function guestListPost(array $args): ?array
    {
        $this->logger->debug('Query.guestListPost started');

        $posts = $this->postService->getGuestListPost($args);

        if (isset($posts['status']) && $posts['status'] === 'error') {
            return $posts;
        }
        $post = $posts[0];

        if ($post instanceof PostAdvanced) {
            return [
                'status' => 'success',
                'counter' => 1,
                'ResponseCode' => "11501",
                'affectedRows' => $post->getArrayCopy(),
            ];
        } else {
            return $this::respondWithError(40301);
        }
    }

    /**
     * Get Moderation Stats
     */
    protected function moderationStats(): array
    {
        if (!$this->checkAuthentication()) {
            return self::respondWithError(60501);
        }
        $this->logger->debug('GraphQLSchemaBuilder.moderationStats started');

        try {
            return $this->moderationService->getModerationStats();
        } catch (PDOException $e) {
            $this->logger->error("Error in GraphQLSchemaBuilder.moderationStats", ['exception' => $e->getMessage()]);
            return self::respondWithError(40302);
        } catch (\Exception $e) {
            $this->logger->error("Error in GraphQLSchemaBuilder.moderationStats", ['exception' => $e->getMessage()]);
            return self::respondWithError(40301);
        }
    }

    /**
     * Get Moderation Items
     */
    protected function moderationItems(array $args): array
    {
        if (!$this->checkAuthentication()) {
            return self::respondWithError(60501);
        }
        $this->logger->debug('GraphQLSchemaBuilder.moderationItems started');

        try {
            return $this->moderationService->getModerationItems($args);

        } catch (\Exception $e) {
            $this->logger->error("Error getting moderation items: " . $e->getMessage());
            return self::respondWithError(40301);
        }
    }

    /**
     * Perform Moderation Action
     */
    protected function performModerationAction(array $args): array
    {
        if (!$this->checkAuthentication()) {
            return self::respondWithError(60501);
        }
        $this->logger->debug('GraphQLSchemaBuilder.performModerationAction started');

        try {
            return $this->moderationService->performModerationAction($args);

        } catch (\Exception $e) {
            $this->logger->error("Error performing moderation action: " . $e->getMessage());
            return self::respondWithError(40301);
        }
    }


    protected function performShopOrder(array $args): ?array
    {
        if (!$this->checkAuthentication()) {
            return $this::respondWithError(60501);
        }

        $this->logger->debug('Query.performShopOrder started');

        $validation = RequestValidator::validate($args, ['tokenAmount', 'shopItemId']);

        if ($validation instanceof ValidatorErrors) {
            return $this::respondWithError(
                $validation->errors[0]
            );
        }

        $orderValidation = RequestValidator::validate($args['orderDetails'], ['name', 'email', 'addressline1', 'zipcode', 'city','country', 'addressline2']);

        if ($orderValidation instanceof ValidatorErrors) {
            return $this::respondWithError(
                $orderValidation->errors[0]
            );
        }

        if ($args['orderDetails']['shopItemSpecs'] && !empty($args['orderDetails']['shopItemSpecs'])) {
            $orderValidation = RequestValidator::validate($args['orderDetails']['shopItemSpecs'], ['size']);
            if ($orderValidation instanceof ValidatorErrors) {
                return $this::respondWithError(
                    $orderValidation->errors[0]
                );
            }
        }

        $results = $this->peerShopService->performShopOrder($args);


        if ($results instanceof ErrorResponse) {
            return $results->response;
        }

        $this->logger->info('Query.performShopOrder successful');
        return $results;
    }


    public function shopOrderDetails(array $args): array
    {
        $this->logger->debug('GraphQLSchemaBuilder.shopOrderDetails started');

        if (!$this->checkAuthentication()) {
            return self::respondWithError(60501);
        }

        $validation = RequestValidator::validate($args, ['transactionId']);

        if ($validation instanceof ValidatorErrors) {
            return $this::respondWithError(
                $validation->errors[0]
            );
        }

        try {
            return $this->peerShopService->shopOrderDetails($validation);
        } catch (\Throwable $e) {
            $this->logger->error("Error in GraphQLSchemaBuilder.shopOrderDetails", ['exception' => $e->getMessage()]);
            return ErrorMapper::toResponse($e);
        }

    }

    public function generateLeaderboard(array $args): array
    {
        $this->logger->debug('GraphQLSchemaBuilder.generateLeaderboard started');

        if (!$this->checkAuthentication()) {
            return self::respondWithError(60501);
        }

        $validation = RequestValidator::validate($args['leaderboardParams'], ['start_date', 'end_date', 'leaderboardUsersCount']);

        if ($validation instanceof ValidatorErrors) {
            return self::respondWithError(
                $validation->errors[0]
            );
        }

        try {
            return $this->leaderBoardService->generateLeaderboard($args['leaderboardParams']);
        } catch (\Throwable $e) {
            $this->logger->error("Error in GraphQLSchemaBuilder.generateLeaderboard", ['exception' => $e->getMessage()]);
            return ErrorMapper::toResponse($e);
        }
    }

}
