<?php

use PHPUnit\Framework\TestCase;
use Fawaz\Database\DailyFreeMapper;
use Fawaz\Database\UserMapper;
use Fawaz\Database\PostMapper;
use Fawaz\Database\WalletMapper;
use Psr\Log\LoggerInterface;
use Fawaz\App\UserService;
use Fawaz\App\UserInfo;
use Fawaz\App\Wallett;
use Fawaz\App\DailyFree;

class UserServiceTest extends TestCase
{
    private $logger;
    private $dailyFreeMapper;
    private $userMapper;
    private $postMapper;
    private $walletMapper;
    private $userService;

    protected function setUp(): void
    {
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->dailyFreeMapper = $this->createMock(DailyFreeMapper::class);
        $this->userMapper = $this->createMock(UserMapper::class);
        $this->postMapper = $this->createMock(PostMapper::class);
        $this->walletMapper = $this->createMock(WalletMapper::class);

        $this->userService = new UserService(
            $this->logger,
            $this->dailyFreeMapper,
            $this->userMapper,
            $this->postMapper,
            $this->walletMapper
        );
    }
    public function testCreateUserWithMissingRequiredFields()
    {

        $required_fields = ['username', 'email', 'password'];

        foreach ($required_fields as $field) {
            $args = ['username' => 'smth', 'email' => 'smth', 'password' => 'smth'];
            $args[$field] = null;
            $result = $this->userService->createUser($args);

            $this->assertArrayHasKey('status', $result);
            $this->assertEquals('error', $result['status']);
            $this->assertStringContainsString($field, $result['ResponseCode']);
        }
    }

    public function testCreateUserWithInvalidUsernameLength()
    {

        $args = [
          'username' => '12',
          'email' => 'valid@example.com',
          'password' => 'Password123',
      ];

      $result = $this->userService->createUser($args);

      $this->assertArrayHasKey('status', $result);
      $this->assertEquals('error', $result['status']);
      $this->assertStringContainsString('Username must be between 3 and 23 characters.', $result['ResponseCode']);
    }

    public function testCreateUserWithInvalidUsernameFormat()
    {

        $args = [
          'username' => '1!!!2',
          'email' => 'valid@example.com',
          'password' => 'Password123',
      ];

      $result = $this->userService->createUser($args);

      $this->assertArrayHasKey('status', $result);
      $this->assertEquals('error', $result['status']);
      $this->assertStringContainsString('Username must only contain letters, numbers, and underscores.', $result['ResponseCode']);
    }

    public function testCreateUserWithInvalidPasswordFormat()
    {

        $args = [
          'username' => 'valid_username',
          'email' => 'valid@example.com',
          'password' => 'password',
      ];

      $result = $this->userService->createUser($args);

      $this->assertArrayHasKey('status', $result);
      $this->assertEquals('error', $result['status']);
      $this->assertStringContainsString('Password must be at least 8 characters long and contain at least one lowercase letter, one uppercase letter, and one number.', $result['ResponseCode']);
    }

    public function testCreateUserWithInvalidEmail()
    {
        $args = [
            'username' => 'testuser',
            'email' => 'invalid-email',
            'password' => 'Password123',
        ];

        $result = $this->userService->createUser($args);

        $this->assertArrayHasKey('status', $result);
        $this->assertEquals('error', $result['status']);
        $this->assertStringContainsString('Invalid email format', $result['ResponseCode']);
    }

    public function testCreateUserWithTakenEmail()
    {
        $args = [
            'username' => 'testuser',
            'email' => 'taken@example.com',
            'password' => 'Password123',
        ];

        $this->userMapper->method('isEmailTaken')->willReturn(true);

        $result = $this->userService->createUser($args);

        $this->assertArrayHasKey('status', $result);
        $this->assertEquals('error', $result['status']);
        $this->assertStringContainsString('Email already registered', $result['ResponseCode']);
    }

    public function testCreateUserSuccessfully()
    {
        $args = [
            'username' => 'testuser',
            'email' => 'test@example.com',
            'password' => 'Password123',
            'biography' => 'Test biography',
            'isprivate' => 0,
        ];

        $user_info = $this->createMock(UserInfo::class);

        $this->userMapper->method('isEmailTaken')->willReturn(false);
  
        $this->userMapper->method('createUser')->willReturn('smth');
        $this->userMapper->method('insertinfo')->willReturn($user_info);
        $this->userMapper->method('logLoginDaten');

        $wallet = $this->createMock(Wallett::class);
        $this->walletMapper->method('insertt')->willReturn($wallet);

        $dailyFree = $this->createMock(DailyFree::class);
        $this->dailyFreeMapper->method('insert')->willReturn($dailyFree);

        $result = $this->userService->createUser($args);
        $this->assertEquals('success', $result['status']);
        $this->assertMatchesRegularExpression('/^[0-9a-f]{8}-([0-9a-f]{4}-){3}[0-9a-f]{12}$/', $result['userid']);
        $this->assertEquals('User registered successfully. Please verify your account.', $result['ResponseCode']);
    }

    public function testCreateUserWithException()
    {
        $args = [
            'username' => 'testuser',
            'email' => 'test@example.com',
            'password' => 'Password123',
        ];

        $this->userMapper->method('isEmailTaken')->willReturn(false);
        $this->userMapper->method('createUser')->willThrowException(new \Exception('Database error'));

        $result = $this->userService->createUser($args);

        $this->assertArrayHasKey('status', $result);
        $this->assertEquals('error', $result['status']);
        $this->assertStringContainsString('Failed to register user', $result['ResponseCode']);
    }
}
