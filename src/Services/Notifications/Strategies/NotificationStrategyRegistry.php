<?php

declare(strict_types=1);

namespace Fawaz\Services\Notifications\Strategies;

use Fawaz\Services\Notifications\Enums\NotificationAction;
use Fawaz\Services\Notifications\Interface\NotificationStrategy;

class NotificationStrategyRegistry
{
    private array $strategyMap = [];

    public function create(NotificationAction $action, array $payload): NotificationStrategy
    {
        $strategies = $this->discoverStrategies();

        if (!isset($strategies[$action->value])) {
            throw new \InvalidArgumentException("Unknown notification action: {$action->value}");
        }

        $strategyClass = $strategies[$action->value];

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

            $action = $fqcn::action();

            if (isset($this->strategyMap[$action->value])) {
                throw new \LogicException("Duplicate notification action: {$action->value}");
            }

            $this->strategyMap[$action->value] = $fqcn;
        }

        return $this->strategyMap;
    }
}
