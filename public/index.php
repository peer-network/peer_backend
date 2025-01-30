<?php

declare(strict_types=1);

\ini_set('log_errors', '1');
\ini_set('error_log', dirname(__FILE__) . '/../runtime-data/logs/errorlog.txt');

use DI\ContainerBuilder;
use Slim\Factory\AppFactory;
use Slim\Handlers\Strategies\RequestHandler;

require __DIR__ . '/../vendor/autoload.php';

$settings = (require __DIR__ . '/../src/config/settings.php')(
    $_ENV['APP_ENV'] ?? $_SERVER['APP_ENV'] ?? 'DEVELOPMENT'
);

// Set up dependencies
$containerBuilder = new ContainerBuilder();
if($settings['di_compilation_path']) {
    $containerBuilder->enableCompilation($settings['di_compilation_path']);
}
(require __DIR__ . '/../src/config/dependencies.php')($containerBuilder, $settings);

$container = $containerBuilder->build();

// Create app
AppFactory::setContainer($container);
$app = AppFactory::create();

// Assign matched route arguments to Request attributes for PSR-15 handlers
$app->getRouteCollector()->setDefaultInvocationStrategy(new RequestHandler(true));

// Register middleware
(require __DIR__ . '/../src/config/middleware.php')($app, $container, $settings);

// Register routes
(require __DIR__ . '/../src/config/routes.php')($app);

// Run app
$app->run();
