<?php

namespace Fawaz\Services\ContentFiltering\Strategies;
use Fawaz\Services\ContentFiltering\Types\ContentType;
use Fawaz\Services\ContentFiltering\Types\ContentFilteringAction;




/**
 * each post has this objects: post itself, user(post author), comments
 * each comment has this objects: comment itself and user(post author)
 * 
 * so here we have a target content(for example, post) and showing content(post itself, post comments, post's author)
 * 
 * here we are replacing users data with placeholder and replacing users posts with placeholder
 */
class GetProfileContentFilteringStrategy implements ContentFilteringStrategy {
    public const STRATEGY = [
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
    public function getAction(ContentType $contentTarget, ContentType $showingContent): ?ContentFilteringAction {
        return self::STRATEGY[$contentTarget->value][$showingContent->value];
    }
}