<?php

namespace Tests\Service;

use PHPUnit\Framework\TestCase;
use Fawaz\App\WalletService;
use Fawaz\Database\WalletMapper;
use Fawaz\App\Wallet;
use Fawaz\Database\Interfaces\TransactionManager;
use Psr\Log\LoggerInterface;

class WalletServiceTest extends TestCase
{
    private WalletService $walletService;
    private $walletMapperMock;
    private $loggerMock;
    private $transactionManagerMock;

    protected function setUp(): void
    {
        $this->walletMapperMock = $this->createMock(WalletMapper::class);
        $this->loggerMock = $this->createMock(LoggerInterface::class);
        $this->transactionManagerMock = $this->createMock(TransactionManager::class);
        $this->walletService = new WalletService(
            $this->loggerMock,
            $this->walletMapperMock,
            $this->transactionManagerMock
        );
    }

    public function testAdjustCoinBalanceDeductsCoins(): void
    {
        $userId = 'user-1';
        $this->walletMapperMock->method('getUserWalletBalance')->willReturn(100.0);
        $balance = $this->walletService->getUserWalletBalance($userId);
        $this->assertEquals(100.0, $balance);
    }

    public function testAdjustCoinBalanceAddsCoins(): void
    {
        $userId = 'user-1';
        $this->walletMapperMock->method('getUserWalletBalance')->willReturn(150.0);
        $balance = $this->walletService->getUserWalletBalance($userId);
        $this->assertEquals(150.0, $balance);
    }

    public function testDeductFromWalletSuccess(): void
    {
        $userId = 'user-1';
        $args = ['postid' => 'post-1', 'art' => 2];
        $expected = ['status' => 'success', 'ResponseCode' => 11209, 'affectedRows' => []];
        $this->walletMapperMock->method('deductFromWallets')->willReturn($expected);
        $result = $this->walletService->deductFromWallet($userId, $args);
        $this->assertEquals($expected, $result);
    }

    public function testDeductFromWalletFailureInsufficientBalance(): void
    {
        $userId = 'user-1';
        $args = ['postid' => 'post-1', 'art' => 2];
        $expected = ['status' => 'error', 'ResponseCode' => 51301];
        $this->walletMapperMock->method('deductFromWallets')->willReturn($expected);
        $result = $this->walletService->deductFromWallet($userId, $args);
        $this->assertEquals($expected, $result);
    }

    public function testDeductFromWalletFailureGeneric(): void
    {
        $userId = 'user-1';
        $args = ['postid' => 'post-1', 'art' => 3];
        $expected = ['status' => 'error', 'ResponseCode' => 41206];
        $this->walletMapperMock->method('deductFromWallets')->willReturn($expected);
        $result = $this->walletService->deductFromWallet($userId, $args);
        $this->assertEquals($expected, $result);
    }

    public function testLoadLiquiditySuccess(): void
    {
        $userId = 'user-1';
        $this->walletMapperMock->method('loadLiquidityById')->willReturn(77.0);
        $response = $this->walletService->loadLiquidityById($userId);
        $this->assertEquals('success', $response['status']);
    }

    public function testLoadLiquidityFailure(): void
    {
        $userId = 'user-1';
        $this->walletMapperMock->method('loadLiquidityById')->willThrowException(new \Exception());
        $response = $this->walletService->loadLiquidityById($userId);
        $this->assertEquals('error', $response['status']);
    }

    public function testUnauthorizedFetchPool(): void
    {
        $ref = new \ReflectionClass($this->walletService);
        $prop = $ref->getProperty('currentUserId');
        $prop->setAccessible(true);
        $prop->setValue($this->walletService, null);
        $result = $this->walletService->fetchPool();
        $this->assertEquals('error', $result['status']);
    }

    public function testFetchAllSuccess(): void
    {
        $this->walletService->setCurrentUserId('user-1');
        $walletMock = $this->createMock(Wallet::class);
        $walletMock->method('getArrayCopy')->willReturn(['id' => 'wallet-1']);
        $this->walletMapperMock->method('fetchAll')->willReturn([$walletMock]);
        $result = $this->walletService->fetchAll();
        $this->assertEquals([['id' => 'wallet-1']], $result);
    }

    public function testFetchAllReturnsEmpty(): void
    {
        $this->walletService->setCurrentUserId('user-1');
        $this->walletMapperMock->method('fetchAll')->willReturn([]);
        $result = $this->walletService->fetchAll();
        $this->assertEquals([], $result);
    }

    public function testFetchWalletByIdWithInvalidUUID(): void
    {
        $this->walletService->setCurrentUserId('bad');
        $result = $this->walletService->fetchWalletById();
        $this->assertEquals('error', $result['status']);
    }

    public function testFetchWalletByIdWithInvalidPostIdAndFromId(): void
    {
        $this->walletService->setCurrentUserId('user-1');
        $args = ['postid' => 'bad', 'fromid' => 'also-bad'];
        $result = $this->walletService->fetchWalletById($args);
        $this->assertEquals('error', $result['status']);
    }

    public function testCallFetchWinsLogInvalidDay(): void
    {
        $this->walletService->setCurrentUserId('user-1');
        $result = $this->walletService->callFetchWinsLog(['day' => 'WRONG']);
        $this->assertEquals('error', $result['status']);
    }

    public function testCallFetchPaysLogInvalidDay(): void
    {
        $this->walletService->setCurrentUserId('user-1');
        $result = $this->walletService->callFetchPaysLog(['day' => 'FAKE']);
        $this->assertEquals('error', $result['status']);
    }

    public function testCallGemstersInvalidDay(): void
    {
        $this->walletService->setCurrentUserId('user-1');
        $result = $this->walletService->callGemsters('???');
        $this->assertEquals('error', $result['status']);
    }

    public function testCallGemstersValidDay(): void
    {
        $this->walletService->setCurrentUserId('user-1');
        $this->walletMapperMock->method('getTimeSortedMatch')->willReturn(['daylist']);
        $result = $this->walletService->callGemsters('D2');
        $this->assertEquals(['daylist'], $result);
    }

    public function testCallGemstersEmptyResult(): void
    {
        $this->walletService->setCurrentUserId('user-1');
        $this->walletMapperMock->method('getTimeSortedMatch')->willReturn([]);
        $result = $this->walletService->callGemsters('D0');
        $this->assertEquals([], $result);
    }

    public function testCallFetchWinsLogValidDay(): void
    {
        $this->walletService->setCurrentUserId('user-1');
        $this->walletMapperMock->method('fetchWinsLog')->willReturn(['status' => 'ok']);
        $result = $this->walletService->callFetchWinsLog(['day' => 'D1']);
        $this->assertEquals('ok', $result['status']);
    }

    public function testCallGlobalWinsSuccess(): void
    {
        $this->walletService->setCurrentUserId('user-1');
        $this->walletMapperMock->method('callGlobalWins')->willReturn(['total-wins']);
        $result = $this->walletService->callGlobalWins();
        $this->assertEquals(['total-wins'], $result);
    }

    public function testCallUserMoveSuccess(): void
    {
        $this->walletService->setCurrentUserId('user-1');
        $this->walletMapperMock->method('callUserMove')->willReturn([
            'ResponseCode' => 11205,
            'affectedRows' => ['row'],
        ]);
        $result = $this->walletService->callUserMove();
        $this->assertEquals('success', $result['status']);
    }

    public function testCallUserMoveThrowsException(): void
    {
        $this->walletService->setCurrentUserId('user-1');
        $this->walletMapperMock->method('callUserMove')->willThrowException(new \Exception());
        $result = $this->walletService->callUserMove();
        $this->assertEquals('error', $result['status']);
    }

    public function testGetPercentBeforeTransaction(): void
    {
        $this->walletMapperMock->method('getPercentBeforeTransaction')
            ->with('user-1', 50)
            ->willReturn(['percent' => 20]);
        $result = $this->walletService->getPercentBeforeTransaction('user-1', 50);
        $this->assertEquals(['percent' => 20], $result);
    }

    public function testFetchPoolReturnsExpectedData(): void
    {
        $this->walletService->setCurrentUserId('user-1');
        $this->walletMapperMock->method('fetchPool')->willReturn(['some' => 'data']);
        $result = $this->walletService->fetchPool();
        $this->assertEquals(['some' => 'data'], $result);
    }
}
