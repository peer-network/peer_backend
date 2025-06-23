<?php

namespace Fawaz\Services;

use Predis\Client as Redis;
use Fawaz\Services\Interface\SubscriptionsService;


class RedisSubscriptionsService implements SubscriptionsService {
    private Redis $redis;
    
    function __construct()  {
        $this->redis = new Redis([
            'scheme' => 'tcp',
            'host'   => '127.0.0.1',
            'port'   => 6379,
        ]);
        // $this->redis->auth('30904622-5260-4f77-ab88-2660c19cd3e9'); // if needed
    }

    function publish(string $channel, string $string): int {
        $message = json_encode($string);
        $result = $this->redis->publish($channel, $message);
        return $result;
    }
}
