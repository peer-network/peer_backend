<?php

namespace Fawaz\App\Specs\SpecTypes;

use Fawaz\App\Specs\Specification;
use Fawaz\App\Specs\HiddenContentFilteringSpecificationFactory;
use Fawaz\App\Specs\SpecificationSQLData;
use Fawaz\App\Status;
use Fawaz\Services\ContentFiltering\Capabilities\HasVisibilityStatus;
use Fawaz\Services\ContentFiltering\ContentFilterServiceImpl;
use Fawaz\Services\ContentFiltering\Types\ContentFilteringAction;
use Fawaz\Services\ContentFiltering\Types\ContentFilteringStrategies;
use Fawaz\Services\ContentFiltering\Types\ContentType;
use Fawaz\Services\ContentFiltering\ContentReplacementPattern;
use Fawaz\Services\ContentFiltering\Replaceables\ProfileReplaceable;
use Fawaz\Services\ContentFiltering\Replaceables\PostReplaceable;
use Fawaz\Services\ContentFiltering\Replaceables\CommentReplaceable;


final class IllegalContentFilterSpec implements Specification
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
        if ($this->contentFilterService->getContentFilterAction(
            $this->contentTarget,
            $this->showingContent,
            null,
            $this->currentUserId, 
            $this->targetUserId
        ) === ContentFilteringAction::hideContent) {
            return (new HiddenContentFilteringSpecificationFactory(
                $this->contentFilterService
            ))->build(
                ContentType::user,
                ContentFilteringAction::hideContent
            );
        }
        return null;
    }

    public function toReplacer(ProfileReplaceable|PostReplaceable|CommentReplaceable $subject): ?ContentReplacementPattern
    {
        if ($subject instanceof ProfileReplaceable) {
            if ($subject->visibilityStatus() === 'illegal') {
                return ContentReplacementPattern::illegal;
            }
        }
        return null;
    }
}
