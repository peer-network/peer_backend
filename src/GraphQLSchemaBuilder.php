<?php

namespace Fawaz;

// whereby
const VIEW_=1;// whereby VIEW
const LIKE_=2;// whereby LIKE
const DISLIKE_=3;// whereby DISLIKE
const COMMENT_=4;// whereby COMMENT
const POST_=5;// whereby POST
const REPORT_=6;// whereby MELDEN
const FRAME_=7;// whereby RAHM
const FRAMES_=8;// whereby RAHMEN
const POSTINCREASE_=9;// whereby POSTERHOHEN
const CRYPTEDMSG_=10;// whereby CRYPTEDMSG
const INVITATION_=11;// whereby EINLADEN
const OWNSHARED_=12;// whereby SHAREN SENDER
const OTHERSHARED_=13;// whereby SHAREN POSTER
const DIRECTDEBIT_=14;// whereby LASTSCHRIFT
const CREDIT_=15;// whereby GUTSCHRIFT
const FRIEND_=16;// whereby FREUND
const MESSAGE_=17;// whereby MESSAGE
const TRANSFER_=18;// whereby TRANSFER
const FREELIKE_=30;// whereby FREELIKE
const FREECOMMENT_=31;// whereby FREECOMMENT
const FREEPOST_=32;// whereby FREEPOST
const TESTER_=99;// whereby TESTER
const FEEWHERBY_= 100;// whereby FEEWHERBY
// DAILY FREE
const DAILYFREEPOST=1;
const DAILYFREELIKE=3;
const DAILYFREECOMMENT=4;
// POSTER WIN
const RECEIVELIKE=5;
const RECEIVEDISLIKE=4;
const RECEIVECOMMENT=2;
const RECEIVEPOSTVIEW=0.25;
const RECEIVEINVITATION=1; // invitater become 1% from any transaction to liquiditypool
// USER PAY
const PRICELIKE=3;
const PRICEDISLIKE=5;
const PRICECOMMENT=0.5;
const PRICEPOST=20;
const PRICEREPORT=20;
const PRICEFRAME=2000;
const PRICEFRAMES=75000;

use Fawaz\App\Chat;
use Fawaz\App\ChatService;
use Fawaz\App\Comment;
use Fawaz\App\CommentAdvanced;
use Fawaz\App\CommentInfoService;
use Fawaz\App\CommentService;
use Fawaz\App\DailyFreeService;
use Fawaz\App\McapService;
use Fawaz\App\Post;
use Fawaz\App\PostAdvanced;
use Fawaz\App\PostInfoService;
use Fawaz\App\PostService;
use Fawaz\App\User;
use Fawaz\App\UserInfoService;
use Fawaz\App\UserService;
use Fawaz\App\TagService;
use Fawaz\App\WalletService;
use Fawaz\Database\ChatMapper;
use Fawaz\Database\CommentMapper;
use Fawaz\Database\CommentInfoMapper;
use Fawaz\Database\ContactusMapper;
use Fawaz\Database\PostMapper;
use Fawaz\Database\TagMapper;
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
        protected PostMapper $postMapper,
        protected ChatMapper $chatMapper,
        protected TagMapper $tagMapper,
        protected TagService $tagService,
        protected CommentMapper $commentMapper,
        protected CommentInfoMapper $commentInfoMapper,
        protected ContactusMapper $contactusMapper,
        protected DailyFreeService $dailyFreeService,
        protected McapService $mcapService,
        protected UserService $userService,
        protected UserInfoService $userInfoService,
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
        } else {
            $schema = 'admin_schema.graphl';
        }

        $contents = file_get_contents(__DIR__ . '/' . $schema);
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
                    $user = $this->userMapper->loadById($decodedToken->uid);
                    if ($user) {
                        $this->currentUserId = $decodedToken->uid;
						$this->userRoles = $decodedToken->rol;
                        $this->setCurrentUserIdForServices($this->currentUserId);
						$this->logger->info('Query.setCurrentUserId started');
                    }
                } else {
                    $this->currentUserId = null;
                }
            } catch (\Exception $e) {
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
			'HelloResponse' => [
				'currentuserid' => function (array $root): string {
					$this->logger->info('Query.HelloResponse Resolvers');
					return $root['currentuserid'] ?? '';
				},
				'userroles' => function (array $root): int {
					return $root['userroles'] ?? 0;
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
			'verifiedAccount' => [
				'status' => function (array $root): string {
					$this->logger->info('Query.verifiedAccount Resolvers');
					return $root['status'] ?? '';
				},
				'ResponseCode' => function (array $root): string {
					return $root['ResponseCode'] ?? '';
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
			'UserSearchResponse' => [
				'status' => function (array $root): string {
					$this->logger->info('Query.UserSearchResponse Resolvers');
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
				'isfollowed' => function (array $root): bool {
					return $root['isfollowed'] ?? false;
				},
				'isfollowing' => function (array $root): bool {
					return $root['isfollowing'] ?? false;
				},
				'amountfollowed' => function (array $root): int {
					return $root['amountfollowed'] ?? 0;
				},
				'amountfollower' => function (array $root): int {
					return $root['amountfollower'] ?? 0;
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
			'ProfilPostMed' => [
				'id' => function (array $root): string {
					$this->logger->info('Query.ProfilPostMed Resolvers');
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
			'ProfilUser' => [
				'id' => function (array $root): string {
					$this->logger->info('Query.ProfilUser Resolvers');
					return $root['uid'] ?? '';
				},
				'username' => function (array $root): string {
					return $root['username'] ?? '';
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
				'biography' => function (array $root): string {
					return $root['biography'] ?? '';
				},
                'updatedat' => function (array $root): string {
                    return $root['updatedat'] ?? '';
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
				'friends' => function (array $root): array {
					return $root['friends'] ?? [];
				},
			],
			'RelationsFollow' => [
				'status' => function (array $root): string {
					$this->logger->info('Query.RelationsFollow Resolvers');
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
			'Followes' => [
				'followers' => function (array $root): array {
					$this->logger->info('Query.Followes Resolvers');
					return $root['followers'] ?? [];
				},
				'following' => function (array $root): array {
					return $root['following'] ?? [];
				},
			],
			'UserFollows' => [
				'status' => function (array $root): string {
					$this->logger->info('Query.UserFollows Resolvers');
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
			'UserFriends' => [
				'status' => function (array $root): string {
					$this->logger->info('Query.UserFriends Resolvers');
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
			'setFollowUseresponse' => [
				'status' => function (array $root): string {
					$this->logger->info('Query.setFollowUseresponse Resolvers');
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
				'user' => function (array $root) {
                    return $root['user'] ?? [];
				},
				'comments' => function (array $root): array {
					return $root['comments'] ?? [];
				},
			],
			'PostResponse' => [
				'status' => function (array $root): string {
					$this->logger->info('Query.PostResponse Resolvers');
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
			'ToggleResponse' => [
				'status' => function (array $root): string {
					$this->logger->info('Query.ToggleResponse Resolvers');
					return $root['status'] ?? '';
				},
				'ResponseCode' => function (array $root): string {
					return $root['ResponseCode'] ?? '';
				},
				'isSaved' => function (array $root): bool {
					return $root['isSaved'] ?? false;
				},
			],
            'Comment' => [
				'commentid' => function (array $root): string {
					$this->logger->info('Query.Comment Resolvers', ['root' => $root]);
					if (!isset($root['commentid'])) {
						return '';
					}
					return $root['commentid'];
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
				'amountlikes' => function (array $root): int {
					return $root['amountlikes'] ?? 0;
				},
				'isliked' => function (array $root): bool {
					return $root['isliked'] ?? false;
				},
                'createdat' => function (array $root): string {
                    return $root['createdat'] ?? '';
                },
				'user' => function (array $root) {
                    return $root['user'] ?? [];
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
			'Chatmessageinfo' => [
				'messid' => function (array $root): int {
					$this->logger->info('Query.Chatmessageinfo Resolvers');
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
            'Wallet' => [
				'token' => function (array $root): string {
					$this->logger->info('Query.Wallet Resolvers', ['root' => $root]);
					return $root['token'] ?? '';
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
				'numbers' => function (array $root): float {
					return $root['numbers'] ?? 0.0;
				},
				'whereby' => function (array $root): int {
					return $root['whereby'] ?? 0;
				},
                'createdat' => function (array $root): string {
                    return $root['createdat'] ?? '';
                },
            ],
			'UserWallet' => [
				'status' => function (array $root): string {
					$this->logger->info('Query.UserWallet Resolvers');
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
            'DailyResponse' => [
				'liken' => function (array $root): int {
					$this->logger->info('Query.DailyResponse Resolvers', ['root' => $root]);
					return $root['liken'] ?? 0;
				},
                'comments' => function (array $root): int {
                    return $root['comments'] ?? 0;
                },
                'posten' => function (array $root): int {
                    return $root['posten'] ?? 0;
                },
            ],
			'CurrentLiquidity' => [
				'currentliquidity' => function (array $root): float {
					$this->logger->info('Query.currentliquidity Resolvers');
					return $root['currentliquidity'] ?? 0.0;
				},
			],
			'GetUserInfo' => [
				'userid' => function (array $root): string {
					$this->logger->info('Query.GetUserInfo Resolvers');
					return $root['userid'] ?? '';
				},
				'liquidity' => function (array $root): float {
					return $root['liquidity'] ?? 0.0;
				},	
				'amountposts' => function (array $root): int {
					return $root['amountposts'] ?? 0;
				},
				'amounttrending' => function (array $root): int {
					return $root['amounttrending'] ?? 0;
				},
				'isfollowed' => function (array $root): bool {
					return $root['isfollowed'] ?? false;
				},
				'isfollowing' => function (array $root): bool {
					return $root['isfollowing'] ?? false;
				},
				'amountfollowed' => function (array $root): int {
					return $root['amountfollowed'] ?? 0;
				},
				'amountfollower' => function (array $root): int {
					return $root['amountfollower'] ?? 0;
				},
                'updatedat' => function (array $root): string {
                    return $root['updatedat'] ?? '';
                },
			],
			'StandardResponse' => [
				'status' => function (array $root): string {
					$this->logger->info('Query.StandardResponse Resolvers', ['root' => $root]);
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
					$this->logger->info('Query.GenericResponse Resolvers', ['root' => $root]);
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
        ];
    }

    protected function buildQueryResolvers(): array
    {

        return [
            'hello' => fn(mixed $root, array $args, mixed $context) => $this->resolveHello($root, $args, $context),
            'searchuser' => fn(mixed $root, array $args) => $this->resolveSearchUser($args),
            'getallusers' => fn(mixed $root, array $args) => $this->resolveUsers($args),
            'profile' => fn(mixed $root, array $args) => $this->resolveProfile($args),
            'follows' => fn(mixed $root, array $args) => $this->resolveFollows($args),
            'followrelations' => fn(mixed $root, array $args) => $this->resolveFollowRelations($args),
            'friends' => fn(mixed $root, array $args) => $this->resolveFriends($args),
            'searchpost' => fn(mixed $root, array $args) => $this->resolvePost($args),
            'getallposts' => fn(mixed $root, array $args) => $this->resolvePosts($args),
            'getpostinfo' => fn(mixed $root, array $args) => $this->resolvePostInfo($args['postid']),
            'getcommentinfo' => fn(mixed $root, array $args) => $this->resolveCommentInfo($args['commentid']),
            'parentcomments' => fn(mixed $root, array $args) => $this->resolveComments($args),
            'tags' => fn(mixed $root, array $args) => $this->resolveTags($args),
            'tagsearch' => fn(mixed $root, array $args) => $this->resolveTagsearch($args),
            'searchchat' => fn(mixed $root, array $args) => $this->resolveChat($args),
            'getallchats' => fn(mixed $root, array $args) => $this->resolveChats($args),
            'readMessages' => fn(mixed $root, array $args) => $this->chatService->readChatMessages($args),
            'dailyfreestatus' => fn(mixed $root, array $args) => $this->dailyFreeService->getUserDailyAvailability($this->currentUserId),
            'getpercentbeforetransaction' => fn(mixed $root, array $args) => $this->resolveBeforeTransaction($args),
            'refreshmarketcap' => fn(mixed $root, array $args) => $this->resolveMcap(),
            'globalwins' => fn(mixed $root, array $args) => $this->walletService->callGlobalWins(),
            'gemster' => fn(mixed $root, array $args) => $this->walletService->callGemster(),
            'gemsters' => fn(mixed $root, array $args) => $this->walletService->callGemsters($args['day']),
            'currentliquidity' => fn(mixed $root, array $args) => $this->resolveLiquidity(),
            'getuserinfo' => fn(mixed $root, array $args) => $this->resolveUserInfo(),
            'fetchwinslog' => fn(mixed $root, array $args) => $this->walletService->callFetchWinsLog($args),
        ];
    }

    protected function buildMutationResolvers(): array
    {

        return [
            'register' => fn(mixed $root, array $args) => $this->userService->createUser($args['input']),
            'verifiedAccount' => fn(mixed $root, array $args) => $this->verifiedAccount($args['userid']),
            'login' => fn(mixed $root, array $args) => $this->login($args['email'], $args['password']),
            'refreshToken' => fn(mixed $root, array $args) => $this->refreshToken($args['refreshToken']),
            'updateName' => fn(mixed $root, array $args) => $this->userService->setUsername($args),
            'updateMail' => fn(mixed $root, array $args) => $this->userService->setEmail($args),
            'updatePassword' => fn(mixed $root, array $args) => $this->userService->setPassword($args),
            'updatePrivateProfile' => fn() => $this->userInfoService->toggleProfilePrivacy(),
            'updateBiography' => fn(mixed $root, array $args) => $this->userInfoService->updateBio($args['biography']),
            'updateProfilePicture' => fn(mixed $root, array $args) => $this->userInfoService->setProfilePicture($args['img']),
			'userFollow' => fn(mixed $root, array $args) => $this->userInfoService->toggleUserFollow($args['userid']),
            'deleteAccount' => fn(mixed $root, array $args) => $this->userService->deleteAccount($args['password']),
            'createChat' => fn(mixed $root, array $args) => $this->chatService->createChatWithRecipients($args['input']),
            'updateChat' => fn(mixed $root, array $args) => $this->chatService->updateChat($args['input']),
            'deleteChat' => fn(mixed $root, array $args) => $this->chatService->deleteChat($args['id']),
            'addParticipants' => fn(mixed $root, array $args) => $this->chatService->addParticipants($args['input']),
            'remParticipants' => fn(mixed $root, array $args) => $this->chatService->removeParticipants($args['input']),
            'createFeed' => fn(mixed $root, array $args) => $this->postService->createPost($args['input']),
            'addMessage' => fn(mixed $root, array $args) => $this->chatService->addMessage($args['chatid'], $args['content']),
            'remMessage' => fn(mixed $root, array $args) => $this->chatService->removeMessage($args['chatid'], $args['messid']),
            'deletePost' => fn(mixed $root, array $args) => $this->postService->deletePost($args['id']),
			'likeComment' => fn(mixed $root, array $args) => $this->commentInfoService->likeComment($args['commentid']),
			'reportComment' => fn(mixed $root, array $args) => $this->commentInfoService->reportComment($args['commentid']),
			'contactus' => fn(mixed $root, array $args) => $this->ContactUs($args),
            'createComment' => fn(mixed $root, array $args) => $this->resolveActionPost($args),
            'createPost' => fn(mixed $root, array $args) => $this->resolveActionPost($args),
            'resolveActionPost' => fn(mixed $root, array $args) => $this->resolveActionPost($args),
        ];
    }

	protected function resolveActionPost(?array $args = []): ?array
	{
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
			return $this->respondWithError('Invalid action parameter provided.');
		}

		$dailyLimits = [
			'like' => DAILYFREELIKE,
			'comment' => DAILYFREECOMMENT,
			'post' => DAILYFREEPOST,
			'dislike' => 0,
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
			return $this->respondWithError('Invalid action parameter provided.');
		}

		$limit = $dailyLimits[$action];
		$price = $actionPrices[$action];
		$actionMap = $args['art'] = $actionMaps[$action];

		try {
			if ($limit > 0) {
				$DailyUsage = $this->dailyFreeService->getUserDailyUsage($this->currentUserId, $actionMap);

				if ($DailyUsage < $limit) {
					if ($action === 'comment') 
					{
						$response = $this->commentService->createComment($args['input']);
					}
					elseif ($action === 'post') 
					{
						$response = $this->postService->createPost($args['input']);
					}
					else 
					{
						$response = $this->postInfoService->{$action . 'Post'}($postId);
					}

					if (isset($response['status']) && $response['status'] === 'success') {
						$incrementResult = $this->dailyFreeService->incrementUserDailyUsage($this->currentUserId, $actionMap);

						if ($incrementResult) {
							$this->logger->info('Daily usage incremented successfully', ['userId' => $this->currentUserId]);
						} else {
							$this->logger->warning('Failed to increment daily usage', ['userId' => $this->currentUserId]);
						}

						$DailyUsage += 1;
						$response['ResponseCode'] = $response['ResponseCode'] . ", DailyFree " . ucfirst($action);
						$response['affectedRows'] = ['Quota remaining' => $limit - $DailyUsage];
						return $response;
					}

					$this->logger->error("{$action}Post failed", ['response' => $response]);
					$response['affectedRows'] = $args;
					return $response;
				}
			}

			$balance = $this->walletService->getUserWalletBalance($this->currentUserId);

			if ($balance < $price) {
				$this->logger->warning('Insufficient wallet balance', ['userId' => $this->currentUserId, 'balance' => $balance, 'price' => $price]);
				return $this->respondWithError('Insufficient_balance: Not enough balance to perform this action.');
			}

			if ($action === 'comments') 
			{
				$response = $this->commentService->createComment($args['input']);
			}
			elseif ($action === 'post') 
			{
				$response = $this->postService->createPost($args['input']);
			}
			else 
			{
				$response = $this->postInfoService->{$action . 'Post'}($postId);
			}

			if (isset($response['status']) && $response['status'] === 'success') {
				$deducted = $this->walletService->deductFromWallet($this->currentUserId, $args);

				if (!$deducted) {
					$this->logger->error('Failed to deduct from wallet', ['userId' => $this->currentUserId]);
					return $this->respondWithError($deducted['ResponseCode']);
				}

				$response['ResponseCode'] = $response['ResponseCode'] . ", Paid " . ucfirst($action);
				$response['affectedRows'] = ['Wallet Balance' => $balance - $price];
				return $response;
			}

			$this->logger->error("{$action}Post failed after wallet deduction", ['response' => $response]);
			$response['affectedRows'] = $args;
			return $response;
		} catch (\Exception $e) {
			$this->logger->error('Unexpected error in resolveActionPost', [
				'exception' => $e->getMessage(),
				'args' => $args,
			]);
			return $this->respondWithError('An unexpected error occurred.');
		}
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

    protected function resolveComments(array $args): ?array
    {
		if (!$this->checkAuthentication()) {
			return $this->respondWithError('Unauthorized');
		}

		if (empty($args)) {
			return $this->respondWithError('No arguments provided. Please provide valid input parameters.');
		}
        
        $comments = $this->commentService->fetchByParentId($args);
        $results = array_map(fn(CommentAdvanced $comment) => $comment->getArrayCopy(), $comments);
        
        if ($results !== false) {
            return [
                'status' => 'success',
                'counter' => count($results),
                'ResponseCode' => 'Success get comments',
                'affectedRows' => $results,
            ];
        }

        return $this->respondWithError('No comments found');
    }

    protected function resolveTags(array $args): ?array
    {
		if (!$this->checkAuthentication()) {
			return $this->respondWithError('Unauthorized');
		}

		if (empty($args)) {
			//return $this->respondWithError('No arguments provided. Please provide valid input parameters.');
		}

        $this->logger->info('Query.resolveTags started');
        $data = $this->tagService->fetchAll($args);
        if ($data !== false) {
            return $data;
        }

        return $this->respondWithError('No tags found');
    }

    protected function resolveTagsearch(array $args): ?array
    {
		if (!$this->checkAuthentication()) {
			return $this->respondWithError('Unauthorized');
		}

		if (empty($args['tagname'])) {
			return $this->respondWithError('No arguments provided. Please provide valid args parameters.');
		}

        $this->logger->info('Query.resolveTagsearch started');
        $data = $this->tagService->loadTag($args);
        if ($data !== false) {
            return $data;
        }

        return $this->respondWithError('No tags found');
    }

	protected function resolveBeforeTransaction(?array $args = []): array
	{
		if (!$this->checkAuthentication()) {
			return $this->respondWithError('Unauthorized');
		}

		if (empty($args['tokenAmount'])) {
			return $this->respondWithError('No input arguments provided. Please provide valid input parameters.');
		}

		$tokenAmount = (int)$args['tokenAmount'] ?? 0;

		if ($tokenAmount < 10) {
			return $this->respondWithError('TokenAmount must be more then 10 token.');
		}

		$results = $this->walletService->getPercentBeforeTransaction($this->currentUserId, $tokenAmount);
        $this->logger->info('Query.resolveBeforeTransaction', $results);

		if (!empty($results['status'])) {
			if ($results['status'] === 'success') {
				return $results;
			}

			return $this->respondWithError($results['ResponseCode'] ?? 'Unknown error');
		}

		return $this->respondWithError('Failed to process request');
	}

	protected function resolveLiquidity(): ?array
	{
		if (!$this->checkAuthentication()) {
			return $this->respondWithError('Unauthorized');
		}

		$this->logger->info('Query.resolveLiquidity started');

		$results = $this->walletService->loadLiquidityById($this->currentUserId);
		if (isset($results['status']) && $results['status'] === 'success') {
            $this->logger->info('Query.resolveLiquidity successful');

			return $results;
		}

		if (isset($results['status']) && $results['status'] === 'error') {
            return $this->respondWithError($results['ResponseCode']);
		}

        $this->logger->warning('Query.resolveLiquidity Failed to find liquidity');
        return $this->respondWithError('Failed to find liquidity');
	}

	protected function resolveMcap(): ?array
	{
		if (!$this->checkAuthentication()) {
			return $this->respondWithError('Unauthorized');
		}

		$this->logger->info('Query.resolveMcap started');

		$results = $this->mcapService->loadLastId();
		if (isset($results['status']) && $results['status'] === 'success') {
            $this->logger->info('Query.resolveMcap successful');

			return $results;
		}

		if (isset($results['status']) && $results['status'] === 'error') {
            return $this->respondWithError($results['ResponseCode']);
		}

        $this->logger->warning('Query.resolveMcap Failed to find mcaps');
        return $this->respondWithError('Failed to find mcaps');
	}

	protected function resolveUserInfo(): ?array
	{
		if (!$this->checkAuthentication()) {
			return $this->respondWithError('Unauthorized');
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

        $this->logger->warning('Query.resolveUserInfo Failed to find mcaps');
        return $this->respondWithError('Failed to find mcaps');
	}

    protected function resolveSearchUser(array $args): ?array
    {
		if (!$this->checkAuthentication()) {
			return $this->respondWithError('Unauthorized');
		}

		if (empty($args)) {
			return $this->respondWithError('No arguments provided. Please provide valid input parameters.');
		}

		$username = isset($args['username']) ? trim($args['username']) : null;
		$userId = $args['userid'] ?? null;
		$email = $args['email'] ?? null;
		$status = $args['status'] ?? null;
		$verified = $args['verified'] ?? null;
		$ip = $args['ip'] ?? null;

		if (empty($args['username']) && empty($args['userid']) && empty($args['email']) && !isset($args['status']) && !isset($args['verified']) && !isset($args['ip'])) {
            return $this->respondWithError('At least one of userId or username is required');
		}

		if (!empty($username) && !empty($userId)) {
            return $this->respondWithError('args username and userId arguments not allowed');
		}

		if ($userId !== null && !self::isValidUUID($userId)) {
			return $this->respondWithError('Invalid uuid input');
		}

		if ($username !== null && strlen($username) < 3 || strlen($username) > 23) {
            return $this->respondWithError('Username must be between 3 and 23 characters');
		}

		if ($username !== null && !preg_match('/^[a-zA-Z0-9]+$/', $username)) {
            return $this->respondWithError('Username must only contain letters and numbers');
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

        return $this->respondWithError('No users founds');
    }

    protected function resolveFollows(array $args): ?array
    {
		if (!$this->checkAuthentication()) {
			return $this->respondWithError('Unauthorized');
		}

		if (empty($args)) {
			//return $this->respondWithError('No arguments provided. Please provide valid input parameters.');
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
        return $this->respondWithError('User not found');
    }

    protected function resolveFollowRelations(array $args): ?array
    {
		if (!$this->checkAuthentication()) {
			return $this->respondWithError('Unauthorized');
		}

		if (empty($args)) {
			//return $this->respondWithError('No arguments provided. Please provide valid input parameters.');
		}

        $this->logger->info('Query.resolveFollowRelations started');

        $results = $this->userService->FollowRelations($args);
		if (isset($results['status']) && $results['status'] === 'success') {
            $this->logger->info('Query.resolveFollowRelations successful');

            return $results;
        }

		if (isset($results['status']) && $results['status'] === 'error') {
            return $this->respondWithError($results['ResponseCode']);
		}

        $this->logger->warning('Query.resolveFollowRelations User not found');
        return $this->respondWithError('User not found');
    }

    protected function resolveProfile(array $args): ?array
    {
		if (!$this->checkAuthentication()) {
			return $this->respondWithError('Unauthorized');
		}

		if (empty($args)) {
			//return $this->respondWithError('No arguments provided. Please provide valid input parameters.');
		}

        if (isset($args['userid']) && !self::isValidUUID($args['userid'])) {
			return $this->respondWithError('Invalid uuid input');
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
        return $this->respondWithError('User not found');
    }

    protected function resolveFriends(array $args): ?array
    {
		if (!$this->checkAuthentication()) {
			return $this->respondWithError('Unauthorized');
		}

		if (empty($args)) {
			//return $this->respondWithError('No arguments provided. Please provide valid input parameters.');
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
        return $this->respondWithError('No friends found');
    }

    protected function resolveUser(array $args): ?array
    {
		if (!$this->checkAuthentication()) {
			return $this->respondWithError('Unauthorized');
		}

		if (empty($args)) {
			return $this->respondWithError('No arguments provided. Please provide valid input parameters.');
		}

		$username = isset($args['username']) ? trim($args['username']) : null;
		$userId = $args['uid'] = $args['userid'] ?? null;

		if (empty($username) && empty($userId)) {
            return $this->respondWithError('At least one of userId or username is required.');
		}

		if (!empty($username) && !empty($userId)) {
            return $this->respondWithError('args username and userId arguments not allowed.');
		}

		if ($userId !== null && !self::isValidUUID($userId)) {
			return $this->respondWithError('Invalid uuid input');
		}

		if ($username !== null && strlen($username) < 3 || strlen($username) > 23) {
            return $this->respondWithError('Username must be between 3 and 23 characters.');
		}

		if ($username !== null && !preg_match('/^[a-zA-Z0-9]+$/', $username)) {
            return $this->respondWithError('Username must only contain letters and numbers.');
		}

        $this->logger->info('Query.resolveUser started');

        $data = $this->userMapper->fetchAll($args, $this->currentUserId);

        if ($data && count($data) > 0) {
            $daten = array_map(
                fn(User $user) => $user->getArrayCopy(),
                $data
            );

            $this->logger->info('Query.resolveUser successful', ['userCount' => count($daten)]);

            return [
                'status' => 'success',
				'counter' => count($data),
                'ResponseCode' => 'Success getting all users',
                'affectedRows' => $daten,
            ];
        }

        $this->logger->warning('Query.resolveUser No users found');
        return $this->respondWithError('No users found');
    }

    protected function resolveUsers(array $args): ?array
    {
		if (!$this->checkAuthentication()) {
			return $this->respondWithError('Unauthorized');
		}

		if (empty($args)) {
			//return $this->respondWithError('No arguments provided. Please provide valid input parameters.');
		}

        $this->logger->info('Query.resolveUsers started');

        if ($this->userRoles === 16) {
            $results = $this->userService->fetchAllAdvance($args);
        } else {
            $results = $this->userService->fetchAll($args);
        }

        return $results;

        $this->logger->warning('Query.resolveUsers No users found');
        return $this->respondWithError('No users found');
    }

	protected function resolveChat(array $args): ?array
	{
		if (!$this->checkAuthentication()) {
			return $this->respondWithError('Unauthorized');
		}

		if (empty($args)) {
			return $this->respondWithError('No arguments provided. Please provide valid input parameters.');
		}

		$chatid = $args['chatid'] ?? null;

		if (!self::isValidUUID($chatid)) {
			return $this->respondWithError('InvalidChatId');
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
			return $this->respondWithError('Unauthorized');
		}

		if (empty($args)) {
			//return $this->respondWithError('No arguments provided. Please provide valid input parameters.');
		}

        $this->logger->info('Query.resolveChats started');
		$chats = $this->chatService->findChatser($args, $this->currentUserId);
        if ($chats) {
			$data = array_map(
				fn(Chat $chat) => $this->mapChatToArray($chat),
				$chats
			);
			return [
				'status' => 'success',
				'counter' => count($data),
				'ResponseCode' => 'Success get all chats',
				'affectedRows' => $data,
			];
		}

        return $this->respondWithError('No chats found for this userid');
    }

    protected function mapChatToArray(Chat $chat): array
    {
        $data = $chat->getArrayCopy();
        return $data;
    }

    protected function resolvePost(array $args): ?array
    {
        if (!$this->checkAuthentication()) {
            return $this->respondWithError('Unauthorized access. Please authenticate.');
        }

        if (empty($args)) {
            //return $this->respondWithError('No arguments provided. Please provide valid input parameters.');
        }

        $title = isset($args['title']) ? trim($args['title']) : '';
        $postid = isset($args['postid']) ? trim($args['postid']) : '';
        $tag = isset($args['tag']) ? trim($args['tag']) : '';

        if (empty($title) && empty($postid) && empty($tag)) {
            return $this->respondWithError('At least one of the following arguments is required: title, postid, or tag.');
        }

        if (!empty($postid) && !self::isValidUUID($postid)) {
            return $this->respondWithError('Invalid postid format. Please provide a valid UUID.');
        }

        if (!empty($tag) && !preg_match('/^[a-zA-Z0-9_]+$/', $tag)) {
            return $this->respondWithError('Invalid tag format. Tags can only contain alphanumeric characters and underscores.');
        }

        if (!empty($postid)) {
            unset($args['tag'], $args['title']);
            $posts = $this->postService->findPostser($args);

            if ($posts === false) {
                return $this->respondWithError('No post found for the provided postid.');
            }
        } elseif (!empty($title)) {
            unset($args['postid'], $args['tag']);
            $posts = $this->postService->findPostser($args);

            if (empty($posts)) {
                return $this->respondWithError('No posts found matching the provided title.');
            }
        } elseif (!empty($tag)) {
            unset($args['postid'], $args['title']);
            $posts = $this->postService->findPostser($args);

            if (empty($posts)) {
                return $this->respondWithError('No posts found matching the provided tag.');
            }
        } else {
            return $this->respondWithError('Unable to locate a post with the provided information.');
        }

        $this->logger->info('Query.resolvePost started');

        $commentOffset = max((int)($args['commentOffset'] ?? 0), 0);
		$commentLimit = min(max((int)($args['commentLimit'] ?? 50), 1), 50);

        $data = array_map(
            fn(PostAdvanced $post) => $this->mapPostWithComments($post, $commentOffset, $commentLimit),
            $posts
        );

        return [
            'status' => 'success',
            'counter' => count($data),
            'ResponseCode' => 'Successfully retrieved all posts.',
            'affectedRows' => $data,
        ];
    }

    protected function resolvePostInfo(string $postId): ?array
    {
        if (!$this->checkAuthentication()) {
            return $this->respondWithError('Unauthorized access. Please authenticate.');
        }

        if (empty($postId)) {
            return $this->respondWithError('No arguments provided. Please provide valid postId parameters.');
        }

        if (!empty($postId) && !self::isValidUUID($postId)) {
            return $this->respondWithError('Invalid postid format. Please provide a valid UUID.');
        }

        $this->logger->info('Query.resolvePostInfo started');

        $postId = isset($postId) ? trim($postId) : '';

        if (!empty($postId)) {
            $posts = $this->postInfoService->findPostInfo($postId);

            if ($posts === false) {
                return $this->respondWithError('No post found for the provided postId.');
            }
        } else {
            return $this->respondWithError('Unable to locate a post with the provided information.');
        }

        return [
            'status' => 'success',
            'ResponseCode' => 'Successfully retrieved post info.',
            'affectedRows' => $posts,
        ];
    }

    protected function resolveCommentInfo(string $commentId): ?array
    {
        if (!$this->checkAuthentication()) {
            return $this->respondWithError('Unauthorized access. Please authenticate.');
        }

        if (empty($commentId)) {
            return $this->respondWithError('No arguments provided. Please provide valid commentId parameters.');
        }

        if (!empty($commentId) && !self::isValidUUID($commentId)) {
            return $this->respondWithError('Invalid commentId format. Please provide a valid UUID.');
        }

        $this->logger->info('Query.resolveCommentInfo started');

        $commentId = isset($commentId) ? trim($commentId) : '';

        if (!empty($commentId)) {
            $comments = $this->commentInfoService->findCommentInfo($commentId);

            if ($comments === false) {
                return $this->respondWithError('No comment found for the provided commentId.');
            }
        } else {
            return $this->respondWithError('Unable to locate a comment with the provided information.');
        }

        return [
            'status' => 'success',
            'ResponseCode' => 'Successfully retrieved comment info.',
            'affectedRows' => $comments,
        ];
    }

    protected function resolvePosts(array $args): ?array
    {
		if (!$this->checkAuthentication()) {
			return $this->respondWithError('Unauthorized');
		}

		if (empty($args)) {
			//return $this->respondWithError('No arguments provided. Please provide valid input parameters.');
		}

        $this->logger->info('Query.resolvePosts started');

        $posts = $this->postService->findPostser($args);

        $commentOffset = max((int)($args['commentOffset'] ?? 0), 0);
        $commentLimit = min(max((int)($args['commentLimit'] ?? 10), 1), 20);

        $data = array_map(
            fn(PostAdvanced $post) => $this->mapPostWithComments($post, $commentOffset, $commentLimit),
            $posts
        );
        return [
            'status' => 'success',
			'counter' => count($data),
            'ResponseCode' => 'Success get all posts',
            'affectedRows' => $data,
        ];
    }

    protected function mapPostWithComments(PostAdvanced $post, int $commentOffset, int $commentLimit): array
    {
        $postArray = $post->getArrayCopy();
        
        $comments = $this->commentMapper->fetchAllByPostIdetaild($post->getPostId(), $this->currentUserId, $commentOffset, $commentLimit);
        
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
            if (isset($this->resolvers[$parentTypeName])) {
                $resolver = $this->resolvers[$parentTypeName];

                if (is_array($resolver)) {
                    if (array_key_exists($fieldName, $resolver)) {
                        $value = $resolver[$fieldName];

                        return is_callable($value) ? $value($source, $args, $context, $info) : $value;
                    }

                    $this->logger->warning('No child resolver for ' . $fieldName . ' in ' . $parentTypeName);
                }

                if (is_object($resolver)) {
                    if (isset($resolver->{$fieldName})) {
                        $value = $resolver->{$fieldName};

                        return is_callable($value) ? $value($source, $args, $context, $info) : $value;
                    }

                    $this->logger->warning('No child object resolver for ' . $fieldName . ' in ' . $parentTypeName);
                }
            } else {
                $this->logger->warning('No resolver for ' . $parentTypeName);
            }
        } catch (\Throwable $e) {
            $this->logger->alert($e->getMessage(), ['exception' => (string)$e]);
            throw $e;
        }
        return Executor::defaultFieldResolver($source, $args, $context, $info);
    }

    protected static function isValidUUID(string $uuid): bool
    {
        return preg_match('/^\{?[a-fA-F0-9]{8}\-[a-fA-F0-9]{4}\-[a-fA-F0-9]{4}\-[a-fA-F0-9]{4}\-[a-fA-F0-9]{12}\}?$/', $uuid) === 1;
    }

    protected function respondWithError(string $message): array
    {
        return ['status' => 'error', 'ResponseCode' => $message];
    }

    protected function checkAuthentication(): bool
    {
        if ($this->currentUserId === null) {
            $this->logger->warning('Unauthorized access attempt');
            return false;
        }
        return true;
    }

    protected function validateRequiredFields(array $args, array $requiredFields): array
    {
        foreach ($requiredFields as $field) {
            if (empty($args[$field])) {
                return $this->respondWithError("$field is required");
            }
        }
        return [];
    }

    protected function validateCaptcha(string $token): bool
    {
        $secret = 'Peer_And_Only_Peer';
        $response = file_get_contents("https://www.google.com/recaptcha/api/siteverify?secret={$secret}&response={$token}");
        $result = json_decode($response, true);

        return $result['success'] && $result['score'] > 0.5;
    }

	protected function ContactUs(?array $args = []): array
	{
		$this->logger->info('Query.ContactUs started');

		$ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

		if (!$this->contactusMapper->checkRateLimit($ip)) {
			return $this->respondWithError('Too many requests. Please try again later.');
		}

		if (empty($args)) {
			$this->logger->error('Mandatory args missing.');
			return $this->respondWithError('Could not find mandatory args');
		}

		$email = isset($args['email']) ? trim($args['email']) : null;
		$name = isset($args['name']) ? trim($args['name']) : null;
		$message = isset($args['message']) ? trim($args['message']) : null;
		$args['ip'] = $ip;
		$args['createdat'] = (new \DateTime())->format('Y-m-d H:i:s.u');

		if (empty($email) || empty($name) || empty($message)) {
			return $this->respondWithError('Email, Name, and Message are required');
		}

		if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
			return $this->respondWithError('Invalid email format');
		}

		if (strlen($name) < 3 || strlen($name) > 33) {
			return $this->respondWithError('Name must be between 3 and 33 characters.');
		}

		if (!preg_match('/^[a-zA-Z0-9_]+$/', $name)) {
			return $this->respondWithError('Name must only contain letters, numbers, and underscores.');
		}

		if (strlen($message) < 3 || strlen($message) > 500) {
			return $this->respondWithError('Message must be between 3 and 500 characters.');
		}

		try {
			$contact = new \Fawaz\App\Contactus($args);

			$insertedContact = $this->contactusMapper->insert($contact);

			if (!$insertedContact) {
				return $this->respondWithError('Failed to insert contact.');
			}

			$this->logger->info('Contact successfully created.', ['contact' => $insertedContact->getArrayCopy()]);

			return [
				'status' => 'success',
				'ResponseCode' => 'Contact successfully created',
				'affectedRows' => $insertedContact->getArrayCopy(),
			];
		} catch (\Throwable $e) {
			$this->logger->error('Unexpected error during contact creation', [
				'error' => $e->getMessage(),
				'args' => $args,
			]);
			return $this->respondWithError('An unexpected error occurred. Please try again later.');
		}
	}

	protected function verifiedAccount(string $userid = null): array
	{
		if ($userid === null) {
			return $this->respondWithError('Empty userid');
		}

		if (!self::isValidUUID($userid)) {
			return $this->respondWithError('Invalid uuid input');
		}

		$this->logger->info('Query.verifiedAccount started');

		try {
			$user = $this->userMapper->loadById($userid);
			if (!$user) {
				return $this->respondWithError('Invalid userid');
			}

			if ($user->getVerified() == 1) {
				$this->logger->info('Account is already verified', ['userid' => $userid]);
				return [
					'status' => 'success',
					'ResponseCode' => 'Account was already verified'
				];
			}

			if ($this->userMapper->verifyAccount($userid)) {
				$this->userMapper->logLoginData($userid, 'verifiedAccount');
				$this->logger->info('Account verified successfully', ['userid' => $userid]);

				return [
					'status' => 'success',
					'ResponseCode' => 'Account freshly verified successfully'
				];
			}

		} catch (PDOException $e) {
			error_log('Database error: ' . $e->getMessage());
			return $this->respondWithError('Database error occurred');
		} catch (Exception $e) {
			error_log('General error: ' . $e->getMessage());
			return $this->respondWithError('An unexpected error occurred');
		}

		return $this->respondWithError('Failed to verify account');
	}

	protected function login(string $email, string $password): array
	{
		$this->logger->info('Query.login started');

		if (empty($email) || empty($password)) {
			$this->logger->warning('Email and password are required', ['email' => $email]);
			return $this->respondWithError('Email and password are required');
		}

		if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
			$this->logger->warning('Invalid email format', ['email' => $email]);
			return $this->respondWithError('Invalid email format');
		}

		$user = $this->userMapper->loadByEmail($email);

		if (!$user) {
			$this->logger->warning('Invalid email or password', ['email' => $email]);
			return $this->respondWithError('Invalid email or password');
		}

		if (!$user->getVerified()) {
			$this->logger->warning('Account not verified', ['email' => $email]);
			return $this->respondWithError('Account not verified. Please verify your account');
		}

		if (!$user->verifyPassword($password)) {
			$this->logger->warning('Invalid password', ['email' => $email]);
			return $this->respondWithError('Invalid password');
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
			'ResponseCode' => 'Login successful',
			'accessToken' => $accessToken,
			'refreshToken' => $refreshToken
		];
	}

    protected function refreshToken(string $refreshToken): array
    {
        $this->logger->info('Query.refreshToken started');

		if (!$this->checkAuthentication()) {
			return $this->respondWithError('Unauthorized');
		}

		if (empty($refreshToken)) {
			return $this->respondWithError('No arguments provided. Please provide valid input parameters.');
		}

        $decodedToken = $this->tokenService->validateToken($refreshToken, true);

        if (!$decodedToken) {
			return $this->respondWithError('Invalid refresh token');
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
            'ResponseCode' => 'Token refreshed successfully',
            'accessToken' => $accessToken,
            'refreshToken' => $newRefreshToken
        ];
    }
}
