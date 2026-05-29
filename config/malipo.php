<?php

return [
    'base_url' => env('MALIPO_BASE_URL', 'https://api.malipopay.co.tz/api/v1'),
    'api_token' => env('MALIPO_API_TOKEN'),
    'public_key' => env('MALIPO_PUBLIC_KEY', env('MALIPO_SECRET_KEY')),
    'secret_key' => env('MALIPO_SECRET_KEY'),
    'project' => env('MALIPO_PROJECT'),
    'merchant_account_id' => env('MALIPO_MERCHANT_ACCOUNT_ID'),
    'webhook_secret' => env('MALIPO_WEBHOOK_SECRET'),
    'timeout' => (int) env('MALIPO_TIMEOUT', 20),
];
