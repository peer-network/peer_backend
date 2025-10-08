<?php

declare(strict_types=1);

namespace Tests\Integration;

use Fawaz\App\Repositories\WalletBalanceRepository;

class WalletBalanceRepositoryTest extends BaseIntegrationTestCase
{
    public function testSetBalanceUpsertInsertAndReturn(): void
    {
        $repo = new WalletBalanceRepository($this->logger, $this->pdo);
        $user = 'b9e94945-abd7-46a5-8c92-59037f1d73bf';
        $stored = $repo->setBalance($user, 100.25);
        $this->assertSame(100.25, $stored);
    }

    public function testSetBalanceUpsertUpdateAndReturn(): void
    {
        $repo = new WalletBalanceRepository($this->logger, $this->pdo);
        $user = '6520ac47-f262-4f7e-b643-9dc5ee4cfa82';
        $repo->setBalance($user, 10.00);
        $stored = $repo->setBalance($user, 77.00);
        $this->assertSame(77.00, $stored);
    }

    public function testAddToBalanceInsertMissingReturnsNew(): void
    {
        $repo = new WalletBalanceRepository($this->logger, $this->pdo);
        $user = 'dbe72768-0d47-4d29-99e7-b6ec4eadfaa3';
        $repo->setBalance($user, 0.0);
        $new = $repo->addToBalance($user, 5.5);
        $this->assertSame(5.5, $new);
    }

    public function testAddToBalanceUpdateExistingReturnsNew(): void
    {
        $repo = new WalletBalanceRepository($this->logger, $this->pdo);
        $user = 'b9e94945-abd7-46a5-8c92-59037f1d73bf';
        $repo->setBalance($user, 10.0);
        $new = $repo->addToBalance($user, -3.5);
        $this->assertSame(6.5, $new);
    }
}
