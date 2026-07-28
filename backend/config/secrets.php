<?php

return [
    /**
     * Provider for external secrets management.
     * Options: 'env' (default), 'aws', 'vault'
     */
    'provider' => env('SECRETS_PROVIDER', 'env'),

    /**
     * Cache secrets to avoid repeated calls to external providers.
     */
    'cache' => [
        'enabled' => env('SECRETS_CACHE_ENABLED', true),
        'ttl' => env('SECRETS_CACHE_TTL', 3600), // 1 hour
    ],

    /**
     * Manual overrides or values that are safe to keep in config.
     */
    'PAYSTACK_SECRET_KEY' => env('PAYSTACK_SECRET_KEY'),
    'FLW_SECRET_KEY' => env('FLW_SECRET_KEY'),
    'FLW_SECRET_HASH' => env('FLW_SECRET_HASH'),
    'MONNIFY_SECRET_KEY' => env('MONNIFY_SECRET_KEY'),
    'OPAY_SECRET_KEY' => env('OPAY_SECRET_KEY'),
    'TERMII_API_KEY' => env('TERMII_API_KEY'),
    'VTPASS_API_KEY' => env('VTPASS_API_KEY'),
    'VTPASS_SECRET_KEY' => env('VTPASS_SECRET_KEY'),
    'CLUBKONNECT_API_KEY' => env('CLUBKONNECT_API_KEY'),
    'GOOGLE_MAPS_API_KEY' => env('GOOGLE_MAPS_API_KEY'),
    'REDIS_PASSWORD' => env('REDIS_PASSWORD'),
    'RESEND_API_KEY' => env('RESEND_API_KEY'),
    'DOJAH_SECRET' => env('DOJAH_SECRET'),
    'GOOGLE_DRIVE_CLIENT_SECRET' => env('GOOGLE_DRIVE_CLIENT_SECRET'),
    'BACKUP_ARCHIVE_PASSWORD' => env('BACKUP_ARCHIVE_PASSWORD'),
    'REVERB_APP_SECRET' => env('REVERB_APP_SECRET'),
    'blind_index_key' => env('SECURITY_BLIND_INDEX_KEY'),
];
