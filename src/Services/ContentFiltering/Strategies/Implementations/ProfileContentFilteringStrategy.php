<?php

declare(strict_types=1);

namespace Fawaz\Services\ContentFiltering\Strategies\Implementations;

use Fawaz\Services\ContentFiltering\Strategies\AContentFilteringStrategy;
use Fawaz\Services\ContentFiltering\Strategies\ContentFilteringStrategy;
use Fawaz\Services\ContentFiltering\Types\ContentType;
use Fawaz\Services\ContentFiltering\Types\ContentFilteringAction;

class ProfileContentFilteringStrategy extends AContentFilteringStrategy implements ContentFilteringStrategy
{
    public static array $strategy = [
        ContentType::user->value => [
            ContentType::post->value => null,
            ContentType::comment->value => null,
            ContentType::user->value => ContentFilteringAction::replaceWithPlaceholder,
        ],
        ContentType::post->value => [
            ContentType::post->value => ContentFilteringAction::replaceWithPlaceholder,
            ContentType::comment->value => null,
            ContentType::user->value => null,
        ],
        ContentType::comment->value => [
            ContentType::post->value => null,
            ContentType::comment->value => null,
            ContentType::user->value => null,
        ],
    ];
}
