<?php

use PHPUnit\Framework\TestCase;
use Fawaz\Utils\TokenCalculations\SwapTokenHelper;
use Fawaz\Utils\TokenCalculations\TokenHelper;

class TokenCalculationsTest extends TestCase
{
    // ------------------ SwapTokenHelper::calculateBtc ------------------

    // Realistic case when everything is bad and everyone cashes out Artem included 
    public function testCalculateBtc100Swap()
    {
        $btcOut = SwapTokenHelper::calculateBtc(
            TokenHelper::convertToQ96(10000.0),
            TokenHelper::convertToQ96(100000.0),
            TokenHelper::convertToQ96(1000.0)
        );
        $btcOut = (float) TokenHelper::decodeFromQ96($btcOut);
        $this->assertSame(99.000099000, $btcOut);
    }


    public function testCalculateBtcZeroSwap()
    {
        $btcOut = SwapTokenHelper::calculateBtc(
            TokenHelper::convertToQ96(50.0),
            TokenHelper::convertToQ96(100.0),
            TokenHelper::convertToQ96(0.0)
        );
        $expected = TokenHelper::convertToQ96(0.0);
        $this->assertSame($expected, $btcOut);
    }


    // Swapping 1 token with 1% LP fee in a 50 BTC / 100 Token pool
    // Expected result is calculated using constant-product AMM math
    public function testCalculateBtcSimpleCase()
    {
        $btcOut = SwapTokenHelper::calculateBtc(
            TokenHelper::convertToQ96(50.0),
            TokenHelper::convertToQ96(100.0),
            TokenHelper::convertToQ96(1.0)
        );
        $btcOut =  (float)  TokenHelper::decodeFromQ96($btcOut);
        $this->assertSame(0.495000495, $btcOut);
    }


    // Swapping 1000 tokens in a large pool to ensure math holds under scale
    public function testCalculateBtcLargeSwap()
    {
        $btcOut = SwapTokenHelper::calculateBtc(
            TokenHelper::convertToQ96(100.0),
            TokenHelper::convertToQ96(10000.0),
            TokenHelper::convertToQ96(1000.0)
        );
        $btcOut = (float) TokenHelper::decodeFromQ96($btcOut);
        $this->assertSame(9.082652134, $btcOut);
    }

    // ------------------ TokenHelper::calculatePeerTokenEURPrice ------------------

    // BTC price = 30,000 EUR; 1 Peer = 0.0001 BTC => Peer = 3 EUR
    public function testCalculatePeerTokenEURPrice()
    {
        $price = TokenHelper::calculatePeerTokenEURPrice(30000.0, 0.0001);
        $price = (float) TokenHelper::decodeFromQ96($price);
        $this->assertSame(3.0, $price);
    }


    // BTC price = 0 should return 0 EUR

    public function testCalculatePeerTokenEURPriceZero()
    {
        $price = TokenHelper::calculatePeerTokenEURPrice(0.0, 0.0001);
        $price = (float) TokenHelper::decodeFromQ96($price);
        $this->assertSame(0.000000000, $price);
    }


    // ------------------ TokenHelper::calculatePeerTokenPriceValue ------------------

    // Exact division: 2 BTC / 8 Tokens = 0.25 BTC per token
    public function testCalculatePeerTokenPriceValueExact()
    {
        $price = TokenHelper::calculatePeerTokenPriceValue(
            TokenHelper::convertToQ96(2.0),
            TokenHelper::convertToQ96(8.0)
        );
        $this->assertSame('0.250000000', $price);
    }


    // Repeating decimal: 1 / 3 = 0.3333333333 (rounded to 10 digits)
    public function testCalculatePeerTokenPriceValueRepeating()
    {
        $price = TokenHelper::calculatePeerTokenPriceValue(
            TokenHelper::convertToQ96(1.0),
            TokenHelper::convertToQ96(3.0)
        );
        $this->assertSame('0.333333333', $price);
    }

    // ------------------ TokenHelper::calculateTokenRequiredAmount ------------------

    // Apply 5% total fee (2% peer + 1% pool + 1% burn + 1% inviter)
    // Should return exactly 105.0
    public function testCalculateTokenRequiredAmountAllFees()
    {
        $result = TokenHelper::calculateTokenRequiredAmount(
            TokenHelper::convertToQ96(100.0),
            0.02, 0.01, 0.01, 0.01
        );
        $result = (float) TokenHelper::decodeFromQ96($result);
        $this->assertSame(105.0, $result);
    }

    // Same as above but no inviter fee, total fee = 4%, result = 104.0
    public function testCalculateTokenRequiredAmountDefaultInviter()
    {
        $result = TokenHelper::calculateTokenRequiredAmount(
            TokenHelper::convertToQ96(100.0),
            0.02, 0.01, 0.01
        );
        $result = (float) TokenHelper::decodeFromQ96($result);
        $this->assertSame(104.0, $result);
    }

    // Extra test: simulates a higher fee structure of 11%
    public function testCalculateTokenRequiredAmountOldExampleStrict()
    {
        $result = TokenHelper::calculateTokenRequiredAmount(
            TokenHelper::convertToQ96(100.0),
            0.05, 0.01, 0.02, 0.03
        );
        $result = (float) TokenHelper::decodeFromQ96($result);
        $this->assertSame(111.0, $result);
    }


    // ------------------ TokenHelper::calculateSwapTokenSenderRequiredAmountIncludingFees ------------------

    // Sums up all 4 fees including inviter
    public function testCalculateSwapTokenSenderRequiredAmountWithInviter()
    {
        $total = TokenHelper::calculateSwapTokenSenderRequiredAmountIncludingFees(
            1.0, 2.0, 1.0, 1.0
        );
        $expected = TokenHelper::convertToQ96(5.0);
        $this->assertSame($expected, $total);
    }

    // Sums up only 3 fields (inviter defaults to 0)
    public function testCalculateSwapTokenSenderRequiredAmountWithoutInviter()
    {
        $total = TokenHelper::calculateSwapTokenSenderRequiredAmountIncludingFees(
            2.0,
            3.0,
            4.0
        );
        $expected = TokenHelper::convertToQ96(9.0);
        $this->assertSame($expected, $total);
    }


}
