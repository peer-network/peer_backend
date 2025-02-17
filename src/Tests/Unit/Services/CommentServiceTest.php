<?php

use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Fawaz\Database\{PostInfoMapper, CommentInfoMapper, CommentMapper};
use Fawaz\App\{CommentService,PostInfo,Comment};
use Mockery;
use Tests\Helper\IdHelper;

class CommentServiceTest extends TestCase
{
    private $logger;
    private $commentMapper;
    private $commentInfoMapper;
    private $postInfoMapper;
    private $commentService;
    private $currentUserId;

    use IdHelper;

    protected function setUp(): void
    {
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->commentMapper = $this->createMock(CommentMapper::class);
        $this->commentInfoMapper = $this->createMock(CommentInfoMapper::class);
        $this->postInfoMapper = $this->createMock(PostInfoMapper::class);

        $this->commentService = new CommentService(
            $this->logger,
            $this->commentMapper,
            $this->commentInfoMapper,
            $this->postInfoMapper
        );
        $this->currentUserId = $this->generateUUID();
        $this->commentService->setCurrentUserId($this->currentUserId);
    }

    public function testCreateCommentUnauthorized()
    {
        $reflection = new ReflectionClass($this->commentService);
        $property = $reflection->getProperty('currentUserId');
        $property->setAccessible(true);
        $property->setValue($this->commentService, null);
        $result = $this->commentService->createComment();

        $this->assertEquals(['status' => 'error', 'ResponseCode' => 'Unauthorized'], $result);
    }

    public function testCreateCommentNoArguments()
    {
        $result = $this->commentService->createComment();

        $this->assertEquals(['status' => 'error', 'ResponseCode' => 'No arguments provided. Please provide valid input parameters.'], $result);
    }

    public function testCreateCommentMissingRequiredFields()
    {
      
        $required_fields = ['content', 'postid'];

        foreach ($required_fields as $field) {
            $args = ['content' => 'smth', 'postid' => '3'];
            $args[$field] = null;
            $result = $this->commentService->createComment($args);

            $this->assertEquals('error', $result['status']);
            $this->assertStringContainsString($field, $result['ResponseCode']);
        }
    }

    public function commonStubsForSuccess($args): void
    {
      $comment_spy = Mockery::spy(Comment::class);
      $post_info = new PostInfo(['postid' => $args['postid'], 'userid' => $args['postid'], 'comments' => 0]);

      $this->commentMapper->expects($this->once())
          ->method('insert')
          ->willReturn($comment_spy);

      $this->postInfoMapper->expects($this->once())
          ->method('loadById')
          ->with($args['postid'])
          ->willReturn($post_info);

      $this->postInfoMapper->expects($this->once())
          ->method('update')
          ->with($post_info);

      $this->commentInfoMapper->expects($this->once())
          ->method('insert')
          ->willReturn(true);
    }
    public function testCreateCommentSuccess()
    {
        $args = ['content' => 'Test content', 'postid' => $this->generateUUID()];
        
        $this->commonStubsForSuccess($args);
        $result = $this->commentService->createComment($args);

        $this->assertEquals('success', $result['status']);
        $this->assertEquals('Comment saved successfully', $result['ResponseCode']);
    }

    public function testCreateCommentWithParent()
    {
        $args = ['content' => 'Test content', 'postid' => $this->generateUUID(), 'parentid' => $this->generateUUID()];

        $this->commonStubsForSuccess($args);

        $result = $this->commentService->createComment($args);

        $this->assertEquals('success', $result['status']);
        $this->assertEquals('Comment saved successfully', $result['ResponseCode']);
    }

    public function testCreateCommentException()
    {
        $args = ['content' => 'Test content', 'postid' => $this->generateUUID()];

        $this->commentMapper->expects($this->once())
            ->method('insert')
            ->willThrowException(new \Exception('Database error'));

        $this->logger->expects($this->once())
            ->method('error')
            ->with('Error occurred while creating comment', $this->anything());

        $result = $this->commentService->createComment($args);

        $this->assertEquals(['status' => 'error', 'ResponseCode' => 'Failed to create comment'], $result);
    }

    public function testFetchByParentIdSuccess()
    {
        $args = ['offset' => 1, 'limit' => 1, 'parent' => $this->generateUUID()];
        $this->commentMapper->expects($this->once())
            ->method('fetchByParentId')
            ->with($args['parent'], $this->currentUserId, $args['offset'], $args['limit'])
            ->willReturn([]);
        $result = $this->commentService->fetchByParentId($args);
        $this->assertEquals([], $result);
    }
}
