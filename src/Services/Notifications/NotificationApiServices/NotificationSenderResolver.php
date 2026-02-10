<?php

namespace Fawaz\Services\Notifications\NotificationApiServices;

use Fawaz\App\Models\UserDeviceToken;
use Fawaz\Services\Notifications\Interface\NotificationSenderStrategy;
use RuntimeException;

final class NotificationSenderResolver
{
    /** @var NotificationSenderStrategy[] */
    private array $strategies;

    public function __construct(NotificationSenderStrategy ...$strategies)
    {
        $this->strategies = $strategies;
    }

    public function resolve(UserDeviceToken $receiver): NotificationSenderStrategy
    {
        foreach ($this->strategies as $strategy) {
            if ($strategy->supports($receiver)) {
                return $strategy;
            }
        }

        throw new \RuntimeException('No sender strategy for platform: ' . (string)$receiver->getPlatform());
    }
}
