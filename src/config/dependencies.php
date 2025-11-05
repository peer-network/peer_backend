<?php

declare(strict_types=1);

use Fawaz\BaseURL;
use Fawaz\Database\InteractionsPermissionsMapperImpl;
use Fawaz\Database\Interfaces\InteractionsPermissionsMapper;
use Fawaz\Database\Interfaces\ProfileRepository;
use Fawaz\Utils\ResponseMessagesProvider;
use Fawaz\Services\JWTService;
use Fawaz\Services\Mailer;
use Fawaz\Services\LiquidityPool;
use DI\ContainerBuilder;
use Fawaz\App\Interfaces\ProfileServiceImpl;
use Fawaz\App\Interfaces\ProfileService;
use Fawaz\App\Models\Core\Model;
use Fawaz\Utils\PeerLogger;
use Fawaz\Utils\ResponseMessagesProviderImpl;
use Fawaz\Database\ProfileRepositoryImpl;
use Monolog\Handler\StreamHandler;
use Psr\Container\ContainerInterface;
use Fawaz\Utils\PeerLoggerInterface;

return static function (ContainerBuilder $containerBuilder, array $settings) {
    $containerBuilder->addDefinitions([
        'settings' => $settings,

        BaseURL::class => fn (ContainerInterface $c) => new BaseURL($c->get('settings')['base_url']),

        PeerLoggerInterface::class => function (ContainerInterface $c) {
            $settings = $c->get('settings')['logger'];

            $logger = new PeerLogger($settings['name']);

            $handler = new StreamHandler($settings['path'], $settings['level']);
            $logger->pushHandler($handler);

            return $logger;
        },

        JWTService::class => function (ContainerInterface $c) {
            $settings = $c->get('settings');
            return new JWTService(
                file_get_contents($settings['privateKeyPath']),
                file_get_contents($settings['publicKeyPath']),
                file_get_contents($settings['refreshPrivateKeyPath']),
                file_get_contents($settings['refreshPublicKeyPath']),
                (int)$settings['accessTokenValidity'],
                (int)$settings['refreshTokenValidity'],
                $c->get(PeerLoggerInterface::class)
            );
        },

        Mailer::class => function (ContainerInterface $c) {
            $settings = $c->get('settings');
            $Envi = [];
            $Envi = ['mailapilink' => (string)$settings['mailapilink'], 'mailapikey' => (string)$settings['mailapikey']];
            return new Mailer(
                $Envi,
                $c->get(PeerLoggerInterface::class)
            );
        },

        LiquidityPool::class => function (ContainerInterface $c) {
            $settings = $c->get('settings')['liquidity'];
            $Envi = [];
            $Envi = ['peer' => (string)$settings['peer'], 'pool' => (string)$settings['pool'], 'burn' => (string)$settings['burn'], 'btcpool' => (string)$settings['btcpool']];
            return new LiquidityPool(
                $Envi
            );
        },

        PDO::class => function (ContainerInterface $c) {
            $settings = $c->get('settings')['db'];

            $pdo = new PDO($settings['dsn'], $settings['username'], $settings['password']);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

            Model::setDB($pdo);
            return $pdo;
        },

        ProfileService::class => \DI\autowire(ProfileServiceImpl::class),
        ProfileRepository::class => \DI\autowire(ProfileRepositoryImpl::class),
        ResponseMessagesProvider::class => function (ContainerInterface $c) {
            $path = __DIR__ . "/../../runtime-data/media/assets/response-codes.json";
            return new ResponseMessagesProviderImpl($path);
        },
        InteractionsPermissionsMapper::class => \DI\autowire(InteractionsPermissionsMapperImpl::class),
    ]);
};
