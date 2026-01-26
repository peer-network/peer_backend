<?php

declare(strict_types=1);

namespace Fawaz\Services\Notifications\InitiatorReceiver;

use Fawaz\App\SystemUser;
use Fawaz\Services\ContentFiltering\Replaceables\ProfileReplaceable;
use Fawaz\Services\Notifications\Interface\NotificationInitiator;

class SystemInitiator implements NotificationInitiator
{

    public $initiator;

    
    public function __construct(string $initiator)
    {
        $this->initiator = $initiator;
    }

    public function initiator(): string
    {
        return '';
    }

    public function initiatorUserObj(): ProfileReplaceable
    {
        return new SystemUser();
    }
}
