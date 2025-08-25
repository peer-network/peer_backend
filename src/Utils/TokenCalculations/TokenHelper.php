<?php
declare(strict_types=1);

namespace Fawaz\Utils\TokenCalculations;

use FFI;

class TokenHelper
{
    /**
     * Calculates the total number of Peer Tokens required including all applicable fees.
     *
     * @param float $numberOfTokens Base amount of tokens.
     * @param float $peerFee Percentage fee for Peer.
     * @param float $poolFee Percentage fee for liquidity pool.
     * @param float $burnFee Percentage of tokens to be burned.
     * @param float $inviterFee Optional percentage for inviter reward.
     * @return float Total required tokens including all fees.
     */
    public static function calculateTokenRequiredAmount(
        float $numberOfTokens,
        float $peerFee,
        float $poolFee,
        float $burnFee,
        float $inviterFee = 0
    ): float {
        $allFees = (1 + $peerFee + $poolFee + $burnFee + $inviterFee);

        $requiredAmount = self::mulRc($numberOfTokens, $allFees);

        return (float) $requiredAmount;
    }

    /**
     * Sums up all the fee-related amounts to calculate the total amount a sender must provide in a swap.
     *
     * @param float $feeAmount Network or service fee.
     * @param float $peerAmount Actual token amount.
     * @param float $burnAmount Burned token amount.
     * @param float $inviterAmount Optional amount for inviter reward.
     * @return float Total required from sender including all parts.
     */
    public static function calculateSwapTokenSenderRequiredAmountIncludingFees(
        float $feeAmount,
        float $peerAmount,
        float $burnAmount,
        float $inviterAmount = 0
    ): float {
        return  (float) ($feeAmount + $peerAmount + $burnAmount + $inviterAmount);
    }

    /**
     * Adds two values.
     *
     * @param float $q96Value1 First  value.
     * @param float $q96Value2 Second  value.
     * @return float Sum of the two  values, as a  string.
     */
    public static function addRc(float $q96Value1, float $q96Value2): float
    {
        $runtIns = self::initRc();

        $result = $runtIns->add_decimal((string) $q96Value1, (string) $q96Value2);

        if(is_numeric($result) === false){
            throw new \RuntimeException("Error in addition operation, result is not numeric.");
        }
        return (float) $result;
    }

    /**
     * Multiply two values.
     *
     * @param float $q96Value1 First value.
     * @param float $q96Value2 Second value.
     * @return float Sum of the two values, as a float.
     */
    public static function mulRc(float $q96Value1, float $q96Value2): float
    {
        $runtIns = self::initRc();

        $result = $runtIns->multiply_decimal((string) $q96Value1, (string) $q96Value2);

        if(is_numeric($result) === false){
            throw new \RuntimeException("Error in addition operation, result is not numeric.");
        }
        return (float) $result;

    }
    
    /**
     * divide two values.
     *
     * @param float $q96Value1 First  value.
     * @param float $q96Value2 Second  value.
     * @return float Sum of the two  values, as a  float.
     */
    public static function divRc(float $q96Value1, float $q96Value2): float
    {
        $runtIns = self::initRc();

        $result = $runtIns->divide_decimal((string) $q96Value1, (string) $q96Value2);

        if(is_numeric($result) === false){
            throw new \RuntimeException("Error in addition operation, result is not numeric.");
        }
        return (float) $result;
    }

    /**
     * Substract two  values.
     *
     * @param float $q96Value1 First  value.
     * @param float $q96Value2 Second  value.
     * @return float Sum of the two  values, as a  float.
     */
    public static function subRc(float $q96Value1, float $q96Value2): float
    {
        $runtIns = self::initRc();

        $result = $runtIns->subtract_decimal((string) $q96Value1, (string) $q96Value2);

        if(is_numeric($result) === false){
            throw new \RuntimeException("Error in addition operation, result is not numeric.");
        }
        return (float) $result;
    }

    /**
     * initialise Rust Module Helper.
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