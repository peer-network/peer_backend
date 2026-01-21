<?php

declare(strict_types=1);

namespace Fawaz\Services\Notifications\Interface;

use Fawaz\App\Profile;
use Fawaz\Services\ContentFiltering\Replaceables\ProfileReplaceable;

interface NotificationInitiator
{
    /**
     * Notification Initiator refernce to trigger's action
     * 
     * Currently initiator can be:
     *  1. Normal
     *  2. System User 
     * 
     * @return void
     */
    public function initiator(): string;

    public function initiatorObj(): ProfileReplaceable;
}