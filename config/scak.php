<?php

return [
    'otp' => [
        'endpoint' => env('OTP_WHATSAPP_ENDPOINT'),
        'timeout' => (int) env('OTP_TIMEOUT_SECONDS', 15),
        'expires_in_minutes' => (int) env('OTP_EXPIRES_IN_MINUTES', 10),
        'resend_cooldown_seconds' => (int) env('OTP_RESEND_COOLDOWN_SECONDS', 120),
        'max_attempts' => (int) env('OTP_MAX_ATTEMPTS', 5),
        'country_code' => env('OTP_DEFAULT_COUNTRY_CODE', '+91'),
        'test_mode' => env('OTP_TEST_MODE', true),
    ],
    'storage' => [
        'disk' => env('PRODUCT_STORAGE_DISK', 'products'),
    ],
    'wordpress' => [
        'uploads_path' => env('WP_UPLOADS_PATH'),
        'archive_path' => env('WP_ARCHIVE_PATH', storage_path('app/private/wordpress-archives')),
    ],
];
