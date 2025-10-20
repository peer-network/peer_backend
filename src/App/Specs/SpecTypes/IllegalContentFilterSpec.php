<?php

namespace Fawaz\App\Specs\SpecTypes;

use Fawaz\App\Specs\IllegalContentFilteringSpecificationFactory;
use Fawaz\App\Specs\Specification;
use Fawaz\App\Specs\SpecificationSQLData;
use Fawaz\config\ContentReplacementPattern;
use Fawaz\Services\ContentFiltering\ContentFilterServiceImpl;
use Fawaz\Services\ContentFiltering\Replaceables\ProfileReplaceable;
use Fawaz\Services\ContentFiltering\Replaceables\PostReplaceable;
use Fawaz\Services\ContentFiltering\Replaceables\CommentReplaceable;
use Fawaz\Services\ContentFiltering\Types\ContentFilteringAction;
use Fawaz\Services\ContentFiltering\Types\ContentFilteringStrategies;
use Fawaz\Services\ContentFiltering\Types\ContentType;


final class IllegalContentFilterSpec implements Specification
{
    private ContentFilterServiceImpl $contentFilterService;
    public function __construct(
        ContentFilteringStrategies $strategy,
        private string $currentUserId,
        private string $targetId,
        private ContentType $contentTarget,
        private ContentType $showingContent,
    ) {
        $this->contentFilterService = new ContentFilterServiceImpl(
            $strategy
        );
    }

    public function toSql(): ?SpecificationSQLData
    {
        $action = $this->contentFilterService->getContentFilterAction(
            $this->contentTarget,
            $this->showingContent,
            null,
            $this->currentUserId, 
            $this->targetId
        );

        // For list queries, pass alias via callers if needed. Here we rely on
        // default aliases.
        return IllegalContentFilteringSpecificationFactory::build(
            $this->showingContent,
            $action,
            null,
        );
    }

    public function toReplacer(ProfileReplaceable|PostReplaceable|CommentReplaceable $subject): ?ContentReplacementPattern
    {
        $action = $this->contentFilterService->getContentFilterAction(
            $this->contentTarget,
                $this->showingContent,
                $subject->getReports(),
                $this->currentUserId, 
                $this->targetId,
                $subject->visibilityStatus()
        );
        if ($subject instanceof ProfileReplaceable && $action === ContentFilteringAction::replaceWithPlaceholder) {
                return ContentReplacementPattern::illegal;
        }
        return null;
    }
}
