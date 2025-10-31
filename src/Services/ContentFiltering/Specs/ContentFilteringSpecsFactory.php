<?php

declare(strict_types=1);

namespace Fawaz\App\Services\ContentFiltering\Specs;

use Fawaz\App\Services\ContentFiltering\Specs\SpecTypes\HiddenContent\HiddenContentFilterSpec;
use Fawaz\Services\ContentFiltering\Types\ContentFilteringStrategies;
use Fawaz\Services\ContentFiltering\Types\ContentType;

final class ContentFilteringSpecsFactory
{
    /**
     * Build the list of profile specifications used for fetching and replacement decisions.
     *
     * @return array<int, Specification>
     */
    public function build(
        string $currentUserId, 
        string $targetUserId, 
        ?string $contentFilterBy
    ): array {
        $strategy = ($currentUserId && $currentUserId === $targetUserId)
            ? ContentFilteringStrategies::myprofile
            : ContentFilteringStrategies::searchById;

        $targetContent = ContentType::user;
        $showingContent = ContentType::user;

        $usersHiddenContentFilterSpec = new HiddenContentFilterSpec(
            $strategy,
            $contentFilterBy,
            $currentUserId,
            $targetUserId,
            $targetContent,
            $showingContent
        );

        return [
            $usersHiddenContentFilterSpec
        ];
    }
}

