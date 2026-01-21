<?php

declare(strict_types=1);

namespace Fawaz\Services\Notifications\Strategies;

use Fawaz\Services\Notifications\Enums\NotificationAction;
use Fawaz\Services\Notifications\Enums\NotificationPayload;
use Fawaz\Services\Notifications\Interface\NotificationStrategy;

class PostLikeStrategy implements NotificationStrategy
{

    public $initiator;

    public $receiver;
    
    public function __construct(public string $contentId)
    {
        
    }

    public function content(): NotificationPayload
    {
        return NotificationPayload::POST;
    }

    public function contentId(): string
    {
        return $this->contentId;
    }


    public function extraFields(): array
    {
        return [];
    }


    public function bodyContent(): string
    {
        return sprintf("{{initiator.username}} liked your post!");
    }

    public function title(): string
    {
        return "Post Liked";
    }
}
