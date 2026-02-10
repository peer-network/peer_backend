<?php

declare(strict_types=1);

namespace Fawaz\Services\Notifications;

use Fawaz\Services\Notifications\Enums\NotificationAction;
use Fawaz\Services\Notifications\Interface\NotificationInitiator;
use Fawaz\Services\Notifications\Interface\NotificationReceiver;
use Fawaz\Utils\PeerLoggerInterface;
use Predis\Client;

final class NotificationQueue
{
    public function __construct(private PeerLoggerInterface $logger)
    {
    }

    public function enqueue(
        NotificationAction $action,
        array $payload,
        NotificationInitiator $initiator,
        NotificationReceiver $receiver
    ): bool {
        $client = $this->createClient();
        if ($client === null) {
            return false;
        }

        $job = [
            'action' => $action->value,
            'payload' => $payload,
            'initiator' => [
                'class' => get_class($initiator),
                'id' => $initiator->initiator(),
            ],
            'receivers' => $receiver->receiver(),
        ];

        $json = json_encode($job, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            return false;
        }

        $queueName = $_ENV['NOTIFICATIONS_QUEUE'] ?? 'notifications_queue';

        try {
            $client->lpush($queueName, [$json]);
            return true;
        } catch (\Exception $exception) {
            $this->logger->error('Failed to enqueue notification', ['exception' => $exception->getMessage()]);
            return false;
        }
    }

    private function createClient(): ?Client
    {
        $host = $_ENV['REDIS_HOST'] ?? '';
        if ($host === '') {
            $this->logger->warning('Redis host not configured for notifications queue');
            return null;
        }

        $config = [
            'scheme' => $_ENV['REDIS_SCHEME'] ?? 'tcp',
            'host' => $host,
            'port' => (int) ($_ENV['REDIS_PORT'] ?? 6379),
            'database' => (int) ($_ENV['REDIS_DB'] ?? 0),
        ];

        $password = $_ENV['REDIS_PASSWORD'] ?? null;
        if ($password !== null && $password !== '') {
            $config['password'] = $password;
        }

        return new Client($config);
    }
}
