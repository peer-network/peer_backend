<?php

use PHPUnit\Framework\TestCase;
use Fawaz\Database\WalletMapper;

class WalletMapperTest extends TestCase
{
    private WalletMapper $walletMapperMock;

    protected function setUp(): void
    {
        $this->walletMapperMock = $this->createMock(WalletMapper::class);
    }

    public function testCallUserMoveWorks()
    {
        $userId = 'user-123';
        $this->walletMapperMock->method('callUserMove')->with($userId)->willReturn([
            'ResponseCode' => 11205,
            'affectedRows' => ['totalInteractions' => 5]
        ]);

        $result = $this->walletMapperMock->callUserMove($userId);
        $this->assertEquals(11205, $result['ResponseCode']);
    }

    public function testCallUserMoveReturnsWeirdResponseCode()
    {
        $userId = 'user-123';
        $this->walletMapperMock->method('callUserMove')->willReturn([
            'ResponseCode' => 99999,
            'affectedRows' => []
        ]);

        $result = $this->walletMapperMock->callUserMove($userId);
        $this->assertEquals(99999, $result['ResponseCode']);
    }

    public function testCallGlobalWinsWorks()
    {
        $this->walletMapperMock->method('callGlobalWins')->willReturn([
            'ResponseCode' => 11206,
            'inserted' => 3
        ]);

        $result = $this->walletMapperMock->callGlobalWins();
        $this->assertEquals(11206, $result['ResponseCode']);
    }

    public function testCallGlobalWinsReturnsEmptyInsert()
    {
        $this->walletMapperMock->method('callGlobalWins')->willReturn([
            'ResponseCode' => 21205,
            'inserted' => 0
        ]);

        $result = $this->walletMapperMock->callGlobalWins();
        $this->assertEquals(0, $result['inserted']);
    }

    public function testGetTimeSortedMatchWorks()
    {
        $this->walletMapperMock->method('getTimeSortedMatch')->with('D1')->willReturn([
            ['userId' => 'user-123', 'gems' => 5]
        ]);

        $result = $this->walletMapperMock->getTimeSortedMatch('D1');
        $this->assertEquals(5, $result[0]['gems']);
    }

    public function testGetTimeSortedMatchEmpty()
    {
        $this->walletMapperMock->method('getTimeSortedMatch')->willReturn([]);

        $result = $this->walletMapperMock->getTimeSortedMatch('D1');
        $this->assertEmpty($result);
    }

    public function testGetTimeSortedWorks()
    {
        $this->walletMapperMock->method('getTimeSorted')->willReturn([
            ['userId' => 'user-123', 'gems' => 7]
        ]);

        $result = $this->walletMapperMock->getTimeSorted();
        $this->assertEquals('user-123', $result[0]['userId']);
    }

    public function testFetchPoolWorks()
    {
        $args = ['userid' => 'user-123'];
        $this->walletMapperMock->method('fetchPool')->with($args)->willReturn(['pool' => 105.7]);

        $result = $this->walletMapperMock->fetchPool($args);
        $this->assertEquals(105.7, $result['pool']);
    }

    public function testFetchPoolThrowsException()
    {
        $args = ['userid' => 'user-123'];
        $this->walletMapperMock->method('fetchPool')->willThrowException(new \Exception('DB error'));

        $this->expectException(\Exception::class);
        $this->walletMapperMock->fetchPool($args);
    }

    public function testDeductFromWalletsWorks()
    {
        $userId = 'user-123';
        $args = ['postid' => 'post-123', 'art' => 2];
        $this->walletMapperMock->method('deductFromWallets')->with($userId, $args)->willReturn([
            'status' => 'success',
            'ResponseCode' => 11209
        ]);

        $result = $this->walletMapperMock->deductFromWallets($userId, $args);
        $this->assertEquals('success', $result['status']);
    }

    public function testDeductFromWalletsMissingPostId()
    {
        $userId = 'user-123';
        $args = ['art' => 2];
        $this->walletMapperMock->method('deductFromWallets')->with($userId, $args)->willReturn([
            'status' => 'error',
            'ResponseCode' => 41206
        ]);

        $result = $this->walletMapperMock->deductFromWallets($userId, $args);
        $this->assertEquals('error', $result['status']);
    }

    public function testLoadLiquidityByIdWorks()
    {
        $userId = 'user-123';
        $this->walletMapperMock->method('loadLiquidityById')->with($userId)->willReturn(77.0);

        $result = $this->walletMapperMock->loadLiquidityById($userId);
        $this->assertEquals(77.0, $result);
    }

    public function testGetUserWalletBalanceWorks()
    {
        $userId = 'user-123';
        $this->walletMapperMock->method('getUserWalletBalance')->with($userId)->willReturn(200.0);

        $result = $this->walletMapperMock->getUserWalletBalance($userId);
        $this->assertEquals(200.0, $result);
    }

    public function testGetUserWalletBalanceZero()
    {
        $userId = 'user-123';
        $this->walletMapperMock->method('getUserWalletBalance')->with($userId)->willReturn(0.0);

        $result = $this->walletMapperMock->getUserWalletBalance($userId);
        $this->assertSame(0.0, $result);
    }

    public function testGetPercentBeforeTransactionWorks()
    {
        $userId = 'user-123';
        $amount = 100;
        $this->walletMapperMock->method('getPercentBeforeTransaction')->with($userId, $amount)->willReturn([
            'percent' => 10
        ]);

        $result = $this->walletMapperMock->getPercentBeforeTransaction($userId, $amount);
        $this->assertEquals(10, $result['percent']);
    }

    public function testGetPercentBeforeTransactionNegativeAmount()
    {
        $userId = 'user-123';
        $amount = -50;
        $this->walletMapperMock->method('getPercentBeforeTransaction')->with($userId, $amount)->willReturn([
            'percent' => 0
        ]);

        $result = $this->walletMapperMock->getPercentBeforeTransaction($userId, $amount);
        $this->assertEquals(0, $result['percent']);
    }

    public function testFetchAllWorks()
    {
        $args = ['userid' => 'user-123'];
        $this->walletMapperMock->method('fetchAll')->with($args)->willReturn([
            ['id' => 'wallet-1'], ['id' => 'wallet-2']
        ]);

        $result = $this->walletMapperMock->fetchAll($args);
        $this->assertCount(2, $result);
    }

    public function testFetchAllReturnsEmptyList()
    {
        $args = ['userid' => 'user-123'];
        $this->walletMapperMock->method('fetchAll')->with($args)->willReturn([]);

        $result = $this->walletMapperMock->fetchAll($args);
        $this->assertEmpty($result);
    }
}
