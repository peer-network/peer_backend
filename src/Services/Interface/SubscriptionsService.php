<?php

namespace Fawaz\Services\Interface;

interface SubscriptionsService {
    function publish(string $channel, string $string): int;
}