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
    'images' => [
        'max_dimension' => (int) env('PRODUCT_IMAGE_MAX_DIMENSION', 1600),
        'medium_dimension' => (int) env('PRODUCT_IMAGE_MEDIUM_DIMENSION', 960),
        'thumb_dimension' => (int) env('PRODUCT_IMAGE_THUMB_DIMENSION', 420),
        'jpeg_quality' => (int) env('PRODUCT_IMAGE_JPEG_QUALITY', 82),
        'webp_quality' => (int) env('PRODUCT_IMAGE_WEBP_QUALITY', 80),
        'avif_quality' => (int) env('PRODUCT_IMAGE_AVIF_QUALITY', 60),
        'prefer_webp' => (bool) env('PRODUCT_IMAGE_PREFER_WEBP', true),
        'prefer_avif' => (bool) env('PRODUCT_IMAGE_PREFER_AVIF', false),
    ],
    'cache' => [
        'catalog_ttl_seconds' => (int) env('CATALOG_CACHE_TTL_SECONDS', 300),
        'filters_ttl_seconds' => (int) env('FILTERS_CACHE_TTL_SECONDS', 1800),
        'admin_ttl_seconds' => (int) env('ADMIN_CACHE_TTL_SECONDS', 180),
    ],
    'retention' => [
        'activity_days' => (int) env('RETENTION_ACTIVITY_DAYS', 180),
        'visitor_days' => (int) env('RETENTION_VISITOR_DAYS', 365),
        'analytics_days' => (int) env('RETENTION_ANALYTICS_DAYS', 365),
        'export_days' => (int) env('RETENTION_EXPORT_DAYS', 14),
    ],
    'support' => [
        'phone' => env('SUPPORT_PHONE', '9350188297'),
        'default_city' => env('SUPPORT_DEFAULT_CITY', 'Delhi'),
    ],
    'wordpress' => [
        'uploads_path' => env('WP_UPLOADS_PATH'),
        'archive_path' => env('WP_ARCHIVE_PATH', storage_path('app/private/wordpress-archives')),
    ],
];
