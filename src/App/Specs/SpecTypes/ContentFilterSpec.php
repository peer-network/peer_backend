<?php

namespace Fawaz\App\Specs\SpecTypes;

use Fawaz\App\Specs\Specification;
use Fawaz\App\Specs\ContentFilteringSpecificationFactory;
use Fawaz\App\Specs\SpecificationSQLData;
use Fawaz\Services\ContentFiltering\ContentFilterServiceImpl;
use Fawaz\Services\ContentFiltering\Strategies\ContentFilteringStrategy;
use Fawaz\Services\ContentFiltering\Types\ContentFilteringAction;
use Fawaz\Services\ContentFiltering\Types\ContentFilteringStrategies;
use Fawaz\Services\ContentFiltering\Types\ContentType;


final class ContentFilterSpec implements Specification
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
            null,
            $contentFilterBy
        );
    }

    public function toSql(): ?SpecificationSQLData
    {
        if ($this->contentFilterService->getContentFilterAction(
            $this->contentTarget,
            $this->showingContent,
            null,
            null,
            $this->currentUserId, 
            $this->targetUserId
        ) === ContentFilteringAction::hideContent) {
            return (new ContentFilteringSpecificationFactory(
                $this->contentFilterService
            ))->build(
                ContentType::user,
                ContentFilteringAction::hideContent
            );
        }
        return null;
    }
}