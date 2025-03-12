<?php

declare(strict_types=1);

use Fawaz\Middleware\HtmlentityDecoderMiddleware;
use Slim\App;
use Psr\Container\ContainerInterface;
use Fawaz\Middleware\RateLimiterMiddleware;
use Fawaz\Middleware\SecurityHeadersMiddleware;
use Fawaz\RateLimiter\RateLimiter;
use Psr\Log\LoggerInterface;

return static function (App $app, ContainerInterface $container, array $settings) {
    $app->addRoutingMiddleware();
    $app->addBodyParsingMiddleware();

    // Create the RateLimiter instance
    $time = (int)$settings['timeLimiter'];
    $rate = (int)$settings['rateLimiter'];
    $path = (string)$settings['rateLimiterpath'];
    $rateLimiter = new RateLimiter($rate, $time, $path); // ? requests per hour

    // Get the logger from the container
    $logger = $container->get(LoggerInterface::class);

    // Register the RateLimiterMiddleware
    $app->add(new RateLimiterMiddleware($rateLimiter, $logger));

    // Register the SecurityHeadersMiddleware
    $app->add(SecurityHeadersMiddleware::class);

    // Add Middleware for Decode special character HTML entities
    $app->add(HtmlentityDecoderMiddleware::class);

    // Add the error middleware
    $app->addErrorMiddleware(true, true, true);
};
