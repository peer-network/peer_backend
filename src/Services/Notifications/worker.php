<?php

declare(strict_types=1);

use Dotenv\Dotenv;
use Fawaz\App\Models\Core\Model;
use Fawaz\Services\Notifications\Enums\NotificationAction;
use Fawaz\Services\Notifications\InitiatorReceiver\SystemInitiator;
use Fawaz\Services\Notifications\InitiatorReceiver\UserInitiator;
use Fawaz\Services\Notifications\InitiatorReceiver\UserReceiver;
use Fawaz\Services\Notifications\NotificationMapperImpl;
use Fawaz\Utils\PeerNullLogger;
use Predis\Client;

require __DIR__ . '/../../../vendor/autoload.php';

if (file_exists(__DIR__ . '/../../../.env')) {
    Dotenv::createImmutable(__DIR__ . '/../../../')->load();
}

$dbHost = $_ENV['DB_HOST'] ?? '';
$dbPort = $_ENV['DB_PORT'] ?? '5432';
$dbName = $_ENV['DB_DATABASE'] ?? '';
$dbUser = $_ENV['DB_USERNAME'] ?? '';
$dbPass = $_ENV['DB_PASSWORD'] ?? '';

$dsn = "pgsql:host={$dbHost};port={$dbPort};dbname={$dbName}";
$pdo = new PDO($dsn, $dbUser, $dbPass);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
Model::setDB($pdo);

$redisConfig = [
    'scheme' => $_ENV['REDIS_SCHEME'] ?? 'tcp',
    'host' => $_ENV['REDIS_HOST'] ?? '127.0.0.1',
    'port' => (int) ($_ENV['REDIS_PORT'] ?? 6379),
    'database' => (int) ($_ENV['REDIS_DB'] ?? 0),
];

$redisPassword = $_ENV['REDIS_PASSWORD'] ?? null;
if ($redisPassword !== null && $redisPassword !== '') {
    $redisConfig['password'] = $redisPassword;
}

$redis = new Client($redisConfig);
$queueName = $_ENV['NOTIFICATIONS_QUEUE'] ?? 'notifications_queue';
$blockTimeout = (int) ($_ENV['NOTIFICATIONS_QUEUE_BLOCK_TIMEOUT'] ?? 5);
$maxRuntime = (int) ($_ENV['NOTIFICATIONS_WORKER_MAX_RUNTIME'] ?? 0);
$startedAt = time();

$logger = new PeerNullLogger();
$mapper = new NotificationMapperImpl($logger);

while ($maxRuntime === 0 || (time() - $startedAt) < $maxRuntime) {
    $job = $redis->brpop([$queueName], $blockTimeout);
    if (empty($job) || !isset($job[1])) {
        continue;
    }

    $data = json_decode($job[1], true);
    if (!is_array($data)) {
        continue;
    }

    $actionValue = $data['action'] ?? null;
    $payload = $data['payload'] ?? [];
    $receivers = $data['receivers'] ?? [];
    $initiatorData = $data['initiator'] ?? [];

    if (!is_string($actionValue) || !is_array($payload) || !is_array($receivers)) {
        continue;
    }

    $action = NotificationAction::tryFrom($actionValue);
    if ($action === null) {
        continue;
    }

    $initiatorClass = is_array($initiatorData) ? ($initiatorData['class'] ?? '') : '';
    $initiatorId = is_array($initiatorData) ? ($initiatorData['id'] ?? '') : '';
    $initiatorId = is_string($initiatorId) ? $initiatorId : '';

    if (!is_string($initiatorClass) || $initiatorClass === '') {
        continue;
    }

    $allowedInitiators = [
        UserInitiator::class,
        SystemInitiator::class,
    ];

    if (!in_array($initiatorClass, $allowedInitiators, true)) {
        continue;
    }

    $initiator = new $initiatorClass($initiatorId);

    $receiverObj = new UserReceiver($receivers);

    $mapper->notifyByType($action, $payload, $initiator, $receiverObj);
}
