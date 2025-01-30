<?php

declare(strict_types=1);

use Fawaz\BaseURL;
use Fawaz\Services\JWTService;
use DI\ContainerBuilder;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Monolog\Processor\UidProcessor;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;

return static function (ContainerBuilder $containerBuilder, array $settings) {
    $containerBuilder->addDefinitions([
        'settings' => $settings,

        BaseURL::class => fn (ContainerInterface $c) => new BaseURL($c->get('settings')['base_url']),

        LoggerInterface::class => function (ContainerInterface $c) {
            $settings = $c->get('settings')['logger'];

            $logger = new Logger($settings['name']);

            $processor = new UidProcessor();
            $logger->pushProcessor($processor);

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
                $c->get(LoggerInterface::class)
            );
        },

        PDO::class => function(ContainerInterface $c) {
            $settings = $c->get('settings')['db'];

            $pdo = new PDO($settings['dsn'], $settings['username'], $settings['password']);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

            return $pdo;
        },
    ]);
};
