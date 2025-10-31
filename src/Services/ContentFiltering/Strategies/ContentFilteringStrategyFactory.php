<?php

declare(strict_types=1);

namespace Fawaz\Services\ContentFiltering\Strategies;

use Fawaz\Services\ContentFiltering\Strategies\Implementations\PlaceholderEverythingContentFilteringStrategy;
use Fawaz\Services\ContentFiltering\Strategies\Implementations\PostsFeedContentFilteringStrategy;
use Fawaz\Services\ContentFiltering\Types\ContentFilteringStrategies as StrategyName;

final class ContentFilteringStrategyFactory
{
    /**
     * Build a concrete ContentFilteringStrategy for a given logical strategy name
     * and current content visibility policy (normal/hidden/illegal).
     */
    public static function create(
        StrategyName $strategy
    ): ContentFilteringStrategy {
        return match ($strategy) {
            StrategyName::postFeed   => new PostsFeedContentFilteringStrategy(),
            StrategyName::myprofile    => new PlaceholderEverythingContentFilteringStrategy(),
            StrategyName::searchById => new PlaceholderEverythingContentFilteringStrategy(),
            StrategyName::searchByMeta => new PlaceholderEverythingContentFilteringStrategy()
        };
    }
}

