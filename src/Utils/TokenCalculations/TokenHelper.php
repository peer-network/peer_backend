<?php

declare(strict_types=1);

namespace Fawaz\Utils\TokenCalculations;

use FFI;

/**
 * Note:
 * All calculations are done using a Rust module via FFI for precision and performance.
 *
 * We have chosen to use `strings` for all numerical values to avoid floating-point precision issues.
 * This ensures that we can handle very large or very small numbers accurately, which is crucial for financial calculations.
 */
class TokenHelper
{
    /**
     * Calculates the Peer Token price in EUR based on BTC-EUR and PeerToken-BTC prices.
     *
     * @param string $btcEURPrice Current BTC to EUR price.
     * @param string $peerTokenBTCPrice Current PeerToken to BTC price.
     * @return string Calculated PeerToken price in EUR.
     */
    public static function calculatePeerTokenEURPrice(string $btcEURPrice, string $peerTokenBTCPrice): string
    {
        $peerValue = self::mulRc($btcEURPrice, $peerTokenBTCPrice);

        return $peerValue;
    }

    /**
     * Calculates the price of one Peer Token in BTC based on liquidity pool data.
     *
     * @param string $btcPoolBTCAmount Total BTC in the liquidity pool.
     * @param string $liqPoolTokenAmount Total Peer Tokens in the liquidity pool.
     * @return string Peer Token price in BTC with 10-digit precision.
     */
    public static function calculatePeerTokenPriceValue(string $btcPoolBTCAmount, string $liqPoolTokenAmount): string
    {
        $beforeToken = self::divRc($btcPoolBTCAmount, $liqPoolTokenAmount);

        return $beforeToken;
    }


    /**
     * Calculates the total number of Peer Tokens required including all applicable fees.
     *
     * @param string $numberOfTokens Base amount of tokens.
     * @param string $peerFee Percentage fee for Peer.
     * @param string $burnFee Percentage of tokens to be burned.
     * @param string $inviterFee Optional percentage for inviter reward.
     * @return string Total required tokens including all fees.
     */
    public static function calculateTokenRequiredAmount(
        string $numberOfTokens,
        string $peerFee,
        string $burnFee,
        string $inviterFee = '0'
    ): string {
        $allFees1 = self::addRc('1', $peerFee);
        $allFees1 = self::addRc($allFees1, $burnFee);
        $allFees = self::addRc($allFees1, $inviterFee);

        $requiredAmount = self::mulRc($numberOfTokens, $allFees);

        return $requiredAmount;
    }

    /**
     * Sums up all the fee-related amounts to calculate the total amount a sender must provide in a swap.
     *
     * @param string $feeAmount Network or service fee.
     * @param string $peerAmount Actual token amount.
     * @param string $burnAmount Burned token amount.
     * @param string $inviterAmount Optional amount for inviter reward.
     * @return string Total required from sender including all parts.
     */
    public static function calculateSwapTokenSenderRequiredAmountIncludingFees(
        string $feeAmount,
        string $peerAmount,
        string $burnAmount,
        string $inviterAmount = '0'
    ): string {
        $cal1 =  self::addRc($feeAmount, $peerAmount);
        $cal2 =  self::addRc($burnAmount, $inviterAmount);
        return self::addRc($cal1, $cal2);
    }

    /**
     * Adds two values.
     *
     * @param string $value1 First  value.
     * @param string $value2 Second  value.
     * @return string Sum of the two  values, as a  string.
     */
    public static function addRc(string $value1, string $value2): string
    {
        $runtIns = self::initRc();

        $result = $runtIns->add_decimal($value1, $value2);

        if (is_numeric($result) === false) {
            throw new \RuntimeException("Error in addition operation, result is not numeric.");
        }
        return $result;
    }

    /**
     * Truncate number to 10 decimal places without rounding.
     */
    public static function truncateToTenDecimalPlaces(string $number): string
    {
        // Bind only the truncate symbol to avoid failing core ops
        $ffi = self::initTruncateRc();

        $result = $ffi->truncate_decimal($number);

        if (is_numeric($result) === false) {
            throw new \RuntimeException("Error in truncation operation, result is not numeric.");
        }
        return $result;
    }


    /**
     * Multiply two values.
     *
     * @param string $value1 First value.
     * @param string $value2 Second value.
     * @return string Sum of the two values, as a string.
     */
    public static function mulRc(string $value1, string $value2): string
    {
        $runtIns = self::initRc();

        $result = $runtIns->multiply_decimal($value1, $value2);

        if (is_numeric($result) === false) {
            throw new \RuntimeException("Error in addition operation, result is not numeric.");
        }
        return $result;

    }

    /**
     * divide two values.
     *
     * @param string $value1 First  value.
     * @param string $value2 Second  value.
     * @return string Sum of the two  values, as a  string.
     */
    public static function divRc(string $value1, string $value2): string
    {
        $runtIns = self::initRc();

        $result = $runtIns->divide_decimal($value1, $value2);

        if (is_numeric($result) === false) {
            throw new \RuntimeException("Error in addition operation, result is not numeric.");
        }
        return $result;
    }

    /**
     * Substract two  values.
     *
     * @param string $value1 First  value.
     * @param string $value2 Second  value.
     * @return string Sum of the two  values, as a  string.
     */
    public static function subRc(string $value1, string $value2): string
    {
        $runtIns = self::initRc();

        $result = $runtIns->subtract_decimal($value1, $value2);

        if (is_numeric($result) === false) {
            throw new \RuntimeException("Error in addition operation, result is not numeric.");
        }
        return $result;
    }

    /**
     * initialise Rust Module Helper.
     */
    public static function initRc()
    {

        if (PHP_OS_FAMILY === 'Windows') {
            $relativePath = dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'tokencalculation/target/release/tokencalculation.dll';
        } elseif (PHP_OS_FAMILY === 'Darwin') {
            $relativePath = dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'tokencalculation/target/release/libtokencalculation.dylib';
        } else {
            $relativePath = dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'tokencalculation/target/release/libtokencalculation.so';
        }
        


        // Load FFI bindings for core arithmetic only
        $ffi = FFI::cdef("
			const char* add_decimal(const char* a, const char* b);
			const char* subtract_decimal(const char* a, const char* b);
			const char* multiply_decimal(const char* a, const char* b);
			const char* divide_decimal(const char* a, const char* b);
		", $relativePath);

        return $ffi;

    }

    /**
     * Initialise FFI with just truncate symbol. Kept separate to avoid
     * failing core arithmetic when the deployed native library is older.
     */
    private static function initTruncateRc()
    {
        if (PHP_OS_FAMILY === 'Windows') {
            $relativePath = dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'tokencalculation/target/release/tokencalculation.dll';
        } elseif (PHP_OS_FAMILY === 'Darwin') {
            $relativePath = dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'tokencalculation/target/release/libtokencalculation.dylib';
        } else {
            $relativePath = dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'tokencalculation/target/release/libtokencalculation.so';
        }

        try {
            return FFI::cdef("
				const char* truncate_decimal(const char* a);
			", $relativePath);
        } catch (\FFI\Exception $e) {
            throw new \RuntimeException('truncate_decimal not available in native library. Please rebuild and deploy tokencalculation with that export.', 0, $e);
        }
    }
}
