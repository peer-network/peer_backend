<?php

namespace Fawaz\Services\ContentFiltering\Strategies;
use Fawaz\Services\ContentFiltering\Types\ContentType;
use Fawaz\Services\ContentFiltering\Types\ContentFilteringAction;


class PlaceholderEverythingFilteringStrategy extends AContentFilteringStrategy implements ContentFilteringStrategy {
    public const STRATEGY = [
        ContentType::user->value => [
            ContentType::post->value => ContentFilteringAction::replaceWithPlaceholder,
            ContentType::comment->value => ContentFilteringAction::replaceWithPlaceholder,
            ContentType::user->value => ContentFilteringAction::replaceWithPlaceholder,
        ],
        ContentType::post->value => [
            ContentType::post->value => ContentFilteringAction::replaceWithPlaceholder,
            ContentType::comment->value => ContentFilteringAction::replaceWithPlaceholder,
            ContentType::user->value => ContentFilteringAction::replaceWithPlaceholder,
        ],
        ContentType::comment->value => [
            ContentType::post->value => ContentFilteringAction::replaceWithPlaceholder,
            ContentType::comment->value => ContentFilteringAction::replaceWithPlaceholder,
            ContentType::user->value => ContentFilteringAction::replaceWithPlaceholder,
        ],
    ];
}