<?php

namespace Fawaz\Services\ContentFiltering\Strategies;

use Fawaz\Services\ContentFiltering\Types\ContentType;
use Fawaz\Services\ContentFiltering\Types\ContentFilteringAction;

class SearchUserContentFilteringStrategy implements ContentFilteringStrategy
{
    public const STRATEGY = [
        ContentType::user->value => [
            ContentType::post->value => null,
            ContentType::comment->value => null,
            ContentType::user->value => ContentFilteringAction::hideContent,
        ],
        ContentType::post->value => [
            ContentType::post->value => null,
            ContentType::comment->value => null,
            ContentType::user->value => null,
        ],
        ContentType::comment->value => [
            ContentType::post->value => null,
            ContentType::comment->value => null,
            ContentType::user->value => null,
        ],
    ];


    /**
     * @param ContentType $contentTarget
     * For example, we have to filter content on listPosts;
     * 'contentTarget' for this API are Post and Comment.
     * @param ContentType $showingContent
     * In post we are showing post itself and a user. So 'showingContent' are post and user.
     * In comment we are showing comment itself and a user. So 'showingContent' are comment and user.
     * @return ?ContentFilteringAction
     * For each combination funciton returns an action 'ContentFilteringAction' according to used strategy.
     */
    public function getAction(ContentType $contentTarget, ContentType $showingContent): ?ContentFilteringAction
    {
        return self::STRATEGY[$contentTarget->value][$showingContent->value];
    }
}
