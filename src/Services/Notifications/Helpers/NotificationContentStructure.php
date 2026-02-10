<?php

namespace Fawaz\Services\Notifications\Helpers;

use Fawaz\Services\ContentFiltering\Replaceables\ProfileReplaceable;
use Fawaz\Services\Notifications\Interface\NotificationInitiator;
use Fawaz\Services\Notifications\Interface\NotificationPayload;
use Fawaz\Services\Notifications\Interface\NotificationStrategy;

class NotificationContentStructure implements NotificationPayload
{
    protected $notificationInitiator;

    protected $notificationStrategy;

    public function __construct(NotificationStrategy $notificationStrategy, NotificationInitiator $notificationInitiator)
    {
        $this->notificationStrategy = $notificationStrategy;
        $this->notificationInitiator = $notificationInitiator;

    }

    public function getTitle(): string
    {
        return $this->notificationStrategy->title();
    }

    public function getBodyContent(): string
    {
        return $this->notificationStrategy->bodyContent();
    }

    public function getContentType(): string
    {
        return $this->notificationStrategy->content()->value;
    }

    public function getContentId(): string
    {
        return $this->notificationStrategy->contentId();
    }

    public function getInitiatorObj(): ProfileReplaceable
    {
        return $this->notificationInitiator->initiatorUserObj();
    }


}
