<?php
declare(strict_types=1);

use Slim\App;
use Psr\Container\ContainerInterface;
use Fawaz\Middleware\RateLimiterMiddleware;
use Fawaz\Middleware\SecurityHeadersMiddleware;
use Fawaz\RateLimiter\RateLimiter;
use Psr\Log\LoggerInterface;

return static function (App $app, ContainerInterface $container, array $settings) {
    $app->addBodyParsingMiddleware();

    $app->add(SecurityHeadersMiddleware::class);

    $time = (int)$settings['timeLimiter'];
    $rate = (int)$settings['rateLimiter'];
    $path = (string)$settings['rateLimiterpath'];
    $rateLimiter = new RateLimiter($rate, $time, $path);

    $logger = $container->get(LoggerInterface::class);

    $app->add(new RateLimiterMiddleware($rateLimiter, $logger));

    $app->addErrorMiddleware(true, true, true);

    $app->addRoutingMiddleware();
};
