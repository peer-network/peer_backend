<?php

declare(strict_types=1);

namespace Fawaz\Services\ContentFiltering\Strategies\Implementations;

use Fawaz\Services\ContentFiltering\Strategies\AContentFilteringStrategy;
use Fawaz\Services\ContentFiltering\Strategies\ContentFilteringStrategy;
use Fawaz\Services\ContentFiltering\Types\ContentType;

class DoNothingContentFilteringStrategy extends AContentFilteringStrategy implements ContentFilteringStrategy
{
    public static array $strategy = [
        ContentType::user->value => [
            ContentType::user->value => null,
        ],
        ContentType::post->value => [
            ContentType::post->value    => null,
            ContentType::comment->value => null,
            ContentType::user->value    => null,
        ],
        ContentType::comment->value => [
            ContentType::comment->value => null,
            ContentType::user->value    => null,
        ],
    ];
}
