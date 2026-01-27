<?php

declare(strict_types=1);

namespace Fawaz\Services\Notifications\Interface;

use Fawaz\Services\Notifications\Enums\NotificationAction;
use Fawaz\Services\Notifications\Enums\NotificationContent;

interface NotificationStrategy
{
    public static function type(): NotificationAction;

    public static function fromPayload(array $payload): self;

    /**
     * Content Type.
     *  1. Post
     *  2. User
     *  3. Comment
     */
    public function content(): NotificationContent;

    public function contentId(): string;

    /**
     * This is the body content, which will sent to users
     * 
     * Replaceable text should follow structure
     * 
     * - User
     *      - {{initiator.username}} -> if username replace by Initiator
     *      - {{receiver.username}} -> if username replace by Receiver
     */
    public function bodyContent(): string;

    public function setBodyContent(string $bodyContent): void;


    public function title(): string;

}
