<?php

declare(strict_types=1);

namespace Fawaz\Services\Notifications\Interface;

interface NotificationInitiator
{
    /**
     * Notification Initiator refernce to trigger's action
     * 
     * Currently initiator can be:
     *  1. Normal
     *  2. System User 
     * 
     * @return string
     */
    public function initiator(): string;
}