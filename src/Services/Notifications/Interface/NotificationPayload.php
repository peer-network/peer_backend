<?php
namespace Fawaz\Services\Notifications\Interface;


use Fawaz\Services\ContentFiltering\Replaceables\ProfileReplaceable;
use Fawaz\Services\Notifications\Interface\NotificationInitiator;
use Fawaz\Services\Notifications\Interface\NotificationStrategy;

interface NotificationPayload
{
 
    public function getTitle(): string;

    public function getBodyContent(): string;

    public function getInitiatorObj(): ProfileReplaceable;


    public function getContentType(): string;

    public function getContentId(): string;

}