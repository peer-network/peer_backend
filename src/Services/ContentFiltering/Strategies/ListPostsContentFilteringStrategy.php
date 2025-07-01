<?php

namespace Fawaz\Services\ContentFiltering\Strategies;
use Fawaz\Services\ContentFiltering\Types\ContentType;
use Fawaz\Services\ContentFiltering\Types\ContentFilteringAction;

class ListPostsContentFilteringStrategy implements ContentFilteringStrategy {
    public const array STRATEGY = [
        ContentType::user->value => [
            ContentType::post->value => ContentFilteringAction::replaceWithPlaceholder,
            ContentType::comment->value => ContentFilteringAction::replaceWithPlaceholder,
            ContentType::user->value => ContentFilteringAction::replaceWithPlaceholder,
        ],
        ContentType::post->value => [
            ContentType::post->value => ContentFilteringAction::hideContent,
            ContentType::comment->value => null,
            ContentType::user->value => ContentFilteringAction::replaceWithPlaceholder,
        ],
        ContentType::comment->value => [
            ContentType::post->value => null,
            ContentType::comment->value => ContentFilteringAction::replaceWithPlaceholder,
            ContentType::user->value => null,
        ],
    ];

    /**
     * @param ContentType $contentTarget
     * @param ContentType $showingContent
     * @return ?ContentFilteringAction
     */
    public function getAction(ContentType $contentTarget, ContentType $showingContent): ?ContentFilteringAction {
        return self::STRATEGY[$contentTarget->value][$showingContent->value];
    }
}