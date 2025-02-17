<?php
namespace Tests\Helper;

use Fawaz\GraphQLSchemaBuilder;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Fawaz\App\{UserService,TagService,DailyFreeService,McapService,
             UserInfoService,PostInfoService,PostService,CommentService,
             CommentInfoService,ChatService,WalletService,User};
use Fawaz\Services\{JWTService,FileReaderService};
use Fawaz\Database\{UserMapper,PostMapper,ChatMapper,TagMapper,
                    CommentMapper,CommentInfoMapper,ContactusMapper};
use stdClass;

trait GraphQLSchemaBuilderHelper
{
    public function createDependencies(TestCase $testCase): array
    {
        return [
            'logger' => $testCase->createMock(LoggerInterface::class),
            'userMapper' => $testCase->createMock(UserMapper::class),
            'postMapper' => $testCase->createMock(PostMapper::class),
            'chatMapper' => $testCase->createMock(ChatMapper::class),
            'tagMapper' => $testCase->createMock(TagMapper::class),
            'tagService' => $testCase->createMock(TagService::class),
            'commentMapper' => $testCase->createMock(CommentMapper::class),
            'commentInfoMapper' => $testCase->createMock(CommentInfoMapper::class),
            'contactusMapper' => $testCase->createMock(ContactusMapper::class),
            'dailyFreeService' => $testCase->createMock(DailyFreeService::class),
            'mcapService' => $testCase->createMock(McapService::class),
            'userService' => $testCase->createMock(UserService::class),
            'userInfoService' => $testCase->createMock(UserInfoService::class),
            'postInfoService' => $testCase->createMock(PostInfoService::class),
            'postService' => $testCase->createMock(PostService::class),
            'commentService' => $testCase->createMock(CommentService::class),
            'commentInfoService' => $testCase->createMock(CommentInfoService::class),
            'chatService' => $testCase->createMock(ChatService::class),
            'walletService' => $testCase->createMock(WalletService::class),
            'tokenService' => $testCase->createMock(JWTService::class),
            'fileReader'  => $testCase->createMock(FileReaderService::class),
        ];
    }

    public function stubCurrentUserAndRole(int $uid, int $role, GraphQLSchemaBuilder $builder, TestCase $testCase, $userMapperDouble, $tokenDouble): void
    {
        $jwt_token = new stdClass();
        $jwt_token->uid = $uid;
        $jwt_token->rol= $role;
        $tokenDouble->method('validateToken')->willReturn($jwt_token);

        $user = $testCase->createMock(User::class);

        $userMapperDouble->method('loadById')->willReturn($user);
        $builder->setCurrentUserId('some_token');
    }
}
