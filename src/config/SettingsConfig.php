<?php

declare(strict_types=1);

namespace Fawaz\config;

use Dotenv\Dotenv;

final class SettingsConfig
{
    private static ?self $instance = null;

    public string $mediaServerUrl;
    public string $webAppUrl;
    public string $diCompilationPath;
    public bool $displayErrorDetails;
    public bool $logErrors;
    public string $defaultDriver;
    public array $db;
    public array $mail;
    public array $logger;
    public array $liquidity;
    public string $privateKeyPath;
    public string $publicKeyPath;
    public string $refreshPrivateKeyPath;
    public string $refreshPublicKeyPath;
    public string $accessTokenValidity;
    public string $refreshTokenValidity;
    public string $rateLimiter;
    public string $timeLimiter;
    public string $rateLimiterPath;
    public string $mailApiLink;
    public string $mailApiKey;

    private function __construct()
    {
    }

    public static function load(): self
    {
        if (self::$instance instanceof self) {
            return self::$instance;
        }

        $dotenv = Dotenv::createImmutable(__DIR__ . '/../../');
        if (file_exists(__DIR__ . '/../../.env')) {
            $dotenv->load();
        }

        require_once __DIR__ . '/checker.php';

        $settings = new self();
        $settings->mediaServerUrl = $_ENV['MEDIA_SERVER_URL'] ?? '';
        $settings->webAppUrl = $_ENV['WEB_APP_URL'] ?? '';
        $settings->diCompilationPath = __DIR__ . '/../../' . ($_ENV['CONTAINER_PATH'] ?? '');
        $settings->displayErrorDetails = false;
        $settings->logErrors = true;
        $settings->defaultDriver = $_ENV['DB_DRIVER'] ?? 'postgres';
        $settings->db = [
            'dsn' => 'pgsql:host=' . ($_ENV['DB_HOST'] ?? '') . ';port=5432;dbname=' . ($_ENV['DB_DATABASE'] ?? ''),
            'username' => $_ENV['DB_USERNAME'] ?? '',
            'password' => $_ENV['DB_PASSWORD'] ?? '',
            'database' => $_ENV['DB_DATABASE'] ?? '',
            'datahost' => $_ENV['DB_HOST'] ?? '',
        ];
        $settings->mail = [
            'driver' => $_ENV['MAIL_DRIVER'] ?? 'smtp',
            'host' => $_ENV['MAIL_HOST'] ?? 'smtp.peerapp.de',
            'port' => (int) ($_ENV['MAIL_PORT'] ?? 587),
            'username' => $_ENV['MAIL_USERNAME'] ?? '',
            'password' => $_ENV['MAIL_PASSWORD'] ?? '',
            'encryption' => $_ENV['MAIL_ENCRYPTION'] ?? 'tls',
            'from' => [
                'address' => $_ENV['MAIL_FROM_ADDRESS'] ?? 'admin@peerapp.de',
                'name' => $_ENV['MAIL_FROM_NAME'] ?? 'peers',
            ],
        ];
        $settings->logger = [
            'name' => $_ENV['LOGGER_NAME'] ?? '',
            'path' => __DIR__ . '/../../' . ($_ENV['LOGGER_PATH'] ?? '') . date('Y-m-d') . '.log',
            'level' => constant('Monolog\\Logger::' . ($_ENV['LOGGER_LEVEL'] ?? 'DEBUG')),
        ];
        $settings->liquidity = [
            'peer' => $_ENV['PEER_BANK'] ?? '',
            'pool' => $_ENV['LIQUIDITY_POOL'] ?? '',
            'burn' => $_ENV['BURN_ACCOUNT'] ?? '',
            'btcpool' => $_ENV['BTC_POOL'] ?? '',
            'peerShop' => $_ENV['PEER_SHOP'] ?? '',
        ];
        $settings->privateKeyPath = __DIR__ . '/../../' . ($_ENV['PRIVATE_KEY_PATH'] ?? '');
        $settings->publicKeyPath = __DIR__ . '/../../' . ($_ENV['PUBLIC_KEY_PATH'] ?? '');
        $settings->refreshPrivateKeyPath = __DIR__ . '/../../' . ($_ENV['REFRESH_PRIVATE_KEY_PATH'] ?? '');
        $settings->refreshPublicKeyPath = __DIR__ . '/../../' . ($_ENV['REFRESH_PUBLIC_KEY_PATH'] ?? '');
        $settings->accessTokenValidity = $_ENV['TOKEN_EXPIRY'] ?? '';
        $settings->refreshTokenValidity = $_ENV['REFRESH_TOKEN_EXPIRY'] ?? '';
        $settings->rateLimiter = $_ENV['LIMITER_RATE'] ?? '';
        $settings->timeLimiter = $_ENV['LIMITER_TIME'] ?? '';
        $settings->rateLimiterPath = __DIR__ . '/../../' . ($_ENV['RATE_LIMITER'] ?? '');
        $settings->mailApiLink = $_ENV['MAIL_API_LINK'] ?? '';
        $settings->mailApiKey = $_ENV['MAIL_API_KEY'] ?? '';

        self::$instance = $settings;

        return self::$instance;
    }
}
