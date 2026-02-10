<?php

declare(strict_types=1);

namespace Tests\Unit;

use Fawaz\Database\WalletMapper;
use Fawaz\Services\LiquidityPool;
use Fawaz\Utils\PeerLoggerInterface;
use PDO;
use PHPUnit\Framework\TestCase;

final class WalletMapperBalanceFromTransactionsTest extends TestCase
{
    public function testFetchUserWalletBalanceFromTransactions_accountsForSenderRecipientAndFees(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec('CREATE TABLE transactions (senderid TEXT, recipientid TEXT, tokenamount REAL)');

        $pdo->exec("INSERT INTO transactions (senderid, recipientid, tokenamount) VALUES ('userA', 'userB', 5.0)");
        $pdo->exec("INSERT INTO transactions (senderid, recipientid, tokenamount) VALUES ('userB', 'userA', 2.0)");
        $pdo->exec("INSERT INTO transactions (senderid, recipientid, tokenamount) VALUES ('userA', 'fees', 1.0)");
        $pdo->exec("INSERT INTO transactions (senderid, recipientid, tokenamount) VALUES ('fees', 'userA', 0.5)");

        $mapper = new WalletMapper(
            $this->createMock(PeerLoggerInterface::class),
            $pdo,
            $this->createMock(LiquidityPool::class)
        );

        $balanceA = $mapper->fetchUserWalletBalanceFromTransactions('userA');
        $balanceB = $mapper->fetchUserWalletBalanceFromTransactions('userB');

        $this->assertEqualsWithDelta(-3.5, (float) $balanceA, 0.0000001);
        $this->assertEqualsWithDelta(3.0, (float) $balanceB, 0.0000001);
    }
}
