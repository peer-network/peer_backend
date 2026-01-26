<?php

declare(strict_types=1);

namespace Fawaz\Services\Notifications\InitiatorReceiver;

use Fawaz\App\User;
use Fawaz\Services\ContentFiltering\Replaceables\ProfileReplaceable;
use Fawaz\Services\Notifications\Interface\NotificationInitiator;

class UserInitiator implements NotificationInitiator
{

    public $initiator;

    
    public function __construct(string $initiator)
    {
        $this->initiator = $initiator;
    }

    public function initiator(): string
    {
        return $this->initiator;
    }

    public function initiatorUserObj(): ProfileReplaceable
    {
        $user = User::query()->where('uid', $this->initiator)->first();
        
        return new User($user, [], false);
    }
}
