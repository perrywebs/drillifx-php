<?php
// Central app configuration / business rules (easy to change later).
//
// APP_URL: public base URL of the install, e.g. 'https://example.com' or
// 'https://example.com/subdir'. Priority: APP_URL env var -> value below ->
// auto-detect from the request. Never commit localhost URLs here for prod.
$envAppUrl = getenv('APP_URL');
$envAppName = getenv('APP_NAME');

return [
    'app_name' => (is_string($envAppName) && trim($envAppName) !== '') ? trim($envAppName) : 'Drillifyx',
    'app_url'  => (is_string($envAppUrl) && trim($envAppUrl) !== '') ? rtrim(trim($envAppUrl), '/') : '',

    // Withdrawals
    'min_withdraw' => 100.00,
    'fx_rates' => [ // server-side source of truth (withdraw page mirrors these for display only)
        'USD' => 1.0,
        'NGN' => 1600.0,
        'GHS' => 21.6,
    ],

    // Referral reward paid when a referred user upgrades to a paid tier
    'referral_reward' => 10.00,

    // Spin wheel prizes (must match spin.php labels) + weights (higher = more common)
    'spin_prizes' => [
        ['amount' => 0.01, 'weight' => 30],
        ['amount' => 0.02, 'weight' => 24],
        ['amount' => 0.05, 'weight' => 18],
        ['amount' => 0.10, 'weight' => 12],
        ['amount' => 0.15, 'weight' => 7],
        ['amount' => 0.20, 'weight' => 5],
        ['amount' => 0.25, 'weight' => 3],
        ['amount' => 0.50, 'weight' => 1],
    ],

    // Support contacts (fixes broken links found in audit)
    'support' => [
        'telegram_channel' => 'https://t.me/Drillifyx',
        'telegram_support' => 'tg://resolve?domain=Drillifyx_SUPPORT',
        'email' => 'support@drillifyx.org',
    ],
];
