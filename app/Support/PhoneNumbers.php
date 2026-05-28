<?php

declare(strict_types=1);

namespace App\Support;

use InvalidArgumentException;

final class PhoneNumbers
{
    public static function normalizeTanzania(?string $input): string
    {
        $digits = preg_replace('/\D+/', '', (string) $input) ?? '';

        if ($digits === '') {
            throw new InvalidArgumentException('Phone number is required.');
        }

        if (str_starts_with($digits, '00')) {
            $digits = substr($digits, 2);
        }

        if (str_starts_with($digits, '2550') && strlen($digits) === 13) {
            $digits = '255'.substr($digits, 4);
        }

        if (str_starts_with($digits, '255') && strlen($digits) === 12) {
            return $digits;
        }

        if (str_starts_with($digits, '0') && strlen($digits) === 10) {
            return '255'.substr($digits, 1);
        }

        if (strlen($digits) === 9) {
            return '255'.$digits;
        }

        throw new InvalidArgumentException(
            'Phone number must be in 0712345678, 712345678, +255712345678, or 255712345678 format.'
        );
    }
}
