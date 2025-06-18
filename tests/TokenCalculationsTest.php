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
        $btcOut = SwapTokenHelper::calculateBtc(0.000001, 1000000.0, 10000.0);
        $this->assertSame(0.00000001, $btcOut); // 1 satoshi
    }
    

    public function testCalculateBtcZeroSwap()
    {
        $btcOut = SwapTokenHelper::calculateBtc(50.0, 100.0, 0.0);
        $this->assertSame(0.0, $btcOut);
    }

    // Swapping 1 token with 1% LP fee in a 50 BTC / 100 Token pool
    // Expected result is calculated using constant-product AMM math
    public function testCalculateBtcSimpleCase()
    {
        $btcOut = SwapTokenHelper::calculateBtc(50.0, 100.0, 1.0);
        $this->assertSame(0.495000495, $btcOut);
    }

    // Swapping 1000 tokens in a large pool to ensure math holds under scale
    public function testCalculateBtcLargeSwap()
    {
        $btcOut = SwapTokenHelper::calculateBtc(100.0, 10000.0, 1000.0);
        $this->assertSame(9.082652134, $btcOut);
    }

    // ------------------ TokenHelper::calculatePeerTokenEURPrice ------------------

    // BTC price = 30,000 EUR; 1 Peer = 0.0001 BTC => Peer = 3 EUR
    public function testCalculatePeerTokenEURPrice()
    {
        $price = TokenHelper::calculatePeerTokenEURPrice(30000.0, 0.0001);
        $this->assertSame(3.0, $price);
    }

    // BTC price = 0 should return 0 EUR
    public function testCalculatePeerTokenEURPriceZero()
    {
        $price = TokenHelper::calculatePeerTokenEURPrice(0.0, 0.0001);
        $this->assertSame(0.0, $price);
    }

    // ------------------ TokenHelper::calculatePeerTokenPriceValue ------------------

    // Exact division: 2 BTC / 8 Tokens = 0.25 BTC per token
    public function testCalculatePeerTokenPriceValueExact()
    {
        $price = TokenHelper::calculatePeerTokenPriceValue(2.0, 8.0);
        $this->assertSame(0.25, $price);
    }

    // Repeating decimal: 1 / 3 = 0.3333333333 (rounded to 10 digits)
    public function testCalculatePeerTokenPriceValueRepeating()
    {
        $price = TokenHelper::calculatePeerTokenPriceValue(1.0, 3.0);
        $this->assertSame(0.3333333333, $price);
    }

    // ------------------ TokenHelper::calculateTokenRequiredAmount ------------------

    // Apply 5% total fee (2% peer + 1% pool + 1% burn + 1% inviter)
    // Should return exactly 105.0
    public function testCalculateTokenRequiredAmountAllFees()
    {
        $result = TokenHelper::calculateTokenRequiredAmount(100.0, 0.02, 0.01, 0.01, 0.01);
        $this->assertSame(105.0, $result);
    }

    // Same as above but no inviter fee, total fee = 4%, result = 104.0
    public function testCalculateTokenRequiredAmountDefaultInviter()
    {
        $result = TokenHelper::calculateTokenRequiredAmount(100.0, 0.02, 0.01, 0.01);
        $this->assertSame(104.0, $result);
    }

    // Extra test: simulates a higher fee structure of 11%
    public function testCalculateTokenRequiredAmountOldExampleStrict()
    {
        $result = TokenHelper::calculateTokenRequiredAmount(100.0, 0.05, 0.01, 0.02, 0.03);
        $this->assertSame(111.0, $result);
    }

    // ------------------ TokenHelper::calculateSwapTokenSenderRequiredAmountIncludingFees ------------------

    // Sums up all 4 fees including inviter
    public function testCalculateSwapTokenSenderRequiredAmountWithInviter()
    {
        $total = TokenHelper::calculateSwapTokenSenderRequiredAmountIncludingFees(1.5, 100.0, 2.5, 4.0);
        $this->assertSame(108.0, $total);
    }

    // Sums up only 3 fields (inviter defaults to 0)
    public function testCalculateSwapTokenSenderRequiredAmountWithoutInviter()
    {
        $total = TokenHelper::calculateSwapTokenSenderRequiredAmountIncludingFees(2.0, 3.0, 4.0);
        $this->assertSame(9.0, $total);
    }

    // ------------------ TokenHelper::roundUp ------------------

    // Round up general values with 2 decimal precision
    public function testRoundUp()
    {
        $this->assertSame(1.24, TokenHelper::roundUp(1.234, 2));       // rounds up
        $this->assertSame(1.23, TokenHelper::roundUp(1.23, 2));        // already rounded
        $this->assertSame(0.01, TokenHelper::roundUp(0.001, 2));       // very small value
        $this->assertSame(-1.23, TokenHelper::roundUp(-1.234, 2));     // negative rounding
    }

    // ------------------ TokenHelper::roundUpFeeAmount ------------------

    // Fee-specific rounding to 2 decimals
    public function testRoundUpFeeAmount()
    {
        $this->assertSame(10.00, TokenHelper::roundUpFeeAmount(10.0));
        $this->assertSame(10.01, TokenHelper::roundUpFeeAmount(10.001));
        $this->assertSame(0.01, TokenHelper::roundUpFeeAmount(0.001));
    }

    // ------------------ TokenHelper::roundUpBTCAmount ------------------

    // BTC precision to 9 decimal places (Satoshi)
    public function testRoundUpBTCAmount()
    {
        $this->assertSame(1.23456789, TokenHelper::roundUpBTCAmount(1.23456789));      // unchanged
        $this->assertSame(1.234567891, TokenHelper::roundUpBTCAmount(1.2345678901));    // 10th digit rounds up
        $this->assertSame(0.000000001, TokenHelper::roundUpBTCAmount(0.0000000001));    // rounds up to 1 satoshi
    }
}
