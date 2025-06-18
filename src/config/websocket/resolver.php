<?php

namespace Fawaz\config\websocket;

return [
    'Query' => [
        'ping' => fn() => 'pong',
    ],
    'Subscription' => [
        'timeUpdated' => function ($root, $args) {
            return $root;
        },
    ],
];