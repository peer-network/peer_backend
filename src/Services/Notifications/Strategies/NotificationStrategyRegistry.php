<?php

declare(strict_types=1);

namespace Fawaz\Services\Notifications\Strategies;

use Fawaz\Services\Notifications\Enums\NotificationAction;
use Fawaz\Services\Notifications\Interface\NotificationStrategy;

class NotificationStrategyRegistry
{
    private array $strategyMap = [];

    public function create(NotificationAction $type, array $payload): NotificationStrategy
    {
        $strategies = $this->discoverStrategies();

        if (!isset($strategies[$type->value])) {
            throw new \InvalidArgumentException("Unknown notification type: {$type->value}");
        }

        $strategyClass = $strategies[$type->value];

        return $strategyClass::fromPayload($payload);
    }

    private function discoverStrategies(): array
    {
        if ($this->strategyMap !== []) {
            return $this->strategyMap;
        }

        $strategyFiles = glob(__DIR__ . '/*.php') ?: [];

        foreach ($strategyFiles as $strategyFile) {
            $className = pathinfo($strategyFile, PATHINFO_FILENAME);

            if ($className === 'NotificationStrategyRegistry') {
                continue;
            }

            $fqcn = __NAMESPACE__ . '\\' . $className;

            if (!class_exists($fqcn)) {
                continue;
            }

            if (!is_subclass_of($fqcn, NotificationStrategy::class)) {
                continue;
            }

            $type = $fqcn::type();

            if (isset($this->strategyMap[$type->value])) {
                throw new \LogicException("Duplicate notification type: {$type->value}");
            }

            $this->strategyMap[$type->value] = $fqcn;
        }

        return $this->strategyMap;
    }
}
