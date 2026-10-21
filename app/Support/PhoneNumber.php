<?php

namespace App\Support;

/**
 * Writes phone numbers one way (+256772123456), however they were typed:
 * "0772 123 456", "772123456", "256772123456" or "+256 772-123456".
 */
class PhoneNumber
{
    public static function normalize(?string $phone): ?string
    {
        if (blank($phone)) {
            return null;
        }

        $international = str_starts_with(trim($phone), '+') || str_starts_with(trim($phone), '00');
        $digits = preg_replace('/\D/', '', $phone);
        $digits = str_starts_with(trim($phone), '00') ? substr($digits, 2) : $digits;
        $country = (string) config('school.phone_country_code', '256');

        if ($digits === '') {
            return null;
        }

        if ($international) {
            return '+'.$digits;
        }

        return match (true) {
            str_starts_with($digits, $country) && strlen($digits) === strlen($country) + 9 => '+'.$digits,
            str_starts_with($digits, '0') && strlen($digits) === 10 => '+'.$country.substr($digits, 1),
            strlen($digits) === 9 => '+'.$country.$digits,
            default => '+'.$digits,
        };
    }

    /**
     * "0772 123 456" for display, for local numbers.
     */
    public static function display(?string $phone): ?string
    {
        $normalized = static::normalize($phone);
        $country = '+'.config('school.phone_country_code', '256');

        if ($normalized && str_starts_with($normalized, $country) && strlen($normalized) === strlen($country) + 9) {
            $local = '0'.substr($normalized, strlen($country));

            return substr($local, 0, 4).' '.substr($local, 4, 3).' '.substr($local, 7);
        }

        return $normalized;
    }
}
