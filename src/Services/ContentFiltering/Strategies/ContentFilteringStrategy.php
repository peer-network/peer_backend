<?php

declare(strict_types=1);

namespace Fawaz\Services\ContentFiltering\Strategies;

use Fawaz\Services\ContentFiltering\Types\ContentFilteringAction;
use Fawaz\Services\ContentFiltering\Types\ContentType;

interface ContentFilteringStrategy
{
    /**
     * For example, we have to filter content on listPosts;
     * @param ContentType $contentTarget
     * 'contentTarget' for this API are Post and Comment.
     * @param ContentType $showingContent
     * In post we are showing post itself and a user. So 'showingContent' are post and user.
     * In comment we are showing comment itself and a user. So 'showingContent' are comment and user.
     * @return ?ContentFilteringAction
     * For each combination funciton returns an action 'ContentFilteringAction' according to used strategy.
     */
    public static function getAction(
        ContentType $contentTarget, 
        ContentType $showingContent
    ): ?ContentFilteringAction;
}
