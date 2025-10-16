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
    public static function create(StrategyName $strategy, ContentVisibility $visibility): ContentFilteringStrategy
    {
        return match ($strategy) {
            StrategyName::postFeed   => self::makePostsFeed($visibility),
            StrategyName::profile    => self::makeProfile($visibility),
            StrategyName::searchById => self::makeSearchById($visibility),
            StrategyName::searchByMeta => self::makeSearchByMeta($visibility),
        };
    }

    private static function makePostsFeed(ContentVisibility $visibility): ?ContentFilteringStrategy
    {
        // Currently no dedicated hidden/illegal variants. If needed, add
        // PostsFeedContentFilteringStrategyHidden/Illegal and switch by $visibility.
        return match ($visibility) {
            ContentVisibility::normal => null,
            // For normal and hidden, reuse the Hidden variant mapping. Add a Normal variant if needed later.
            default => new PostsFeedContentFilteringStrategy()
        };
    }

    private static function makeProfile(ContentVisibility $visibility): ?ContentFilteringStrategy
    {
        return match ($visibility) {
            ContentVisibility::normal => null,
            // For normal and hidden, reuse the Hidden variant mapping. Add a Normal variant if needed later.
            default => new ProfileContentFilteringStrategy()
        };
    }

    private static function makeSearchById(ContentVisibility $visibility): ?ContentFilteringStrategy
    {
        return match ($visibility) {
            ContentVisibility::normal => null,
            // For normal and hidden, reuse the Hidden variant mapping. Add a Normal variant if needed later.
            default => new SearchByIdContentFilteringStrategy()
        };
    }

    private static function makeSearchByMeta(ContentVisibility $visibility): ?ContentFilteringStrategy
    {
        // We have explicit variants for hidden and illegal search-by-metadata flows.
        return match ($visibility) {
            ContentVisibility::normal => null,
            ContentVisibility::illegal => new Special\SearchByMetadataIllegalContentFilteringStrategy(),
            // For normal and hidden, reuse the Hidden variant mapping. Add a Normal variant if needed later.
            default => new SearchByMetadataHiddenContentFilteringStrategy(),
        };
    }
}

