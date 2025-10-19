<?php

declare(strict_types=1);

namespace Fawaz\App\Specs;

use Fawaz\App\Specs\SpecTypes\ActiveUserSpec;
use Fawaz\App\Specs\SpecTypes\BasicUserSpec;
use Fawaz\App\Specs\SpecTypes\CurrentUserIsBlockedUserSpec;
use Fawaz\App\Specs\SpecTypes\HiddenContentFilterSpec;
use Fawaz\App\Specs\SpecTypes\IllegalContentFilterSpec;
use Fawaz\Services\ContentFiltering\Types\ContentFilteringStrategies;
use Fawaz\Services\ContentFiltering\Types\ContentType;

final class ProfileSpecsFactory
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

        $activeUserSpec = new ActiveUserSpec($targetUserId);
        $basicUserSpec = new BasicUserSpec($targetUserId);
        $currentUserIsBlockedSpec = new CurrentUserIsBlockedUserSpec($currentUserId, $targetUserId);
        $usersHiddenContentFilterSpec = new HiddenContentFilterSpec(
            $strategy,
            $contentFilterBy,
            $currentUserId,
            $targetUserId,
            $targetContent,
            $showingContent
        );
        $usersIllegalContentFilterSpec = new IllegalContentFilterSpec(
            $strategy,
            $contentFilterBy,
            $currentUserId,
            $targetUserId,
            $targetContent,
            $showingContent
        );

        return [
            $activeUserSpec,
            $basicUserSpec,
            // $currentUserIsBlockedSpec,
            $usersHiddenContentFilterSpec,
            $usersIllegalContentFilterSpec,
        ];
    }
}

