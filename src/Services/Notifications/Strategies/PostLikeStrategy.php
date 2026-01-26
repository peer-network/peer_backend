<?php

declare(strict_types=1);

namespace Fawaz\Services\Notifications\Strategies;

use Fawaz\Services\Notifications\Enums\NotificationContent;
use Fawaz\Services\Notifications\Interface\NotificationStrategy;

class PostLikeStrategy implements NotificationStrategy
{

    public $initiator;

    public $receiver;

    // should be set by default
    public $bodyContent = "{{receiver.username}} liked your post!";
    
    public function __construct(public string $contentId)
    {
        
    }

    public function content(): NotificationContent
    {
        return NotificationContent::POST;
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
        return $this->bodyContent;
    }

    public function setBodyContent(string $bodyContent): void
    {
        $this->bodyContent = $bodyContent;
    }

    public function title(): string
    {
        return "Post Liked";
    }
}
