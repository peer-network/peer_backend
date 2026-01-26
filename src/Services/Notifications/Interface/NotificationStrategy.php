<?php

declare(strict_types=1);

namespace Fawaz\Services\Notifications\Interface;

use Fawaz\Services\Notifications\Enums\NotificationContent;

interface NotificationStrategy
{
    /**
     * Transaction type for the recipient credit.
     * If a fallback is provided, strategy may honor it.
     */
    public function content(): NotificationContent;

    public function contentId(): string;

    /**
     * This is the message, which will sent to users
     * 
     * Replaceable text should be placed inside {{}}
     * 
     * Replaceable test should follow structure
     * 
     * - User
     *      - initiator.username -> if username replace by Initiator
     *      - receiver.username -> if username replce by Initiator
     */
    public function bodyContent(): string;

    public function setBodyContent(string $bodyContent): void;


    public function title(): string;

    /**
     * This can include additional data related to the notification
     * 
     */
    public function extraFields(): array;
}
