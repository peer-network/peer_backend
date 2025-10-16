<?php

namespace Fawaz\Services\ContentFiltering\Strategies;
use Fawaz\Services\ContentFiltering\Types\ContentType;
use Fawaz\Services\ContentFiltering\Types\ContentFilteringAction;


class HideEverythingFilteringStrategy extends AContentFilteringStrategy implements ContentFilteringStrategy {
    public const STRATEGY = [
        ContentType::user->value => [
            ContentType::post->value => ContentFilteringAction::hideContent,
            ContentType::comment->value => ContentFilteringAction::hideContent,
            ContentType::user->value => ContentFilteringAction::hideContent,
        ],
        ContentType::post->value => [
            ContentType::post->value => ContentFilteringAction::hideContent,
            ContentType::comment->value => ContentFilteringAction::hideContent,
            ContentType::user->value => ContentFilteringAction::hideContent,
        ],
        ContentType::comment->value => [
            ContentType::post->value => ContentFilteringAction::hideContent,
            ContentType::comment->value => ContentFilteringAction::hideContent,
            ContentType::user->value => ContentFilteringAction::hideContent,
        ],
    ];
}