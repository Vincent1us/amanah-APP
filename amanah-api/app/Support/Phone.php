<?php

namespace App\Support;

class Phone
{
    public const REGEX = '/^\+628\d{8,11}$/';

    /** "812-3456-7890", "0812...", "62812...", "+62812..." => "+6281234567890" */
    public static function normalize(?string $raw): ?string
    {
        if ($raw === null) return null;
        $digits = preg_replace('/\D+/', '', $raw);
        if ($digits === '') return null;

        if (str_starts_with($digits, '62')) {
            // sudah kode negara
        } elseif (str_starts_with($digits, '0')) {
            $digits = '62'.substr($digits, 1);
        } elseif (str_starts_with($digits, '8')) {
            $digits = '62'.$digits;
        }
        return '+'.$digits;
    }
}
