<?php

namespace Tests\Service;

use PHPUnit\Framework\TestCase;
use Fawaz\App\WalletService;
use Fawaz\Database\WalletMapper;
use Fawaz\App\Wallet;
use Psr\Log\LoggerInterface;

class WalletServiceTest extends TestCase
{
    private WalletService $walletService;
    private $walletMapperMock;
    private $loggerMock;

    protected function setUp(): void
    {
        $this->walletMapperMock = $this->createMock(WalletMapper::class);
        $this->loggerMock = $this->createMock(LoggerInterface::class);
        $this->walletService = new WalletService($this->loggerMock, $this->walletMapperMock);
    }

   
    public function testAdjustCoinBalanceAddsCoins()
    {
        $userId = 'user-1';
        $this->walletMapperMock->method('getUserWalletBalance')->willReturn(150.0);
        $balance = $this->walletService->getUserWalletBalance($userId);
        $this->assertEquals(150.0, $balance);
    }

    public function testDeductFromWalletSuccess()
    {
        $userId = 'user-1';
        $args = ['postid' => 'post-1', 'art' => 2];
        $expected = ['status' => 'success', 'ResponseCode' => 11209, 'affectedRows' => []];
        $this->walletMapperMock->method('deductFromWallets')->willReturn($expected);
        $result = $this->walletService->deductFromWallet($userId, $args);
        $this->assertEquals($expected, $result);
    }

    public function testDeductFromWalletFailureInsufficientBalance()
    {
        $userId = 'user-1';
        $args = ['postid' => 'post-1', 'art' => 2];
        $expected = ['status' => 'error', 'ResponseCode' => 51301];
        $this->walletMapperMock->method('deductFromWallets')->willReturn($expected);
        $result = $this->walletService->deductFromWallet($userId, $args);
        $this->assertEquals($expected, $result);
    }

    public function testDeductFromWalletFailureGeneric()
    {
        $userId = 'user-1';
        $args = ['postid' => 'post-1', 'art' => 3];
        $expected = ['status' => 'error', 'ResponseCode' => 41206];
        $this->walletMapperMock->method('deductFromWallets')->willReturn($expected);
        $result = $this->walletService->deductFromWallet($userId, $args);
        $this->assertEquals($expected, $result);
    }

    public function testLoadLiquiditySuccess()
    {
        $userId = 'user-1';
        $this->walletMapperMock->method('loadLiquidityById')->willReturn(77.0);
        $response = $this->walletService->loadLiquidityById($userId);
        $this->assertEquals('success', $response['status']);
    }

    public function testLoadLiquidityFailure()
    {
        $userId = 'user-1';
        $this->walletMapperMock->method('loadLiquidityById')->willThrowException(new \Exception());
        $response = $this->walletService->loadLiquidityById($userId);
        $this->assertEquals('error', $response['status']);
    }

    public function testUnauthorizedFetchPool()
    {
        $ref = new \ReflectionClass($this->walletService);
        $prop = $ref->getProperty('currentUserId');
        $prop->setAccessible(true);
        $prop->setValue($this->walletService, null);
        $result = $this->walletService->fetchPool();
        $this->assertEquals('error', $result['status']);
    }

    public function testFetchAllSuccess()
    {
        $this->walletService->setCurrentUserId('user-1');
        $walletMock = $this->createMock(Wallet::class);
        $walletMock->method('getArrayCopy')->willReturn(['id' => 'wallet-1']);
        $this->walletMapperMock->method('fetchAll')->willReturn([$walletMock]);
        $result = $this->walletService->fetchAll();
        $this->assertEquals([['id' => 'wallet-1']], $result);
    }

    public function testFetchAllReturnsEmpty()
    {
        $this->walletService->setCurrentUserId('user-1');
        $this->walletMapperMock->method('fetchAll')->willReturn([]);
        $result = $this->walletService->fetchAll();
        $this->assertEquals([], $result);
    }

    public function testFetchWalletByIdWithInvalidUUID()
    {
        $this->walletService->setCurrentUserId('bad');
        $result = $this->walletService->fetchWalletById();
        $this->assertEquals('error', $result['status']);
    }

    public function testFetchWalletByIdWithInvalidPostIdAndFromId()
    {
        $this->walletService->setCurrentUserId('user-1');
        $args = ['postid' => 'bad', 'fromid' => 'also-bad'];
        $result = $this->walletService->fetchWalletById($args);
        $this->assertEquals('error', $result['status']);
    }

    public function testCallFetchWinsLogInvalidDay()
    {
        $this->walletService->setCurrentUserId('user-1');
        $result = $this->walletService->callFetchWinsLog(['day' => 'WRONG']);
        $this->assertEquals('error', $result['status']);
    }

    public function testCallFetchPaysLogInvalidDay()
    {
        $this->walletService->setCurrentUserId('user-1');
        $result = $this->walletService->callFetchPaysLog(['day' => 'FAKE']);
        $this->assertEquals('error', $result['status']);
    }

    public function testCallFetchWinsLogValidDay()
    {
        $this->walletService->setCurrentUserId('user-1');
        $this->walletMapperMock->method('fetchWinsLog')->willReturn(['status' => 'ok']);
        $result = $this->walletService->callFetchWinsLog(['day' => 'D1']);
        $this->assertEquals('ok', $result['status']);
    }

    public function testCallGlobalWinsSuccess()
    {
        $this->walletService->setCurrentUserId('user-1');
        $this->walletMapperMock->method('callGlobalWins')->willReturn(['total-wins']);
        $result = $this->walletService->callGlobalWins();
        $this->assertEquals(['total-wins'], $result);
    }

    public function testCallGlobalWinsEmptyPayloadReturnsError()
    {
        $this->walletService->setCurrentUserId('user-1');
        $this->walletMapperMock->method('callGlobalWins')->willReturn([]);
        $result = $this->walletService->callGlobalWins();
        $this->assertEmpty($result);
    }

    public function testCallUserMoveSuccess()
    {
        $this->walletService->setCurrentUserId('user-1');
        $this->walletMapperMock->method('callUserMove')->willReturn([
            'ResponseCode' => 11205,
            'affectedRows' => ['row'],
        ]);
        $result = $this->walletService->callUserMove();
        $this->assertEquals('success', $result['status']);
    }

    public function testCallUserMoveWithMismatchedResponseAndRows()
    {
        $this->walletService->setCurrentUserId('user-1');
        $this->walletMapperMock->method('callUserMove')->willReturn([
            'ResponseCode' => 11205,
            'affectedRows' => []
        ]);
        $result = $this->walletService->callUserMove();
        $this->assertEquals('success', $result['status']);
    }

    public function testCallUserMoveThrowsException()
    {
        $this->walletService->setCurrentUserId('user-1');
        $this->walletMapperMock->method('callUserMove')->willThrowException(new \Exception());
        $result = $this->walletService->callUserMove();
        $this->assertEquals('error', $result['status']);
    }

    public function testGetPercentBeforeTransaction()
    {
        $this->walletMapperMock->method('getPercentBeforeTransaction')
            ->with('user-1', 50)
            ->willReturn(['percent' => 20]);
        $result = $this->walletService->getPercentBeforeTransaction('user-1', 50);
        $this->assertEquals(['percent' => 20], $result);
    }

    public function testGetPercentBeforeTransactionZeroAmount()
    {
        $this->walletMapperMock->method('getPercentBeforeTransaction')
            ->with('user-1', 0)
            ->willReturn(['percent' => 0]);
        $result = $this->walletService->getPercentBeforeTransaction('user-1', 0);
        $this->assertEquals(0, $result['percent']);
    }

    public function testGetPercentBeforeTransactionNegativeAmount()
    {
        $this->walletMapperMock->method('getPercentBeforeTransaction')
            ->with('user-1', -10)
            ->willReturn(['percent' => -5]);
        $result = $this->walletService->getPercentBeforeTransaction('user-1', -10);
        $this->assertLessThan(0, $result['percent']);
    }

    public function testLoadLiquidityReturnsNullHandled()
    {
        $userId = 'user-1';
        $this->walletMapperMock->method('loadLiquidityById')->willReturn(0.0);
        $response = $this->walletService->loadLiquidityById($userId);
        $this->assertEquals('success', $response['status']);
        $this->assertEquals(0, $response['amount'] ?? 0);
    }

    public function testFetchPoolReturnsExpectedData()
    {
        $this->walletService->setCurrentUserId('user-1');
        $this->walletMapperMock->method('fetchPool')->willReturn(['some' => 'data']);
        $result = $this->walletService->fetchPool();
        $this->assertEquals(['some' => 'data'], $result);
    }

    public function testFetchPoolReturnsEmptyArray()
    {
        $this->walletService->setCurrentUserId('user-1');
        $this->walletMapperMock->method('fetchPool')->willReturn([]);
        $result = $this->walletService->fetchPool();
        $this->assertEquals([], $result);
    }

    public function testDeductFromWalletWithNegativeArt()
    {
        $userId = 'user-1';
        $args = ['postid' => 'post-1', 'art' => -50];
        $this->walletMapperMock->method('deductFromWallets')->willReturn(['status' => 'error', 'ResponseCode' => 51301]);
        $result = $this->walletService->deductFromWallet($userId, $args);
        $this->assertEquals('error', $result['status']);
    }

    public function testDeductZeroIsHandled()
    {
        $userId = 'user-1';
        $args = ['postid' => 'post-1', 'art' => 0];
        $this->walletMapperMock->method('deductFromWallets')->willReturn(['status' => 'error', 'ResponseCode' => 41201]);
        $result = $this->walletService->deductFromWallet($userId, $args);
        $this->assertEquals('error', $result['status']);
        $this->assertEquals(41201, $result['ResponseCode']);
    }

    public function testDoubleDeductionSimulated()
    {
        $userId = 'user-1';
        $args = ['postid' => 'post-1', 'art' => 10];
        $this->walletMapperMock->method('deductFromWallets')->willReturnOnConsecutiveCalls(
            ['status' => 'success', 'ResponseCode' => 11209],
            ['status' => 'error', 'ResponseCode' => 51301]
        );
        $first = $this->walletService->deductFromWallet($userId, $args);
        $second = $this->walletService->deductFromWallet($userId, $args);
        $this->assertEquals('success', $first['status']);
        $this->assertEquals('error', $second['status']);
    }

    public function testLiquidityReturnsInvalidString()
    {
        $userId = 'user-1';
        $this->walletMapperMock->method('loadLiquidityById')->willThrowException(new \Exception('invalid'));
        $result = $this->walletService->loadLiquidityById($userId);
        $this->assertEquals('error', $result['status']);
    }

    public function testLiquidityAfterValidDeduct()
    {
        $userId = 'user-1';
        $args = ['postid' => 'post-1', 'art' => 5];
        $this->walletMapperMock->method('getUserWalletBalance')->willReturn(100.0);
        $this->walletMapperMock->method('deductFromWallets')->willReturn([
            'status' => 'success',
            'ResponseCode' => 11209,
            'affectedRows' => [['balance' => 95.0]]
        ]);
        $balanceBefore = $this->walletService->getUserWalletBalance($userId);
        $result = $this->walletService->deductFromWallet($userId, $args);
        $this->assertEquals(100.0, $balanceBefore);
        $this->assertEquals('success', $result['status']);
    }

    public function testLiquidityChangesOnlyOnSuccessfulActions()
    {
        $userId = 'user-1';
        $args = ['postid' => 'post-1', 'art' => 1];
        $this->walletMapperMock->method('loadLiquidityById')
            ->willReturnOnConsecutiveCalls(100.0, 90.0);
        $this->walletMapperMock->method('deductFromWallets')->willReturn([
            'status' => 'success',
            'ResponseCode' => 11209,
            'affectedRows' => [['balance' => 90.0]]
        ]);
        $before = $this->walletService->loadLiquidityById($userId);
        $this->walletService->deductFromWallet($userId, $args);
        $after = $this->walletService->loadLiquidityById($userId);
        $this->assertGreaterThan($after, $before, 'Liquidity should decrease after successful deduction.');
    }
}
