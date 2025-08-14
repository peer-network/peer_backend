<?php

namespace Fawaz\Database;

class Rechnen {

    // @return string
    public static function format_percentage(float $percentage, int $precision = 2): string 
    {

        return round($percentage, $precision) . '%';
    }

    // @return float val
    public static function calculate_percentage(float $number, float $total): float 
    {
        if ($total == 0) {
            return 0;
        }

        return ($number / $total) * 100;
    }

    // @return string
    public static function calculate_percentage_for_display(float $number, float $total): string 
    {
        return self::format_percentage(self::calculate_percentage($number, $total));
    }

    // @return float val
    public static function calculate_account_stand(float $cost = 0, float $account = 0): float 
    {
        if ($cost == 0 || $account == 0) {
            return 0;
        }

        return ($account - $cost);
    }

    // @return float val
    public static function calculate_cost_plus_feesum(float $price = 1, float $sum = 1, float $tax = PEERFEE): float 
    {
        if ($price == 0 || $sum == 0) {
            return 0;
        }

        return ($price*$sum) + (($price*$sum) * $tax);
    }

    // @return float val
    public static function calculate_cost_to_wallet(float $price = 1, float $sum = 1, float $tax = PEERFEE): float 
    {
        if ($price == 0 || $sum == 0) {
            return 0;
        }

        $sales_tax = $tax;
        return ($price * $sum) - (($price * $sum) * $sales_tax);
    }

    // @return float val
    public static function calculate_total_feesum(float $price = 1, float $sum = 1, float $tax = PEERFEE): float 
    {
        if ($price == 0 || $sum == 0) {
            return 0;
        }

        $sales_tax = $tax;
        return ($sum * $price) * $sales_tax;
    }

    // @return float val
    public static function calculate_feesum_once(float $price = 1, float $sum = 1, float $tax = PEERFEE): float 
    {
        if ($price == 0 || $sum == 0) {
            return 0;
        }

        return ((($sum * $price) * $tax) / $sum);
    }

    // @return float val
    public static function calculate_total_burn_feesum(float $price = 1, float $sum = 1, float $tax = PEERFEE): float 
    {
        if ($price == 0 || $sum == 0) {
            return 0;
        }

        return self::calculate_total_feesum($price, $sum, $tax);
    }

    // @return float val
    public static function calculate_percentage_of_num(float $percentage, float $total): float 
    {
        if ($percentage == 0 || $total == 0) {
            return 0;
        }

        return ($percentage / 100) * $total;
    }

    // @return float val
    public static function calculate_token_preis(float $daily_token = 0, float $daily_gems = 0, int $precision = 10): float 
    {
        if ($daily_token <= 0 || $daily_gems <= 0) {
            return 0.0;
        }

        $result = bcdiv((string)$daily_token, (string)$daily_gems, $precision);

        return (float) $result;
    }

    // @return float val
    public static function calculate_gems_preis(float $d_token = 0, float $d_gems = 0, float $t_price = 0): float 
    {
        if ($d_token == 0 || $d_gems == 0 || $t_price == 0) {
            return 0;
        }

        return ($d_token * $t_price) / $d_gems;
    }

    // @return float val
    public static function calculate_user_win(float $d_token = 0, float $d_gems = 0, float $user_gems = 0): float 
    {
        if ($d_token == 0 || $d_gems == 0 || $user_gems == 0) {
            return 0;
        }

        $token_preis = self::calculate_token_preis($d_token, $d_gems);
        return ($token_preis * $user_gems);
    }

    // @return float val i make it to get sum by user of $auth->callsetExange();
    public static function sum_any_by_col_by_name(array $arr, int $sumcol, string $usercol, string $userid): float 
    {
        $sum = 0;

        if (isset($arr[$usercol]) && $arr[$usercol] === $userid) {
            $sum += \array_sum((array) $arr[$sumcol]); // Cast to array to handle different data types
        }

        foreach ($arr as $child) {
            if (\is_array($child)) {
                if (!isset($child[$sumcol])) {
                    $sum = self::sum_any_by_col_by_name($child, $sumcol, $usercol, $userid);
                } elseif (isset($child[$usercol]) && $child[$usercol] === $userid) {
                    $sum += \array_sum((array) $child[$sumcol]);
                }
            }
        }

        return $sum;
    }

    // @return float val i make it to get sum by user of $auth->callsetExange();
    public static function sum_all_by_col(array $arr, string $sumcol, $x = 0): float 
    {
        $sum = $x;

        if (isset($arr[$sumcol])) {
            $sum += \array_sum((array) $arr[$sumcol]); // Cast to array to ensure summing works correctly
        }

        foreach ($arr as $child) {
            if (\is_array($child)) {
                if (!isset($child[$sumcol])) {
                    $sum = self::sum_all_by_col($child, $sumcol, $sum);
                } else {
                    $sum += \array_sum((array) $child[$sumcol]);
                }
            }
        }

        return $sum;
    }

    // @return float val i make it to get sum by user of $auth->callsetExange();
    public static function array_all_by_col(array $arr, string $sumcol): array 
    {
        return array_values(\array_unique(\array_column($arr, $sumcol)));
    }

    private function __construct() {}
}
