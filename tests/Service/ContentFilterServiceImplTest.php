<?php

declare(strict_types=1);

namespace Tests\Service;

use Fawaz\Services\ContentFiltering\ContentFilterServiceImpl;
use Fawaz\Services\ContentFiltering\Types\ContentFilteringAction;
use Fawaz\Services\ContentFiltering\Types\ContentFilteringStrategies;
use Fawaz\Services\ContentFiltering\Types\ContentType;
use PHPUnit\Framework\TestCase;

final class ContentFilterServiceImplTest extends TestCase
{
    private function makeService(ContentFilteringStrategies $strategy, ?string $contentFilterBy): ContentFilterServiceImpl
    {
        return new ContentFilterServiceImpl(
            $strategy, 
            $contentFilterBy
        );
    }

    public function testHiddenContentVisibility(): void
    {
        $service = $this->makeService(
            ContentFilteringStrategies::searchById,
            contentFilterBy: "MYGRANDMALIKES"
        );
        $action = $service->getContentFilterAction(
            ContentType::user,
            ContentType::user,
            5,
            'user-1',
            'user-2',
            'normal'
        );

        // For profile strategy and user->user, expected: replaceWithPlaceholder
        $this->assertSame(
            ContentFilteringAction::replaceWithPlaceholder, 
            $action
        );
    }

    public function testMyProfileOverridesStrategyReturnsNull(): void
    {
        $service = $this->makeService(
            ContentFilteringStrategies::searchById,
            contentFilterBy: "MYGRANDMALIKES"
        );

        // When currentUserId equals targetUserId, MyProfile strategy is used => null
        $action = $service->getContentFilterAction(
            ContentType::user,
            ContentType::user,
            5,
            'same-user',
            'same-user',
            'hidden'
        );

        $this->assertNull($action);
    }

    public function testBelowThresholdReturnsNullUsers(): void
    {
        $service = $this->makeService(
            ContentFilteringStrategies::searchById,
            contentFilterBy: "MYGRANDMALIKES"
        );

        // Normal visibility and reports below threshold => null (user -> user)
        $action = $service->getContentFilterAction(
            ContentType::user,
            ContentType::user,
            3, // below threshold of 5
            'user-1',
            'user-2',
            'normal'
        );

        $this->assertNull($action);
    }

    public function testPostsFeedUserToUser(): void
    {
        $service = $this->makeService(
            ContentFilteringStrategies::postFeed,
            contentFilterBy: "MYGRANDMALIKES"
        );

        // user -> user => replaceWithPlaceholder when hidden and severity matches
        $action = $service->getContentFilterAction(
            ContentType::user,
            ContentType::user,
            5,
            'u1',
            'u2',
            'hidden'
        );
        $this->assertSame(ContentFilteringAction::replaceWithPlaceholder, $action);
    }

    public function testSearchByMetaUserToUser(): void
    {
        $service = $this->makeService(
            ContentFilteringStrategies::searchByMeta,
            contentFilterBy: "MYGRANDMALIKES"
        );

        $action = $service->getContentFilterAction(
            ContentType::user,
            ContentType::user,
            0,
            'user-1',
            'user-2',
            'hidden'
        );
        $this->assertSame(ContentFilteringAction::replaceWithPlaceholder, $action);
    }

    public function testDifferentSeverityLevelSkipsFilteringUsers(): void
    {
        // Using a different allowed severity level string should skip filtering entirely
        $service = $this->makeService(
            ContentFilteringStrategies::searchById,
            contentFilterBy: "MYGRANDMAHATES"
        );

        $action = $service->getContentFilterAction(
            ContentType::user,
            ContentType::user,
            100, // even with large reports
            'user-1',
            'user-2',
            'hidden'
        );
        $this->assertNull($action);
    }
}
