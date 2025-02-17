<?php

use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Fawaz\Database\{PostInfoMapper, TagMapper, CommentMapper, TagPostMapper, PostMapper};
use Fawaz\App\{FileUploader, PostService};
use Tests\Helper\IdHelper;

class PostServiceTest extends TestCase
{
    use IdHelper;
    protected $logger;
    protected $postMapper;
    protected $commentMapper;
    protected $postInfoMapper;
    protected $tagMapper;
    protected $tagPostMapper;
    protected $fileUploader;
    protected $postService;
    protected $currentUserId;
    protected $feedId;

    protected function setUp(): void
    {
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->postMapper = $this->createMock(PostMapper::class);
        $this->commentMapper = $this->createMock(CommentMapper::class);
        $this->postInfoMapper = $this->createMock(PostInfoMapper::class);
        $this->tagMapper = $this->createMock(TagMapper::class);
        $this->tagPostMapper = $this->createMock(TagPostMapper::class);
        $this->fileUploader = $this->createMock(FileUploader::class);

        $this->postService = new PostService(
            $this->logger,
            $this->postMapper,
            $this->commentMapper,
            $this->postInfoMapper,
            $this->tagMapper,
            $this->tagPostMapper
        );

        // Set current user ID for testing
        $this->currentUserId = $this->generateUUID();
        $this->postService->setCurrentUserId($this->currentUserId);
        $this->feedId = $this->generateUUID();
    }

    public function testCreatePostUnauthorized()
    {
        $reflection = new \ReflectionClass($this->postService);
        $property = $reflection->getProperty('currentUserId');
        $property->setAccessible(true);
        $property->setValue($this->postService, null);

        $result = $this->postService->createPost([]);
        $this->assertEquals(['status' => 'error', 'ResponseCode' => 'Unauthorized'], $result);
    }

    public function testCreatePostNoArguments()
    {
        $result = $this->postService->createPost([]);
        $this->assertEquals( ['status' => 'error', 'ResponseCode' => 'No arguments provided. Please provide valid input parameters.'], $result);
    }

    public function testCreatePostMissingRequiredFields()
    {
      
        $required_fields = ['title', 'media', 'contenttype'];

        foreach ($required_fields as $field) {
            $args = ['title' => 'smth', 'media' => '3', 'contenttype' => 'smth'];
            $args[$field] = null;
            $result = $this->postService->createPost($args);

            $this->assertEquals('error', $result['status']);
            $this->assertStringContainsString($field, $result['ResponseCode']);
        }
    }

    public function testCreatePostInvalidNewsfeedID()
    {
        $this->postMapper->method('isNewsFeedExist')->with($this->feedId)->willReturn(false);

        $args = [
            'title' => 'Test Title',
            'media' => 'test.jpg',
            'contenttype' => 'image',
            'feedid' => $this->feedId
        ];

        $result = $this->postService->createPost($args);
        $this->assertEquals(['status' => 'error', 'ResponseCode' => 'Invalid newsfeed ID'], $result);
    }

    public function testCreatePostNoAccessToNewsfeed()
    {
        $this->postMapper->method('isNewsFeedExist')->with($this->feedId)->willReturn(true);
        $this->postMapper->method('isHasAccessInNewsFeed')->with($this->feedId, $this->currentUserId)->willReturn(false);

        $args = [
            'title' => 'Test Title',
            'media' => 'test.jpg',
            'contenttype' => 'image',
            'feedid' => $this->feedId
        ];

        $result = $this->postService->createPost($args);
        $this->assertEquals(['status' => 'error', 'ResponseCode' => 'No access to the newsfeed'], $result);
    }

    public function testCreatePostSuccess()
    {
        $this->postMapper->method('isNewsFeedExist')->with($this->feedId)->willReturn(true);
        $this->postMapper->method('isHasAccessInNewsFeed')->with($this->feedId, $this->currentUserId)->willReturn(true);
        $this->fileUploader->method('handleFileUpload')->willReturn('path/to/media.jpg');
        $this->postMapper->method('insert');
        $this->tagMapper->method('insert');
        $this->tagPostMapper->method('insert');

        $args = [
            'title' => 'Test Title',
            'contenttype' => 'image',
            'feedid' => $this->feedId
        ];

        $result = $this->postService->createPost($args);
        $this->assertEquals($result['status'],  'success');
        $this->assertEquals($result['counter'],  9);
        $this->assertEquals($result['ResponseCode'], 'Post created successfully');
        $this->assertEquals($result['affectedRows']['title'], $args['title']);
        $this->assertEquals($result['affectedRows']['feedid'], $this->feedId);
        $this->assertEquals($result['affectedRows']['contenttype'], $args['contenttype']);
    }
}
