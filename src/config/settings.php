<?php

declare(strict_types=1);

use Monolog\Logger;
use Dotenv\Dotenv;

$dotenv = Dotenv::createImmutable(__DIR__ . '/../../');
if (file_exists(__DIR__.'/../../.env')) {
    $dotenv->load();
}

require __DIR__ . '/checker.php';

return static function (string $appEnv) {

    $settings =  [
        'media_server_url' => $_ENV['MEDIA_SERVER_URL'] ?? '',
        'web_app_url' => $_ENV['WEB_APP_URL'] ?? '',
        'di_compilation_path' => __DIR__ . '/../../' . $_ENV['CONTAINER_PATH'],
        'display_error_details' => false,
        'log_errors' => true,
        'default' => $_ENV['DB_DRIVER'] ?? 'postgres',
        'db' => [
            'dsn' => 'pgsql:host=' . $_ENV['DB_HOST'] . ';port=5433;dbname=' . $_ENV['DB_DATABASE'] . '',
            'username' => $_ENV['DB_USERNAME'],
            'password' => $_ENV['DB_PASSWORD'],
            'database' => $_ENV['DB_DATABASE'],
            'datahost' => $_ENV['DB_HOST'],
        ],
        'mail' => [
            'driver' => $_ENV['MAIL_DRIVER'] ?? 'smtp',
            'host' => $_ENV['MAIL_HOST'] ?? 'smtp.peerapp.de',
            'port' => $_ENV['MAIL_PORT'] ?? 587,
            'username' => $_ENV['MAIL_USERNAME'] ?? '',
            'password' => $_ENV['MAIL_PASSWORD'] ?? '',
            'encryption' => $_ENV['MAIL_ENCRYPTION'] ?? 'tls',
            'from' => [
                'address' => $_ENV['MAIL_FROM_ADDRESS'] ?? 'admin@peerapp.de',
                'name' => $_ENV['MAIL_FROM_NAME'] ?? 'peers',
            ],
        ],
        'logger' => [
            'name' => $_ENV['LOGGER_NAME'],
            'path' => __DIR__ . '/../../' . $_ENV['LOGGER_PATH'] . date('Y-m-d') . '.log',
            'level' => constant('Monolog\Logger::' . $_ENV['LOGGER_LEVEL']),
        ],
        'liquidity' => [
            'peer' => $_ENV['PEER_BANK'] ?? '',
            'pool' => $_ENV['LIQUIDITY_POOL'] ?? '',
            'burn' => $_ENV['BURN_ACCOUNT'] ?? '',
            'btcpool' => $_ENV['BTC_POOL'] ?? '',
            'peerShop' => $_ENV['PEER_SHOP'] ?? '',
        ],
        'privateKeyPath' => __DIR__ . '/../../' . $_ENV['PRIVATE_KEY_PATH'],
        'publicKeyPath' => __DIR__ . '/../../' . $_ENV['PUBLIC_KEY_PATH'],
        'refreshPrivateKeyPath' => __DIR__ . '/../../' . $_ENV['REFRESH_PRIVATE_KEY_PATH'],
        'refreshPublicKeyPath' => __DIR__ . '/../../' . $_ENV['REFRESH_PUBLIC_KEY_PATH'],
        'accessTokenValidity' => $_ENV['TOKEN_EXPIRY'], // 1 hour
        'refreshTokenValidity' => $_ENV['REFRESH_TOKEN_EXPIRY'], // 1 week
        'rateLimiter' => $_ENV['LIMITER_RATE'],
        'timeLimiter' => $_ENV['LIMITER_TIME'],
        'rateLimiterpath' => __DIR__ . '/../../' . $_ENV['RATE_LIMITER'],
        'mailapilink' => $_ENV['MAIL_API_LINK'],
        'mailapikey' => $_ENV['MAIL_API_KEY']
    ];

    if ($appEnv === 'DEVELOPMENT') {
        $settings['di_compilation_path'] = '';
        $settings['display_error_details'] = true;
    }

    return $settings;
};
