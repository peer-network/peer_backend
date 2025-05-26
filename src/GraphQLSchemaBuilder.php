<?php

namespace Fawaz;

// whereby
const VIEW_=1;// whereby VIEW
const LIKE_=2;// whereby LIKE
const DISLIKE_=3;// whereby DISLIKE
const COMMENT_=4;// whereby COMMENT
const POST_=5;// whereby POST
const REPORT_=6;// whereby MELDEN
const INVITATION_=11;// whereby EINLADEN
const OWNSHARED_=12;// whereby SHAREN SENDER
const OTHERSHARED_=13;// whereby SHAREN POSTER
const FREELIKE_=30;// whereby FREELIKE
const FREECOMMENT_=31;// whereby FREECOMMENT
const FREEPOST_=32;// whereby FREEPOST
// DAILY FREE
const DAILYFREEPOST=1;
const DAILYFREELIKE=3;
const DAILYFREECOMMENT=4;
const DAILYFREEDISLIKE=0;
// USER PAY
const PRICELIKE=3;
const PRICEDISLIKE=5;
const PRICECOMMENT=0.5;
const PRICEPOST=20;

use Fawaz\App\Chat;
use Fawaz\App\ChatService;
use Fawaz\App\Comment;
use Fawaz\App\CommentAdvanced;
use Fawaz\App\CommentInfoService;
use Fawaz\App\CommentService;
use Fawaz\App\ContactusService;
use Fawaz\App\DailyFreeService;
use Fawaz\App\McapService;
use Fawaz\App\PoolService;
use Fawaz\App\Post;
use Fawaz\App\PostAdvanced;
use Fawaz\App\PostInfoService;
use Fawaz\App\PostService;
use Fawaz\App\User;
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
use Psr\Log\LoggerInterface;

class GraphQLSchemaBuilder
{
    protected array $resolvers = [];
    protected ?string $currentUserId = null;
    protected ?int $userRoles = 0;

    public function __construct(
        protected LoggerInterface $logger,
        protected UserMapper $userMapper,
        protected TagService $tagService,
        protected CommentMapper $commentMapper,
        protected ContactusService $contactusService,
        protected DailyFreeService $dailyFreeService,
        protected McapService $mcapService,
        protected UserService $userService,
        protected UserInfoService $userInfoService,
        protected PoolService $poolService,
        protected PostInfoService $postInfoService,
        protected PostService $postService,
        protected CommentService $commentService,
        protected CommentInfoService $commentInfoService,
        protected ChatService $chatService,
        protected WalletService $walletService,
        protected JWTService $tokenService
    ) {
        $this->resolvers = $this->buildResolvers();
    }

    public function build(): Schema
    {
        if ($this->currentUserId === null) {
            $schema = 'schemaguest.graphl';
        } else {
            $schema = 'schema.graphl';
        }

        if ($this->userRoles <= 0) {
            $schema = $schema;
        } elseif ($this->userRoles === 8) {
            $schema = 'bridge_schema.graphl';
        } elseif ($this->userRoles === 16) {
            $schema = 'admin_schema.graphl';
        }

        if (empty($schema)){
            $this->logger->error('Invalid schema', ['schema' => $schema]);
            return $this->respondWithError(40301);
        }

        $contents = \file_get_contents(__DIR__ . '/' . $schema);
        $schema = BuildSchema::build($contents);

        Executor::setDefaultFieldResolver([$this, 'fieldResolver']);

        return $schema;
    }

    public function setCurrentUserId(?string $bearerToken): void
    {
        if ($bearerToken !== null && $bearerToken !== '') {
            try {
                $decodedToken = $this->tokenService->validateToken($bearerToken);
                if ($decodedToken) {
                    $user = $this->userMapper->loadByIdMAin($decodedToken->uid, $decodedToken->rol);
                    //$user = $this->userMapper->loadTokenById($decodedToken->uid);
                    if ($user) {
                        $this->currentUserId = $decodedToken->uid;
                        $this->userRoles = $decodedToken->rol;
                        $this->setCurrentUserIdForServices($this->currentUserId);
                        $this->logger->info('Query.setCurrentUserId started');
                    }
                } else {
                    $this->currentUserId = null;
                }
            } catch (\Throwable $e) {
                $this->logger->error('Invalid token', ['exception' => $e]);
                $this->currentUserId = null;
            }
        } else {
            $this->currentUserId = null;
        }
    }

    protected function setCurrentUserIdForServices(string $userid): void
    {
        $this->userService->setCurrentUserId($userid);
        $this->userInfoService->setCurrentUserId($userid);
        $this->poolService->setCurrentUserId($userid);
        $this->postService->setCurrentUserId($userid);
        $this->postInfoService->setCurrentUserId($userid);
        $this->commentService->setCurrentUserId($userid);
        $this->commentInfoService->setCurrentUserId($userid);
        $this->dailyFreeService->setCurrentUserId($userid);
        $this->chatService->setCurrentUserId($userid);
        $this->mcapService->setCurrentUserId($userid);
        $this->walletService->setCurrentUserId($userid);
        $this->tagService->setCurrentUserId($userid);
    }

    protected function getStatusNameByID(int $status): ?string
    {
        $statusCode = $status;
        $statusMap = \Fawaz\App\Status::getMap();

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
            'TodaysInteractionsData' => [
                'totalInteractions' => function (array $root): int {
                    $this->logger->info('Query.TodaysInteractionsData Resolvers');
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
                    $this->logger->info('Query.TodaysInteractionsDetailsData Resolvers');
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
                    $this->logger->info('Query.ContactusResponsePayload Resolvers');
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
                    $this->logger->info('Query.HelloResponse Resolvers');
                    return $root['currentuserid'] ?? '';
                },
                'userroles' => function (array $root): int {
                    return $root['userroles'] ?? 0;
                },
                'currentVersion' => function (array $root): string {
                    return $root['currentVersion'] ?? '1.2.0';
                },
                'wikiLink' => function (array $root): string {
                    return $root['wikiLink'] ?? 'https://github.com/peer-network/peer_backend/wiki/Backend-Version-Update-1.2.0';
                },
            ],
            'RegisterResponse' => [
                'status' => function (array $root): string {
                    $this->logger->info('Query.RegisterResponse Resolvers');
                    return $root['status'] ?? '';
                },
                'ResponseCode' => function (array $root): string {
                    return $root['ResponseCode'] ?? '';
                },
                'userid' => function (array $root): string {
                    return $root['userid'] ?? '';
                },
            ],
            'User' => [
                'id' => function (array $root): string {
                    $this->logger->info('Query.User Resolvers');
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
                'status' => function (array $root): string {
                    $this->logger->info('Query.UserInfoResponse Resolvers');
                    return $root['status'] ?? '';
                },
                'ResponseCode' => function (array $root): string {
                    return $root['ResponseCode'] ?? '';
                },
                'affectedRows' => function (array $root): array {
                    return $root['affectedRows'] ?? [];
                },
            ],
            'UserInfo' => [
                'postid' => function (array $root): string {
                    $this->logger->info('Query.UserInfo Resolvers');
                    return $root['postid'] ?? '';
                },
                'userid' => function (array $root): string {
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
            'UserListResponse' => [
                'status' => function (array $root): string {
                    $this->logger->info('Query.UserListResponse Resolvers');
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
            'Profile' => [
                'id' => function (array $root): string {
                    $this->logger->info('Query.User Resolvers');
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
                'isfollowed' => function (array $root): bool {
                    return $root['isfollowed'] ?? false;
                },
                'isfollowing' => function (array $root): bool {
                    return $root['isfollowing'] ?? false;
                },
                'imageposts' => function (array $root): array {
                    return $root['imageposts'] ?? [];
                },
                'textposts' => function (array $root): array {
                    return $root['textposts'] ?? [];
                },
                'videoposts' => function (array $root): array {
                    return $root['videoposts'] ?? [];
                },
                'audioposts' => function (array $root): array {
                    return $root['audioposts'] ?? [];
                },
            ],
            'ProfileInfo' => [
                'status' => function (array $root): string {
                    $this->logger->info('Query.ProfileInfo Resolvers');
                    return $root['status'] ?? '';
                },
                'ResponseCode' => function (array $root): string {
                    return $root['ResponseCode'] ?? '';
                },
                'affectedRows' => function (array $root): array {
                    return $root['affectedRows'] ?? [];
                },
            ],
            'ProfilePostMedia' => [
                'id' => function (array $root): string {
                    $this->logger->info('Query.ProfilePostMedia Resolvers');
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
                    $this->logger->info('Query.ProfileUser Resolvers');
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
            ],
            'Userinfo' => [
                'userid' => function (array $root): string {
                    $this->logger->info('Query.Userinfo Resolvers');
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
                    $this->logger->info('Query.BlockedUser Resolvers');
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
                    $this->logger->info('Query.BlockedUsers Resolvers');
                    return $root['iBlocked'] ?? [];
                },
                'blockedBy' => function (array $root): array {
                    return $root['blockedBy'] ?? [];
                },
            ],
            'BlockedUsersResponse' => [
                'status' => function (array $root): string {
                    $this->logger->info('Query.BlockedUsersResponse Resolvers');
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
            'FollowRelations' => [
                'followers' => function (array $root): array {
                    $this->logger->info('Query.FollowRelations Resolvers');
                    return $root['followers'] ?? [];
                },
                'following' => function (array $root): array {
                    return $root['following'] ?? [];
                },
            ],
            'FollowRelationsResponse' => [
                'status' => function (array $root): string {
                    $this->logger->info('Query.FollowRelationsResponse Resolvers');
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
            'UserFriendsResponse' => [
                'status' => function (array $root): string {
                    $this->logger->info('Query.UserFriendsResponse Resolvers');
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
            'Userinforesponse' => [
                'status' => function (array $root): string {
                    $this->logger->info('Query.Userinforesponse Resolvers');
                    return $root['status'] ?? '';
                },
                'ResponseCode' => function (array $root): string {
                    return $root['ResponseCode'] ?? '';
                },
                'affectedRows' => function (array $root): array {
                    return $root['affectedRows'] ?? [];
                },
            ],
            'FollowStatusResponse' => [
                'status' => function (array $root): string {
                    $this->logger->info('Query.FollowStatusResponse Resolvers');
                    return $root['status'] ?? '';
                },
                'ResponseCode' => function (array $root): string {
                    return $root['ResponseCode'] ?? '';
                },
                'isfollowing' => function (array $root): bool {
                    return $root['isfollowing'] ?? false;
                },
            ],
            'Post' => [
                'id' => function (array $root): string {
                    $this->logger->info('Query.Post Resolvers');
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
                'status' => function (array $root): string {
                    $this->logger->info('Query.PostInfoResponse Resolvers');
                    return $root['status'] ?? '';
                },
                'ResponseCode' => function (array $root): string {
                    return $root['ResponseCode'] ?? '';
                },
                'affectedRows' => function (array $root): array {
                    return $root['affectedRows'] ?? [];
                },
            ],
            'PostInfo' => [
                'userid' => function (array $root): string {
                    $this->logger->info('Query.PostInfo Resolvers');
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
                'status' => function (array $root): string {
                    $this->logger->info('Query.PostListResponse Resolvers');
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
            'PostResponse' => [
                'status' => function (array $root): string {
                    $this->logger->info('Query.PostResponse Resolvers');
                    return $root['status'] ?? '';
                },
                'ResponseCode' => function (array $root): string {
                    return $root['ResponseCode'] ?? '';
                },
                'affectedRows' => function (array $root): array {
                    return $root['affectedRows'] ?? [];
                },
            ],
            'AddPostResponse' => [
                'status' => function (array $root): string {
                    $this->logger->info('Query.AddPostResponse Resolvers');
                    return $root['status'] ?? '';
                },
                'ResponseCode' => function (array $root): string {
                    return $root['ResponseCode'] ?? '';
                },
                'affectedRows' => function (array $root): array {
                    return $root['affectedRows'] ?? [];
                },
            ],
            'Postinfo' => [
                'userid' => function (array $root): string {
                    $this->logger->info('Query.Postinfo Resolvers');
                    return $root['userid'] ?? '';
                },
                'postid' => function (array $root): string {
                    return $root['postid'] ?? '';
                },
                'title' => function (array $root): string {
                    return $root['title'] ?? '';
                },
                'media' => function (array $root): string {
                    return $root['media'] ?? '';
                },
                'mediadescription' => function (array $root): string {
                    return $root['mediadescription'] ?? '';
                },
                'contenttype' => function (array $root): string {
                    return $root['contenttype'] ?? '';
                },
            ],
            'Comment' => [
                'commentid' => function (array $root): string {
                    $this->logger->info('Query.Comment Resolvers');
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
                },
            ],
            'CommentInfoResponse' => [
                'status' => function (array $root): string {
                    $this->logger->info('Query.CommentInfoResponse Resolvers');
                    return $root['status'] ?? '';
                },
                'ResponseCode' => function (array $root): string {
                    return $root['ResponseCode'] ?? '';
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
                'status' => function (array $root): string {
                    $this->logger->info('Query.CommentResponse Resolvers');
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
            'Chat' => [
                'id' => function (array $root): string {
                    $this->logger->info('Query.Chat Resolvers');
                    return $root['chatid'] ?? '';
                },
                'name' => function (array $root): string {
                    return $root['name'] ?? '';
                },
                'image' => function (array $root): string {
                    return $root['image'] ?? '';
                },
                'createdat' => function (array $root): string {
                    return $root['createdat'] ?? '';
                },
                'updatedat' => function (array $root): string {
                    return $root['updatedat'] ?? '';
                },
                'user' => function (array $root): array {
                    return $root['user'] ?? [];
                },
                'chatmessages' => function (array $root): array {
                    return $root['chatmessages'] ?? [];
                },
                'chatparticipants' => function (array $root): array {
                    return $root['chatparticipants'] ?? [];
                },
            ],
            'ChatMessage' => [
                'id' => function (array $root): int {
                    $this->logger->info('Query.ChatMessage Resolvers');
                    return $root['messid'] ?? 0;
                },
                'senderid' => function (array $root): string {
                    return $root['userid'] ?? '';
                },
                'chatid' => function (array $root): string {
                    return $root['chatid'] ?? '';
                },
                'content' => function (array $root): string {
                    return $root['content'] ?? '';
                },
                'createdat' => function (array $root): string {
                    return $root['createdat'] ?? '';
                },
            ],
            'ChatParticipant' => [
                'userid' => function (array $root): string {
                    $this->logger->info('Query.ChatParticipant Resolvers');
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
                'hasaccess' => function (array $root): int {
                    return $root['hasaccess'] ?? 0;
                },
            ],
            'Chatinfo' => [
                'chatid' => function (array $root): string {
                    $this->logger->info('Query.Chatinfo Resolvers');
                    return $root['chatid'] ?? '';
                },
            ],
            'ChatMessageInfo' => [
                'messid' => function (array $root): int {
                    $this->logger->info('Query.ChatMessageInfo Resolvers');
                    return $root['messid'] ?? 0;
                },
                'userid' => function (array $root): string {
                    return $root['userid'] ?? '';
                },
                'chatid' => function (array $root): string {
                    return $root['chatid'] ?? '';
                },
                'content' => function (array $root): string {
                    return $root['content'] ?? '';
                },
                'createdat' => function (array $root): string {
                    return $root['createdat'] ?? '';
                },
            ],
            'ChatResponse' => [
                'status' => function (array $root): string {
                    $this->logger->info('Query.ChatResponse Resolvers');
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
            'AddChatResponse' => [
                'status' => function (array $root): string {
                    $this->logger->info('Query.AddChatResponse Resolvers');
                    return $root['status'] ?? '';
                },
                'ResponseCode' => function (array $root): string {
                    return $root['ResponseCode'] ?? '';
                },
                'affectedRows' => function (array $root): array {
                    return $root['affectedRows'] ?? [];
                },
            ],
            'AddChatmessageResponse' => [
                'status' => function (array $root): string {
                    $this->logger->info('Query.AddChatmessageResponse Resolvers');
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
            'DefaultResponse' => [
                'status' => function (array $root): string {
                    $this->logger->info('Query.DefaultResponse Resolvers');
                    return $root['status'] ?? '';
                },
                'ResponseCode' => function (array $root): string {
                    return $root['ResponseCode'] ?? '';
                },
            ],
            'AuthPayload' => [
                'status' => function (array $root): string {
                    $this->logger->info('Query.AuthPayload Resolvers');
                    return $root['status'] ?? '';
                },
                'ResponseCode' => function (array $root): string {
                    return $root['ResponseCode'] ?? '';
                },
                'accessToken' => function (array $root): string {
                    return $root['accessToken'] ?? '';
                },
                'refreshToken' => function (array $root): string {
                    return $root['refreshToken'] ?? '';
                },
            ],
            'TagSearchResponse' => [
                'status' => function (array $root): string {
                    $this->logger->info('Query.TagSearchResponse Resolvers');
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
            'Tag' => [
                'tagid' => function (array $root): int {
                    $this->logger->info('Query.Tag Resolvers');
                    return $root['tagid'] ?? 0;
                },
                'name' => function (array $root): string {
                    return $root['name'] ?? '';
                },
            ],
            'GetDailyResponse' => [
                'status' => function (array $root): string {
                    $this->logger->info('Query.GetDailyResponse Resolvers');
                    return $root['status'] ?? '';
                },
                'ResponseCode' => function (array $root): string {
                    return $root['ResponseCode'] ?? '';
                },
                'affectedRows' => function (array $root): array {
                    return $root['affectedRows'] ?? [];
                },
            ],
            'DailyFreeResponse' => [
                'name' => function (array $root): string {
                    $this->logger->info('Query.DailyFreeResponse Resolvers');
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
                'status' => function (array $root): string {
                    $this->logger->info('Query.CurrentLiquidity Resolvers');
                    return $root['status'] ?? '';
                },
                'ResponseCode' => function (array $root): string {
                    return $root['ResponseCode'] ?? '';
                },
                'currentliquidity' => function (array $root): float {
                    $this->logger->info('Query.currentliquidity Resolvers');
                    return $root['currentliquidity'] ?? 0.0;
                },
            ],
            'UserInfo' => [
                'userid' => function (array $root): string {
                    $this->logger->info('Query.UserInfo Resolvers');
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
                'updatedat' => function (array $root): string {
                    return $root['updatedat'] ?? '';
                },
            ],
            'StandardResponse' => [
                'status' => function (array $root): string {
                    $this->logger->info('Query.StandardResponse Resolvers');
                    return $root['status'] ?? '';
                },
                'ResponseCode' => function (array $root): string {
                    return $root['ResponseCode'] ?? '';
                },
                'affectedRows' => function (array $root): array {
                    return $root['affectedRows'] ?? [];
                },
            ],
            'ListTodaysInteractionsResponse' => [
                'status' => function (array $root): string {
                    $this->logger->info('Query.StandardResponse Resolvers');
                    return $root['status'] ?? '';
                },
                'ResponseCode' => function (array $root): string {
                    return $root['ResponseCode'] ?? '';
                },
                'affectedRows' => function (array $root): array {
                    return $root['affectedRows'] ?? [];
                },
            ],
            'PercentBeforeTransactionResponse' => [
                'status' => function (array $root): string {
                    $this->logger->info('Query.StandardResponse Resolvers');
                    return $root['status'] ?? '';
                },
                'ResponseCode' => function (array $root): string {
                    return $root['ResponseCode'] ?? '';
                },
                'affectedRows' => function (array $root): array {
                    return $root['affectedRows'] ?? [];
                },
            ],
            'PercentBeforeTransactionResponse' => [
                'status' => function (array $root): string {
                    $this->logger->info('Query.StandardResponse Resolvers');
                    return $root['status'] ?? '';
                },
                'ResponseCode' => function (array $root): string {
                    return $root['ResponseCode'] ?? '';
                },
                'affectedRows' => function (array $root): array {
                    return $root['affectedRows'] ?? [];
                },
            ],
            'PercentBeforeTransactionData' => [
                'inviterId' => function (array $root): string {
                    $this->logger->info('Query.PercentBeforeTransactionResponse Resolvers');
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
                'status' => function (array $root): string {
                    $this->logger->info('Query.GemsterResponse Resolvers');
                    return $root['status'] ?? '';
                },
                'ResponseCode' => function (array $root): string {
                    return $root['ResponseCode'] ?? '';
                },
                'affectedRows' => function (array $root): array {
                    return $root['affectedRows'] ?? [];
                },
            ],
            'DailyGemStatusResponse' => [
                'status' => function (array $root): string {
                    $this->logger->info('Query.DailyGemStatusResponse Resolvers');
                    return $root['status'] ?? '';
                },
                'ResponseCode' => function (array $root): string {
                    return $root['ResponseCode'] ?? '';
                },
                'affectedRows' => function (array $root): array {
                    return $root['affectedRows'] ?? [];
                },
            ],
            'DailyGemsResultsResponse' => [
                'status' => function (array $root): string {
                    $this->logger->info('Query.DailyGemsResultsResponse Resolvers');
                    return $root['status'] ?? '';
                },
                'ResponseCode' => function (array $root): string {
                    return $root['ResponseCode'] ?? '';
                },
                'affectedRows' => function (array $root): array {
                    return $root['affectedRows'] ?? [];
                },
            ],
            'DailyGemStatusData' => [
                'd0' => function (array $root): int {
                    $this->logger->info('Query.DailyGemStatusData Resolvers');
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
                    $this->logger->info('Query.DailyGemsResultsData Resolvers');
                    return $root['data'] ?? [];
                },
                'totalGems' => function (array $root): float {
                    return $root['totalGems'] ?? 0.0;
                },
            ],
            'DailyGemsResultsUserData' => [
                'userid' => function (array $root): string {
                    $this->logger->info('Query.DailyGemsResultsUserData Resolvers');
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
                'status' => function (array $root): string {
                    $this->logger->info('Query.StandardResponse Resolvers');
                    return $root['status'] ?? '';
                },
                'ResponseCode' => function (array $root): string {
                    return $root['ResponseCode'] ?? '';
                },
                'affectedRows' => function (array $root): array {
                    return $root['affectedRows'] ?? [];
                },
            ],
            'GenericResponse' => [
                'status' => function (array $root): string {
                    $this->logger->info('Query.GenericResponse Resolvers');
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
            'GemstersResponse' => [
                'status' => function (array $root): string {
                    $this->logger->info('Query.GenericResponse Resolvers');
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
            'GemstersData' => [
                'winStatus' => function (array $root): array {
                    $this->logger->info('Query.GemstersData Resolvers');
                    return $root['winStatus'] ?? [];
                },
                'userStatus' => function (array $root): array {
                    return $root['userStatus'] ?? [];
                },
            ],
            'WinStatus' => [
                'totalGems' => function (array $root): float {
                    $this->logger->info('Query.WinStatus Resolvers');
                    return $root['totalGems'] ?? 0.0;
                },
                'gemsintoken' => function (array $root): float {
                    return $root['gemsintoken'] ?? 0.0;
                },
                'bestatigung' => function (array $root): float {
                    return $root['bestatigung'] ?? 0.0;
                },
            ],
            'GemstersUserStatus' => [
                'userid' => function (array $root): string {
                    $this->logger->info('Query.GemstersUserStatus Resolvers');
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
                    $this->logger->info('Query.GemstersUserStatusDetails Resolvers');
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
                'whereby' => function (array $root): float {
                    return $root['whereby'] ?? 0.0;
                },
                'createdat' => function (array $root): string {
                    return $root['createdat'] ?? '';
                }
            ],
            'LiquidityPoolResponse' => [
                'status' => function (array $root): string {
                    $this->logger->info('Query.LiquidityPoolResponse Resolvers');
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
            'LiquidityPoolData' => [
                'overallTotalNumbers' => function (array $root): float {
                    $this->logger->info('Query.LiquidityPoolData Resolvers');
                    return $root['overall_total_numbers'] ?? 0.0;
                },
                'overallTotalNumbersq' => function (array $root): int {
                    return $root['overall_total_numbersq'] ?? 0;
                },
                'posts' => function (array $root): array {
                    return $root['posts'] ?? [];
                },
            ],
            'LiquidityPoolPostData' => [
                'postid' => function (array $root): string {
                    $this->logger->info('Query.LiquidityPoolPostData Resolvers');
                    return $root['postid'] ?? '';
                },
                'totalNumbers' => function (array $root): float {
                    return $root['total_numbers'] ?? 0.0;
                },
                'totalNumbersq' => function (array $root): int {
                    return $root['total_numbersq'] ?? 0;
                },
                'transactionCount' => function (array $root): int {
                    return $root['transaction_count'] ?? 0;
                },
            ],
            'TestingPoolResponse' => [
                'status' => function (array $root): string {
                    $this->logger->info('Query.GenericResponse Resolvers');
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
            'PostCommentsResponse' => [
                'status' => function (array $root): string {
                    $this->logger->info('Query.PostCommentsResponse Resolvers');
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
            'PostCommentsData' => [
                'commentid' => function (array $root): string {
                    $this->logger->info('Query.PostCommentsData Resolvers');
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
                    $this->logger->info('Query.PostSubCommentsData Resolvers');
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
                    $this->logger->info('Query.UserInfo Resolvers');
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
                'status' => function (array $root): string {
                    $this->logger->info('Query.UserLogWins Resolvers');
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
            'AllUserInfo' => [
                'followerid' => function (array $root): string {
                    $this->logger->info('Query.AllUserInfo Resolvers');
                    return $root['follower'] ?? '';
                },
                'followername' => function (array $root): string {
                    return $root['followername'].'.'.$root['followerslug'] ?? '';
                },
                'followedid' => function (array $root): string {
                    return $root['followed'] ?? '';
                },
                'followedname' => function (array $root): string {
                    return $root['followedname'].'.'.$root['followedslug'] ?? '';
                },
            ],
            'AllUserFriends' => [
                'status' => function (array $root): string {
                    $this->logger->info('Query.AllUserFriends Resolvers');
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
            'RefreshMarketCapResponse' => [
                'status' => function (array $root): string {
                    $this->logger->info('Query.RefreshMarketCapResponse Resolvers');
                    return $root['status'] ?? '';
                },
                'ResponseCode' => function (array $root): string {
                    return $root['ResponseCode'] ?? '';
                },
                'affectedRows' => function (array $root): array {
                    return $root['affectedRows'] ?? [];
                },
            ],
            'SwapTokenResponse' => [
                'status' => function (array $root): string {
                    $this->logger->info('Query.getTransactionHistory Resolvers');
                    return $root['status'] ?? '';
                },
                'responseCode' => function (array $root): string {
                    return $root['ResponseCode'] ?? '';
                },
                'affectedRows' => function (array $root): array {
                    return $root['affectedRows'] ?? [];
                },
            ],
            'RefreshMarketCapData' => [
                'NumberOfTokens' => function (array $root): float {
                    $this->logger->info('Query.RefreshMarketCapData Resolvers');
                    return $root['NumberOfTokens'] ?? 0.0;
                },
                'NumberOfGems' => function (array $root): float {
                    return $root['NumberOfGems'] ?? 0.0;
                },
                'coverage' => function (array $root): float {
                    return $root['coverage'] ?? 0.0;
                },
                'TokenPrice' => function (array $root): float {
                    return $root['TokenPrice'] ?? 0.0;
                },  
                'GemsPrice' => function (array $root): float {
                    return $root['GemsPrice'] ?? 0.0;
                },  
            ],
            'ReferralInfoResponse' => [
                'status' => function (array $root): string {
                    $this->logger->info('Query.ReferralInfoResponse Resolvers');
                    return $root['status'] ?? '';
                },
                'ResponseCode' => function (array $root): string {
                    return $root['ResponseCode'] ?? '';
                },
                'referralUuid' => function (array $root): string {
                    return $root['referralUuid'] ?? '';
                },
                'referralLink' => function (array $root): string {
                    return $root['referralLink'] ?? '';
                },
            ],
            'ReferralListResponse' => [
                'status' => function (array $root): string {
                    $this->logger->info('Query.ReferralListResponse Resolvers');
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
            'AddLiquidityResponse' => [
                'status' => function (array $root): string {
                    $this->logger->info('Query.getTransactionHistory Resolvers');
                    return $root['status'] ?? '';
                },
                'responseCode' => function (array $root): string {
                    return $root['ResponseCode'] ?? '';
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
            'AddLiquidity' => [
                'newTokenAmount' => function (array $root): float {
                    return $root['newTokenAmount'] ?? 0.0;
                },
                'newBtcAmount' => function (array $root): float {
                    return $root['newBtcAmount'] ?? 0.0;
                },
                'newTokenPrice' => function (array $root): float {
                    return $root['newTokenPrice'] ?? 0.0;
                },
            ],
            'SwapToken' => [
                'tokenSend' => function (array $root): float {
                    return $root['tokenSend'] ?? 0.0;
                },
                'tokensSubstractedFromWallet' => function (array $root): float {
                    return $root['tokensSubstractedFromWallet'] ?? 0.0;
                },
                'expectedBtcReturn' => function (array $root): float {
                    return $root['expectedBtcReturn'] ?? 0.0;
                },
            ],
            'TransactionResponse' => [
                'status' => function (array $root): string {
                    $this->logger->info('Query.getTransactionHistory Resolvers');
                    return $root['status'] ?? '';
                },
                'responseCode' => function (array $root): string {
                    return $root['ResponseCode'] ?? '';
                },
                'affectedRows' => function (array $root): array {
                    return $root['affectedRows'] ?? [];
                },
            ],
            'LiquidityPoolHistoryResponse' => [
                'status' => function (array $root): string {
                    $this->logger->info('Query.getTransactionHistory Resolvers');
                    return $root['status'] ?? '';
                },
                'responseCode' => function (array $root): string {
                    return $root['ResponseCode'] ?? '';
                },
                'affectedRows' => function (array $root): array {
                    return $root['affectedRows'] ?? [];
                },
            ],
            'TokenPriceResponse' => [
                'status' => function (array $root): string {
                    $this->logger->info('Query.getTransactionHistory Resolvers');
                    return $root['status'] ?? '';
                },
                'responseCode' => function (array $root): string {
                    return $root['ResponseCode'] ?? '';
                },
                 'currentTokenPrice' => function (array $root): float {
                    return $root['currentTokenPrice'] ?? [];
                },
                'updatedAt' => function (array $root): string {
                    return $root['updatedAt'] ?? [];
                },
            ],
            'Transaction' => [
                'transactionid' => function (array $root): string {
                    return $root['transactionid'] ?? '';
                },
                'transuniqueid' => function (array $root): string {
                    return $root['transuniqueid'] ?? '';
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
                    return $root['tokenamount'] ?? 0;
                },
                'transferaction' => function (array $root): string {
                    return $root['transferaction'] ?? '';
                },
                'message' => function (array $root): string {
                    return $root['message'] ?? '';
                },
                'flag' => function (array $root): string {
                    return $root['flag'] ?? '';
                },
                'timestamp' => function (array $root): string {
                    return $root['timestamp'] ?? '';
                },
                'status' => function (array $root): string {
                    return $root['status'] ?? '';
                },
            ],
            'LiquidityPoolTransaction' => [
                'transactionid' => function (array $root): string {
                    return $root['transactionid'] ?? '';
                },
                'transactiontype' => function (array $root): string {
                    return $root['transactiontype'] ?? '';
                },
                'senderid' => function (array $root): string {
                    return $root['senderid'] ?? '';
                },
                'tokenamount' => function (array $root): float {
                    return $root['tokenamount'] ?? 0;
                },
                'btcamount' => function (array $root): float {
                    return $root['btcamount'] ?? 0;
                },
                'message' => function (array $root): string {
                    return $root['message'] ?? '';
                },
                'status' => function (array $root): string {
                    return $root['status'] ?? '';
                },
                'createdat' => function (array $root): string {
                    return $root['createdat'] ?? '';
                }
            ],                              
        ];
    }

    protected function buildQueryResolvers(): array
    {

        return [
            'hello' => fn(mixed $root, array $args, mixed $context) => $this->resolveHello($root, $args, $context),
            'searchUser' => fn(mixed $root, array $args) => $this->resolveSearchUser($args),
            'listUsers' => fn(mixed $root, array $args) => $this->resolveUsers($args),
            'getProfile' => fn(mixed $root, array $args) => $this->resolveProfile($args),
            'listFollowRelations' => fn(mixed $root, array $args) => $this->resolveFollows($args),
            'listFriends' => fn(mixed $root, array $args) => $this->resolveFriends($args),
            'listPosts' => fn(mixed $root, array $args) => $this->resolvePosts($args),
            'getPostInfo' => fn(mixed $root, array $args) => $this->resolvePostInfo($args['postid']),
            'getCommentInfo' => fn(mixed $root, array $args) => $this->resolveCommentInfo($args['commentId']),
            'listChildComments' => fn(mixed $root, array $args) => $this->resolveComments($args),
            'listTags' => fn(mixed $root, array $args) => $this->resolveTags($args),
            'searchTags' => fn(mixed $root, array $args) => $this->resolveTagsearch($args),
            'getChat' => fn(mixed $root, array $args) => $this->resolveChat($args),
            'listChats' => fn(mixed $root, array $args) => $this->resolveChats($args),
            'listChatMessages' => fn(mixed $root, array $args) => $this->resolveChatMessages($args),
            'getDailyFreeStatus' => fn(mixed $root, array $args) => $this->dailyFreeService->getUserDailyAvailability($this->currentUserId),
            'getpercentbeforetransaction' => fn(mixed $root, array $args) => $this->resolveBeforeTransaction($args),
            'refreshmarketcap' => fn(mixed $root, array $args) => $this->resolveMcap(),
            'globalwins' => fn(mixed $root, array $args) => $this->walletService->callGlobalWins(),
            'gemster' => fn(mixed $root, array $args) => $this->walletService->callGemster(),
            'gemsters' => fn(mixed $root, array $args) => $this->walletService->callGemsters($args['day']),
            'balance' => fn(mixed $root, array $args) => $this->resolveLiquidity(),
            'getUserInfo' => fn(mixed $root, array $args) => $this->resolveUserInfo(),
            'listWinLogs' => fn(mixed $root, array $args) => $this->resolveFetchWinsLog($args),
            'listPaymentLogs' => fn(mixed $root, array $args) => $this->resolveFetchPaysLog($args),
            'listBlockedUsers' => fn(mixed $root, array $args) => $this->resolveBlocklist($args),
            'listTodaysInteractions' => fn(mixed $root, array $args) => $this->walletService->callUserMove(),
            'liquiditypool' => fn(mixed $root, array $args) => $this->resolvePool($args),
            'allfriends' => fn(mixed $root, array $args) => $this->resolveAllFriends($args),
            'testingpool' => fn(mixed $root, array $args) => $this->resolveTestingPool($args),
            'postcomments' => fn(mixed $root, array $args) => $this->resolvePostComments($args),
            'dailygemstatus' => fn(mixed $root, array $args) => $this->poolService->callGemster(),
            'dailygemsresults' => fn(mixed $root, array $args) => $this->poolService->callGemsters($args['day']),
            'getReferralInfo' => fn(mixed $root, array $args) => $this->resolveReferralInfo(),
            'referralList' => fn(mixed $root, array $args) => $this->resolveReferralList($args),
            'getTransactionHistory' => fn(mixed $root, array $args) => $this->walletService->transactionsHistory($args),
            'getLiquidityPoolHistory' => fn(mixed $root, array $args) => $this->walletService->getLiquidityPoolHistory($args),
            'getTokenPrice' => fn(mixed $root, array $args) => $this->walletService->getTokenPrice(),
        ];
    }

    protected function buildMutationResolvers(): array
    {

        return [
            'requestPasswordReset' => fn(mixed $root, array $args) => $this->userService->requestPasswordReset($args['email']),
            'resetPassword' => fn(mixed $root, array $args) => $this->userService->resetPassword($args),
            'register' => fn(mixed $root, array $args) => $this->createUser($args['input']),
            'verifyAccount' => fn(mixed $root, array $args) => $this->verifyAccount($args['userid']),
            'login' => fn(mixed $root, array $args) => $this->login($args['email'], $args['password']),
            'refreshToken' => fn(mixed $root, array $args) => $this->refreshToken($args['refreshToken']),
            'updateUsername' => fn(mixed $root, array $args) => $this->userService->setUsername($args),
            'updateEmail' => fn(mixed $root, array $args) => $this->userService->setEmail($args),
            'updatePassword' => fn(mixed $root, array $args) => $this->userService->setPassword($args),
            'toggleProfilePrivacy' => fn() => $this->userInfoService->toggleProfilePrivacy(),
            'updateBio' => fn(mixed $root, array $args) => $this->userInfoService->updateBio($args['biography']),
            'updateProfileImage' => fn(mixed $root, array $args) => $this->userInfoService->setProfilePicture($args['img']),
            'toggleUserFollowStatus' => fn(mixed $root, array $args) => $this->userInfoService->toggleUserFollow($args['userid']),
            'toggleBlockUserStatus' => fn(mixed $root, array $args) => $this->userInfoService->toggleUserBlock($args['userid']),
            'deleteAccount' => fn(mixed $root, array $args) => $this->userService->deleteAccount($args['password']),
            'createChat' => fn(mixed $root, array $args) => $this->chatService->createChatWithRecipients($args['input']),
            'updateChatInformations' => fn(mixed $root, array $args) => $this->chatService->updateChat($args['input']),
            'deleteChat' => fn(mixed $root, array $args) => $this->chatService->deleteChat($args['id']),
            'addChatParticipants' => fn(mixed $root, array $args) => $this->chatService->addParticipants($args['input']),
            'removeChatParticipants' => fn(mixed $root, array $args) => $this->chatService->removeParticipants($args['input']),
            'createChatFeed' => fn(mixed $root, array $args) => $this->postService->createPost($args['input']),
            'sendChatMessage' => fn(mixed $root, array $args) => $this->chatService->addMessage($args['chatid'], $args['content']),
            'deleteChatMessage' => fn(mixed $root, array $args) => $this->chatService->removeMessage($args['chatid'], $args['messid']),
            'deletePost' => fn(mixed $root, array $args) => $this->postService->deletePost($args['id']),
            'likeComment' => fn(mixed $root, array $args) => $this->commentInfoService->likeComment($args['commentid']),
            'reportComment' => fn(mixed $root, array $args) => $this->commentInfoService->reportComment($args['commentid']),
            'contactus' => fn(mixed $root, array $args) => $this->ContactUs($args),
            'createComment' => fn(mixed $root, array $args) => $this->resolveActionPost($args),
            'createPost' => fn(mixed $root, array $args) => $this->resolveActionPost($args),
            'resolvePostAction' => fn(mixed $root, array $args) => $this->resolveActionPost($args),
            'resolveTransfer' => fn(mixed $root, array $args) => $this->walletService->transferToken($args),
            'swapTokens' => fn(mixed $root, array $args) => $this->walletService->swapTokens($args),
            'addLiquidity' => fn(mixed $root, array $args) => $this->walletService->addLiquidity($args),
            'updateSwapTranStatus' => fn(mixed $root, array $args) => $this->walletService->updateSwapTranStatus($args),
        ];
    }

    protected function buildSubscriptionResolvers(): array
    {
        return [
            'setChatMessages' => fn(mixed $root, array $args) => $this->chatService->setChatMessages($args['chatid'], $args['content']),
            'getChatMessages' => fn(mixed $root, array $args) => $this->chatService->getChatMessages($args['chatid']),
        ];
    }

    protected function resolveHello(mixed $root, array $args, mixed $context): array
    {
        $this->logger->info('Query.hello started', ['args' => $args]);

        return [
            'userroles' => $this->userRoles,
            'currentuserid' => $this->currentUserId
        ];
    }

    protected function createUser(array $args): ?array
    {
        $this->logger->info('Query.createUser started');

        $response = $this->userService->createUser($args);
        if (isset($response['status']) && $response['status'] === 'error') {
            return $response;
        }

        if (!empty($response)) {
            return $response;
        }

        $this->logger->warning('Query.createUser No data found');
        return $this->respondWithError(41105);
    }

    protected function resolveBlocklist(array $args): ?array
    {
        if (!$this->checkAuthentication()) {
            return $this->respondWithError(60501);
        }

        $validationResult = $this->validateOffsetAndLimit($args);
        if (isset($validationResult['status']) && $validationResult['status'] === 'error') {
            return $validationResult;
        }

        $this->logger->info('Query.resolveBlocklist started');

        $response = $this->userInfoService->loadBlocklist($args);
        if (isset($response['status']) && $response['status'] === 'error') {
            return $response;
        }

        if (empty($response['counter'])) {
            return $this->createSuccessResponse(11107, [], false);
        }

        if (is_array($response) || !empty($response)) {
            return $response;
        }

        $this->logger->warning('Query.resolveBlocklist No data found');
        return $this->respondWithError(41105);
    }

    protected function resolveFetchWinsLog(array $args): ?array
    {
        if (!$this->checkAuthentication()) {
            return $this->respondWithError(60501);
        }

        if (empty($args)) {
            return $this->respondWithError(30101);
        }

        $validationResult = $this->validateOffsetAndLimit($args);
        if (isset($validationResult['status']) && $validationResult['status'] === 'error') {
            return $validationResult;
        }

        $this->logger->info('Query.resolveFetchWinsLog started');

        $response = $this->walletService->callFetchWinsLog($args);
        if (isset($response['status']) && $response['status'] === 'error') {
            return $response;
        }

        if (empty($response)) {
            return $this->createSuccessResponse(21202, [], false);
        }

        if (is_array($response) || !empty($response)) {
            return $this->createSuccessResponse(11203, $response);
        }

        $this->logger->warning('Query.resolveFetchWinsLog No records found');
        return $this->createSuccessResponse(21202);
    }

    protected function resolveFetchPaysLog(array $args): ?array
    {
        if (!$this->checkAuthentication()) {
            return $this->respondWithError(60501);
        }

        if (empty($args)) {
            return $this->respondWithError(30101);
        }

        $validationResult = $this->validateOffsetAndLimit($args);
        if (isset($validationResult['status']) && $validationResult['status'] === 'error') {
            return $validationResult;
        }

        $this->logger->info('Query.resolveFetchPaysLog started');

        $response = $this->walletService->callFetchPaysLog($args);
        if (isset($response['status']) && $response['status'] === 'error') {
            return $response;
        }

        if (empty($response)) {
            return $this->createSuccessResponse(21202, [], false);
        }

        if (is_array($response) || !empty($response)) {
            return $this->createSuccessResponse(11203, $response);
        }

        $this->logger->warning('Query.resolveFetchPaysLog No records found');
        return $this->createSuccessResponse(21202);
    }
    
    protected function resolveReferralInfo(): ?array
    {
        if (!$this->checkAuthentication()) {
            return $this->respondWithError(60501);
        }

        $this->logger->info('Query.resolveReferralInfo started');

        try {
            $userId = $this->currentUserId;
            $this->logger->info('Current userId in resolveReferralInfo', [
                'userId' => $userId,
            ]);


            $info = $this->userMapper->getReferralInfoByUserId($userId);
            if (empty($info)) {
                return $this->createSuccessResponse(21002);
            }

            $response = [
                'referralUuid' => $info['referral_uuid'] ?? '', 
                'referralLink' => $info['referral_link'] ?? '',
                'status' => 'success',
                'ResponseCode' => 11011
            ];

            return $response;
        } catch (\Throwable $e) {
            $this->logger->error('Query.resolveReferralInfo exception', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return $this->respondWithError(41013);
        }
    }

    protected function resolveReferralList(array $args): ?array
    {
        if (!$this->checkAuthentication()) {
            return $this->respondWithError(60501);
        }

        $this->logger->info('Query.resolveReferralList started');

        $userId = $this->currentUserId;

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

            $referrals = $this->userMapper->getReferralRelations($userId);
            $this->logger->info('Referral relations', ['referrals' => $referrals]);

            if (!empty($referrals['iInvited'])) {
                $referralUsers['iInvited'] = $referrals['iInvited'];
            }

            if (empty($referralUsers['invitedBy']) && empty($referralUsers['iInvited'])) {
                return $this->createSuccessResponse(21003, $referralUsers, false);
            }

            $this->logger->info('Returning final referralList response', ['referralUsers' => $referralUsers]);

            return [
                'status' => 'success',
                'ResponseCode' => 11011,
                'counter' => count($referralUsers['iInvited']),
                'affectedRows' => $referralUsers
            ];
        } catch (\Throwable $e) {
            $this->logger->error('Query.resolveReferralList exception', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return $this->respondWithError(41013);
        }
    }

    protected function resolveChatMessages(array $args): ?array
    {
        if (!$this->checkAuthentication()) {
            return $this->respondWithError(60501);
        }

        if (empty($args)) {
            return $this->respondWithError(30101);
        }

        $validationResult = $this->validateOffsetAndLimit($args);
        if (isset($validationResult['status']) && $validationResult['status'] === 'error') {
            return $validationResult;
        }

        $this->logger->info('Query.resolveChatMessages started');

        $response = $this->chatService->readChatMessages($args);
        if (isset($response['status']) && $response['status'] === 'error') {
            return $response;
        }

        if (empty($response)) {
            return $this->createSuccessResponse(21806, [], false);
        }

        if (is_array($response) || !empty($response)) {
            return $this->createSuccessResponse(11807, $response, true);
        }

        $this->logger->warning('Query.resolveChatMessages No messages found');
        return $this->createSuccessResponse(21806);
    }

    protected function resolveTestingPool(array $args): ?array
    {
        if (!$this->checkAuthentication()) {
            return $this->respondWithError(60501);
        }

        $this->logger->info('Query.resolvePool started');

        $response = $this->walletService->fetchPool($args);
        if (isset($response['status']) && $response['status'] === 'error') {
            return $response;
        }

        if ($response !== false) {
            return [
                'status' => 'success',
                'counter' => count($response['posts']),
                'ResponseCode' => 11204,
                'affectedRows' => $response,
            ];
        }

        $this->logger->warning('Query.resolvePool No transactions found');
        return $this->respondWithError(41201);
    }

    protected function resolvePool(array $args): ?array
    {
        if (!$this->checkAuthentication()) {
            return $this->respondWithError(60501);
        }

        $this->logger->info('Query.resolvePool started');

        $response = $this->walletService->fetchPool($args);
        if (isset($response['status']) && $response['status'] === 'error') {
            return $response;
        }

        if (empty($response)) {
            return $this->respondWithError(41214, [], false);
        }

        if (is_array($response) || !empty($response)) {
            return $this->createSuccessResponse(11204, $response, true, 'posts');
        }

        $this->logger->warning('Query.resolvePool No transactions found');
        return $this->respondWithError(41201);
    }

    protected function resolveActionPost(?array $args = []): ?array
    {
        if (!$this->checkAuthentication()) {
            return $this->respondWithError(60501);
        }

        $this->logger->info('Query.resolveActionPost started');

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
            return $this->respondWithError(30105);
        }

        $dailyLimits = [
            'like' => DAILYFREELIKE,
            'comment' => DAILYFREECOMMENT,
            'post' => DAILYFREEPOST,
            'dislike' => DAILYFREEDISLIKE,
        ];

        $actionPrices = [
            'like' => PRICELIKE,
            'comment' => PRICECOMMENT,
            'post' => PRICEPOST,
            'dislike' => PRICEDISLIKE,
        ];

        $actionMaps = [
            'like' => LIKE_,
            'comment' => COMMENT_,
            'post' => POST_,
            'dislike' => DISLIKE_,
        ];

        // Validations
        if (!isset($dailyLimits[$action]) || !isset($actionPrices[$action])) {
            $this->logger->error('Invalid action parameter', ['action' => $action]);
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
                    if ($action === 'comment') 
                    {
                        $response = $this->commentService->createComment($args);
                        if (isset($response['status']) && $response['status'] === 'error') {
                            return $response;
                        }
                        $response['ResponseCode'] = 11608;

                    }
                    elseif ($action === 'post') 
                    {
                        $response = $this->postService->createPost($args['input']);
                        if (isset($response['status']) && $response['status'] === 'error') {
                            return $response;
                        }
                        $response['ResponseCode'] = 11513;
                    }
                    elseif ($action === 'like') 
                    {
                        $response = $this->postInfoService->likePost($postId);
                        if (isset($response['status']) && $response['status'] === 'error') {
                            return $response;
                        }
                        $response['ResponseCode'] = 11514;
                    }
                    else 
                    {
                        return $this->respondWithError(30105);
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
                return $this->respondWithError(51301);
            }

            if ($action === 'comment') 
            {
                $response = $this->commentService->createComment($args);
                if (isset($response['status']) && $response['status'] === 'error') {
                    return $response;
                }
                $response['ResponseCode'] = 11605;
            }
            elseif ($action === 'post') 
            {
                $response = $this->postService->createPost($args['input']);
                if (isset($response['status']) && $response['status'] === 'error') {
                    return $response;
                }
                $response['ResponseCode'] = 11508;

                if (isset($response['affectedRows']['postid']) && !empty($response['affectedRows']['postid'])){

                    unset($args['input'], $args['action']);
                    $args['postid'] = $response['affectedRows']['postid'];
                }
            }
            elseif ($action === 'like') 
            {
                $response = $this->postInfoService->likePost($postId);
                if (isset($response['status']) && $response['status'] === 'error') {
                    return $response;
                }
                $response['ResponseCode'] = 11503;
            }
            elseif ($action === 'dislike') 
            {
                $response = $this->postInfoService->dislikePost($postId);
                if (isset($response['status']) && $response['status'] === 'error') {
                    return $response;
                }
                $response['ResponseCode'] = 11504;
            }
            else 
            {
                return $this->respondWithError(30105);
            }

            if (isset($response['status']) && $response['status'] === 'success') {
                $deducted = $this->walletService->deductFromWallet($this->currentUserId, $args);
                if (isset($deducted['status']) && $deducted['status'] === 'error') {
                    return $deducted;
                }

                if (!$deducted) {
                    $this->logger->error('Failed to deduct from wallet', ['userId' => $this->currentUserId]);
                    return $this->respondWithError($deducted['ResponseCode']);
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
            return $this->respondWithError(41203);
        }
    }

    protected function resolveComments(array $args): array
    {
        if (!$this->checkAuthentication()) {
            return $this->respondWithError(60501);
        }

        if (empty($args)) {
            return $this->respondWithError(30104);
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
            return $this->createSuccessResponse(21606, [], false);
        }

        $results = array_map(fn(CommentAdvanced $comment) => $comment->getArrayCopy(), $comments);

        if (is_array($results) || !empty($results)) {
            return $this->createSuccessResponse(11607, $results);
        }

        return $this->createSuccessResponse(21601);
    }

    protected function resolvePostComments(array $args): array
    {
        if (!$this->checkAuthentication()) {
            return $this->respondWithError(60501);
        }

        if (empty($args)) {
            return $this->respondWithError(30104);
        }

        $comments = $this->commentService->fetchAllByPostId($args);
        if (isset($comments['status']) && $comments['status'] === 'error') {
            return $comments;
        }

        if (empty($comments)) {
            return $this->createSuccessResponse(21601, [], false);
        }

        if (is_array($comments) || !empty($comments)) {
            $this->logger->info('Query.resolveTags successful');

            return $this->createSuccessResponse(11601, $comments);
        }

        return $this->createSuccessResponse(21601);
    }

    protected function resolveTags(array $args): ?array
    {
        if (!$this->checkAuthentication()) {
            return $this->respondWithError(60501);
        }

        $validationResult = $this->validateOffsetAndLimit($args);
        if (isset($validationResult['status']) && $validationResult['status'] === 'error') {
            return $validationResult;
        }

        $this->logger->info('Query.resolveTags started');

        $tags = $this->tagService->fetchAll($args);
        if (isset($tags['status']) && $tags['status'] === 'success') {
            $this->logger->info('Query.resolveTags successful');

            return $tags;
        }

        if (isset($tags['status']) && $tags['status'] === 'error') {
            return $tags;
        }

        return $this->createSuccessResponse(21701);
    }

    protected function resolveTagsearch(array $args): ?array
    {
        if (!$this->checkAuthentication()) {
            return $this->respondWithError(60501);
        }

        $validationResult = $this->validateOffsetAndLimit($args);
        if (isset($validationResult['status']) && $validationResult['status'] === 'error') {
            return $validationResult;
        }

        $this->logger->info('Query.resolveTagsearch started');
        $data = $this->tagService->loadTag($args);
        if (isset($data['status']) && $data['status'] === 'success') {
            $this->logger->info('Query.resolveTagsearch successful');

            return $data;
        }

        if (isset($data['status']) && $data['status'] === 'error') {
            return $data;
        }

        return $this->createSuccessResponse(21701);
    }

    protected function resolveBeforeTransaction(?array $args = []): array
    {
        if (!$this->checkAuthentication()) {
            return $this->respondWithError(60501);
        }

        if (empty($args['tokenAmount'])) {
            return $this->respondWithError(30242);
        }

        $tokenAmount = (int)$args['tokenAmount'] ?? 0;

        if ($tokenAmount < 10) {
            return $this->respondWithError(30243);
        }

        $results = $this->walletService->getPercentBeforeTransaction($this->currentUserId, $tokenAmount);
        if (isset($results['status']) && $results['status'] === 'success') {
            $this->logger->info('Query.resolveBeforeTransaction successful');

            return $results;
        }

        if (isset($results['status']) && $results['status'] === 'error') {
            return $results;
        }

        $this->logger->info('Query.resolveBeforeTransaction', $results);
        return $this->respondWithError(40301);
    }

    protected function resolveLiquidity(): ?array
    {
        if (!$this->checkAuthentication()) {
            return $this->respondWithError(60501);
        }

        $this->logger->info('Query.resolveLiquidity started');

        $results = $this->walletService->loadLiquidityById($this->currentUserId);
        if (isset($results['status']) && $results['status'] === 'success') {
            $this->logger->info('Query.resolveLiquidity successful');
            return $results;
        }

        if (isset($results['status']) && $results['status'] === 'error') {
            return $results;
        }

        $this->logger->warning('Query.resolveLiquidity Failed to find liquidity');
        return $this->respondWithError(41201);
    }

    protected function resolveMcap(): ?array
    {
        if (!$this->checkAuthentication()) {
            return $this->respondWithError(60501);
        }

        $this->logger->info('Query.resolveMcap started');

        $results = $this->mcapService->loadLastId();
        if (isset($results['status']) && $results['status'] === 'success') {
            $this->logger->info('Query.resolveMcap successful');

            return $results;
        }

        if (isset($results['status']) && $results['status'] === 'error') {
            return $results;
        }

        $this->logger->warning('Query.resolveMcap Failed to find mcaps');
        return $this->respondWithError(41202);
    }

    protected function resolveUserInfo(): ?array
    {
        if (!$this->checkAuthentication()) {
            return $this->respondWithError(60501);
        }

        $this->logger->info('Query.resolveUserInfo started');

        $results = $this->userInfoService->loadInfoById();
        if (isset($results['status']) && $results['status'] === 'success') {
            $this->logger->info('Query.resolveUserInfo successful');

            return $results;
        }

        if (isset($results['status']) && $results['status'] === 'error') {
            return $this->respondWithError($results['ResponseCode']);
        }

        $this->logger->warning('Query.resolveUserInfo Failed to find INFO');
        return $this->respondWithError(41001);
    }

    protected function resolveSearchUser(array $args): ?array
    {
        if (!$this->checkAuthentication()) {
            return $this->respondWithError(60501);
        }

        if (empty($args)) {
            return $this->respondWithError(30101);
        }

        $validationResult = $this->validateOffsetAndLimit($args);
        if (isset($validationResult['status']) && $validationResult['status'] === 'error') {
            return $validationResult;
        }

        $username = isset($args['username']) ? trim($args['username']) : null;
        $userId = $args['userid'] ?? null;
        $email = $args['email'] ?? null;
        $status = $args['status'] ?? null;
        $verified = $args['verified'] ?? null;
        $ip = $args['ip'] ?? null;

        if (empty($args['username']) && empty($args['userid']) && empty($args['email']) && !isset($args['status']) && !isset($args['verified']) && !isset($args['ip'])) {
            return $this->respondWithError(30102);
        }

        if (!empty($username) && !empty($userId)) {
            return $this->respondWithError(30104);
        }

        if ($userId !== null && !self::isValidUUID($userId)) {
            return $this->respondWithError(30201);
        }

        if ($username !== null && (strlen($username) < 3 || strlen($username) > 23)) {
            return $this->respondWithError(30202);
        }

        if ($username !== null && !preg_match('/^[a-zA-Z0-9]+$/', $username)) {
            return $this->respondWithError(30202);
        }

        if (!empty($userId)) {
            $args['uid'] = $userId;
        }

        if (!empty($ip) && !filter_var($ip, FILTER_VALIDATE_IP)) {
            return $this->respondWithError("The IP '$ip' is not a valid IP address.");
        }

        $args['limit'] = min(max((int)($args['limit'] ?? 10), 1), 20);

        $this->logger->info('Query.resolveSearchUser started');

        $data = $this->userService->fetchAllAdvance($args);

        if ($data && count($data) > 0) {
            $this->logger->info('Query.resolveSearchUser.fetchAll successful', ['userCount' => count($data)]);

            return $data;
        }

        return $this->createSuccessResponse(21001);
    }

    protected function resolveFollows(array $args): ?array
    {
        if (!$this->checkAuthentication()) {
            return $this->respondWithError(60501);
        }

        $validationResult = $this->validateOffsetAndLimit($args);
        if (isset($validationResult['status']) && $validationResult['status'] === 'error') {
            return $validationResult;
        }

        $this->logger->info('Query.resolveFollows started');

        $results = $this->userService->Follows($args);
        if (isset($results['status']) && $results['status'] === 'success') {
            $this->logger->info('Query.resolveFollows successful');

            return $results;
        }

        if (isset($results['status']) && $results['status'] === 'error') {
            return $this->respondWithError($results['ResponseCode']);
        }

        $this->logger->warning('Query.resolveFollows User not found');
        return $this->createSuccessResponse(21001);
    }

    protected function resolveProfile(array $args): ?array
    {
        if (!$this->checkAuthentication()) {
            return $this->respondWithError(60501);
        }

        if (isset($args['userid']) && !self::isValidUUID($args['userid'])) {
            return $this->respondWithError(30201);
        }

        $this->logger->info('Query.resolveProfile started');

        $results = $this->userService->Profile($args);
        if (isset($results['status']) && $results['status'] === 'success') {
            $this->logger->info('Query.resolveProfile successful');

            return $results;
        }

        if (isset($results['status']) && $results['status'] === 'error') {
            return $this->respondWithError($results['ResponseCode']);
        }

        $this->logger->warning('Query.resolveProfile User not found');
        return $this->createSuccessResponse(21001);
    }

    protected function resolveFriends(array $args): ?array
    {
        if (!$this->checkAuthentication()) {
            return $this->respondWithError(60501);
        }

        $validationResult = $this->validateOffsetAndLimit($args);
        if (isset($validationResult['status']) && $validationResult['status'] === 'error') {
            return $validationResult;
        }

        $this->logger->info('Query.resolveFriends started');

        $results = $this->userService->getFriends($args);
        if (isset($results['status']) && $results['status'] === 'success') {
            $this->logger->info('Query.resolveFriends successful');

            return $results;
        }

        if (isset($results['status']) && $results['status'] === 'error') {
            return $this->respondWithError($results['ResponseCode']);
        }

        $this->logger->warning('Query.resolveFriends Users not found');
        return $this->createSuccessResponse(21101);
    }

    protected function resolveAllFriends(array $args): ?array
    {
        if (!$this->checkAuthentication()) {
            return $this->respondWithError(60501);
        }

        $this->logger->info('Query.resolveAllFriends started');

        $results = $this->userService->getAllFriends($args);
        if (isset($results['status']) && $results['status'] === 'success') {
            $this->logger->info('Query.resolveAllFriends successful');

            return $results;
        }

        if (isset($results['status']) && $results['status'] === 'error') {
            return $this->respondWithError($results['ResponseCode']);
        }

        $this->logger->warning('Query.resolveAllFriends No listFriends found');
        return $this->createSuccessResponse(21101);
    }

    protected function resolveUsers(array $args): ?array
    {
        if (!$this->checkAuthentication()) {
            return $this->respondWithError(60501);
        }

        $validationResult = $this->validateOffsetAndLimit($args);
        if (isset($validationResult['status']) && $validationResult['status'] === 'error') {
            return $validationResult;
        }

        $this->logger->info('Query.resolveUsers started');

        if ($this->userRoles === 16) {
            $results = $this->userService->fetchAllAdvance($args);
        } else {
            $results = $this->userService->fetchAll($args);
        }

        return $results;

        $this->logger->warning('Query.resolveUsers No users found');
        return $this->createSuccessResponse(21001);
    }

    protected function resolveChat(array $args): ?array
    {
        if (!$this->checkAuthentication()) {
            return $this->respondWithError(60501);
        }

        if (empty($args)) {
            return $this->respondWithError(30101);
        }

        $validationResult = $this->validateOffsetAndLimit($args);
        if (isset($validationResult['status']) && $validationResult['status'] === 'error') {
            return $validationResult;
        }

        $chatid = $args['chatid'] ?? null;

        if (!self::isValidUUID($chatid)) {
            return $this->respondWithError(30218);
        }

        $this->logger->info('Query.resolveChat started');

        $response = $this->chatService->loadChatById($args);

        if ($response['status'] === 'success') {
            $chat = $response['data'];
            $data = [$this->mapChatToArray($chat)];
            return [
                'status' => 'success',
                'counter' => count($data),
                'ResponseCode' => $response['ResponseCode'],
                'affectedRows' => $data,
            ];
        }

        return $this->respondWithError($response['ResponseCode']);
    }

    protected function resolveChats(array $args): ?array
    {
        if (!$this->checkAuthentication()) {
            return $this->respondWithError(60501);
        }

        $validationResult = $this->validateOffsetAndLimit($args);
        if (isset($validationResult['status']) && $validationResult['status'] === 'error') {
            return $validationResult;
        }

        $this->logger->info('Query.resolveChats started');
        $chats = $this->chatService->findChatser($args);
        if ($chats) {
            $data = array_map(
                fn(Chat $chat) => $this->mapChatToArray($chat),
                $chats
            );
            return [
                'status' => 'success',
                'counter' => count($data),
                'ResponseCode' => 11801,
                'affectedRows' => $data,
            ];
        }

        return $this->createSuccessResponse(21801);
    }

    protected function mapChatToArray(Chat $chat): array
    {
        $data = $chat->getArrayCopy();
        return $data;
    }

    protected function resolvePostInfo(string $postId): ?array
    {
        if (!$this->checkAuthentication()) {
            return $this->respondWithError(60501);
        }

        if (empty($postId)) {
            return $this->respondWithError(30101);
        }

        if (!empty($postId) && !self::isValidUUID($postId)) {
            return $this->respondWithError(30209);
        }

        $this->logger->info('Query.resolvePostInfo started');

        $postId = isset($postId) ? trim($postId) : '';

        if (!empty($postId)) {
            $posts = $this->postInfoService->findPostInfo($postId);
            if (isset($posts['status']) && $posts['status'] === 'error') {
                return $posts;
            }
        } else {
            return $this->createSuccessResponse(21504);
        }

        return $this->createSuccessResponse(11502, $posts);
    }

    protected function resolveCommentInfo(string $commentId): ?array
    {
        if (!$this->checkAuthentication()) {
            return $this->respondWithError(60501);
        }

        if (empty($commentId)) {
            return $this->respondWithError(30101);
        }

        if (!empty($commentId) && !self::isValidUUID($commentId)) {
            return $this->respondWithError(30217);
        }

        $this->logger->info('Query.resolveCommentInfo started');

        $commentId = isset($commentId) ? trim($commentId) : '';

        if (!empty($commentId)) {
            $comments = $this->commentInfoService->findCommentInfo($commentId);

            if ($comments === false) {
                return $this->createSuccessResponse(21505);
            }
        } else {
            return $this->createSuccessResponse(21506);
        }

        return [
            'status' => 'success',
            'ResponseCode' => 11602,
            'affectedRows' => $comments,
        ];
    }

    protected function resolvePosts(array $args): ?array
    {
        if (!$this->checkAuthentication()) {
            return $this->respondWithError(60501);
        }

        $validationResult = $this->validateOffsetAndLimit($args);
        if (isset($validationResult['status']) && $validationResult['status'] === 'error') {
            return $validationResult;
        }

        $this->logger->info('Query.resolvePosts started');

        $posts = $this->postService->findPostser($args);
        if (isset($posts['status']) && $posts['status'] === 'error') {
            return $posts;
        }

        $commentOffset = max((int)($args['commentOffset'] ?? 0), 0);
        $commentLimit = min(max((int)($args['commentLimit'] ?? 10), 1), 20);

        $data = array_map(
            fn(PostAdvanced $post) => $this->mapPostWithComments($post, $commentOffset, $commentLimit),
            $posts
        );
        return [
            'status' => 'success',
            'counter' => count($data),
            'ResponseCode' => empty($data) ? 21518 : 11501,
            'affectedRows' => $data,
        ];
    }

    protected function mapPostWithComments(PostAdvanced $post, int $commentOffset, int $commentLimit): array
    {
        $postArray = $post->getArrayCopy();
        
        $comments = $this->commentService->fetchAllByPostIdetaild($post->getPostId(), $commentOffset, $commentLimit);
        
        $postArray['comments'] = array_map(
            fn(CommentAdvanced $comment) => $this->fetchCommentWithoutReplies($comment),
            $comments
        );
        return $postArray;
    }

    protected function fetchCommentWithoutReplies(CommentAdvanced $comment): array
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

                    if ($firstParamType instanceof ReflectionNamedType 
                        && !$firstParamType->isBuiltin() 
                        && $firstParamType->getName() !== 'mixed' 
                        && !($source instanceof ($firstParamType->getName() ?? ''))) {

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

        return Executor::defaultFieldResolver($source, $args, $context, $info);
    }

    protected static function isValidUUID(string $uuid): bool
    {
        return preg_match('/^\{?[a-fA-F0-9]{8}\-[a-fA-F0-9]{4}\-[a-fA-F0-9]{4}\-[a-fA-F0-9]{4}\-[a-fA-F0-9]{12}\}?$/', $uuid) === 1;
    }

    protected function respondWithError(int $message): array
    {
        return ['status' => 'error', 'ResponseCode' => $message];
    }

    protected function createSuccessResponse(string $message, array|object $data = [], bool $countEnabled = true, ?string $countKey = null): array 
    {
        $response = [
            'status' => 'success',
            'ResponseCode' => $message,
            'affectedRows' => $data,
        ];

        if ($countEnabled && is_array($data)) {
            if ($countKey !== null && isset($data[$countKey]) && is_array($data[$countKey])) {
                $response['counter'] = count($data[$countKey]);
            } else {
                $response['counter'] = count($data);
            }
        }

        return $response;
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

        if ($offset !== null) {
            if ($offset < 0 || $offset > 200) {
                return $this->respondWithError(30203);
            }
        }

        if ($limit !== null) {
            if ($limit < 1 || $limit > 20) {  
                return $this->respondWithError(30204);
            }
        }

        if ($postOffset !== null) {
            if ($postOffset < 0 || $postOffset > 200) {
                return $this->respondWithError(30203);
            }
        }

        if ($postLimit !== null) {
            if ($postLimit < 1 || $postLimit > 20) {  
                return $this->respondWithError(30204);
            }
        }

        if ($commentOffset !== null) {
            if ($commentOffset < 0 || $commentOffset > 200) {
                return $this->respondWithError(30215);
            }
        }

        if ($commentLimit !== null) {
            if ($commentLimit < 1 || $commentLimit > 20) {  
                return $this->respondWithError(30216);
            }
        }

        if ($messageOffset !== null) {
            if ($messageOffset < 0 || $messageOffset > 200) {
                return $this->respondWithError(30219);
            }
        }

        if ($messageLimit !== null) {
            if ($messageLimit < 1 || $messageLimit > 20) {  
                return $this->respondWithError(30220);
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
        $this->logger->info('Query.ContactUs started');

        $ip = filter_var($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0', FILTER_VALIDATE_IP) ?: '0.0.0.0';
        if ($ip === '0.0.0.0') {
            return $this->respondWithError(30257);
        }

        if (!$this->contactusService->checkRateLimit($ip)) {
            return $this->respondWithError(30302);
        }

        if (empty($args)) {
            $this->logger->error('Mandatory args missing.');
            return $this->respondWithError(30101);
        }

        $email = isset($args['email']) ? trim($args['email']) : null;
        $name = isset($args['name']) ? trim($args['name']) : null;
        $message = isset($args['message']) ? trim($args['message']) : null;
        $args['ip'] = $ip;
        $args['createdat'] = (new \DateTime())->format('Y-m-d H:i:s.u');

        if (empty($email) || empty($name) || empty($message)) {
            return $this->respondWithError(30101);
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $this->respondWithError(30224);
        }

        if (strlen($name) < 3 || strlen($name) > 33) {
            return $this->respondWithError(30202);
        }

        if (!preg_match('/^[a-zA-Z0-9_]+$/', $name)) {
            return $this->respondWithError(30202);
        }

        if (strlen($message) < 3 || strlen($message) > 500) {
            return $this->respondWithError(30103);
        }

        try {
            $contact = new \Fawaz\App\Contactus($args);

            $insertedContact = $this->contactusService->insert($contact);

            if (!$insertedContact) {
                return $this->respondWithError(30401);
            }

            $this->logger->info('Contact successfully created.', ['contact' => $insertedContact->getArrayCopy()]);

            return [
                'status' => 'success',
                'ResponseCode' => 10401,
                'affectedRows' => $insertedContact->getArrayCopy(),
            ];
        } catch (\Throwable $e) {
            $this->logger->error('Unexpected error during contact creation', [
                'error' => $e->getMessage(),
                'args' => $args,
            ]);
            return $this->respondWithError(30401);
        }
    }

    protected function verifyAccount(string $userid = null): array
    {
        if ($userid === null) {
            return $this->respondWithError(30101);
        }

        if (!self::isValidUUID($userid)) {
            return $this->respondWithError(30201);
        }

        $this->logger->info('Query.verifyAccount started');

        try {
            $user = $this->userMapper->loadById($userid);
            if (!$user) {
                return $this->respondWithError(31007);
            }

            if ($user->getVerified() == 1) {
                $this->logger->info('Account is already verified', ['userid' => $userid]);
                return [
                    'status' => 'error',
                    'ResponseCode' => 30701
                ];
            }

            if ($this->userMapper->verifyAccount($userid)) {
                $this->userMapper->logLoginData($userid, 'verifyAccount');
                $this->logger->info('Account verified successfully', ['userid' => $userid]);

                return [
                    'status' => 'success',
                    'ResponseCode' => 10701
                ];
            }

        } catch (\Throwable $e) {
            return $this->respondWithError(40701);
        }

        return $this->respondWithError(40701);
    }

    protected function login(string $email, string $password): array
    {
        $this->logger->info('Query.login started');

        try {
            if (empty($email) || empty($password)) {
                $this->logger->warning('Email and password are required', ['email' => $email]);
                return $this->respondWithError(30801);
            }

            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $this->logger->warning('Invalid email format', ['email' => $email]);
                return $this->respondWithError(30801);
            }

            $user = $this->userMapper->loadByEmail($email);

            if (!$user) {
                $this->logger->warning('Invalid email or password', ['email' => $email]);
                return $this->respondWithError(30801);
            }

            if (!$user->getVerified()) {
                $this->logger->warning('Account not verified', ['email' => $email]);
                return $this->respondWithError(60801);
            }

            if (!$user->verifyPassword($password)) {
                $this->logger->warning('Invalid password', ['email' => $email]);
                return $this->respondWithError(30801);
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
                'ResponseCode' => 10801,
                'accessToken' => $accessToken,
                'refreshToken' => $refreshToken
            ];
        } catch (\Throwable $e) {
            $this->logger->error('Error during login process', [
                'email' => $email,
                'exception' => $e->getMessage(),
                'stackTrace' => $e->getTraceAsString()
            ]);

            return $this->respondWithError(40801);
        }
    }

    protected function refreshToken(string $refreshToken): array
    {
        $this->logger->info('Query.refreshToken started');

        try {
            if (empty($refreshToken)) {
                return $this->respondWithError(30101);
            }

            $decodedToken = $this->tokenService->validateToken($refreshToken, true);

            if (!$decodedToken) {
                return $this->respondWithError(30901);
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
                'ResponseCode' => 10901,
                'accessToken' => $accessToken,
                'refreshToken' => $newRefreshToken
            ];

        } catch (\Throwable $e) {
            $this->logger->error('Error during refreshToken process', [
                'exception' => $e->getMessage(),
                'stackTrace' => $e->getTraceAsString()
            ]);
            
            return $this->respondWithError(40901);
        }
    }
}
