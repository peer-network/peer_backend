<?php

declare(strict_types=1);

namespace Fawaz\Services\ContentFiltering\Strategies;

use Fawaz\Services\ContentFiltering\Types\ContentFilteringStrategies as StrategyName;
use Fawaz\Services\ContentFiltering\Types\ContentVisibility;

final class ContentFilteringStrategyFactory
{
    /**
     * Build a concrete ContentFilteringStrategy for a given logical strategy name
     * and current content visibility policy (normal/hidden/illegal).
     */
    public static function create(StrategyName $strategy): ContentFilteringStrategy
    {
        return match ($strategy) {
            StrategyName::postFeed   => new PostsFeedContentFilteringStrategy(),
            StrategyName::profile    => new ProfileContentFilteringStrategy(),
            StrategyName::searchById => new SearchByIdContentFilteringStrategy(),
            StrategyName::searchByMeta => new SearchByMetadataHiddenContentFilteringStrategy()
        };
    }
}

