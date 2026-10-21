<?php
namespace App\Helpers;

/**
 * Small server-side input checks (N4). Each method returns the cleaned value,
 * or null when the input is not acceptable, so callers can reject with one test.
 */
class Validator {
    /** Positive number within [min, max]; rejects strings like "1e9", "abc", NaN. */
    public static function number($v, float $min, float $max): ?float {
        if (is_string($v)) {
            $v = trim($v);
        }
        if (!is_int($v) && !is_float($v) && !(is_string($v) && preg_match('/^[0-9]+(\.[0-9]+)?$/', $v))) {
            return null;
        }
        $n = (float)$v;
        return ($n >= $min && $n <= $max) ? $n : null;
    }

    /** Whole number within [min, max] (for columns that hold integers); rejects 2.5, \"abc\", \"1e9\". */
    public static function wholeNumber($v, int $min, int $max): ?int {
        $n = self::number($v, $min, $max);
        return ($n !== null && $n == floor($n)) ? (int)$n : null;
    }

    /** Positive integer id. */
    public static function id($v): ?int {
        if (is_int($v) && $v > 0) {
            return $v;
        }
        if (is_string($v) && preg_match('/^[1-9][0-9]{0,18}$/', $v)) {
            return (int)$v;
        }
        return null;
    }

    /** Real calendar date as Y-m-d; optionally not in the past. */
    public static function date($v, bool $notPast = false): ?string {
        if (!is_string($v)) {
            return null;
        }
        $d = \DateTime::createFromFormat('!Y-m-d', trim($v));
        if (!$d || $d->format('Y-m-d') !== trim($v)) {
            return null;
        }
        if ($notPast && $d < new \DateTime('today')) {
            return null;
        }
        return $d->format('Y-m-d');
    }

    /** Character count that works with or without the mbstring extension. */
    public static function length(string $v): int {
        return function_exists('mb_strlen') ? mb_strlen($v, 'UTF-8') : (int)preg_match_all('/./us', $v);
    }

    /** Trimmed string of 1..max characters. */
    public static function text($v, int $max): ?string {
        if (!is_string($v)) {
            return null;
        }
        $v = trim($v);
        $len = self::length($v);
        return ($len >= 1 && $len <= $max) ? $v : null;
    }

    /** Value must be one of the allowed strings. */
    public static function oneOf($v, array $allowed): ?string {
        return (is_string($v) && in_array($v, $allowed, true)) ? $v : null;
    }
}
