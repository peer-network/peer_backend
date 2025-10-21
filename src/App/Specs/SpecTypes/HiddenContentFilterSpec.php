<?php

namespace Fawaz\App\Specs\SpecTypes;

use Fawaz\App\Specs\Specification;
use Fawaz\App\Specs\HiddenContentFilteringSpecificationFactory;
use Fawaz\App\Specs\SpecificationSQLData;
use Fawaz\Services\ContentFiltering\ContentFilterServiceImpl;
use Fawaz\Services\ContentFiltering\Types\ContentFilteringAction;
use Fawaz\Services\ContentFiltering\Types\ContentFilteringStrategies;
use Fawaz\Services\ContentFiltering\Types\ContentType;
use Fawaz\config\ContentReplacementPattern;
use Fawaz\Services\ContentFiltering\Replaceables\ProfileReplaceable;
use Fawaz\Services\ContentFiltering\Replaceables\PostReplaceable;
use Fawaz\Services\ContentFiltering\Replaceables\CommentReplaceable;


final class HiddenContentFilterSpec implements Specification
{
    private ContentFilterServiceImpl $contentFilterService;

    public function __construct(
        ContentFilteringStrategies $strategy, 
        ?string $contentFilterBy,
        private string $currentUserId,
        private string $targetUserId,
        private ContentType $contentTarget,
        private ContentType $showingContent,
    ) {
        $this->contentFilterService = new ContentFilterServiceImpl(
            $strategy,
            $contentFilterBy
        );
    }

    public function toSql(): ?SpecificationSQLData
    {
        $action = $this->contentFilterService->getContentFilterAction(
            $this->contentTarget,
            $this->showingContent,
            null,
            $this->currentUserId, 
            $this->targetUserId
        );

        return (new HiddenContentFilteringSpecificationFactory(
            $this->contentFilterService
        ))->build(
            ContentType::user,
            $action
        );
    }

    public function toReplacer(ProfileReplaceable|PostReplaceable|CommentReplaceable $subject): ?ContentReplacementPattern
    {
        $action = $this->contentFilterService->getContentFilterAction(
            $this->contentTarget,
                $this->showingContent,
                $subject->getReports(),
                $this->currentUserId, 
                $this->targetUserId,
                $subject->visibilityStatus()
        );
        if ($subject instanceof ProfileReplaceable && $action === ContentFilteringAction::replaceWithPlaceholder) {
                return ContentReplacementPattern::hidden;
        }
        return null;
    }
}
