<?php
// src/WebSocketServer.php

namespace Fawaz\Services;

use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;
use GraphQL\GraphQL;
use GraphQL\Type\Schema;
use GraphQL\Utils\BuildSchema;
use GraphQL\Executor\Executor;
use Psr\Log\LoggerInterface;

class GraphQLSubscriptionServer implements MessageComponentInterface {
    protected \SplObjectStorage $clients;

    public function __construct() {
        $this->clients = new \SplObjectStorage;
    }

    public function onOpen(ConnectionInterface $conn) {

        $this->clients->attach($conn);
        // Send initial greeting or something to the client
    }

    public function onMessage(ConnectionInterface $from, $msg) {
        // Handle incoming WebSocket messages (subscriptions, etc.)
        // $msg = json_decode($msg, true);
        echo "haha2";
        // echo $msg . " " . $from;
        // if ($msg['type'] === 'start') {
            // Handle subscription logic here
            // $subscription = $msg['subscription'];

            // Execute GraphQL subscription
            // $result = GraphQL::executeQuery($this->schema, $subscription);
            // $data = $result->toArray();

            // // Send the result back to the client
            // $from->send(json_encode([
            //     'type' => 'data',
            //     'data' => $data
            // ]));
        // }
    }

    public function onClose(ConnectionInterface $conn) {
        echo "haha3";
        $this->clients->detach($conn);
    }

    public function onError(ConnectionInterface $conn, \Exception $e) {
        echo "haha5";
        echo "Error: {$e->getMessage()}\n";
        $conn->close();
    }
}
