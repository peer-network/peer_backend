<?php

namespace Fawaz\App\Specs\SpecTypes;

use Fawaz\App\Specs\Specification;
use Fawaz\App\Specs\ContentFilteringSpecificationFactory;
use Fawaz\App\Specs\SpecificationSQLData;
use Fawaz\Services\ContentFiltering\ContentFilterServiceImpl;
use Fawaz\Services\ContentFiltering\Strategies\ContentFilteringStrategy;
use Fawaz\Services\ContentFiltering\Types\ContentFilteringAction;
use Fawaz\Services\ContentFiltering\Types\ContentType;


final class ContentFilterSpec implements Specification
{
    private ContentFilterServiceImpl $contentFilterService;

    public function __construct(
        ContentFilteringStrategy $strategy, 
        ?string $contentFilterBy
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
            ContentType::post,
            ContentType::post
        ) === ContentFilteringAction::hideContent) {
            return new ContentFilteringSpecificationFactory(
                $this->contentFilterService
            )->build(
                ContentType::post,
                ContentFilteringAction::hideContent
            );
        }
        if ($this->contentFilterService->getContentFilterAction(
            ContentType::user,
            ContentType::user
        ) === ContentFilteringAction::hideContent) {
            return new ContentFilteringSpecificationFactory(
                $this->contentFilterService
            )->build(
                ContentType::user,
                ContentFilteringAction::hideContent
            );
        }
        return null;
    }

    // public function getParameters(): array
    // {
    //     // return ['ratings' => $this->allowedRatings];
    //     return ['ratings' => "soem string"];
    // }
}