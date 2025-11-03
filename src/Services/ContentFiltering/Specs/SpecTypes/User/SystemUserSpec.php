<?php

namespace Fawaz\Services\ContentFiltering\Specs\SpecTypes\User;

use Fawaz\Services\ContentFiltering\ContentFilterServiceImpl;
use Fawaz\Services\ContentFiltering\Specs\Specification;
use Fawaz\Services\ContentFiltering\Specs\SpecificationSQLData;
use Fawaz\config\ContentReplacementPattern;
use Fawaz\Services\ContentFiltering\Replaceables\ProfileReplaceable;
use Fawaz\Services\ContentFiltering\Replaceables\PostReplaceable;
use Fawaz\Services\ContentFiltering\Replaceables\CommentReplaceable;
use Fawaz\Services\ContentFiltering\Strategies\ContentFilteringStrategy;
use Fawaz\Services\ContentFiltering\Strategies\Implementations\HideEverythingContentFilteringStrategy;
use Fawaz\Services\ContentFiltering\Types\ContentFilteringAction;
use Fawaz\Services\ContentFiltering\Types\ContentFilteringCases;
use Fawaz\Services\ContentFiltering\Types\ContentType;
final class SystemUserSpec implements Specification
{
    private ContentFilterServiceImpl $contentFilterService;
    private ContentFilteringStrategy $contentFilterStrategy;

    public function __construct(
        ContentFilteringCases $case,
        ContentType $targetContent
    ) {
        $this->contentFilterService = new ContentFilterServiceImpl(
            $targetContent
        );
        $this->contentFilterStrategy = self::createStrategy(
            $case
        );
    }

    public function toSql(ContentType $showingContent): ?SpecificationSQLData
    {
        if ($this->contentFilterService->getContentFilterAction(
            $showingContent,
            $this->contentFilterStrategy
        ) === ContentFilteringAction::hideContent) {
            return match ($showingContent) {
                ContentType::user => new SpecificationSQLData(
                [
                    "EXISTS (
                        SELECT 1
                        FROM users SystemUserSpec_users
                        WHERE SystemUserSpec_users.uid = u.uid AND
                        SystemUserSpec_users.roles_mask IN (0,2,16,256) AND
                        SystemUserSpec_users.verified = 1
                    )" ],[]),
                ContentType::post => new SpecificationSQLData(
                [
                    "EXISTS (
                        SELECT 1
                        FROM users SystemUserSpec_users
                        WHERE SystemUserSpec_users.uid = p.userid AND
                        SystemUserSpec_users.roles_mask IN (0,2,16,256) AND
                        SystemUserSpec_users.verified = 1
                    )" ],[]),
                ContentType::comment => new SpecificationSQLData(
                [
                    "EXISTS (
                        SELECT 1
                        FROM users SystemUserSpec_users
                        WHERE SystemUserSpec_users.uid = c.userid AND
                        SystemUserSpec_users.roles_mask IN (0,2,16,256) AND
                        SystemUserSpec_users.verified = 1
                    )" ],[]),
            };
        }
        return null;
    }

    public function toReplacer(ProfileReplaceable|PostReplaceable|CommentReplaceable $subject): ?ContentReplacementPattern
    {
        return null;
    }

    private static function createStrategy(
        ContentFilteringCases $strategy
    ): ContentFilteringStrategy {
        return new HideEverythingContentFilteringStrategy();
    }
}
