<?php

namespace Fawaz\Utils\TokenCalculations;

use FFI;

define('Q64_96_SCALE', bcpow('2', '96'));

class TokenHelper
{
    /**
     * Calculates the Peer Token price in EUR based on BTC-EUR and PeerToken-BTC prices.
     *
     * @param string $btcEURPrice Current BTC to EUR price.
     * @param string $peerTokenBTCPrice Current PeerToken to BTC price.
     * @return float|null Calculated PeerToken price in EUR.
     */
    public static function calculatePeerTokenEURPrice(string $btcEURPrice, string $peerTokenBTCPrice): ?string
    {
        $peerValue = self::mulRc($btcEURPrice, $peerTokenBTCPrice);

        return ($peerValue);
    }

    /**
     * Calculates the price of one Peer Token in BTC based on liquidity pool data.
     *
     * @param float $btcPoolBTCAmount Total BTC in the liquidity pool.
     * @param float $liqPoolTokenAmount Total Peer Tokens in the liquidity pool.
     * @return float|null Peer Token price in BTC with 10-digit precision.
     */
    public static function calculatePeerTokenPriceValue(string $btcPoolBTCAmount, string $liqPoolTokenAmount): ?string
    {
        $beforeToken = self::divRc($btcPoolBTCAmount, $liqPoolTokenAmount);
        
        return ($beforeToken);
    }

    /**
     * Calculates the total number of Peer Tokens required including all applicable fees.
     *
     * @param float $numberOfTokens Base amount of tokens.
     * @param float $peerFee Percentage fee for Peer.
     * @param float $poolFee Percentage fee for liquidity pool.
     * @param float $burnFee Percentage of tokens to be burned.
     * @param float $inviterFee Optional percentage for inviter reward.
     * @return float|null Total required tokens including all fees.
     */
    public static function calculateTokenRequiredAmount(
        string $numberOfTokens,
        float $peerFee,
        float $poolFee,
        float $burnFee,
        float $inviterFee = 0
    ): ?string {
        $allFees = (1 + $peerFee + $poolFee + $burnFee + $inviterFee);

        $requiredAmount = self::mulRc($numberOfTokens, $allFees);

        return $requiredAmount;
    }

    /**
     * Sums up all the fee-related amounts to calculate the total amount a sender must provide in a swap.
     *
     * @param float $feeAmount Network or service fee.
     * @param float $peerAmount Actual token amount.
     * @param float $burnAmount Burned token amount.
     * @param float $inviterAmount Optional amount for inviter reward.
     * @return float|null Total required from sender including all parts.
     */
    public static function calculateSwapTokenSenderRequiredAmountIncludingFees(
        float $feeAmount,
        float $peerAmount,
        float $burnAmount,
        float $inviterAmount = 0
    ): ?string {
        return ($feeAmount + $peerAmount + $burnAmount + $inviterAmount);
    }

    /**
     * Converts a decimal string to its Q96 fixed-point representation.
     *
     * @param string $decimal The number as a string (not float).
     * @return string Q96 fixed-point representation.
     */
    public static function convertToQ96(string $decimal): string
    {
        return bcmul($decimal, Q64_96_SCALE, 0);
    }

    /**
     * Converts a Q96 fixed-point string back to a decimal string.
     *
     * @param string $q96Value The Q96 fixed-point value.
     * @param int $precision Number of decimal places in output.
     * @return string
     */
    // public static function decodeFromQ96(string $q96Value, int $precision = 30): string
    // {
    //     return bcdiv($q96Value, Q64_96_SCALE, $precision);
    // }


    /**
     * Adds two Q96-encoded fixed-point values.
     *
     * This method assumes both values are already scaled by 2^96.
     *
     * @param string $q96Value1 First Q96-encoded value.
     * @param string $q96Value2 Second Q96-encoded value.
     * @return string Sum of the two Q96 values, as a Q96-encoded string.
     */
    public static function addRc(string $q96Value1, string $q96Value2): string
    {
        $runtIns = self::initRc();

        $result = $runtIns->add_decimal($q96Value1, $q96Value2);

        return $result;
    }


    /**
     * Multiply two Q96-encoded fixed-point values.
     *
     * This method assumes both values are already scaled by 2^96.
     *
     * @param string $q96Value1 First Q96-encoded value.
     * @param string $q96Value2 Second Q96-encoded value.
     * @return string Sum of the two Q96 values, as a Q96-encoded string.
     */
    public static function mulRc(string $q96Value1, string $q96Value2): string
    {
        $runtIns = self::initRc();

        $result = $runtIns->multiply_decimal($q96Value1, $q96Value2);

        return $result;

    }
    
    /**
     * divide two Q96-encoded fixed-point values.
     *
     * This method assumes both values are already scaled by 2^96.
     *
     * @param string $q96Value1 First Q96-encoded value.
     * @param string $q96Value2 Second Q96-encoded value.
     * @return string Sum of the two Q96 values, as a Q96-encoded string.
     */
    public static function divRc(string $q96Value1, string $q96Value2): string
    {
        $runtIns = self::initRc();

        $result = $runtIns->divide_decimal($q96Value1, $q96Value2);

        return $result;
    }

    /**
     * compare two Q96-encoded values.
     *
     * This method assumes both values are already scaled by 2^96.
     *
     * @param string $q96Value1 First Q96-encoded value.
     * @param string $q96Value2 Second Q96-encoded value.
     * @return string Sum of the two Q96 values, as a Q96-encoded string.
     */
    /**
     * Substract two Q96-encoded fixed-point values.
     *
     * This method assumes both values are already scaled by 2^96.
     *
     * @param string $q96Value1 First Q96-encoded value.
     * @param string $q96Value2 Second Q96-encoded value.
     * @return string Sum of the two Q96 values, as a Q96-encoded string.
     */
    public static function subRc(string $q96Value1, string $q96Value2): string
    {
        $runtIns = self::initRc();

        $result = $runtIns->subtract_decimal($q96Value1, $q96Value2);

        return $result;
    }

    /**
     * initialise Rust.
     *
     * This method assumes both values are already scaled by 2^96.
     *
     * @param string $q96Value1 First Q96-encoded value.
     * @param string $q96Value2 Second Q96-encoded value.
     * @return string Sum of the two Q96 values, as a Q96-encoded string.
     */
    public static function initRc(){

        if (PHP_OS_FAMILY === 'Windows') {
            $relativePath = dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'tokencalculation/target/release/tokencalculation.dll';
        } elseif (PHP_OS_FAMILY === 'Darwin') {
            $relativePath = dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'tokencalculation/target/release/libtokencalculation.dylib';
        } else {
            $relativePath = dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'tokencalculation/target/release/libtokencalculation.so';
        }
        

        // Load FFI bindings
        $ffi = FFI::cdef("
            const char* add_decimal(const char* a, const char* b);
            const char* subtract_decimal(const char* a, const char* b);
            const char* multiply_decimal(const char* a, const char* b);
            const char* divide_decimal(const char* a, const char* b);
        ", $relativePath);

        return $ffi;

    }
}