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
use Fawaz\App\PostAdvanced;
use Fawaz\App\PostInfoService;
use Fawaz\App\PostService;
use Fawaz\App\UserInfoService;
use Fawaz\App\UserService;
use Fawaz\App\TagService;
use Fawaz\App\WalletService;
use Fawaz\Database\CommentMapper;
use Fawaz\Database\UserMapper;
use Fawaz\Services\JWTService;
use GraphQL\Executor\Executor;
use GraphQL\Type\Definition\ResolveInfo;
use GraphQL\Type\Schema;
use GraphQL\Utils\BuildSchema;
use Fawaz\Utils\LastGithubPullRequestNumberProvider;
use Fawaz\App\PeerTokenService;
use Fawaz\config\constants\ConstantsConfig;
use Fawaz\Utils\ResponseHelper;
use Fawaz\Utils\PeerLoggerInterface;
use Fawaz\Utils\ResponseMessagesProvider;
use DateTimeImmutable;
use Fawaz\App\ValidationException;
use Fawaz\App\ModerationService;
use Fawaz\App\Status;
use Fawaz\App\Validation\RequestValidator;
use Fawaz\App\Validation\ValidatorErrors;
use Fawaz\App\Profile;
use Fawaz\Utils\ErrorResponse;
use Fawaz\App\Role;


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
        protected PostInfoService $postInfoService,
        protected PostService $postService,
        protected CommentService $commentService,
        protected CommentInfoService $commentInfoService,
        protected WalletService $walletService,
        protected PeerTokenService $peerTokenService,
        protected AdvertisementService $advertisementService,
        protected JWTService $tokenService,
        protected ModerationService $moderationService,
        protected ResponseMessagesProvider $responseMessagesProvider,
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
            } elseif ($this->userRoles === Role::SUPER_MODERATOR) { // Role::SUPER_MODERATOR
                $schema = $moderatorSchema;
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
            $this->logger->critical('Invalid schema', ['schema' => $schema]);
            return $this::respondWithError(40301);
        }

        $schemaSource = $scalars . $enum . $inputs . $types . $response . $schema;

        try {
            $resultSchema = BuildSchema::build($schemaSource);
            Executor::setDefaultFieldResolver([$this, 'fieldResolver']);
            return $resultSchema;
        } catch (\Throwable $e) {
            $this->logger->critical('Invalid schema', ['schema' => $schema, 'exception' => $e->getMessage()]);
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
        $this->moderationService->setCurrentUserId($userid);
        $this->userService->setCurrentUserId($userid);
        $this->profileService->setCurrentUserId($userid);
        $this->userInfoService->setCurrentUserId($userid);
        $this->poolService->setCurrentUserId($userid);
        $this->postService->setCurrentUserId($userid);
        $this->postInfoService->setCurrentUserId($userid);
        $this->commentService->setCurrentUserId($userid);
        $this->commentInfoService->setCurrentUserId($userid);
        $this->dailyFreeService->setCurrentUserId($userid);
        $this->walletService->setCurrentUserId($userid);
        $this->peerTokenService->setCurrentUserId($userid);
        $this->tagService->setCurrentUserId($userid);
        $this->advertisementService->setCurrentUserId($userid);
    }

    protected function getStatusNameByID(int $status): ?string
    {
        $statusCode = $status;
        $statusMap = Status::getMap();

        if (isset($statusMap[$statusCode])) {
            return $statusMap[$statusCode];
        }

        return null;
    }

    public function buildResolvers(): array
    {
        return [
            'Query' => $this->buildQueryResolvers(),
            'Mutation' => $this->buildMutationResolvers(),
            'Subscription' => $this->buildSubscriptionResolvers(),
            'UserPreferencesResponse' => [
                'meta' => function (array $root): array {
                    return [
                        'status' => $root['status'] ?? '',
                        'ResponseCode' => isset($root['ResponseCode']) ? (string)$root['ResponseCode'] : '',
                        'ResponseMessage' => $this->responseMessagesProvider->getMessage($root['ResponseCode'] ?? '') ?? '',
                        'RequestId' => $this->logger->getRequestUid()
                    ];
                },
                'status' => function (array $root): string {
                    $this->logger->debug('Query.DefaultResponse Resolvers');
                    return $root['status'] ?? '';
                },
                'ResponseCode' => function (array $root): string {
                    return $root['ResponseCode'] ?? "";
                },
                'affectedRows' => function (array $root): array {
                    return $root['affectedRows'] ?? [];
                },
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
                'totalScore' => function (array $root): int {
                    return $root['totalScore'] ?? 0;
                },
                'totalDetails' => function (array $root): array {
                    return $root['totalDetails'] ?? [];
                },
            ],
            'TodaysInteractionsDetailsData' => [
                'views' => function (array $root): int {
                    $this->logger->debug('Query.TodaysInteractionsDetailsData Resolvers');
                    return $root['msgid'] ?? 0;
                },
                'likes' => function (array $root): int {
                    return $root['likes'] ?? 0;
                },
                'dislikes' => function (array $root): int {
                    return $root['dislikes'] ?? 0;
                },
                'comments' => function (array $root): int {
                    return $root['comments'] ?? 0;
                },
                'viewsScore' => function (array $root): int {
                    return $root['viewsScore'] ?? 0;
                },
                'likesScore' => function (array $root): int {
                    return $root['likesScore'] ?? 0;
                },
                'dislikesScore' => function (array $root): int {
                    return $root['dislikesScore'] ?? 0;
                },
                'commentsScore' => function (array $root): int {
                    return $root['commentsScore'] ?? 0;
                }
            ],
            'ContactusResponsePayload' => [
                'msgid' => function (array $root): int {
                    $this->logger->debug('Query.ContactusResponsePayload Resolvers');
                    return $root['msgid'] ?? 0;
                },
                'email' => function (array $root): string {
                    return $root['email'] ?? '';
                },
                'name' => function (array $root): string {
                    return $root['name'] ?? '';
                },
                'message' => function (array $root): string {
                    return $root['message'] ?? '';
                },
                'ip' => function (array $root): string {
                    return $root['ip'] ?? '';
                },
                'createdat' => function (array $root): string {
                    return $root['createdat'] ?? '';
                },
            ],
            'HelloResponse' => [
                'currentuserid' => function (array $root): string {
                    $this->logger->debug('Query.HelloResponse Resolvers');
                    return $root['currentuserid'] ?? '';
                },
                'userroles' => function (array $root): int {
                    return $root['userroles'] ?? 0;
                },
                'userRoleString' => function (array $root): string {
                    return  $root['userRoleString'] ?? '';
                },
                'currentVersion' => function (array $root): string {
                    return $root['currentVersion'] ?? '1.2.0';
                },
                'wikiLink' => function (array $root): string {
                    return $root['wikiLink'] ?? 'https://github.com/peer-network/peer_backend/wiki/Backend-Version-Update-1.2.0';
                },
                'lastMergedPullRequestNumber' => function (array $root): string {
                    return $root['lastMergedPullRequestNumber'] ?? '';
                },
                'companyAccountId' => function (array $root): string {
                    return $root['companyAccountId'] ?? '';
                },
            ],
            'RegisterResponse' => [
                'meta' => function (array $root): array {
                    return [
                        'status' => $root['status'] ?? '',
                        'ResponseCode' => isset($root['ResponseCode']) ? (string)$root['ResponseCode'] : '',
                        'ResponseMessage' => $this->responseMessagesProvider->getMessage($root['ResponseCode'] ?? '') ?? '',
                        'RequestId' => $this->logger->getRequestUid(),
                    ];
                },
                'status' => function (array $root): string {
                    $this->logger->debug('Query.RegisterResponse Resolvers');
                    return $root['status'] ?? '';
                },
                'ResponseCode' => function (array $root): string {
                    return $root['ResponseCode'] ?? "";
                },
                'userid' => function (array $root): string {
                    return $root['userid'] ?? '';
                },
            ],
            'ReferralResponse' => [
                'meta' => function (array $root): array {
                    return [
                        'status' => $root['status'] ?? '',
                        'ResponseCode' => isset($root['ResponseCode']) ? (string)$root['ResponseCode'] : '',
                        'ResponseMessage' => $this->responseMessagesProvider->getMessage($root['ResponseCode'] ?? '') ?? '',
                        'RequestId' => $this->logger->getRequestUid(),
                    ];
                },
                'status' => function (array $root): string {
                    $this->logger->debug('Query.ReferralResponse Resolvers');
                    return $root['status'] ?? '';
                },
                'ResponseCode' => function (array $root): string {
                    return $root['ResponseCode'] ?? "";
                },
                'affectedRows' => function (array $root): array {
                    return $root['affectedRows'] ?? [];
                },
            ],
            'ReferralInfo' => [
                'uid' => function (array $root): string {
                    $this->logger->debug('Query.ReferralInfo Resolvers');
                    return $root['uid'] ?? '';
                },
                'username' => function (array $root): string {
                    return $root['username'] ?? '';
                },
                'slug' => function (array $root): int {
                    return $root['slug'] ?? 0;
                },
                'img' => function (array $root): string {
                    return $root['img'] ?? '';
                },
            ],
            'User' => [
                'id' => function (array $root): string {
                    $this->logger->debug('Query.User Resolvers');
                    return $root['uid'] ?? '';
                },
                'situation' => function (array $root): string {
                    $status = $root['status'] ?? 0;
                    return $this->getStatusNameByID($status) ?? '';
                },
                'email' => function (array $root): string {
                    return $root['email'] ?? '';
                },
                'username' => function (array $root): string {
                    return $root['username'] ?? '';
                },
                'password' => function (array $root): string {
                    return $root['password'] ?? '';
                },
                'status' => function (array $root): int {
                    return $root['status'] ?? 0;
                },
                'verified' => function (array $root): int {
                    return $root['verified'] ?? 0;
                },
                'slug' => function (array $root): int {
                    return $root['slug'] ?? 0;
                },
                'roles_mask' => function (array $root): int {
                    return $root['roles_mask'] ?? 0;
                },
                'ip' => function (array $root): string {
                    return $root['ip'] ?? '';
                },
                'img' => function (array $root): string {
                    return $root['img'] ?? '';
                },
                'biography' => function (array $root): string {
                    return $root['biography'] ?? '';
                },
                'liquidity' => function (array $root): float {
                    return $root['liquidity'] ?? 0.0;
                },
                'createdat' => function (array $root): string {
                    return $root['createdat'] ?? '';
                },
                'updatedat' => function (array $root): string {
                    return $root['updatedat'] ?? '';
                },
            ],
            'UserInfoResponse' => [
                'meta' => function (array $root): array {
                    return [
                        'status' => $root['status'] ?? '',
                        'ResponseCode' => isset($root['ResponseCode']) ? (string)$root['ResponseCode'] : '',
                        'ResponseMessage' => $this->responseMessagesProvider->getMessage($root['ResponseCode'] ?? '') ?? '',
                        'RequestId' => $this->logger->getRequestUid(),
                    ];
                },
                'status' => function (array $root): string {
                    $this->logger->debug('Query.UserInfoResponse Resolvers');
                    return $root['status'] ?? '';
                },
                'ResponseCode' => function (array $root): string {
                    return $root['ResponseCode'] ?? "";
                },
                'affectedRows' => function (array $root): array {
                    return $root['affectedRows'] ?? [];
                },
            ],
            'UserListResponse' => [
                'meta' => function (array $root): array {
                    return [
                        'status' => $root['status'] ?? '',
                        'ResponseCode' => isset($root['ResponseCode']) ? (string)$root['ResponseCode'] : '',
                        'ResponseMessage' => $this->responseMessagesProvider->getMessage($root['ResponseCode'] ?? '') ?? '',
                        'RequestId' => $this->logger->getRequestUid(),
                    ];
                },
                'status' => function (array $root): string {
                    $this->logger->debug('Query.UserListResponse Resolvers');
                    return $root['status'] ?? '';
                },
                'counter' => function (array $root): int {
                    return $root['counter'] ?? 0;
                },
                'ResponseCode' => function (array $root): string {
                    return $root['ResponseCode'] ?? "";
                },
                'affectedRows' => function (array $root): array {
                    return $root['affectedRows'] ?? [];
                },
            ],
            'Profile' => [
                'id' => function (array $root): string {
                    $this->logger->debug('Query.User Resolvers');
                    return $root['uid'] ?? '';
                },
                'situation' => function (array $root): string {
                    $status = $root['status'] ?? 0;
                    return $this->getStatusNameByID(0) ?? '';
                },
                'username' => function (array $root): string {
                    return $root['username'] ?? '';
                },
                'status' => function (array $root): int {
                    return $root['status'] ?? 0;
                },
                'slug' => function (array $root): int {
                    return $root['slug'] ?? 0;
                },
                'img' => function (array $root): string {
                    return $root['img'] ?? '';
                },
                'biography' => function (array $root): string {
                    return $root['biography'] ?? '';
                },
                'amountposts' => function (array $root): int {
                    return $root['amountposts'] ?? 0;
                },
                'amounttrending' => function (array $root): int {
                    return $root['amounttrending'] ?? 0;
                },
                'amountfollower' => function (array $root): int {
                    return $root['amountfollower'] ?? 0;
                },
                'amountfollowed' => function (array $root): int {
                    return $root['amountfollowed'] ?? 0;
                },
                'amountfriends' => function (array $root): int {
                    return $root['amountfriends'] ?? 0;
                },
                'amountblocked' => function (array $root): int {
                    return $root['amountblocked'] ?? 0;
                },
                'amountreports' => function (array $root): int {
                    return $root['amountreports'] ?? 0;
                },
                'isfollowed' => function (array $root): bool {
                    return $root['isfollowed'] ?? false;
                },
                'isfollowing' => function (array $root): bool {
                    return $root['isfollowing'] ?? false;
                },
                'imageposts' => function (array $root): array {
                    return [];
                },
                'textposts' => function (array $root): array {
                    return [];
                },
                'videoposts' => function (array $root): array {
                    return [];
                },
                'audioposts' => function (array $root): array {
                    return [];
                },
            ],
            'ProfileInfo' => [
                'meta' => function (array $root): array {
                    return [
                        'status' => $root['status'] ?? '',
                        'ResponseCode' => isset($root['ResponseCode']) ? (string)$root['ResponseCode'] : '',
                        'ResponseMessage' => $this->responseMessagesProvider->getMessage($root['ResponseCode'] ?? '') ?? '',
                        'RequestId' => $this->logger->getRequestUid(),
                    ];
                },
                'status' => function (array $root): string {
                    $this->logger->debug('Query.ProfileInfo Resolvers');
                    return $root['status'] ?? '';
                },
                'ResponseCode' => function (array $root): string {
                    return $root['ResponseCode'] ?? "";
                },
                'affectedRows' => function (array $root): array {
                    return $root['affectedRows'] ?? [];
                },
            ],
            'ProfilePostMedia' => [
                'id' => function (array $root): string {
                    $this->logger->debug('Query.ProfilePostMedia Resolvers');
                    return $root['postid'] ?? '';
                },
                'title' => function (array $root): string {
                    return $root['title'] ?? '';
                },
                'contenttype' => function (array $root): string {
                    return $root['contenttype'] ?? '';
                },
                'media' => function (array $root): string {
                    return $root['media'] ?? '';
                },
                'createdat' => function (array $root): string {
                    return $root['createdat'] ?? '';
                },
            ],
            'ProfileUser' => [
                'id' => function (array $root): string {
                    $this->logger->debug('Query.ProfileUser Resolvers');
                    return $root['uid'] ?? '';
                },
                'username' => function (array $root): string {
                    return $root['username'] ?? '';
                },
                'slug' => function (array $root): int {
                    return $root['slug'] ?? 0;
                },
                'img' => function (array $root): string {
                    return $root['img'] ?? '';
                },
                'isfollowed' => function (array $root): bool {
                    return $root['isfollowed'] ?? false;
                },
                'isfollowing' => function (array $root): bool {
                    return $root['isfollowing'] ?? false;
                },
                'isfriend' => function (array $root): bool {
                    return $root['isfriend'] ?? false;
                },
            ],
            'BasicUserInfo' => [
                'userid' => function (array $root): string {
                    $this->logger->debug('Query.BasicUserInfo Resolvers');
                    return $root['uid'] ?? '';
                },
                'img' => function (array $root): string {
                    return $root['img'] ?? '';
                },
                'username' => function (array $root): string {
                    return $root['username'] ?? '';
                },
                'slug' => function (array $root): int {
                    return $root['slug'] ?? 0;
                },
                'biography' => function (array $root): string {
                    return $root['biography'] ?? '';
                },
                'updatedat' => function (array $root): string {
                    return $root['updatedat'] ?? '';
                },
            ],
            'BlockedUser' => [
                'userid' => function (array $root): string {
                    $this->logger->debug('Query.BlockedUser Resolvers');
                    return $root['userid'] ?? '';
                },
                'img' => function (array $root): string {
                    return $root['img'] ?? '';
                },
                'username' => function (array $root): string {
                    return $root['username'] ?? '';
                },
                'slug' => function (array $root): int {
                    return $root['slug'] ?? 0;
                },
            ],
            'BlockedUsers' => [
                'iBlocked' => function (array $root): array {
                    $this->logger->debug('Query.BlockedUsers Resolvers');
                    return $root['iBlocked'] ?? [];
                },
                'blockedBy' => function (array $root): array {
                    return $root['blockedBy'] ?? [];
                },
            ],
            'BlockedUsersResponse' => [
                'meta' => function (array $root): array {
                    return [
                        'status' => $root['status'] ?? '',
                        'ResponseCode' => isset($root['ResponseCode']) ? (string)$root['ResponseCode'] : '',
                        'ResponseMessage' => $this->responseMessagesProvider->getMessage($root['ResponseCode'] ?? '') ?? '',
                        'RequestId' => $this->logger->getRequestUid(),
                    ];
                },
                'status' => function (array $root): string {
                    $this->logger->debug('Query.BlockedUsersResponse Resolvers');
                    return $root['status'] ?? '';
                },
                'counter' => function (array $root): int {
                    return $root['counter'] ?? 0;
                },
                'ResponseCode' => function (array $root): string {
                    return $root['ResponseCode'] ?? "";
                },
                'affectedRows' => function (array $root): array {
                    return $root['affectedRows'] ?? [];
                },
            ],
            'FollowRelations' => [
                'followers' => function (array $root): array {
                    $this->logger->debug('Query.FollowRelations Resolvers');
                    return $root['followers'] ?? [];
                },
                'following' => function (array $root): array {
                    return $root['following'] ?? [];
                },
            ],
            'FollowRelationsResponse' => [
                'meta' => function (array $root): array {
                    return [
                        'status' => $root['status'] ?? '',
                        'ResponseCode' => isset($root['ResponseCode']) ? (string)$root['ResponseCode'] : '',
                        'ResponseMessage' => $this->responseMessagesProvider->getMessage($root['ResponseCode'] ?? '') ?? '',
                        'RequestId' => $this->logger->getRequestUid(),
                    ];
                },
                'status' => function (array $root): string {
                    $this->logger->debug('Query.FollowRelationsResponse Resolvers');
                    return $root['status'] ?? '';
                },
                'counter' => function (array $root): int {
                    return $root['counter'] ?? 0;
                },
                'ResponseCode' => function (array $root): string {
                    return $root['ResponseCode'] ?? "";
                },
                'affectedRows' => function (array $root): array {
                    return $root['affectedRows'] ?? [];
                },
            ],
            'UserFriendsResponse' => [
                'meta' => function (array $root): array {
                    return [
                        'status' => $root['status'] ?? '',
                        'ResponseCode' => isset($root['ResponseCode']) ? (string)$root['ResponseCode'] : '',
                        'ResponseMessage' => $this->responseMessagesProvider->getMessage($root['ResponseCode'] ?? '') ?? '',
                        'RequestId' => $this->logger->getRequestUid(),
                    ];
                },
                'status' => function (array $root): string {
                    $this->logger->debug('Query.UserFriendsResponse Resolvers');
                    return $root['status'] ?? '';
                },
                'counter' => function (array $root): int {
                    return $root['counter'] ?? 0;
                },
                'ResponseCode' => function (array $root): string {
                    return $root['ResponseCode'] ?? "";
                },
                'affectedRows' => function (array $root): array {
                    return $root['affectedRows'] ?? [];
                },
            ],
            'BasicUserInfoResponse' => [
                'meta' => function (array $root): array {
                    return [
                        'status' => $root['status'] ?? '',
                        'ResponseCode' => isset($root['ResponseCode']) ? (string)$root['ResponseCode'] : '',
                        'ResponseMessage' => $this->responseMessagesProvider->getMessage($root['ResponseCode'] ?? '') ?? '',
                        'RequestId' => $this->logger->getRequestUid(),
                    ];
                },
                'status' => function (array $root): string {
                    $this->logger->debug('Query.BasicUserInfoResponse Resolvers');
                    return $root['status'] ?? '';
                },
                'ResponseCode' => function (array $root): string {
                    return $root['ResponseCode'] ?? "";
                },
                'affectedRows' => function (array $root): array {
                    return $root['affectedRows'] ?? [];
                },
            ],
            'FollowStatusResponse' => [
                'meta' => function (array $root): array {
                    return [
                        'status' => $root['status'] ?? '',
                        'ResponseCode' => isset($root['ResponseCode']) ? (string)$root['ResponseCode'] : '',
                        'ResponseMessage' => $this->responseMessagesProvider->getMessage($root['ResponseCode'] ?? '') ?? '',
                        'RequestId' => $this->logger->getRequestUid(),
                    ];
                },
                'status' => function (array $root): string {
                    $this->logger->debug('Query.FollowStatusResponse Resolvers');
                    return $root['status'] ?? '';
                },
                'ResponseCode' => function (array $root): string {
                    return $root['ResponseCode'] ?? "";
                },
                'isfollowing' => function (array $root): bool {
                    return $root['isfollowing'] ?? false;
                },
            ],
            'Post' => [
                'id' => function (array $root): string {
                    $this->logger->debug('Query.Post Resolvers');
                    return $root['postid'] ?? '';
                },
                'contenttype' => function (array $root): string {
                    return $root['contenttype'] ?? '';
                },
                'title' => function (array $root): string {
                    return $root['title'] ?? '';
                },
                'media' => function (array $root): string {
                    return $root['media'] ?? '';
                },
                'cover' => function (array $root): string {
                    return $root['cover'] ?? '';
                },
                'url' => function (array $root): string {
                    return $root['url'] ?? '';
                },
                'mediadescription' => function (array $root): string {
                    return $root['mediadescription'] ?? '';
                },
                'amountlikes' => function (array $root): int {
                    return $root['amountlikes'] ?? 0;
                },
                'amountdislikes' => function (array $root): int {
                    return $root['amountdislikes'] ?? 0;
                },
                'amountviews' => function (array $root): int {
                    return $root['amountviews'] ?? 0;
                },
                'amountcomments' => function (array $root): int {
                    return $root['amountcomments'] ?? 0;
                },
                'amounttrending' => function (array $root): int {
                    return $root['amounttrending'] ?? 0;
                },
                'amountreports' => function (array $root): int {
                    return $root['amountreports'] ?? 0;
                },
                'isliked' => function (array $root): bool {
                    return $root['isliked'] ?? false;
                },
                'isviewed' => function (array $root): bool {
                    return $root['isviewed'] ?? false;
                },
                'isreported' => function (array $root): bool {
                    return $root['isreported'] ?? false;
                },
                'isdisliked' => function (array $root): bool {
                    return $root['isdisliked'] ?? false;
                },
                'issaved' => function (array $root): bool {
                    return $root['issaved'] ?? false;
                },
                'createdat' => function (array $root): string {
                    return $root['createdat'] ?? '';
                },
                'tags' => function (array $root): array {
                    return $root['tags'] ?? [];
                },
                'user' => function (array $root): array {
                    return $root['user'] ?? [];
                },
                'comments' => function (array $root): array {
                    return $root['comments'] ?? [];
                },
            ],
            'PostInfoResponse' => [
                'meta' => function (array $root): array {
                    return [
                        'status' => $root['status'] ?? '',
                        'ResponseCode' => isset($root['ResponseCode']) ? (string)$root['ResponseCode'] : '',
                        'ResponseMessage' => $this->responseMessagesProvider->getMessage($root['ResponseCode'] ?? '') ?? '',
                        'RequestId' => $this->logger->getRequestUid(),
                    ];
                },
                'status' => function (array $root): string {
                    $this->logger->debug('Query.PostInfoResponse Resolvers');
                    return $root['status'] ?? '';
                },
                'ResponseCode' => function (array $root): string {
                    return $root['ResponseCode'] ?? "";
                },
                'affectedRows' => function (array $root): array {
                    return $root['affectedRows'] ?? [];
                },
            ],
            'PostInfo' => [
                'userid' => function (array $root): string {
                    $this->logger->debug('Query.PostInfo Resolvers');
                    return $root['userid'] ?? '';
                },
                'likes' => function (array $root): int {
                    return $root['likes'] ?? 0;
                },
                'dislikes' => function (array $root): int {
                    return $root['dislikes'] ?? 0;
                },
                'reports' => function (array $root): int {
                    return $root['reports'] ?? 0;
                },
                'views' => function (array $root): int {
                    return $root['views'] ?? 0;
                },
                'saves' => function (array $root): int {
                    return $root['saves'] ?? 0;
                },
                'shares' => function (array $root): int {
                    return $root['shares'] ?? 0;
                },
                'comments' => function (array $root): int {
                    return $root['comments'] ?? 0;
                },
            ],
            'PostListResponse' => [
                'meta' => function (array $root): array {
                    return [
                        'status' => $root['status'] ?? '',
                        'ResponseCode' => isset($root['ResponseCode']) ? (string)$root['ResponseCode'] : '',
                        'ResponseMessage' => $this->responseMessagesProvider->getMessage($root['ResponseCode'] ?? '') ?? '',
                        'RequestId' => $this->logger->getRequestUid(),
                    ];
                },
                'status' => function (array $root): string {
                    $this->logger->debug('Query.PostListResponse Resolvers');
                    return $root['status'] ?? '';
                },
                'counter' => function (array $root): int {
                    return $root['counter'] ?? 0;
                },
                'ResponseCode' => function (array $root): string {
                    return $root['ResponseCode'] ?? "";
                },
                'affectedRows' => function (array $root): array {
                    return $root['affectedRows'] ?? [];
                },
            ],
            'PostResponse' => [
                'meta' => function (array $root): array {
                    return [
                        'status' => $root['status'] ?? '',
                        'ResponseCode' => isset($root['ResponseCode']) ? (string)$root['ResponseCode'] : '',
                        'ResponseMessage' => $this->responseMessagesProvider->getMessage($root['ResponseCode'] ?? '') ?? '',
                        'RequestId' => $this->logger->getRequestUid(),
                    ];
                },
                'status' => function (array $root): string {
                    $this->logger->debug('Query.PostResponse Resolvers');
                    return $root['status'] ?? '';
                },
                'ResponseCode' => function (array $root): string {
                    return $root['ResponseCode'] ?? "";
                },
                'affectedRows' => function (array $root): array {
                    return $root['affectedRows'] ?? [];
                },
            ],
            'AddPostResponse' => [
                'meta' => function (array $root): array {
                    return [
                        'status' => $root['status'] ?? '',
                        'ResponseCode' => isset($root['ResponseCode']) ? (string)$root['ResponseCode'] : '',
                        'ResponseMessage' => $this->responseMessagesProvider->getMessage($root['ResponseCode'] ?? '') ?? '',
                        'RequestId' => $this->logger->getRequestUid(),
                    ];
                },
                'status' => function (array $root): string {
                    $this->logger->debug('Query.AddPostResponse Resolvers');
                    return $root['status'] ?? '';
                },
                'ResponseCode' => function (array $root): string {
                    return $root['ResponseCode'] ?? "";
                },
                'affectedRows' => function (array $root): array {
                    return $root['affectedRows'] ?? [];
                },
            ],
            'Comment' => [
                'commentid' => function (array $root): string {
                    $this->logger->debug('Query.Comment Resolvers');
                    return $root['commentid'] ?? '';
                },
                'userid' => function (array $root): string {
                    return $root['userid'] ?? '';
                },
                'postid' => function (array $root): string {
                    return $root['postid'] ?? '';
                },
                'parentid' => function (array $root): string {
                    return $root['parentid'] ?? '';
                },
                'content' => function (array $root): string {
                    return $root['content'] ?? '';
                },
                'createdat' => function (array $root): string {
                    return $root['createdat'] ?? '';
                },
                'amountlikes' => function (array $root): int {
                    return $root['amountlikes'] ?? 0;
                },
                'amountreplies' => function (array $root): int {
                    return $root['amountreplies'] ?? 0;
                },
                'amountreports' => function (array $root): int {
                    return $root['amountreports'] ?? 0;
                },
                'isliked' => function (array $root): bool {
                    return $root['isliked'] ?? false;
                },
                'user' => function (array $root): array {
                    return $root['user'] ?? [];
                },
            ],
            'CommentInfoResponse' => [
                'meta' => function (array $root): array {
                    return [
                        'status' => $root['status'] ?? '',
                        'ResponseCode' => isset($root['ResponseCode']) ? (string)$root['ResponseCode'] : '',
                        'ResponseMessage' => $this->responseMessagesProvider->getMessage($root['ResponseCode'] ?? '') ?? '',
                        'RequestId' => $this->logger->getRequestUid(),
                    ];
                },
                'status' => function (array $root): string {
                    $this->logger->debug('Query.CommentInfoResponse Resolvers');
                    return $root['status'] ?? '';
                },
                'ResponseCode' => function (array $root): string {
                    return $root['ResponseCode'] ?? "";
                },
                'affectedRows' => function (array $root): array {
                    return $root['affectedRows'] ?? [];
                },
            ],
            'CommentInfo' => [
                'userid' => function (array $root): string {
                    return $root['userid'] ?? '';
                },
                'likes' => function (array $root): int {
                    return $root['likes'] ?? 0;
                },
                'reports' => function (array $root): int {
                    return $root['reports'] ?? 0;
                },
                'comments' => function (array $root): int {
                    return $root['comments'] ?? 0;
                },
            ],
            'CommentResponse' => [
                'meta' => function (array $root): array {
                    return [
                        'status' => $root['status'] ?? '',
                        'ResponseCode' => isset($root['ResponseCode']) ? (string)$root['ResponseCode'] : '',
                        'ResponseMessage' => $this->responseMessagesProvider->getMessage($root['ResponseCode'] ?? '') ?? '',
                        'RequestId' => $this->logger->getRequestUid(),
                    ];
                },
                'status' => function (array $root): string {
                    $this->logger->debug('Query.CommentResponse Resolvers');
                    return $root['status'] ?? '';
                },
                'counter' => function (array $root): int {
                    return $root['counter'] ?? 0;
                },
                'ResponseCode' => function (array $root): string {
                    return $root['ResponseCode'] ?? "";
                },
                'affectedRows' => function (array $root): array {
                    return $root['affectedRows'] ?? [];
                },
            ],
            'AdvCreator' => [
                'advertisementid' => function (array $root): string {
                    $this->logger->debug('Query.AdvCreator Resolvers');
                    return $root['advertisementid'] ?? '';
                },
                'postid' => function (array $root): string {
                    return $root['postid'] ?? '';
                },
                'advertisementtype' => function (array $root): string {
                    return strtoupper($root['status']);
                },
                'startdate' => function (array $root): string {
                    return $root['timestart'] ?? '';
                },
                'enddate' => function (array $root): string {
                    return $root['timeend'] ?? '';
                },
                'createdat' => function (array $root): string {
                    return $root['createdat'] ?? '';
                },
                'user' => function (array $root): array {
                    return $root['user'] ?? [];
                },
            ],
            'ListAdvertisementPostsResponse' => [
                'meta' => function (array $root): array {
                    return [
                        'status' => $root['status'] ?? '',
                        'ResponseCode' => isset($root['ResponseCode']) ? (string)$root['ResponseCode'] : '',
                        'ResponseMessage' => $this->responseMessagesProvider->getMessage($root['ResponseCode'] ?? '') ?? '',
                        'RequestId' => $this->logger->getRequestUid(),
                    ];
                },
                'status' => function (array $root): string {
                    $this->logger->debug('Query.ListAdvertisementPostsResponse Resolvers');
                    return $root['status'] ?? '';
                },
                'counter' => function (array $root): int {
                    return $root['counter'] ?? 0;
                },
                'ResponseCode' => function (array $root): string {
                    return $root['ResponseCode'] ?? '';
                },
                'affectedRows' => function (array $root): array {
                    return $root['affectedRows'] ?? [];
                },
            ],
            'AdvertisementPost' => [
                'post' => function (array $root): array {
                    $this->logger->debug('Query.AdvertisementPost Resolvers');
                    return $root['post'] ?? [];
                },
                'advertisement' => function (array $root): array {
                    return $root['advertisement'] ?? [];
                },
            ],
            'DefaultResponse' => [
                'status' => function (array $root): string {
                    $this->logger->debug('Query.DefaultResponse Resolvers');
                    return $root['status'] ?? '';
                },
                'ResponseCode' => function (array $root): string {
                    return $root['ResponseCode'] ?? "";
                },
                'ResponseMessage' => function (array $root): string {
                    return $this->responseMessagesProvider->getMessage($root['ResponseCode']) ?? '';
                },
                'RequestId' => function (array $root): string {
                    return $this->logger->getRequestUid();
                },
            ],
            'AuthPayload' => [
                'meta' => function (array $root): array {
                    return [
                        'status' => $root['status'] ?? '',
                        'ResponseCode' => isset($root['ResponseCode']) ? (string)$root['ResponseCode'] : '',
                        'ResponseMessage' => $this->responseMessagesProvider->getMessage($root['ResponseCode'] ?? '') ?? '',
                        'RequestId' => $this->logger->getRequestUid(),
                    ];
                },
                'status' => function (array $root): string {
                    $this->logger->debug('Query.AuthPayload Resolvers');
                    return $root['status'] ?? '';
                },
                'ResponseCode' => function (array $root): string {
                    return $root['ResponseCode'] ?? "";
                },
                'accessToken' => function (array $root): string {
                    return $root['accessToken'] ?? '';
                },
                'refreshToken' => function (array $root): string {
                    return $root['refreshToken'] ?? '';
                },
            ],
            'TagSearchResponse' => [
                'meta' => function (array $root): array {
                    return [
                        'status' => $root['status'] ?? '',
                        'ResponseCode' => isset($root['ResponseCode']) ? (string)$root['ResponseCode'] : '',
                        'ResponseMessage' => $this->responseMessagesProvider->getMessage($root['ResponseCode'] ?? '') ?? '',
                        'RequestId' => $this->logger->getRequestUid(),
                    ];
                },
                'status' => function (array $root): string {
                    $this->logger->debug('Query.TagSearchResponse Resolvers');
                    return $root['status'] ?? '';
                },
                'counter' => function (array $root): int {
                    return $root['counter'] ?? 0;
                },
                'ResponseCode' => function (array $root): string {
                    return $root['ResponseCode'] ?? "";
                },
                'affectedRows' => function (array $root): array {
                    return $root['affectedRows'] ?? [];
                },
            ],
            'Tag' => [
                'tagid' => function (array $root): int {
                    $this->logger->debug('Query.Tag Resolvers');
                    return $root['tagid'] ?? 0;
                },
                'name' => function (array $root): string {
                    return $root['name'] ?? '';
                },
            ],
            'GetDailyResponse' => [
                'meta' => function (array $root): array {
                    return [
                        'status' => $root['status'] ?? '',
                        'ResponseCode' => isset($root['ResponseCode']) ? (string)$root['ResponseCode'] : '',
                        'ResponseMessage' => $this->responseMessagesProvider->getMessage($root['ResponseCode'] ?? '') ?? '',
                        'RequestId' => $this->logger->getRequestUid(),
                    ];
                },
                'status' => function (array $root): string {
                    $this->logger->debug('Query.GetDailyResponse Resolvers');
                    return $root['status'] ?? '';
                },
                'ResponseCode' => function (array $root): string {
                    return $root['ResponseCode'] ?? "";
                },
                'affectedRows' => function (array $root): array {
                    return $root['affectedRows'] ?? [];
                },
            ],
            'DailyFreeResponse' => [
                'name' => function (array $root): string {
                    $this->logger->debug('Query.DailyFreeResponse Resolvers');
                    return $root['name'] ?? '';
                },
                'used' => function (array $root): int {
                    return $root['used'] ?? 0;
                },
                'available' => function (array $root): int {
                    return $root['available'] ?? 0;
                },
            ],
            'CurrentLiquidity' => [
                'meta' => function (array $root): array {
                    return [
                        'status' => $root['status'] ?? '',
                        'ResponseCode' => isset($root['ResponseCode']) ? (string)$root['ResponseCode'] : '',
                        'ResponseMessage' => $this->responseMessagesProvider->getMessage($root['ResponseCode'] ?? '') ?? '',
                        'RequestId' => $this->logger->getRequestUid(),
                    ];
                },
                'status' => function (array $root): string {
                    $this->logger->debug('Query.CurrentLiquidity Resolvers');
                    return $root['status'] ?? '';
                },
                'ResponseCode' => function (array $root): string {
                    return $root['ResponseCode'] ?? "";
                },
                'currentliquidity' => function (array $root): float {
                    $this->logger->debug('Query.currentliquidity Resolvers');
                    return $root['currentliquidity'] ?? 0.0;
                },
            ],
            'UserInfo' => [
                'userid' => function (array $root): string {
                    $this->logger->debug('Query.UserInfo Resolvers');
                    return $root['userid'] ?? '';
                },
                'liquidity' => function (array $root): float {
                    return $root['liquidity'] ?? 0.0;
                },
                'isfollowed' => function (array $root): bool {
                    return $root['isfollowed'] ?? false;
                },
                'isfollowing' => function (array $root): bool {
                    return $root['isfollowing'] ?? false;
                },
                'amountreports' => function (array $root): int {
                    return $root['reports'] ?? 0;
                },
                'amountposts' => function (array $root): int {
                    return $root['amountposts'] ?? 0;
                },
                'amountblocked' => function (array $root): int {
                    return $root['amountblocked'] ?? 0;
                },
                'amountfollowed' => function (array $root): int {
                    return $root['amountfollowed'] ?? 0;
                },
                'amountfollower' => function (array $root): int {
                    return $root['amountfollower'] ?? 0;
                },
                'amountfriends' => function (array $root): int {
                    return $root['amountfriends'] ?? 0;
                },
                'invited' => function (array $root): string {
                    return $root['invited'] ?? '';
                },
                'updatedat' => function (array $root): string {
                    return $root['updatedat'] ?? '';
                },
                'userPreferences' => function (array $root): array {
                    return $root['userPreferences'] ?? [];
                },
            ],
            'StandardResponse' => [
                'meta' => function (array $root): array {
                    return [
                        'status' => $root['status'] ?? '',
                        'ResponseCode' => isset($root['ResponseCode']) ? (string)$root['ResponseCode'] : '',
                        'ResponseMessage' => $this->responseMessagesProvider->getMessage($root['ResponseCode'] ?? '') ?? '',
                        'RequestId' => $this->logger->getRequestUid(),
                    ];
                },
                'status' => function (array $root): string {
                    $this->logger->debug('Query.StandardResponse Resolvers');
                    return $root['status'] ?? '';
                },
                'ResponseCode' => function (array $root): string {
                    return $root['ResponseCode'] ?? "";
                },
                'affectedRows' => function (array $root): array {
                    return $root['affectedRows'] ?? [];
                },
            ],
            'ListTodaysInteractionsResponse' => [
                'meta' => function (array $root): array {
                    return [
                        'status' => $root['status'] ?? '',
                        'ResponseCode' => isset($root['ResponseCode']) ? (string)$root['ResponseCode'] : '',
                        'ResponseMessage' => $this->responseMessagesProvider->getMessage($root['ResponseCode'] ?? '') ?? '',
                        'RequestId' => $this->logger->getRequestUid(),
                    ];
                },
                'status' => function (array $root): string {
                    $this->logger->debug('Query.StandardResponse Resolvers');
                    return $root['status'] ?? '';
                },
                'ResponseCode' => function (array $root): string {
                    return $root['ResponseCode'] ?? "";
                },
                'affectedRows' => function (array $root): array {
                    return $root['affectedRows'] ?? [];
                },
            ],
            'PercentBeforeTransactionResponse' => [
                'meta' => function (array $root): array {
                    return [
                        'status' => $root['status'] ?? '',
                        'ResponseCode' => isset($root['ResponseCode']) ? (string)$root['ResponseCode'] : '',
                        'ResponseMessage' => $this->responseMessagesProvider->getMessage($root['ResponseCode'] ?? '') ?? '',
                        'RequestId' => $this->logger->getRequestUid(),
                    ];
                },
                'status' => function (array $root): string {
                    $this->logger->debug('Query.StandardResponse Resolvers');
                    return $root['status'] ?? '';
                },
                'ResponseCode' => function (array $root): string {
                    return $root['ResponseCode'] ?? "";
                },
                'affectedRows' => function (array $root): array {
                    return $root['affectedRows'] ?? [];
                },
            ],
            'PercentBeforeTransactionData' => [
                'inviterId' => function (array $root): string {
                    $this->logger->debug('Query.PercentBeforeTransactionResponse Resolvers');
                    return $root['inviterId'] ?? '';
                },
                'tosend' => function (array $root): float {
                    return $root['tosend'] ?? 0.0;
                },
                'percentTransferred' => function (array $root): float {
                    return $root['percentTransferred'] ?? 0.0;
                },
            ],
            'GemsterResponse' => [
                'meta' => function (array $root): array {
                    return [
                        'status' => $root['status'] ?? '',
                        'ResponseCode' => isset($root['ResponseCode']) ? (string)$root['ResponseCode'] : '',
                        'ResponseMessage' => $this->responseMessagesProvider->getMessage($root['ResponseCode'] ?? '') ?? '',
                        'RequestId' => $this->logger->getRequestUid(),
                    ];
                },
                'status' => function (array $root): string {
                    $this->logger->debug('Query.GemsterResponse Resolvers');
                    return $root['status'] ?? '';
                },
                'ResponseCode' => function (array $root): string {
                    return $root['ResponseCode'] ?? "";
                },
                'affectedRows' => function (array $root): array {
                    return $root['affectedRows'] ?? [];
                },
            ],
            'DailyGemStatusResponse' => [
                'meta' => function (array $root): array {
                    return [
                        'status' => $root['status'] ?? '',
                        'ResponseCode' => isset($root['ResponseCode']) ? (string)$root['ResponseCode'] : '',
                        'ResponseMessage' => $this->responseMessagesProvider->getMessage($root['ResponseCode'] ?? '') ?? '',
                        'RequestId' => $this->logger->getRequestUid(),
                    ];
                },
                'status' => function (array $root): string {
                    $this->logger->debug('Query.DailyGemStatusResponse Resolvers');
                    return $root['status'] ?? '';
                },
                'ResponseCode' => function (array $root): string {
                    return $root['ResponseCode'] ?? "";
                },
                'affectedRows' => function (array $root): array {
                    return $root['affectedRows'] ?? [];
                },
            ],
            'DailyGemsResultsResponse' => [
                'meta' => function (array $root): array {
                    return [
                        'status' => $root['status'] ?? '',
                        'ResponseCode' => isset($root['ResponseCode']) ? (string)$root['ResponseCode'] : '',
                        'ResponseMessage' => $this->responseMessagesProvider->getMessage($root['ResponseCode'] ?? '') ?? '',
                        'RequestId' => $this->logger->getRequestUid(),
                    ];
                },
                'status' => function (array $root): string {
                    $this->logger->debug('Query.DailyGemsResultsResponse Resolvers');
                    return $root['status'] ?? '';
                },
                'ResponseCode' => function (array $root): string {
                    return $root['ResponseCode'] ?? "";
                },
                'affectedRows' => function (array $root): array {
                    return $root['affectedRows'] ?? [];
                },
            ],
            'DailyGemStatusData' => [
                'd0' => function (array $root): int {
                    $this->logger->debug('Query.DailyGemStatusData Resolvers');
                    return $root['d0'] ?? 0;
                },
                'd1' => function (array $root): int {
                    return $root['d1'] ?? 0;
                },
                'd2' => function (array $root): int {
                    return $root['d2'] ?? 0;
                },
                'd3' => function (array $root): int {
                    return $root['d3'] ?? 0;
                },
                'd4' => function (array $root): int {
                    return $root['d4'] ?? 0;
                },
                'd5' => function (array $root): int {
                    return $root['d5'] ?? 0;
                },
                'w0' => function (array $root): int {
                    return $root['q0'] ?? 0;
                },
                'm0' => function (array $root): int {
                    return $root['m0'] ?? 0;
                },
                'y0' => function (array $root): int {
                    return $root['y0'] ?? 0;
                },
            ],
            'DailyGemsResultsData' => [
                'data' => function (array $root): array {
                    $this->logger->debug('Query.DailyGemsResultsData Resolvers');
                    return $root['data'] ?? [];
                },
                'totalGems' => function (array $root): float {
                    return $root['totalGems'] ?? 0.0;
                },
            ],
            'DailyGemsResultsUserData' => [
                'userid' => function (array $root): string {
                    $this->logger->debug('Query.DailyGemsResultsUserData Resolvers');
                    return $root['userid'] ?? '';
                },
                'gems' => function (array $root): float {
                    return $root['gems'] ?? 0.0;
                },
                'pkey' => function (array $root): string {
                    return $root['pkey'] ?? '';
                },
            ],
            'ContactusResponse' => [
                'meta' => function (array $root): array {
                    return [
                        'status' => $root['status'] ?? '',
                        'ResponseCode' => isset($root['ResponseCode']) ? (string)$root['ResponseCode'] : '',
                        'ResponseMessage' => $this->responseMessagesProvider->getMessage($root['ResponseCode'] ?? '') ?? '',
                        'RequestId' => $this->logger->getRequestUid(),
                    ];
                },
                'status' => function (array $root): string {
                    $this->logger->debug('Query.StandardResponse Resolvers');
                    return $root['status'] ?? '';
                },
                'ResponseCode' => function (array $root): string {
                    return $root['ResponseCode'] ?? "";
                },
                'affectedRows' => function (array $root): array {
                    return $root['affectedRows'] ?? [];
                },
            ],
            'GenericResponse' => [
                'meta' => function (array $root): array {
                    return [
                        'status' => $root['status'] ?? '',
                        'ResponseCode' => isset($root['ResponseCode']) ? (string)$root['ResponseCode'] : '',
                        'ResponseMessage' => $this->responseMessagesProvider->getMessage($root['ResponseCode'] ?? '') ?? '',
                        'RequestId' => $this->logger->getRequestUid(),
                    ];
                },
                'status' => function (array $root): string {
                    $this->logger->debug('Query.GenericResponse Resolvers');
                    return $root['status'] ?? '';
                },
                'counter' => function (array $root): int {
                    return $root['counter'] ?? 0;
                },
                'ResponseCode' => function (array $root): string {
                    return $root['ResponseCode'] ?? "";
                },
                'affectedRows' => function (array $root): array {
                    return $root['affectedRows'] ?? [];
                },
            ],
            'GemstersResponse' => [
                'meta' => function (array $root): array {
                    return [
                        'status' => $root['status'] ?? '',
                        'ResponseCode' => isset($root['ResponseCode']) ? (string)$root['ResponseCode'] : '',
                        'ResponseMessage' => $this->responseMessagesProvider->getMessage($root['ResponseCode'] ?? '') ?? '',
                        'RequestId' => $this->logger->getRequestUid(),
                    ];
                },
                'status' => function (array $root): string {
                    $this->logger->debug('Query.GenericResponse Resolvers');
                    return $root['status'] ?? '';
                },
                'counter' => function (array $root): int {
                    return $root['counter'] ?? 0;
                },
                'ResponseCode' => function (array $root): string {
                    return $root['ResponseCode'] ?? "";
                },
                'affectedRows' => function (array $root): array {
                    return $root['affectedRows'] ?? [];
                },
            ],
            'GemstersData' => [
                'winStatus' => function (array $root): array {
                    $this->logger->debug('Query.GemstersData Resolvers');
                    return $root['winStatus'] ?? [];
                },
                'userStatus' => function (array $root): array {
                    return $root['userStatus'] ?? [];
                },
            ],
            'WinStatus' => [
                'totalGems' => function (array $root): float {
                    $this->logger->debug('Query.WinStatus Resolvers');
                    return isset($root['totalGems']) ? (float)$root['totalGems'] : 0.0;
                },
                'gemsintoken' => function (array $root): float {
                    return isset($root['gemsintoken']) ? (float)$root['gemsintoken'] : 0.0;
                },
                'bestatigung' => function (array $root): float {
                    return isset($root['bestatigung']) ? (float)$root['bestatigung'] : 0.0;
                },
            ],
            'GemstersUserStatus' => [
                'userid' => function (array $root): string {
                    $this->logger->debug('Query.GemstersUserStatus Resolvers');
                    return $root['userid'] ?? '';
                },
                'gems' => function (array $root): float {
                    return $root['gems'] ?? 0.0;
                },
                'tokens' => function (array $root): float {
                    return $root['tokens'] ?? 0.0;
                },
                'percentage' => function (array $root): float {
                    return $root['percentage'] ?? 0.0;
                },
                'details' => function (array $root): array {
                    return $root['details'] ?? [];
                }
            ],
            'GemstersUserStatusDetails' => [
                'gemid' => function (array $root): string {
                    $this->logger->debug('Query.GemstersUserStatusDetails Resolvers');
                    return $root['gemid'] ?? '';
                },
                'userid' => function (array $root): string {
                    return $root['userid'] ?? '';
                },
                'postid' => function (array $root): string {
                    return $root['postid'] ?? '';
                },
                'fromid' => function (array $root): string {
                    return $root['fromid'] ?? '';
                },
                'gems' => function (array $root): float {
                    return $root['gems'] ?? 0.0;
                },
                'numbers' => function (array $root): float {
                    return $root['numbers'] ?? 0.0;
                },
                'whereby' => function (array $root): int {
                    return $root['whereby'] ?? 0;
                },
                'createdat' => function (array $root): string {
                    return $root['createdat'] ?? '';
                }
            ],
            'TestingPoolResponse' => [
                'meta' => function (array $root): array {
                    return [
                        'status' => $root['status'] ?? '',
                        'ResponseCode' => isset($root['ResponseCode']) ? (string)$root['ResponseCode'] : '',
                        'ResponseMessage' => $this->responseMessagesProvider->getMessage($root['ResponseCode'] ?? '') ?? '',
                        'RequestId' => $this->logger->getRequestUid(),
                    ];
                },
                'status' => function (array $root): string {
                    $this->logger->debug('Query.GenericResponse Resolvers');
                    return $root['status'] ?? '';
                },
                'counter' => function (array $root): int {
                    return $root['counter'] ?? 0;
                },
                'ResponseCode' => function (array $root): string {
                    return $root['ResponseCode'] ?? "";
                },
                'affectedRows' => function (array $root): array {
                    return $root['affectedRows'] ?? [];
                },
            ],
            'PostCommentsResponse' => [
                'meta' => function (array $root): array {
                    return [
                        'status' => $root['status'] ?? '',
                        'ResponseCode' => isset($root['ResponseCode']) ? (string)$root['ResponseCode'] : '',
                        'ResponseMessage' => $this->responseMessagesProvider->getMessage($root['ResponseCode'] ?? '') ?? '',
                        'RequestId' => $this->logger->getRequestUid(),
                    ];
                },
                'status' => function (array $root): string {
                    $this->logger->debug('Query.PostCommentsResponse Resolvers');
                    return $root['status'] ?? '';
                },
                'counter' => function (array $root): int {
                    return $root['counter'] ?? 0;
                },
                'ResponseCode' => function (array $root): string {
                    return $root['ResponseCode'] ?? "";
                },
                'affectedRows' => function (array $root): array {
                    return $root['affectedRows'] ?? [];
                },
            ],
            'PostCommentsData' => [
                'commentid' => function (array $root): string {
                    $this->logger->debug('Query.PostCommentsData Resolvers');
                    return $root['commentid'] ?? '';
                },
                'userid' => function (array $root): string {
                    return $root['userid'] ?? '';
                },
                'postid' => function (array $root): string {
                    return $root['postid'] ?? '';
                },
                'parentid' => function (array $root): string {
                    return $root['parentid'] ?? '';
                },
                'content' => function (array $root): string {
                    return $root['content'] ?? '';
                },
                'createdat' => function (array $root): string {
                    return $root['createdat'] ?? '';
                },
                'amountlikes' => function (array $root): int {
                    return $root['amountlikes'] ?? 0;
                },
                'isliked' => function (array $root): bool {
                    return $root['isliked'] ?? false;
                },
                'user' => function (array $root): array {
                    return $root['user'] ?? [];
                },
                'subcomments' => function (array $root): array {
                    return $root['subcomments'] ?? [];
                },
            ],
            'PostSubCommentsData' => [
                'commentid' => function (array $root): string {
                    $this->logger->debug('Query.PostSubCommentsData Resolvers');
                    return $root['commentid'] ?? '';
                },
                'userid' => function (array $root): string {
                    return $root['userid'] ?? '';
                },
                'postid' => function (array $root): string {
                    return $root['postid'] ?? '';
                },
                'parentid' => function (array $root): string {
                    return $root['parentid'] ?? '';
                },
                'content' => function (array $root): string {
                    return $root['content'] ?? '';
                },
                'createdat' => function (array $root): string {
                    return $root['createdat'] ?? '';
                },
                'amountlikes' => function (array $root): int {
                    return $root['amountlikes'] ?? 0;
                },
                'amountreplies' => function (array $root): int {
                    return $root['amountreplies'] ?? 0;
                },
                'isliked' => function (array $root): bool {
                    return $root['isliked'] ?? false;
                },
                'user' => function (array $root): array {
                    return $root['user'] ?? [];
                }
            ],
            'LogWins' => [
                'from' => function (array $root): string {
                    $this->logger->debug('Query.UserInfo Resolvers');
                    return $root['from'] ?? '';
                },
                'token' => function (array $root): string {
                    return $root['token'] ?? '';
                },
                'userid' => function (array $root): string {
                    return $root['userid'] ?? '';
                },
                'postid' => function (array $root): string {
                    return $root['postid'] ?? '';
                },
                'action' => function (array $root): string {
                    return $root['action'] ?? '';
                },
                'numbers' => function (array $root): float {
                    return $root['numbers'] ?? 0.0;
                },
                'createdat' => function (array $root): string {
                    return $root['createdat'] ?? '';
                },
            ],
            'UserLogWins' => [
                'meta' => function (array $root): array {
                    return [
                        'status' => $root['status'] ?? '',
                        'ResponseCode' => isset($root['ResponseCode']) ? (string)$root['ResponseCode'] : '',
                        'ResponseMessage' => $this->responseMessagesProvider->getMessage($root['ResponseCode'] ?? '') ?? '',
                        'RequestId' => $this->logger->getRequestUid(),
                    ];
                },
                'status' => function (array $root): string {
                    $this->logger->debug('Query.UserLogWins Resolvers');
                    return $root['status'] ?? '';
                },
                'counter' => function (array $root): int {
                    return $root['counter'] ?? 0;
                },
                'ResponseCode' => function (array $root): string {
                    return $root['ResponseCode'] ?? "";
                },
                'affectedRows' => function (array $root): array {
                    return $root['affectedRows'] ?? [];
                },
            ],
            'AllUserInfo' => [
                'followerid' => function (array $root): string {
                    $this->logger->debug('Query.AllUserInfo Resolvers');
                    return $root['follower'] ?? '';
                },
                'followername' => function (array $root): string {
                    return ($root['followername'] ?? '') . '.' . ($root['followerslug'] ?? '');
                },
                'followedid' => function (array $root): string {
                    return $root['followed'] ?? '';
                },
                'followedname' => function (array $root): string {
                    return ($root['followedname'] ?? '') . '.' . ($root['followedslug'] ?? '');
                },
            ],
            'AllUserFriends' => [
                'meta' => function (array $root): array {
                    return [
                        'status' => $root['status'] ?? '',
                        'ResponseCode' => isset($root['ResponseCode']) ? (string)$root['ResponseCode'] : '',
                        'ResponseMessage' => $this->responseMessagesProvider->getMessage($root['ResponseCode'] ?? '') ?? '',
                        'RequestId' => $this->logger->getRequestUid(),
                    ];
                },
                'status' => function (array $root): string {
                    $this->logger->debug('Query.AllUserFriends Resolvers');
                    return $root['status'] ?? '';
                },
                'counter' => function (array $root): int {
                    return $root['counter'] ?? 0;
                },
                'ResponseCode' => function (array $root): string {
                    return $root['ResponseCode'] ?? "";
                },
                'affectedRows' => function (array $root): array {
                    return $root['affectedRows'] ?? [];
                },
            ],
            'ReferralInfoResponse' => [
                'meta' => function (array $root): array {
                    return [
                        'status' => $root['status'] ?? '',
                        'ResponseCode' => isset($root['ResponseCode']) ? (string)$root['ResponseCode'] : '',
                        'ResponseMessage' => $this->responseMessagesProvider->getMessage($root['ResponseCode'] ?? '') ?? '',
                        'RequestId' => $this->logger->getRequestUid(),
                    ];
                },
                'status' => function (array $root): string {
                    $this->logger->debug('Query.ReferralInfoResponse Resolvers');
                    return $root['status'] ?? '';
                },
                'ResponseCode' => function (array $root): string {
                    return $root['ResponseCode'] ?? "";
                },
                'referralUuid' => function (array $root): string {
                    return $root['referralUuid'] ?? '';
                },
                'referralLink' => function (array $root): string {
                    return $root['referralLink'] ?? '';
                },
            ],
            'ReferralListResponse' => [
                'meta' => function (array $root): array {
                    return [
                        'status' => $root['status'] ?? '',
                        'ResponseCode' => isset($root['ResponseCode']) ? (string)$root['ResponseCode'] : '',
                        'ResponseMessage' => $this->responseMessagesProvider->getMessage($root['ResponseCode'] ?? '') ?? '',
                        'RequestId' => $this->logger->getRequestUid(),
                    ];
                },
                'status' => function (array $root): string {
                    $this->logger->debug('Query.ReferralListResponse Resolvers');
                    return $root['status'] ?? '';
                },
                'counter' => function (array $root): int {
                    return $root['counter'] ?? 0;
                },
                'ResponseCode' => function (array $root): string {
                    return $root['ResponseCode'] ?? "";
                },
                'affectedRows' => function (array $root): array {
                    return $root['affectedRows'] ?? [];
                },
            ],
            'ReferralUsers' => [
                'invitedBy' => function (array $root): ?array {
                    return $root['invitedBy'] ?? null;
                },
                'iInvited' => function (array $root): array {
                    return $root['iInvited'] ?? [];
                },
            ],
            'GetActionPricesResponse' => [
                'meta' => function (array $root): array {
                    return [
                        'status' => $root['status'] ?? '',
                        'ResponseCode' => isset($root['ResponseCode']) ? (string)$root['ResponseCode'] : '',
                        'ResponseMessage' => $this->responseMessagesProvider->getMessage($root['ResponseCode'] ?? '') ?? '',
                        'RequestId' => $this->logger->getRequestUid(),
                    ];
                },
                'status' => function (array $root): string {
                    $this->logger->debug('Query.GetActionPricesResponse Resolvers');
                    return $root['status'] ?? '';
                },
                'ResponseCode' => function (array $root): string {
                    return $root['ResponseCode'] ?? "";
                },
                'affectedRows' => function (array $root): ?array {
                    return $root['affectedRows'] ?? null;
                },
            ],
            'ActionPriceResult' => [
                'postPrice' => function (array $root): float {
                    return (float) ($root['postPrice'] ?? 0);
                },
                'likePrice' => function (array $root): float {
                    return (float) ($root['likePrice'] ?? 0);
                },
                'dislikePrice' => function (array $root): float {
                    return (float) ($root['dislikePrice'] ?? 0);
                },
                'commentPrice' => function (array $root): float {
                    return (float) ($root['commentPrice'] ?? 0);
                },
            ],
            'ActionGemsReturns' => [
                'viewGemsReturn' => function (array $root): float {
                    return (float)($root['viewGemsReturn'] ?? 0.0);
                },
                'likeGemsReturn' => function (array $root): float {
                    return (float)($root['likeGemsReturn'] ?? 0.0);
                },
                'dislikeGemsReturn' => function (array $root): float {
                    return (float)($root['dislikeGemsReturn'] ?? 0.0);
                },
                'commentGemsReturn' => function (array $root): float {
                    return (float)($root['commentGemsReturn'] ?? 0.0);
                },
            ],
            'MintingData' => [
                'tokensMintedYesterday' => function (array $root): float {
                    return (float)($root['tokensMintedYesterday'] ?? 0.0);
                },
            ],
            'TokenomicsResponse' => [
                'meta' => function (array $root): array {
                    return [
                        'status' => $root['status'] ?? '',
                        'ResponseCode' => isset($root['ResponseCode']) ? (string)$root['ResponseCode'] : '',
                        'ResponseMessage' => $this->responseMessagesProvider->getMessage($root['ResponseCode'] ?? '') ?? '',
                        'RequestId' => $this->logger->getRequestUid(),
                    ];
                },
                'status' => function (array $root): string {
                    $this->logger->debug('Query.TokenomicsResponse Resolvers');
                    return $root['status'] ?? '';
                },
                'ResponseCode' => function (array $root): int {
                    return $root['ResponseCode'] ?? 0;
                },
                'actionTokenPrices' => function (array $root): array {
                    return $root['actionTokenPrices'] ?? [];
                },
                'actionGemsReturns' => function (array $root): array {
                    return $root['actionGemsReturns'] ?? [];
                },
                'mintingData' => function (array $root): array {
                    return $root['mintingData'] ?? [];
                },
            ],
            'ResetPasswordRequestResponse' => [
                'meta' => function (array $root): array {
                    return [
                        'status' => $root['status'] ?? '',
                        'ResponseCode' => isset($root['ResponseCode']) ? (string)$root['ResponseCode'] : '',
                        'ResponseMessage' => $this->responseMessagesProvider->getMessage($root['ResponseCode'] ?? '') ?? '',
                        'RequestId' => $this->logger->getRequestUid(),
                    ];
                },
                'status' => function (array $root): string {
                    $this->logger->debug('Query.ResetPasswordRequestResponse Resolvers');
                    return $root['status'] ?? '';
                },
                'ResponseCode' => function (array $root): string {
                    return $root['ResponseCode'] ?? "";
                },
                'nextAttemptAt' => function (array $root): string {
                    return $root['nextAttemptAt'] ?? '';
                },
            ],
            'PostEligibilityResponse' => [
                'meta' => function (array $root): array {
                    return [
                        'status' => $root['status'] ?? '',
                        'ResponseCode' => isset($root['ResponseCode']) ? (string)$root['ResponseCode'] : '',
                        'ResponseMessage' => $this->responseMessagesProvider->getMessage($root['ResponseCode'] ?? '') ?? '',
                        'RequestId' => $this->logger->getRequestUid(),
                    ];
                },
                'status' => function (array $root): string {
                    $this->logger->debug('Query.PostEligibilityResponse Resolvers');
                    return $root['status'] ?? '';
                },
                'ResponseCode' => function (array $root): string {
                    return  isset($root['ResponseCode']) ? (string) $root['ResponseCode'] : '';
                },
                'eligibilityToken' => function (array $root): string {
                    return $root['eligibilityToken'] ?? '';
                }
            ],
             'TransactionResponse' => [
                'meta' => function (array $root): array {
                    return [
                        'status' => $root['status'] ?? '',
                        'ResponseCode' => isset($root['ResponseCode']) ? (string)$root['ResponseCode'] : '',
                        'ResponseMessage' => $this->responseMessagesProvider->getMessage($root['ResponseCode'] ?? '') ?? '',
                        'RequestId' => $this->logger->getRequestUid(),
                    ];
                },
                'status' => function (array $root): string {
                    $this->logger->debug('Query.TransactionResponse Resolvers');
                    return $root['status'] ?? '';
                },
                'ResponseCode' => function (array $root): string {
                    return  isset($root['ResponseCode']) ? (string) $root['ResponseCode'] : '';
                },
                'affectedRows' => function (array $root): array {
                    return $root['affectedRows'] ?? [];
                },
            ],
            'TransferTokenResponse' => [
                'meta' => function (array $root): array {
                    return [
                        'status' => $root['status'] ?? '',
                        'ResponseCode' => isset($root['ResponseCode']) ? (string)$root['ResponseCode'] : '',
                        'ResponseMessage' => $this->responseMessagesProvider->getMessage($root['ResponseCode'] ?? '') ?? '',
                        'RequestId' => $this->logger->getRequestUid(),
                    ];
                },
                'status' => function (array $root): string {
                    $this->logger->debug('Query.TransferTokenResponse Resolvers');
                    return $root['status'] ?? '';
                },
                'ResponseCode' => function (array $root): string {
                    return  isset($root['ResponseCode']) ? (string) $root['ResponseCode'] : '';
                },
                'affectedRows' => function (array $root): array {
                    return $root['affectedRows'] ?? [];
                },
            ],
            'TransferToken' => [
                'tokenSend' => function (array $root): float {
                    return $root['tokenSend'] ?? 0.0;
                },
                'tokensSubstractedFromWallet' => function (array $root): float {
                    return $root['tokensSubstractedFromWallet'] ?? 0.0;
                },
                'createdat' => function (array $root): string {
                    return $root['createdat'] ?? '';
                },
            ],
            'Transaction' => [
                'transactionid' => function (array $root): string {
                    return $root['transactionid'] ?? '';
                },
                'operationid' => function (array $root): string {
                    return $root['operationid'] ?? '';
                },
                'transactiontype' => function (array $root): string {
                    return $root['transactiontype'] ?? '';
                },
                'senderid' => function (array $root): string {
                    return $root['senderid'] ?? '';
                },
                'recipientid' => function (array $root): string {
                    return $root['recipientid'] ?? '';
                },
                'tokenamount' => function (array $root): float {
                    return $root['tokenamount'] ?? 0.0;
                },
                'transferaction' => function (array $root): string {
                    return $root['transferaction'] ?? '';
                },
                'message' => function (array $root): string {
                    return $root['message'] ?? '';
                },
                'createdat' => function (array $root): string {
                    return $root['createdat'] ?? '';
                },
            ],
            'PostInteractionResponse' => [
                'meta' => function (array $root): array {
                    return [
                        'status' => $root['status'] ?? '',
                        'ResponseCode' => isset($root['ResponseCode']) ? (string)$root['ResponseCode'] : '',
                        'ResponseMessage' => $this->responseMessagesProvider->getMessage($root['ResponseCode'] ?? '') ?? '',
                        'RequestId' => $this->logger->getRequestUid(),
                    ];
                },
                'status' => function (array $root): string {
                    $this->logger->debug('Query.PostInteractionResponse Resolvers');
                    return $root['status'] ?? '';
                },
                'ResponseCode' => function (array $root): string {
                    return $root['ResponseCode'] ?? "";
                },
                'affectedRows' => function (array $root): array {
                    return $root['affectedRows'] ?? [];
                },
            ],
            'ListAdvertisementData' => [
                'status' => function (array $root): string {
                    $this->logger->debug('Query.ListAdvertisementData Resolvers');
                    return $root['status'] ?? '';
                },
                'ResponseCode' => function (array $root): string {
                    return $root['ResponseCode'] ?? '';
                },
                'affectedRows' => function (array $root): ?array {
                    return $root['affectedRows'] ?? null;
                },
            ],
            'AdvertisementRow' => [
                'id' => function (array $root): string {
                    $this->logger->debug('Query.AdvertisementRow Resolvers');
                    return $root['advertisementid'] ?? '';
                },
                'createdAt' => function (array $root): string {
                    return $root['createdat'] ?? '';
                },
                'type' => function (array $root): string {
                    return strtoupper($root['status']);
                },
                'timeframeStart' => function (array $root): string {
                    return $root['timestart'] ?? '';
                },
                'timeframeEnd' => function (array $root): string {
                    return $root['timeend'] ?? '';
                },
                'totalTokenCost' => function (array $root): float {
                    return $root['tokencost'] ?? 0.0;
                },
                'totalEuroCost' => function (array $root): float {
                    return $root['eurocost'] ?? 0.0;
                },
            ],
            'ListedAdvertisementData' => [
                'status' => function (array $root): string {
                    $this->logger->debug('Query.ListedAdvertisementData Resolvers');
                    return $root['status'] ?? '';
                },
                'ResponseCode' => function (array $root): string {
                    return $root['ResponseCode'] ?? '';
                },
                'affectedRows' => function (array $root): ?array {
                    return $root['affectedRows'] ?? null;
                },
            ],
            'Advertisement' => [
                'id' => function (array $root): string {
                    $this->logger->debug('Query.Advertisement Resolvers');
                    return $root['advertisementid'] ?? '';
                },
                'creatorId' => function (array $root): string {
                    return $root['userid'] ?? '';
                },
                'postId' => function (array $root): string {
                    return $root['postid'] ?? '';
                },
                'type' => function (array $root): string {
                    return strtoupper($root['status']);
                },
                'timeframeStart' => function (array $root): string {
                    return $root['timestart'] ?? '';
                },
                'timeframeEnd' => function (array $root): string {
                    return $root['timeend'] ?? '';
                },
                'totalTokenCost' => function (array $root): float {
                    return $root['tokencost'] ?? 0.0;
                },
                'totalEuroCost' => function (array $root): float {
                    return $root['eurocost'] ?? 0.0;
                },
                'gemsEarned' => function (array $root): float {
                    return $root['gemsearned'] ?? 0.0;
                },
                'amountLikes' => function (array $root): int {
                    return $root['amountlikes'] ?? 0;
                },
                'amountViews' => function (array $root): int {
                    return $root['amountviews'] ?? 0;
                },
                'amountComments' => function (array $root): int {
                    return $root['amountcomments'] ?? 0;
                },
                'amountDislikes' => function (array $root): int {
                    return $root['amountdislikes'] ?? 0;
                },
                'amountReports' => function (array $root): int {
                    return $root['amountreports'] ?? 0;
                },
                'createdAt' => function (array $root): string {
                    return $root['createdat'] ?? '';
                },
                'user' => function (array $root): array { // neu
                    return $root['user'] ?? [];
                },
                'post' => function (array $root): array { // neu
                    return $root['post'] ?? [];
                },
            ],
            'TotalAdvertisementHistoryStats' => [
                'tokenSpent' => function (array $root): float {
                    $this->logger->debug('Query.TotalAdvertisementHistoryStats Resolvers');
                    return $root['tokenSpent'] ?? 0.0;
                },
                'euroSpent' => function (array $root): float {
                    return $root['euroSpent'] ?? 0.0;
                },
                'amountAds' => function (array $root): int {
                    return $root['amountAds'] ?? 0;
                },
                'gemsEarned' => function (array $root): float {
                    return $root['gemsEarned'] ?? 0.0;
                },
                'amountLikes' => function (array $root): int {
                    return $root['amountLikes'] ?? 0;
                },
                'amountViews' => function (array $root): int {
                    return $root['amountViews'] ?? 0;
                },
                'amountComments' => function (array $root): int {
                    return $root['amountComments'] ?? 0;
                },
                'amountDislikes' => function (array $root): int {
                    return $root['amountDislikes'] ?? 0;
                },
                'amountReports' => function (array $root): int {
                    return $root['amountReports'] ?? 0;
                },
            ],
            'AdvertisementHistoryResult' => [
                'stats' => function (array $root): array {
                    $this->logger->debug('Query.AdvertisementHistoryResult Resolvers');
                    return $root['stats'] ?? [];
                },
                'advertisements' => function (array $root): array {
                    return $root['advertisements'] ?? [];
                },
            ],
            'ModerationStatsResponse' => [
                'status' => function (array $root): string {
                    $this->logger->info('Query.ModerationStatsResponse Resolvers');
                    return $root['status'] ?? '';
                },
                'ResponseCode' => function (array $root): string {
                    return $root['ResponseCode'] ?? '';
                },
                'affectedRows' => function (array $root): ?array {
                    return $root['affectedRows'] ?? null;
                },
                'meta' => function (array $root): array {
                    return [
                        'status' => $root['status'] ?? '',
                        'ResponseCode' => isset($root['ResponseCode']) ? (string)$root['ResponseCode'] : '',
                        'ResponseMessage' => $this->responseMessagesProvider->getMessage($root['ResponseCode'] ?? '') ?? '',
                        'RequestId' => $this->logger->getRequestUid(),
                    ];
                },
            ],
            'ModerationStats' => [
                'AmountAwaitingReview' => function (array $root): int {
                    return $root['AmountAwaitingReview'] ?? 0;
                },
                'AmountHidden' => function (array $root): int {
                    return $root['AmountHidden'] ?? 0;
                },
                'AmountRestored' => function (array $root): int {
                    return $root['AmountRestored'] ?? 0;
                },
                'AmountIllegal' => function (array $root): int {
                    return $root['AmountIllegal'] ?? 0;
                }
            ],
            'ModerationItemListResponse' => [
                'status' => function (array $root): string {
                    $this->logger->info('Query.ModerationItemListResponse Resolvers');
                    return $root['status'] ?? '';
                },
                'ResponseCode' => function (array $root): string {
                    return $root['ResponseCode'] ?? '';
                },
                'affectedRows' => function (array $root): ?array {
                    return $root['affectedRows'] ?? null;
                },
                'meta' => function (array $root): array {
                    return [
                        'status' => $root['status'] ?? '',
                        'ResponseCode' => isset($root['ResponseCode']) ? (string)$root['ResponseCode'] : '',
                        'ResponseMessage' => $this->responseMessagesProvider->getMessage($root['ResponseCode'] ?? '') ?? '',
                        'RequestId' => $this->logger->getRequestUid(),
                    ];
                },
            ],
            'ModerationItem' => [
                'targetContentId' => function (array $root): string {
                    return $root['uid'] ?? '';
                },
                'targettype' => function (array $root): string {
                    return $root['targettype'] ?? '';
                },
                'status' => function (array $root): string {
                    return $root['status'] ?? '';
                },
                'reportscount' => function (array $root): int {
                    return $root['reportscount'] ?? 1;
                },
                'targetcontent' => function (array $root): array {
                    return $root['targetcontent'] ?? [];
                },
                'reporters' => function (array $root): array {
                    return $root['reporters'] ?? [];
                },
                'createdat' => function (array $root): string {
                    return $root['createdat'] ?? '';
                },
            ],
            'TargetContent' => [
                'post' => function (array|null $root): ?array {
                    return $root['post'] ?? null;
                },
                'comment' => function (array|null $root): ?array {
                    return $root['comment'] ?? null;
                },
                'user' => function (array|null $root): ?array {
                    return $root['user'] ?? null;
                },
            ],
        ];
    }

    protected function buildSubscriptionResolvers(): array {
        return [];
    }
    protected function buildQueryResolvers(): array
    {

        return [
            'hello' => fn (mixed $root, array $args, mixed $context) => $this->resolveHello($root, $args, $context),
            'searchUser' => fn (mixed $root, array $args) => $this->resolveSearchUser($args),
            'searchUserAdmin' => fn (mixed $root, array $args) => $this->resolveSearchUser($args),
            'listUsersV2' => fn (mixed $root, array $args) => $this->resolveListUsersV2($args),
            'listUsersAdminV2' => fn (mixed $root, array $args) => $this->resolveListUsersV2($args),
            'listUsers' => fn (mixed $root, array $args) => $this->resolveUsers($args),
            'getProfile' => fn (mixed $root, array $args) => $this->resolveProfile($args),
            'listFollowRelations' => fn (mixed $root, array $args) => $this->resolveFollows($args),
            'listFriends' => fn (mixed $root, array $args) => $this->resolveFriends($args),
            'listPosts' => fn (mixed $root, array $args) => $this->resolvePosts($args),
            'guestListPost' => fn (mixed $root, array $args) => $this->guestListPost($args),
            'listAdvertisementPosts' => fn (mixed $root, array $args) => $this->resolveAdvertisementsPosts($args),
            'listChildComments' => fn (mixed $root, array $args) => $this->resolveComments($args),
            'listTags' => fn (mixed $root, array $args) => $this->resolveTags($args),
            'searchTags' => fn (mixed $root, array $args) => $this->resolveTagsearch($args),
            'getDailyFreeStatus' => fn (mixed $root, array $args) => $this->dailyFreeService->getUserDailyAvailability($this->currentUserId),
            'gemster' => fn (mixed $root, array $args) => $this->walletService->callGemster(),
            'balance' => fn (mixed $root, array $args) => $this->resolveLiquidity(),
            'getUserInfo' => fn (mixed $root, array $args) => $this->resolveUserInfo(),
            'listWinLogs' => fn (mixed $root, array $args) => $this->resolveFetchWinsLog($args),
            'listPaymentLogs' => fn (mixed $root, array $args) => $this->resolveFetchPaysLog($args),
            'listBlockedUsers' => fn (mixed $root, array $args) => $this->resolveBlocklist($args),
            'listTodaysInteractions' => fn (mixed $root, array $args) => $this->walletService->callUserMove(),
            'allfriends' => fn (mixed $root, array $args) => $this->resolveAllFriends($args),
            'postcomments' => fn (mixed $root, array $args) => $this->resolvePostComments($args),
            'dailygemstatus' => fn (mixed $root, array $args) => $this->poolService->callGemster(),
            'dailygemsresults' => fn (mixed $root, array $args) => $this->poolService->callGemsters($args['day']),
            'getReferralInfo' => fn (mixed $root, array $args) => $this->resolveReferralInfo(),
            'referralList' => fn (mixed $root, array $args) => $this->resolveReferralList($args),
            'getActionPrices' => fn (mixed $root, array $args) => $this->resolveActionPrices(),
            'postEligibility' => fn (mixed $root, array $args) => $this->postService->postEligibility(),
            'getTransactionHistory' => fn (mixed $root, array $args) => $this->transactionsHistory($args),
            'postInteractions' => fn (mixed $root, array $args) => $this->postInteractions($args),
            'advertisementHistory' => fn (mixed $root, array $args) => $this->resolveAdvertisementHistory($args),
            'getTokenomics' => fn (mixed $root, array $args) => $this->resolveTokenomics(),
            'moderationStats' => fn (mixed $root, array $args) => $this->moderationService->getModerationStats(),
            'moderationItems' => fn (mixed $root, array $args) => $this->moderationService->getModerationItems($args),
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
            'createComment' => fn (mixed $root, array $args) => $this->resolveActionPost($args),
            'createPost' => fn (mixed $root, array $args) => $this->resolveActionPost($args),
            'resolvePostAction' => fn (mixed $root, array $args) => $this->resolveActionPost($args),
            'resolveTransfer' => fn (mixed $root, array $args) => $this->peerTokenService->transferToken($args),
            'resolveTransferV2' => fn (mixed $root, array $args) => $this->peerTokenService->transferToken($args),
            'globalwins' => fn (mixed $root, array $args) => $this->walletService->callGlobalWins(),
            'gemsters' => fn (mixed $root, array $args) => $this->walletService->callGemsters($args['day']),
            'advertisePostBasic' => fn (mixed $root, array $args) => $this->resolveAdvertisePost($args),
            'advertisePostPinned' => fn (mixed $root, array $args) => $this->resolveAdvertisePost($args),
            'performModeration' => fn (mixed $root, array $args) => $this->moderationService->performModerationAction($args),
        ];
    }

    protected function resolveHello(mixed $root, array $args, mixed $context): array
    {
        $this->logger->debug('Query.hello started', ['args' => $args]);

        $lastMergedPullRequestNumber = LastGithubPullRequestNumberProvider::getValue();

        /**
         * Map Role Mask 
         */
        if(Role::mapRolesMaskToNames($this->userRoles)[0]){
            $userRole = Role::mapRolesMaskToNames($this->userRoles)[0];
        }
        $userRoleString = $userRole ?? 'USER';

        return [
            'userroles' => $this->userRoles,
            'userRoleString' => $userRoleString,
            'currentuserid' => $this->currentUserId,
            'lastMergedPullRequestNumber' => $lastMergedPullRequestNumber ?? "",
            'companyAccountId' => FeesAccountHelper::getAccounts()['PEER_BANK'],
        ];
    }

    // Berechne den Basispreis des Beitrags
    protected function advertisePostBasicResolver(?array $args = []): int
    {
        try {
            $this->logger->debug('Query.advertisePostBasicResolver started');

            $postId = $args['postid'];
            $duration = $args['durationInDays'];

            $price = $this->advertisementService::calculatePrice($this->advertisementService::PLAN_BASIC, $duration);

            return $price;
        } catch (\Throwable $e) {
            $this->logger->warning('Invalid price provided.', ['Error' => $e]);
            return 0;
        }
    }

    // Berechne den Preis für angehefteten Beitrag
    protected function advertisePostPinnedResolver(?array $args = []): int
    {
        try {
            $this->logger->debug('Query.advertisePostPinnedResolver started');

            $postId = $args['postid'];

            $price = $this->advertisementService::calculatePrice($this->advertisementService::PLAN_PINNED);

            return $price;
        } catch (\Throwable $e) {
            $this->logger->warning('Invalid price provided.', ['Error' => $e]);
            return 0;
        }
    }

    // Werbeanzeige prüfen, validieren und freigeben
    protected function resolveAdvertisePost(?array $args = []): ?array
    {
        // Authentifizierung prüfen
        if (!$this->checkAuthentication()) {
            return $this->respondWithError(60501);
        }

        //$this->logger->info('Query.resolveAdvertisePost gestartet');

        $postId = $args['postid'] ?? null;
        $durationInDays = $args['durationInDays'] ?? null;
        $startdayInput = $args['startday'] ?? null;
        $advertisePlan = $args['advertisePlan'] ?? null;
        $reducePrice = false;
        $CostPlan = 0;

        // postId validieren
        if ($postId !== null && !self::isValidUUID($postId)) {
            return $this->respondWithError(30209);
        }

        if ($this->postService->postExistsById($postId) === false) {
            return $this->respondWithError(31510);
        }

        $advertiseActions = ['BASIC', 'PINNED'];

        // Werbeplan validieren
        if (!in_array($advertisePlan, $advertiseActions, true)) {
            $this->logger->warning('Ungültiger Werbeplan', ['advertisePlan' => $advertisePlan]);
            return $this->respondWithError(32006);
        }

        $actionPrices = [
            'BASIC' => BASIC,
            'PINNED' => PINNED,
        ];

        // Preisvalidierung
        if (!isset($actionPrices[$advertisePlan])) {
            $this->logger->warning('Ungültiger Preisplan', ['advertisePlan' => $advertisePlan]);
            return $this->respondWithError(32005);
        }

        if ($advertisePlan === $this->advertisementService::PLAN_BASIC) {
            // Startdatum validieren
            if (isset($startdayInput) && empty($startdayInput)) {
                $this->logger->warning('Startdatum fehlt oder ist leer', ['startdayInput' => $startdayInput]);
                return $this->respondWithError(32007);
            }

            // Startdatum prüfen und Format validieren
            $startday = DateTimeImmutable::createFromFormat('Y-m-d', $startdayInput);
            $errors = DateTimeImmutable::getLastErrors();

            if (!$startday) {
                $this->logger->warning("Ungültiges Startdatum: '$startdayInput'. Format muss YYYY-MM-DD sein.");
                return $this->respondWithError(32008);
            }

            if (isset($errors['warning_count']) && $errors['warning_count'] > 0 || isset($errors['error_count']) && $errors['error_count'] > 0) {
                $this->logger->warning("Ungültiges Startdatum: '$startdayInput'. Format muss YYYY-MM-DD sein.");
                return $this->respondWithError(42004);
            }

            // Prüfen, ob das Startdatum in der Vergangenheit liegt
            $tomorrow = new DateTimeImmutable('tomorrow');
            if ($startday < $tomorrow) {
                $this->logger->warning('Startdatum darf nicht in der Vergangenheit liegen', ['today' => $startdayInput]);
                return $this->respondWithError(32008);
            }

            $durationActions = ['ONE_DAY', 'TWO_DAYS', 'THREE_DAYS', 'FOUR_DAYS', 'FIVE_DAYS', 'SIX_DAYS', 'SEVEN_DAYS'];

            // Laufzeit validieren
            if ($durationInDays !== null && !in_array($durationInDays, $durationActions, true)) {
                $this->logger->warning('Ungültige Laufzeit', ['durationInDays' => $durationInDays]);
                return $this->respondWithError(32009);
            }
        }

        if ($this->advertisementService->isAdvertisementDurationValid($postId) === true) {
            $reducePrice = true;
        }

        if ($reducePrice === false) {
            if ($this->advertisementService->hasShortActiveAdWithUpcomingAd($postId) === true) {
                $reducePrice = true;
            }
        }

        // Kosten berechnen je nach Plan (BASIC oder PINNED)
        if ($advertisePlan === $this->advertisementService::PLAN_PINNED) {
            $CostPlan = $this->advertisePostPinnedResolver($args); // PINNED Kosten berechnen

            // 20% discount weil advertisement >= 24 stunde aktive noch
            if ($reducePrice === true) {
                $CostPlan = $CostPlan - ($CostPlan * 0.20); // 80% vom ursprünglichen Wert
                //$CostPlan *= 0.80; // 80% vom ursprünglichen Wert
                $this->logger->info('20% Discount Exestiert:', ['CostPlan' => $CostPlan]);
            }

            $this->logger->info('Werbeanzeige PINNED', ['CostPlan' => $CostPlan]);
            $rescode = 12003;
        } elseif ($advertisePlan === $this->advertisementService::PLAN_BASIC) {
            $CostPlan = $this->advertisePostBasicResolver($args); // BASIC Kosten berechnen
            $this->logger->info('Werbeanzeige BASIC', ["Kosten für $durationInDays Tage: " => $CostPlan]);
            $rescode = 12004;
        } else {
            $this->logger->warning('Ungültige Ads Plan', ['CostPlan' => $CostPlan]);
            return $this->respondWithError(32005);
        }

        // Wenn Kosten leer oder 0 sind, Fehler zurückgeben
        $args['eurocost'] = $CostPlan;
        if (empty($CostPlan) || (int)$CostPlan === 0) {
            $this->logger->warning('Kostenprüfung fehlgeschlagen', ['CostPlan' => $CostPlan]);
            return $this->respondWithError(42005);
        }

        // Euro in PeerTokens umrechnen
        $results = $this->advertisementService->convertEuroToTokens($CostPlan, $rescode);
        if (isset($results['status']) && $results['status'] === 'error') {
            $this->logger->warning('Fehler bei convertEuroToTokens', ['results' => $results]);
            return $results;
        }
        if (isset($results['status']) && $results['status'] === 'success') {
            $this->logger->info('Umrechnung erfolgreich', ["€$CostPlan in PeerTokens: " => $results['affectedRows']['TokenAmount']]);
            $CostPlan = $results['affectedRows']['TokenAmount'];
            $args['tokencost'] = $CostPlan;
        }

        try {
            // Wallet prüfen
            $balance = $this->walletService->getUserWalletBalance($this->currentUserId);
            if ($balance < $CostPlan) {
                $this->logger->warning('Unzureichendes Wallet-Guthaben', ['userId' => $this->currentUserId, 'balance' => $balance, 'CostPlan' => $CostPlan]);
                return $this->respondWithError(51301);
            }

            // Werbeanzeige erstellen
            $response = $this->advertisementService->createAdvertisement($args);
            if (isset($response['status']) && $response['status'] === 'success') {
                $args['art'] = ($advertisePlan === $this->advertisementService::PLAN_BASIC) ? 6 : (($advertisePlan === $this->advertisementService::PLAN_PINNED) ? 7 : null);
                $args['price'] = $CostPlan ?? null;

                $deducted = $this->walletService->deductFromWallet($this->currentUserId, $args);
                if (isset($deducted['status']) && $deducted['status'] === 'error') {
                    return $deducted;
                }

                if (!$deducted) {
                    $this->logger->warning('Abbuchung vom Wallet fehlgeschlagen', ['userId' => $this->currentUserId]);
                    return $this->respondWithError($deducted['ResponseCode']);
                }

                return $response;
            }

            return $response;

        } catch (\Throwable $e) {
            return $this->respondWithError(40301);
        }
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

        $this->logger->warning('Query.createUser No data found');
        return $this::respondWithError(41105);
    }

    protected function resolveBlocklist(array $args): ?array
    {
        if (!$this->checkAuthentication()) {
            return $this::respondWithError(60501);
        }

        $validationResult = $this->validateOffsetAndLimit($args);
        if (isset($validationResult['status']) && $validationResult['status'] === 'error') {
            return $validationResult;
        }

        $this->logger->debug('Query.resolveBlocklist started');

        $response = $this->userInfoService->loadBlocklist($args);
        if (isset($response['status']) && $response['status'] === 'error') {
            return $response;
        }

        if (empty($response['counter'])) {
            return $this::createSuccessResponse(11107, [], false);
        }

        if (is_array($response) || !empty($response)) {
            return $response;
        }

        $this->logger->warning('Query.resolveBlocklist No data found');
        return $this::respondWithError(41105);
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
            return $this::createSuccessResponse(21202, [], false);
        }

        if (is_array($response) || !empty($response)) {
            return $this::createSuccessResponse(11203, $response);
        }

        $this->logger->warning('Query.resolveFetchWinsLog No records found');
        return $this::createSuccessResponse(21202);
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
            return $this::createSuccessResponse(21202, [], false);
        }

        if (is_array($response) || !empty($response)) {
            return $this::createSuccessResponse(11203, $response);
        }

        $this->logger->warning('Query.resolveFetchPaysLog No records found');
        return $this::createSuccessResponse(21202);
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

            $inviter = $this->userMapper->getInviterByInvitee($userId);
            $this->logger->info('Inviter data', ['inviter' => $inviter]);

            if (!empty($inviter)) {
                $referralUsers['invitedBy'] = $inviter;
            }
            $offset = $args['offset'] ?? 0;
            $limit = $args['limit'] ?? 20;
            $referrals = $this->userMapper->getReferralRelations($userId, $offset, $limit);
            $this->logger->info('Referral relations', ['referrals' => $referrals]);

            if (!empty($referrals['iInvited'])) {
                $referralUsers['iInvited'] = $referrals['iInvited'];
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

    protected function resolveActionPost(?array $args = []): ?array
    {
        $tokenomicsConfig = ConstantsConfig::tokenomics();
        $dailyfreeConfig = ConstantsConfig::dailyFree();
        $actions = ConstantsConfig::wallet()['ACTIONS'];
        if (!$this->checkAuthentication()) {
            return $this::respondWithError(60501);
        }

        $this->logger->debug('Query.resolveActionPost started');

        $postId = $args['postid'] ?? null;
        $action = $args['action'] = strtolower($args['action'] ?? 'LIKE');
        $args['fromid'] = $this->currentUserId;

        $freeActions = ['report', 'save', 'share', 'view'];

        if (in_array($action, $freeActions, true)) {
            $response = $this->postInfoService->{$action . 'Post'}($postId);
            return $response;
        }

        $paidActions = ['like', 'dislike', 'comment', 'post'];

        if (!in_array($action, $paidActions, true)) {
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
            $this->logger->warning('Invalid action parameter', ['action' => $action]);
            return $this->respondWithError(30105);
        }

        $limit = $dailyLimits[$action];
        $price = $actionPrices[$action];
        $actionMap = $args['art'] = $actionMaps[$action];

        try {
            if ($limit > 0) {
                $DailyUsage = $this->dailyFreeService->getUserDailyUsage($this->currentUserId, $actionMap);

                // Return ResponseCode with Daily Free Code
                if ($DailyUsage < $limit) {
                    if ($action === 'comment') {
                        $response = $this->commentService->createComment($args);
                        if (isset($response['status']) && $response['status'] === 'error') {
                            return $response;
                        }
                        $response['ResponseCode'] = "11608";

                    } elseif ($action === 'post') {
                        $response = $this->postService->createPost($args['input']);
                        if (isset($response['status']) && $response['status'] === 'error') {
                            return $response;
                        }
                        $response['ResponseCode'] = "11513";
                    } elseif ($action === 'like') {
                        $response = $this->postInfoService->likePost($postId);
                        if (isset($response['status']) && $response['status'] === 'error') {
                            return $response;
                        }
                        $response['ResponseCode'] = "11514";
                    } else {
                        return $this::respondWithError(30105);
                    }

                    if (isset($response['status']) && $response['status'] === 'success') {
                        $incrementResult = $this->dailyFreeService->incrementUserDailyUsage($this->currentUserId, $actionMap);

                        if ($incrementResult) {
                            $this->logger->info('Daily usage incremented successfully', ['userId' => $this->currentUserId]);
                        } else {
                            $this->logger->warning('Failed to increment daily usage', ['userId' => $this->currentUserId]);
                        }

                        $DailyUsage += 1;
                        return $response;
                    }

                    $this->logger->error("{$action}Post failed", ['response' => $response]);
                    $response['affectedRows'] = $args;
                    return $response;
                }
            }
            $balance = $this->walletService->getUserWalletBalance($this->currentUserId);

            // Return ResponseCode with Daily Free Code

            if ($balance < $price) {
                $this->logger->warning('Insufficient wallet balance', ['userId' => $this->currentUserId, 'balance' => $balance, 'price' => $price]);
                return $this::respondWithError(51301);
            }

            if ($action === 'comment') {
                $response = $this->commentService->createComment($args);
                if (isset($response['status']) && $response['status'] === 'error') {
                    return $response;
                }
                $response['ResponseCode'] = "11605";
            } elseif ($action === 'post') {
                $response = $this->postService->createPost($args['input']);
                if (isset($response['status']) && $response['status'] === 'error') {
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
                    return $response;
                }
                $response['ResponseCode'] = "11503";
            } elseif ($action === 'dislike') {
                $response = $this->postInfoService->dislikePost($postId);
                if (isset($response['status']) && $response['status'] === 'error') {
                    return $response;
                }
                $response['ResponseCode'] = "11504";
            } else {
                return $this::respondWithError(30105);
            }

            if (isset($response['status']) && $response['status'] === 'success') {
                $deducted = $this->walletService->deductFromWallet($this->currentUserId, $args);
                if (isset($deducted['status']) && $deducted['status'] === 'error') {
                    return $deducted;
                }

                if (!$deducted) {
                    $this->logger->error('Failed to deduct from wallet', ['userId' => $this->currentUserId]);
                    return $this::respondWithError($deducted['ResponseCode']);
                }

                return $response;
            }

            $this->logger->error("{$action}Post failed after wallet deduction", ['response' => $response]);
            $response['affectedRows'] = $args;
            return $response;
        } catch (\Throwable $e) {
            $this->logger->error('Unexpected error in resolveActionPost', [
                'exception' => $e->getMessage(),
                'args' => $args,
            ]);
            return $this::respondWithError(40301);
        }
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

        if (is_array($results) || !empty($results)) {
            return $this::createSuccessResponse(11607, $results);
        }

        return $this::createSuccessResponse(21601);
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

        if (is_array($comments) || !empty($comments)) {
            $this->logger->info('Query.resolveTags successful');

            return $this::createSuccessResponse(11601, $comments);
        }

        return $this::createSuccessResponse(21601);
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

        $this->logger->warning('Query.resolveLiquidity Failed to find liquidity');
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

        $this->logger->warning('Query.resolveUserInfo Failed to find INFO');
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

        if (!empty($userId)) {
            $args['uid'] = $userId;
        }

        if (!empty($ip) && !filter_var($ip, FILTER_VALIDATE_IP)) {
            return $this::respondWithError(30257);//"The IP '$ip' is not a valid IP address."
        }

        $args['limit'] = min(max((int)($args['limit'] ?? 10), 1), 20);

        $this->logger->debug('Query.resolveSearchUser started');

        if ($this->userRoles === 16) {
            $args['includeDeleted'] = true;
        }

        $data = $this->userService->fetchAllAdvance($args);

        if (!empty($data)) {
            $this->logger->info('Query.resolveSearchUser.fetchAll successful', ['userCount' => count($data)]);

            return $data;
        }

        return $this::createSuccessResponse(21001);
    }

    protected function resolveListUsersV2(array $args): ?array   
    {
        if (!$this->checkAuthentication()) {
            return $this::respondWithError(60501);
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

        if (!empty($userId)) {
            $args['uid'] = $userId;
        }

        if (!empty($ip) && !filter_var($ip, FILTER_VALIDATE_IP)) {
            return $this::respondWithError(30257);//"The IP '$ip' is not a valid IP address."
        }

        $args['limit'] = min(max((int)($args['limit'] ?? 10), 1), 20);

        $this->logger->debug('Query.resolveListUsersV2 started');

        $isAdmin = $this->userRoles === 16;
        $searchesByIdentifier = !empty($username) || !empty($userId);
        if ($isAdmin) {
            $args['includeDeleted'] = true;
        }
        $data = $isAdmin || $searchesByIdentifier
            ? $this->userService->fetchAllAdvance($args)
            : $this->userService->fetchAll($args);

        if (!empty($data)) {
            $this->logger->info('Query.resolveListUsersV2.fetchAll successful', ['userCount' => count($data)]);

            return $data;
        }

        return $this::createSuccessResponse(21001);
    }

    protected function resolveFollows(array $args): ?array
    {
        if (!$this->checkAuthentication()) {
            return $this::respondWithError(60501);
        }

        $this->logger->debug('Query.resolveFollows started');

        $validation = RequestValidator::validate($args);

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
        
        if ($result instanceof Profile) {
            $this->logger->info('Query.resolveProfile successful');
            return $this::createSuccessResponse(
                11008,
                $result->getArrayCopy(),
                false
            );
        } 
        return $result->response;
    }

    protected function resolveVerifyReferral(array $args): array {

        $this->logger->debug('Query.resolveVerifyReferral started');
        $referralString = $args['referralString'];

        if (empty($referralString) || !$this->isValidUUID($referralString)) {
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

        $validationResult = $this->validateOffsetAndLimit($args);
        if (isset($validationResult['status']) && $validationResult['status'] === 'error') {
            return $validationResult;
        }

        $this->logger->debug('Query.resolveFriends started');

        $results = $this->userService->getFriends($args);
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
        } catch (\Exception $e) {
            $this->logger->error("Error in GraphQLSchemaBuilder.transactionsHistory", ['exception' => $e->getMessage()]);
            return self::respondWithError(41226);  // Error occurred while retrieving transaction history
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
                isset($results['affectedRows']) ? $results['affectedRows'] : [],
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

        $commentOffset = max((int)($args['commentOffset'] ?? 0), 0);
        $commentLimit = min(max((int)($args['commentLimit'] ?? 10), 1), 20);

        $contentFilterBy = $args['contentFilterBy'] ?? null;

        $data = array_map(
            fn (PostAdvanced $post) => $this->mapPostWithComments($post, $commentOffset, $commentLimit, $contentFilterBy),
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

        $contentFilterBy = $args['contentFilterBy'] ?? null;

        $posts = $this->advertisementService->findAdvertiser($args);
        if (isset($posts['status']) && $posts['status'] === 'error') {
            return $posts;
        }

        $commentOffset = max((int)($args['commentOffset'] ?? 0), 0);
        $commentLimit = min(max((int)($args['commentLimit'] ?? 10), 1), 20);

        $data = array_map(
            function (array $row) use ($commentOffset, $commentLimit, $contentFilterBy) {
                $postWithComments = $this->mapPostWithComments(
                    $row['post'],
                    $commentOffset,
                    $commentLimit,
                    $contentFilterBy
                );

                return [
                    // PostAdvanced Objekt
                    'post' => $postWithComments,
                    // Advertisements Objekt
                    'advertisement' => $this->mapPostWithAdvertisement($row['advertisement']),
                ];
            },
            $posts
        );

        $this->logger->info('findAdvertiser', ['data' => $data]);

        return self::createSuccessResponse(
            empty($data) ? 21501 : 11501,
            $data
        );
    }

    protected function mapPostWithAdvertisement(Advertisements $advertise): ?array
    {
        return $advertise->getArrayCopy();
    }

    protected function mapPostWithComments(PostAdvanced $post, int $commentOffset, int $commentLimit, ?string $contentFilterBy = null): array
    {
        $postArray = $post->getArrayCopy();
        $comments = $this->commentService->fetchAllByPostIdetaild($post->getPostId(), $commentOffset, $commentLimit, $contentFilterBy);

        $postArray['comments'] = array_map(
            fn (CommentAdvanced $comment) => $this->fetchCommentWithoutReplies($comment),
            $comments
        );
        return $postArray;
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

    protected static function isValidUUID(string $uuid): bool
    {
        return preg_match('/^\{?[a-fA-F0-9]{8}\-[a-fA-F0-9]{4}\-[a-fA-F0-9]{4}\-[a-fA-F0-9]{4}\-[a-fA-F0-9]{12}\}?$/', $uuid) === 1;
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
        $args['createdat'] = (new \DateTime())->format('Y-m-d H:i:s.u');

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

        if (strlen($message) < 3 || strlen($message) > 500) {
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
            $user = $this->userMapper->loadById($userid);
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
                $this->logger->warning('Email and password are required', ['email' => $email]);
                return $this::respondWithError(30801);
            }

            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $this->logger->warning('Invalid email format', ['email' => $email]);
                return $this::respondWithError(30801);
            }

            $user = $this->userMapper->loadByEmail($email);

            if (!$user) {
                $this->logger->warning('Invalid email or password', ['email' => $email]);
                return $this::respondWithError(30801);
            }

            if (!$user->getVerified()) {
                $this->logger->warning('Account not verified', ['email' => $email]);
                return $this::respondWithError(60801);
            }

            if ($user->getStatus() == 6) {
                $this->logger->warning('Account has been deleted', ['email' => $email]);
                return $this::respondWithError(30801);
            }

            if (!$user->verifyPassword($password)) {
                $this->logger->warning('Invalid password', ['email' => $email]);
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

            $this->logger->info('Login successful', ['email' => $email]);

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

            $users = $this->userMapper->loadById($decodedToken->uid);
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

        $commentOffset = max((int)($args['commentOffset'] ?? 0), 0);
        $commentLimit = min(max((int)($args['commentLimit'] ?? 10), 1), 20);

        $data = array_map(
            fn (PostAdvanced $post) => $this->guestPostMapPostWithComments($post, $commentOffset, $commentLimit),
            $posts
        );

        return [
            'status' => 'success',
            'counter' => count($data),
            'ResponseCode' => empty($data) ? "21501" : "11501",
            'affectedRows' => $data[0] ?? [],
        ];
    }

    /**
     * Map Guest Post with Comments
     *
     */
    protected function guestPostMapPostWithComments(PostAdvanced $post, int $commentOffset, int $commentLimit): array
    {
        $postArray = $post->getArrayCopy();
        $comments = $this->commentService->fetchAllByGuestPostIdetaild($post->getPostId(), $commentOffset, $commentLimit);

        $postArray['comments'] = array_map(
            fn (CommentAdvanced $comment) => $comment->getArrayCopy(),
            $comments
        );
        return $postArray;
    }
}
