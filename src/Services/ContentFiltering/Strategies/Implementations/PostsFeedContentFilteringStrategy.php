<?php

declare(strict_types=1);

namespace Fawaz\Services\ContentFiltering\Strategies\Implementations;

use Fawaz\Services\ContentFiltering\Strategies\AContentFilteringStrategy;
use Fawaz\Services\ContentFiltering\Strategies\ContentFilteringStrategy;
use Fawaz\Services\ContentFiltering\Types\ContentType;
use Fawaz\Services\ContentFiltering\Types\ContentFilteringAction;

class PostsFeedContentFilteringStrategy extends AContentFilteringStrategy implements ContentFilteringStrategy
{
    public static array $strategy = [
        ContentType::user->value => [
            ContentType::user->value => ContentFilteringAction::replaceWithPlaceholder,
        ],
        ContentType::post->value => [
            ContentType::post->value => ContentFilteringAction::hideContent,
            ContentType::user->value => ContentFilteringAction::replaceWithPlaceholder,
        ],
        ContentType::comment->value => [
            ContentType::comment->value => ContentFilteringAction::replaceWithPlaceholder,
            ContentType::user->value => ContentFilteringAction::replaceWithPlaceholder,
        ],
    ];
}
