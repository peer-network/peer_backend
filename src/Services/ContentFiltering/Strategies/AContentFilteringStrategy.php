<?php

declare(strict_types=1);

namespace Fawaz\Services\ContentFiltering\Strategies;

use Fawaz\Services\ContentFiltering\Types\ContentFilteringAction;
use Fawaz\Services\ContentFiltering\Types\ContentPolicy;
use Fawaz\Services\ContentFiltering\Types\ContentType;

abstract class AContentFilteringStrategy implements ContentFilteringStrategy
{
    protected static array $strategy;
    public function getAction(ContentType $contentTarget, ContentType $showingContent): ?ContentFilteringAction
    {
        return self::$strategy[$contentTarget->value][$showingContent->value];
    }
}
