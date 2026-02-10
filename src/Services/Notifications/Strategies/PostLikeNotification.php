<?php

declare(strict_types=1);

namespace Fawaz\Services\Notifications\Strategies;

use Fawaz\config\constants\ConstantsNotification;
use Fawaz\Services\Notifications\Enums\NotificationAction;
use Fawaz\Services\Notifications\Enums\NotificationContent;
use Fawaz\Services\Notifications\Interface\NotificationStrategy;

class PostLikeNotification implements NotificationStrategy
{
    public $initiator;

    public $receiver;

    // should be set by default
    public $bodyContent = ConstantsNotification::NOTIFICATIONS['POST_LIKE']['BODY'];

    public function __construct(public string $contentId)
    {

    }

    public static function action(): NotificationAction
    {
        return NotificationAction::POST_LIKE;
    }

    public static function fromPayload(array $payload): NotificationStrategy
    {
        $contentId = $payload['contentId'] ?? null;

        if ($contentId === null || $contentId === '') {
            throw new \InvalidArgumentException('PostLikeNotification requires contentId');
        }

        return new self($contentId);
    }

    public function content(): NotificationContent
    {
        return NotificationContent::POST;
    }

    public function contentId(): string
    {
        return $this->contentId;
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
        return ConstantsNotification::NOTIFICATIONS['POST_LIKE']['TITLE'];
    }
}
