<?php

namespace Fawaz\Database;

use function number_format;
use function bcmul;
use function bcdiv;
use function bcadd;
use function bcpow;
use function bccomp;
use function bcsub;

// Definiert den Skalierungsfaktor für Q64.96-Darstellung
define('Q64_96_SCALE', bcpow('2', '96'));
// Definiert den Maximalwert (2^160 - 1) für Q64.96
define('MAX_VAL_Q_96', bcpow('2', '160'));

class Q96
{
    // Wandelt eine Dezimalzahl in Q64.96-Darstellung um
    public static function decimalToQ64_96(float $value): string
    {
        $decimalString = number_format($value, 30, '.', ''); // 30 Nachkommastellen
        return bcmul($decimalString, Q64_96_SCALE, 0);
    }

    // Wandelt eine Q64.96-Zahl zurück in eine Dezimalzahl
    public static function q64_96ToDecimal(string $qValue): float
    {
        if (!$this->isValidQ64_96($qValue)) {
            $this->logger->error("Invalid Q64.96 value: {$qValue}");
            return 0.0;
        }
        return round(bcdiv($qValue, Q64_96_SCALE, 18), 2);
    }

    // Addiert zwei Q64.96 Werte
    public static function addQ64_96(string $qValue1, string $qValue2): string
    {
        return bcadd($qValue1, $qValue2);
    }

    // Überprüft, ob der Wert eine gültige Q64.96-Zahl ist
    public static function isValidQ64_96(string $qValue): bool
    {
        if (!preg_match('/^[0-9]+$/', $qValue)) {
            $this->logger->warning("Invalid Q64.96 value: contains invalid characters");
            return false;
        }
        if (bccomp($qValue, MAX_VAL_Q_96) >= 0) {
            $this->logger->warning("Invalid Q64.96 value: out of range (>= 2^160)");
            return false;
        }
        return true;
    }

    // Vergleicht zwei Q64.96 Werte
    public static function compare(string $qValue1, string $qValue2): int
    {
        if (!$this->isValidQ64_96($qValue1) || !$this->isValidQ64_96($qValue2)) {
            $this->logger->error("Invalid Q64.96 value for comparison.");
            return 0;
        }
        return bccomp($qValue1, $qValue2);
    }

    // Subtrahiert zwei Q64.96 Werte
    public static function subtractQ64_96($qValue1, $qValue2) {
        return bcsub($qValue1, $qValue2);
    }

    // Multipliziert zwei Q64.96 Werte
    public static function multiplyQ64_96($qValue1, $qValue2) {
        $result = bcmul($qValue1, $qValue2);
        return bcdiv($result, Q64_96_SCALE, 0);
    }

    // Dividiert zwei Q64.96 Werte
    public static function divideQ64_96($qValue1, $qValue2) {
        $scaled = bcmul($qValue1, Q64_96_SCALE);
        return bcdiv($scaled, $qValue2, 0);
    }
}
