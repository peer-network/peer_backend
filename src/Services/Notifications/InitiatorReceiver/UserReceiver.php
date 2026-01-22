<?php

declare(strict_types=1);

namespace Fawaz\Services\Notifications\InitiatorReceiver;

use Fawaz\Services\Notifications\Enums\NotificationAction;
use Fawaz\Services\Notifications\Interface\NotificationInititor;
use Fawaz\Services\Notifications\Interface\NotificationReceiver;

class UserReceiver implements NotificationReceiver
{

    protected array $receivers = [];

    public function __construct(array $receivers = [])
    {
        $this->receivers = $receivers;
    }

    public function receiver(): array
    {
        return $this->receivers;
    }
}
