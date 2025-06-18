<?php
declare(strict_types=1);

use Ratchet\Server\IoServer;
use Ratchet\Http\HttpServer;
use Ratchet\WebSocket\WsServer;
use Fawaz\Services\GraphQLSubscriptionServer;


require __DIR__ . '/../vendor/autoload.php';


$graphqlServer = new GraphQLSubscriptionServer();

$server = IoServer::factory(
    new HttpServer(
        new WsServer(
            $graphqlServer
        )
    ),
    8080
);

$server->run();