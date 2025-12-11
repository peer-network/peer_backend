<?php

declare(strict_types=1);

namespace Tests\Unit;

use Fawaz\App\DTO\GemsInTokenResult;
use Fawaz\App\DTO\UncollectedGemsResult;
use Fawaz\App\DTO\UncollectedGemsRow;
use PHPUnit\Framework\TestCase;
use Tests\Support\UncollectedGemsFactory;

final class MintServiceImplTest extends TestCase
{
    private const DELTA = 0.00000001;
    private \ReflectionMethod $calculateGemsInToken;

    protected function setUp(): void
    {
        parent::setUp();
        $ref = new \ReflectionClass(\Fawaz\App\MintServiceImpl::class);
        $this->calculateGemsInToken = $ref->getMethod('calculateGemsInToken');
    }

    public function testCalculateGemsInToken_singleUser_quarterGem(): void
    {
        // Arrange
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
        // Arrange: two users, each with 0.25 gems => total 0.5
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
        // Arrange: u1 = 0.25, u2 = 0.25 + 2 + 5 => total = 7.5
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
        // Arrange: u1 = 0.25, u2 = 0.25 + 2 + 5 = 7.25, u3 = 0.25 => total = 7.75
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

    public function testCalculateGemsInToken_oneUser_dislikeAndView(): void
    {
        // Arrange: u1 = 0.25, u2 = 0.25 + 2 + 5 = 7.25, u3 = 0.25 => total = 7.75
        $uncollected = UncollectedGemsFactory::makeFiveUsersSample([
            'u1' => [-3, 0.25],
        ]);

        /** @var GemsInTokenResult $result */
        $result = $this->calculateGemsInToken->invoke(null, $uncollected);

        $this->assertSame('0', $result->totalGems);
    }

    public function testCalculateGemsInToken_oneUser_like_dislike_comment_view(): void
    {
        // Arrange: u1 = 0.25, u2 = 0.25 + 2 + 5 = 7.25, u3 = 0.25 => total = 7.75
        $uncollected = UncollectedGemsFactory::makeFiveUsersSample([
            'u1' => [-3, 2, 5, 0.25],
        ]);

        /** @var GemsInTokenResult $result */
        $result = $this->calculateGemsInToken->invoke(null, $uncollected);

        $this->assertSame('4.25', $result->totalGems);
    }

    public function testCalculateGemsInToken_twoUsers_one_with_negative_gems_amount(): void
    {
        // Arrange: u1 = 0.25, u2 = 0.25 + 2 + 5 = 7.25, u3 = 0.25 => total = 7.75
        $uncollected = UncollectedGemsFactory::makeFiveUsersSample([
            'u1' => [-3, 2, 5, 0.25],
            'u2' => [-3, 0.25],
        ]);

        /** @var GemsInTokenResult $result */
        $result = $this->calculateGemsInToken->invoke(null, $uncollected);

        $this->assertSame('4.25', $result->totalGems);
    }

}
