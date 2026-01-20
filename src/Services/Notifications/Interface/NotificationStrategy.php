<?php

declare(strict_types=1);

namespace Fawaz\Services\Notifications\Interface;

use Fawaz\Services\Notifications\Enums\NotificationAction;
use Fawaz\Services\Notifications\Enums\NotificationContent;

interface NotificationStrategy
{
    /**
     * Transaction type for the recipient credit.
     * If a fallback is provided, strategy may honor it.
     */
    public function action(): NotificationAction;

    public function initiator(): string;

    public function receiver(): string;

    public function content(): NotificationContent;

    public function contentId(): string;
}
