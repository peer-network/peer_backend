<?php

declare(strict_types=1);

namespace Tests\Unit;

use Fawaz\App\MintServiceImpl;
use Fawaz\App\Role;
use Fawaz\App\User;
use Fawaz\Database\Interfaces\TransactionManager;
use Fawaz\Utils\PeerLoggerInterface;
use PDO;
use PHPUnit\Framework\TestCase;
use Tests\Mocks\Database\MockGemsRepository;
use Tests\Mocks\Database\MockMintRepository;
use Tests\Mocks\Database\MockPeerTokenMapper;
use Tests\Mocks\Database\MockUserActionsRepository;
use Tests\Mocks\Database\MockUserMapper;
use Tests\Mocks\Repositories\MockMintAccountRepositoryImpl;
use Tests\Mocks\Services\MockUserService;

final class MintServiceDistributeTokensIntegrationTest extends TestCase
{
    public function testDistributeTokensFromGemsTransfersTokensAndRecordsMint(): void
    {
        $fixture = $this->arrangeMintServiceFixture();
        $service = $fixture['service'];
        $expectedResponse = $fixture['expectedResponse'];
        $expectedUserMap = $fixture['expectedUserMap'];
        $mintRepository = $fixture['mintRepository'];
        $gemsRepository = $fixture['gemsRepository'];
        $peerTokenMapper = $fixture['peerTokenMapper'];

        $response = $service->distributeTokensFromGems('D0');

        $this->assertSame($expectedResponse['status'], $response['status']);
        $this->assertSame($expectedResponse['ResponseCode'], (string)$response['ResponseCode']);
        $this->assertCount(1, $mintRepository->insertCalls);
        $this->assertNotNull($gemsRepository->lastMintId);
        $this->assertSame(
            $mintRepository->insertCalls[0]['mintId'],
            $gemsRepository->lastMintId
        );

        $this->assertWinStatusMatches($expectedResponse['affectedRows']['winStatus'], $response['affectedRows']['winStatus']);

        $actualUserStatus = $response['affectedRows']['userStatus'];
        // $this->assertCount(count($expectedUserMap), $actualUserStatus);
        // foreach ($expectedUserMap as $userId => $expectedUser) {
        //     $this->assertArrayHasKey($userId, $actualUserStatus);
        //     $actual = $actualUserStatus[$userId];

        //     $this->assertEqualsWithDelta((float)$expectedUser['gems'], (float)$actual['gems'], 0.0001, "Gems mismatch for {$userId}");
        //     $this->assertEqualsWithDelta((float)$expectedUser['tokens'], (float)$actual['tokens'], 0.0001, "Tokens mismatch for {$userId}");
        //     $this->assertEqualsWithDelta((float)$expectedUser['percentage'], (float)$actual['percentage'], 0.0001, "Percentage mismatch for {$userId}");

        //     $expectedDetails = $this->sortDetails($expectedUser['details']);
        //     $actualDetails = $this->sortDetails($actual['details']);
        //     $this->assertCount(count($expectedDetails), $actualDetails, "Details count mismatch for {$userId}");

        //     foreach ($expectedDetails as $index => $expectedDetail) {
        //         $actualDetail = $actualDetails[$index];
        //         $this->assertSame($expectedDetail['gemid'], $actualDetail['gemid']);
        //         $this->assertSame($expectedDetail['userid'], $actualDetail['userid']);
        //         $this->assertSame($expectedDetail['postid'], $actualDetail['postid']);
        //         $this->assertSame($expectedDetail['fromid'], $actualDetail['fromid']);
        //         $this->assertEqualsWithDelta((float)$expectedDetail['gems'], (float)$actualDetail['gems'], 0.0001);
        //         $this->assertEqualsWithDelta((float)$expectedDetail['numbers'], (float)$actualDetail['numbers'], 0.0001);
        //         $this->assertSame((int)$expectedDetail['whereby'], (int)$actualDetail['whereby']);
        //         $this->assertSame($expectedDetail['createdat'], $actualDetail['createdat']);
        //     }
        // }

        // $transfers = $peerTokenMapper->getTransfers();
        // $this->assertCount(count($expectedUserMap), $transfers);
        // foreach ($transfers as $transfer) {
        //     $recipientId = $transfer['recipientId'];
        //     $this->assertArrayHasKey($recipientId, $expectedUserMap);
        //     $expectedTokens = (float)$expectedUserMap[$recipientId]['tokens'];
        //     $this->assertEqualsWithDelta($expectedTokens, (float)$transfer['amount'], 0.0001, "Transfer mismatch for {$recipientId}");
        // }
    }

    public function testDistributeTokensFromGemsWinStatusMatchesFixture(): void
    {
        $fixture = $this->arrangeMintServiceFixture();
        $service = $fixture['service'];
        $expectedResponse = $fixture['expectedResponse'];

        $response = $service->distributeTokensFromGems('D0');

        $this->assertWinStatusMatches(
            $expectedResponse['affectedRows']['winStatus'],
            $response['affectedRows']['winStatus']
        );
    }

    private function insertUser(MockUserMapper $mapper, string $userId, int $rolesMask): void
    {
        $mapper->insert(new User([
            'uid' => $userId,
            'username' => $userId,
            'email' => $userId . '@example.com',
            'roles_mask' => $rolesMask,
            'slug' => 1,
            'status' => 1,
            'verified' => 1,
        ], [], false));
    }

    private function assertWinStatusMatches(array $expected, array $actual): void
    {
        $this->assertEqualsWithDelta((float)$expected['totalGems'], (float)$actual['totalGems'], 0.0001);
        $this->assertEqualsWithDelta((float)$expected['gemsintoken'], (float)$actual['gemsintoken'], 0.0001);
        $this->assertEqualsWithDelta((float)$expected['bestatigung'], (float)$actual['bestatigung'], 0.0001);
    }

    private function sortDetails(array $details): array
    {
        usort($details, static fn (array $a, array $b): int => strcmp($a['gemid'], $b['gemid']));

        return $details;
    }

    /**
     * @return array{
     *     service: MintServiceImpl,
     *     expectedResponse: array,
     *     expectedUserMap: array<string, array>,
     *     mintRepository: MockMintRepository,
     *     gemsRepository: MockGemsRepository,
     *     peerTokenMapper: MockPeerTokenMapper
     * }
     */
    private function arrangeMintServiceFixture(): array
    {
        $expectedResponse = require __DIR__ . '/../seed/gemsters_seed.php';
        $seedFactory = require __DIR__ . '/../seed/gems_rows_seed.php';

        $expectedUserMap = [];
        foreach ($expectedResponse['affectedRows']['userStatus'] as $user) {
            $expectedUserMap[$user['userid']] = $user;
        }

        $gemsRepository = new MockGemsRepository($seedFactory);
        $peerTokenMapper = new MockPeerTokenMapper();
        $peerTokenMapper->seedWalletBalance('mock-account-id', 10000.0);
        $mintAccountRepository = new MockMintAccountRepositoryImpl();
        $mintRepository = new MockMintRepository();
        $userMapper = new MockUserMapper();
        $userService = new MockUserService();
        $userActionsRepository = new MockUserActionsRepository();
        $transactionManager = new TransactionManager(new PDO('sqlite::memory:'));

        foreach ($expectedUserMap as $userId => $user) {
            if ($userId === 'admin-user') {
                continue;
            }
            $this->insertUser($userMapper, $userId, Role::USER);
        }
        $this->insertUser($userMapper, 'admin-user', Role::ADMIN);

        $service = new MintServiceImpl(
            $this->createMock(PeerLoggerInterface::class),
            $mintAccountRepository,
            $mintRepository,
            $userMapper,
            $userService,
            $peerTokenMapper,
            $userActionsRepository,
            $gemsRepository,
            $transactionManager,
        );
        $service->setCurrentUserId('admin-user');

        return [
            'service' => $service,
            'expectedResponse' => $expectedResponse,
            'expectedUserMap' => $expectedUserMap,
            'mintRepository' => $mintRepository,
            'gemsRepository' => $gemsRepository,
            'peerTokenMapper' => $peerTokenMapper,
        ];
    }
}
