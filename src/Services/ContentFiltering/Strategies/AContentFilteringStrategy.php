<?php

declare(strict_types=1);

namespace Fawaz\Services\ContentFiltering\Strategies;

use Fawaz\Services\ContentFiltering\Types\ContentFilteringAction;
use Fawaz\Services\ContentFiltering\Types\ContentPolicy;
use Fawaz\Services\ContentFiltering\Types\ContentType;

abstract class AContentFilteringStrategy implements ContentFilteringStrategy
{
    protected static array $strategy;
    public static function getAction(ContentType $contentTarget, ContentType $showingContent): ?ContentFilteringAction
    {
        return static::$strategy[$contentTarget->value][$showingContent->value] ?? null;
    }
}
