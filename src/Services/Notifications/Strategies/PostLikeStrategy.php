<?php

declare(strict_types=1);

namespace Fawaz\Services\Notifications\Strategies;

use Fawaz\Services\Notifications\Enums\NotificationAction;
use Fawaz\Services\Notifications\Enums\NotificationContent;
use Fawaz\Services\Notifications\Interface\NotificationStrategy;

class PostLikeStrategy implements NotificationStrategy
{

    public $receiver;
    
    public function __construct(public string $initiator, public string $contentId)
    {
        
    }

    public function action(): NotificationAction
    {
        return NotificationAction::POST_LIKE;
    }
    
    public function initiator(): string
    {
        return $this->initiator;
    }

    public function receiver(): string
    {
        return $this->receiver;
    }

    public function content(): NotificationContent
    {
        return NotificationContent::POST;
    }

    public function contentId(): string
    {
        return $this->contentId;
    }
}
