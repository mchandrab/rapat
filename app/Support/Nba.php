<?php

namespace App\Support;

final class Nba
{
    /**
     * Normalisasi input petugas ke 15 digit tanpa titik (BR-001).
     * Mengembalikan null bila bukan 15 digit ASCII 0-9.
     */
    public static function normalize(?string $input): ?string
    {
        $digits = preg_replace('/[.\s]/', '', (string) $input);

        return preg_match('/^[0-9]{15}$/', $digits) === 1 ? $digits : null;
    }

    /**
     * Format tampilan 6.3.3.3; nilai yang tidak valid dikembalikan apa adanya.
     */
    public static function format(?string $digits): string
    {
        $digits = (string) $digits;

        if (preg_match('/^([0-9]{6})([0-9]{3})([0-9]{3})([0-9]{3})$/', $digits, $m) !== 1) {
            return $digits;
        }

        return "{$m[1]}.{$m[2]}.{$m[3]}.{$m[4]}";
    }
}
