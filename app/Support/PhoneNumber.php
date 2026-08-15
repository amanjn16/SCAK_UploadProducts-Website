<?php

namespace App\Support;

use InvalidArgumentException;

class PhoneNumber
{
    public static function normalizeIndian(string $phone): string
    {
        $normalized = preg_replace('/\D+/', '', $phone) ?? '';

        if (str_starts_with($normalized, '91') && strlen($normalized) === 12) {
            $normalized = substr($normalized, 2);
        }

        if (strlen($normalized) !== 10) {
            throw new InvalidArgumentException('Phone number must contain 10 digits.');
        }

        return config('scak.otp.country_code', '+91').$normalized;
    }
}
