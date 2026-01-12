<?php

declare(strict_types=1);

namespace Tests\Unit;

use Fawaz\App\DTO\Gems;
use Fawaz\App\DTO\GemsInTokenResult;
use Fawaz\App\DTO\GemsRow;
use Fawaz\App\DTO\UncollectedGemsResult;
use Fawaz\App\DTO\UncollectedGemsRow;
use Fawaz\App\MintServiceImpl;
use Fawaz\App\Repositories\MintAccountRepositoryInterface;
use Fawaz\Database\GemsRepository;
use Fawaz\Database\Interfaces\TransactionManager;
use Fawaz\Database\MintRepository;
use Fawaz\Database\PeerTokenMapperInterface;
use Fawaz\Database\UserActionsRepository;
use Fawaz\Database\UserMapperInterface;
use Fawaz\Database\WalletMapper;
use Fawaz\Utils\PeerLoggerInterface;
use PHPUnit\Framework\TestCase;
use Tests\Support\UncollectedGemsFactory;

/**
 * Unit tests for MintServiceImpl::calculateGemsInToken.
 *
 * Covers scenarios with single and multiple users, mixed positive/negative
 * gem events, and verifies computed fields: totalGems, gemsInToken, and
 * confirmation. Assumes DAILY_NUMBER_TOKEN is 5000 for expected values.
 */
final class MintServiceImplTest extends TestCase
{
    private const DELTA = 0.00000001;
    private MintServiceImpl $service;
    private \ReflectionMethod $calculateGemsInToken;
    private \ReflectionMethod $buildUncollectedGemsResult;

    protected function setUp(): void
    {
        parent::setUp();
        $ref = new \ReflectionClass(MintServiceImpl::class);
        $this->calculateGemsInToken = $ref->getMethod('calculateGemsInToken');
        $this->calculateGemsInToken->setAccessible(true);
        $this->buildUncollectedGemsResult = $ref->getMethod('buildUncollectedGemsResult');
        $this->buildUncollectedGemsResult->setAccessible(true);

        $this->service = new MintServiceImpl(
            $this->createMock(PeerLoggerInterface::class),
            $this->createMock(MintAccountRepositoryInterface::class),
            $this->createMock(MintRepository::class),
            $this->createMock(UserMapperInterface::class),
            $this->createMock(PeerTokenMapperInterface::class),
            $this->createMock(UserActionsRepository::class),
            $this->createMock(GemsRepository::class),
            $this->createMock(TransactionManager::class),
            $this->createMock(WalletMapper::class),
            new \PDO('sqlite::memory:')
        );
    }

    public function testBuildUncollectedGemsResult_singlePositiveUser(): void
    {
        $gems = $this->makeGems([
            'u1' => [0.25],
        ]);

        /** @var UncollectedGemsResult $result */
        $result = $this->buildUncollectedGemsResult->invoke($this->service, $gems);

        $this->assertSame('0.25', $result->overallTotal);
        $this->assertCount(1, $result->rows);
        $row = $result->rows[0];
        $this->assertSame('u1', $row->userid);
        $this->assertSame('0.25', $row->totalGems);
        $this->assertSame('100', $row->percentage);
        $this->assertSame('0.25', $row->overallTotal);
    }

    public function testBuildUncollectedGemsResult_filtersNegativeUsers(): void
    {
        $gems = $this->makeGems([
            'u1' => [0.25, 2.0, 5.0],
            'u2' => [-3.0, 0.25],
        ]);

        /** @var UncollectedGemsResult $result */
        $result = $this->buildUncollectedGemsResult->invoke($this->service, $gems);

        $this->assertSame('7.25', $result->overallTotal);
        $this->assertCount(3, $result->rows);
        foreach ($result->rows as $row) {
            $this->assertSame('u1', $row->userid);
            $this->assertSame('7.25', $row->totalGems);
            $this->assertSame('100', $row->percentage);
        }
    }

    public function testBuildUncollectedGemsResult_allNegativeTotalsReturnsEmpty(): void
    {
        $gems = $this->makeGems([
            'u1' => [-3.0],
            'u2' => [-2.0, 1.5, -0.5],
        ]);

        /** @var UncollectedGemsResult $result */
        $result = $this->buildUncollectedGemsResult->invoke($this->service, $gems);

        $this->assertSame('0', $result->overallTotal);
        $this->assertCount(0, $result->rows);
    }

    public function testCalculateGemsInToken_singleUser_quarterGem(): void
    {
        $uncollected = UncollectedGemsFactory::makeFiveUsersSample([
            'u1' => [0.25]
        ]);

        /** @var GemsInTokenResult $result */
        $result = $this->calculateGemsInToken->invoke(null, $uncollected);

        // Assert:
        // DAILY_NUMBER_TOKEN is 5000.0; 5000 / 0.25 = 20000 gems per token;
        // confirmation = totalGems * gemsInToken = 0.25 * 20000 = 5000
        $this->assertSame('0.25', $result->totalGems);

        // Use numeric comparison for robustness against trailing decimals
        $this->assertEquals(20000.0, (float)$result->gemsInToken);
        $this->assertEquals(5000.0, (float)$result->confirmation);
    }

    public function testCalculateGemsInToken_twoUsers_quarterEach(): void
    {
        $uncollected = UncollectedGemsFactory::makeFiveUsersSample([
            'u1' => [0.25],
            'u2' => [0.25],
        ]);

        /** @var GemsInTokenResult $result */
        $result = $this->calculateGemsInToken->invoke(null, $uncollected);

        // Assert:
        // totalGems = 0.5
        // DAILY_NUMBER_TOKEN = 5000; gemsInToken = 5000 / 0.5 = 10000
        // confirmation = totalGems * gemsInToken = 0.5 * 10000 = 5000
        $this->assertSame('0.5', $result->totalGems);
        $this->assertEquals(10000.0, (float)$result->gemsInToken);
        $this->assertEquals(5000.0, (float)$result->confirmation);
    }

    public function testCalculateGemsInToken_twoUsers_mixedGems(): void
    {
        $uncollected = UncollectedGemsFactory::makeFiveUsersSample([
            'u1' => [0.25],
            'u2' => [0.25, 2.0, 5.0],
        ]);

        /** @var GemsInTokenResult $result */
        $result = $this->calculateGemsInToken->invoke(null, $uncollected);

        // DAILY_NUMBER_TOKEN = 5000; gemsInToken = 5000 / 7.5 = 666.666...
        $this->assertSame('7.5', $result->totalGems);
        $this->assertEqualsWithDelta(666.6666666666, (float)$result->gemsInToken, self::DELTA);
        $this->assertEqualsWithDelta(5000.0, (float)$result->confirmation, self::DELTA);
    }

    public function testCalculateGemsInToken_threeUsers_mixedGems(): void
    {
        $uncollected = UncollectedGemsFactory::makeFiveUsersSample([
            'u1' => [0.25],
            'u2' => [0.25, 2.0, 5.0],
            'u3' => [0.25],
        ]);

        /** @var GemsInTokenResult $result */
        $result = $this->calculateGemsInToken->invoke(null, $uncollected);

        // DAILY_NUMBER_TOKEN = 5000; gemsInToken = 5000 / 7.75 ≈ 645.1613
        $this->assertSame('7.75', $result->totalGems);
        $this->assertEqualsWithDelta(645.1612903225, (float)$result->gemsInToken, self::DELTA);
        $this->assertEqualsWithDelta(5000.0, (float)$result->confirmation, self::DELTA);
    }

    public function testCalculateGemsInToken_oneUser_like_dislike_comment_view(): void
    {
        $uncollected = UncollectedGemsFactory::makeFiveUsersSample([
            'u1' => [-3, 2, 5, 0.25],
        ]);

        // sum of all gems is positive, result = sum of gems
        /** @var GemsInTokenResult $result */
        $result = $this->calculateGemsInToken->invoke(null, $uncollected);

        $this->assertSame('4.25', $result->totalGems);
    }

    public function testCalculateGemsInToken_twoUsers_one_with_negative_gems_amount(): void
    {

        $uncollected = $this->makeSanitizedUncollected([
            'u1' => [-3, 2, 5, 0.25],
            'u2' => [-3, 0.25],
        ]);

        // u1: sum of gems is negative => u1 gems = 0
        // so sum of tokens = u1 tokens = 4.25
        /** @var GemsInTokenResult $result */
        $result = $this->calculateGemsInToken->invoke(null, $uncollected);

        $this->assertSame('4.25', $result->totalGems);
    }

    public function testCalculateGemsInToken_twoUsers_one_with_zero_sum(): void
    {

        $uncollected = UncollectedGemsFactory::makeFiveUsersSample([
            'u1' => [-3, 2, 5, 0.25],
            'u2' => [-3, 2,0.25,0.25,0.25,0.25],
        ]);

        // u1: sum of gems is negative => u1 gems = 0
        // so sum of tokens = u1 tokens = 4.25
        /** @var GemsInTokenResult $result */
        $result = $this->calculateGemsInToken->invoke(null, $uncollected);

        $this->assertSame('4.25', $result->totalGems);
    }

    /**
     * Build sanitized UncollectedGemsResult using the service conversion logic.
     *
     * @param array<string, list<float|int>> $usersWithGems
     */
    private function makeSanitizedUncollected(array $usersWithGems): UncollectedGemsResult
    {
        $gems = $this->makeGems($usersWithGems);

        /** @var UncollectedGemsResult $result */
        $result = $this->buildUncollectedGemsResult->invoke($this->service, $gems);

        return $result;
    }

    /**
     * @param array<string, list<float|int>> $usersWithGems
     */
    private function makeGems(array $usersWithGems): Gems
    {
        $rows = [];
        $i = 1;
        foreach ($usersWithGems as $uid => $entries) {
            foreach ($entries as $value) {
                $rows[] = new GemsRow(
                    userid: (string)$uid,
                    gemid: 'g'.$i,
                    postid: 'p'.$i,
                    fromid: 'f'.$i,
                    gems: rtrim(rtrim(sprintf('%.10F', (float)$value), '0'), '.'),
                    whereby: 0,
                    createdat: '2025-01-01 00:00:00'
                );
                $i++;
            }
        }

        return new Gems($rows);
    }
}
