<?php

return [
    'base_url' => env('MALIPO_BASE_URL', 'https://api.malipopay.co.tz/api/v1'),
    'api_token' => env('MALIPO_API_TOKEN'),
    'secret_key' => env('MALIPO_SECRET_KEY'),
    'webhook_secret' => env('MALIPO_WEBHOOK_SECRET'),
    'timeout' => (int) env('MALIPO_TIMEOUT', 20),
];
