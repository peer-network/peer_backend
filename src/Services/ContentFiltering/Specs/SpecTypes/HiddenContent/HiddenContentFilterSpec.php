<?php

namespace Fawaz\Services\ContentFiltering\Specs\SpecTypes\HiddenContent;

use Fawaz\Services\ContentFiltering\HiddenContentFilterServiceImpl;
use Fawaz\Services\ContentFiltering\Specs\Specification;
use Fawaz\Services\ContentFiltering\Specs\HiddenContentSpecSQLFactory;
use Fawaz\Services\ContentFiltering\Specs\SpecificationSQLData;
use Fawaz\Services\ContentFiltering\Types\ContentFilteringAction;
use Fawaz\Services\ContentFiltering\Types\ContentFilteringCases;
use Fawaz\Services\ContentFiltering\Types\ContentType;
use Fawaz\config\ContentReplacementPattern;
use Fawaz\Services\ContentFiltering\Replaceables\ProfileReplaceable;
use Fawaz\Services\ContentFiltering\Replaceables\PostReplaceable;
use Fawaz\Services\ContentFiltering\Replaceables\CommentReplaceable;


final class HiddenContentFilterSpec implements Specification
{
    private HiddenContentFilterServiceImpl $contentFilterService;

    public function __construct(
        ContentFilteringCases $case, 
        ?string $contentFilterBy,
        private string $currentUserId,
        private ?string $targetUserId,
        private ContentType $contentTarget,
    ) {
        $this->contentFilterService = new HiddenContentFilterServiceImpl(
            $case,
            $contentFilterBy
        );
    }

    public function toSql(ContentType $showingContent): ?SpecificationSQLData
    {
        $action = $this->contentFilterService->getContentFilterAction(
            $this->contentTarget,
            $showingContent,
            null,
            $this->currentUserId, 
            $this->targetUserId
        );

        if ($action === ContentFilteringAction::hideContent) {
            return (new HiddenContentSpecSQLFactory(
                $this->contentFilterService
            ))->build($showingContent);
        }
        return null;
    }

    public function toReplacer(ProfileReplaceable|PostReplaceable|CommentReplaceable $subject): ?ContentReplacementPattern
    {
        if ($subject instanceof ProfileReplaceable) {
            $showingContent = ContentType::user;
        } elseif ($subject instanceof PostReplaceable) {
            $showingContent = ContentType::post;
        } else {
            $showingContent = ContentType::comment;
        }
        $action = $this->contentFilterService->getContentFilterAction(
            $this->contentTarget,
                $showingContent,
                $subject->getReports(),
                $this->currentUserId, 
                $this->targetUserId,
                $subject->visibilityStatus()
        );
        if ($action === ContentFilteringAction::replaceWithPlaceholder) {
                return ContentReplacementPattern::hidden;
        }
        return null;
    }
}
