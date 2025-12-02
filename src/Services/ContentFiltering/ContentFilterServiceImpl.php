<?php

declare(strict_types=1);

namespace Fawaz\Services\ContentFiltering;

use Fawaz\Services\ContentFiltering\Strategies\ContentFilteringStrategy;
use Fawaz\Services\ContentFiltering\Types\ContentFilteringAction;
use Fawaz\Services\ContentFiltering\Types\ContentType;

class ContentFilterServiceImpl
{
    public function __construct(
        private readonly ContentType $targetContent
    ) {
    }

    /**
    * @param ContentType $showingContent
    * @return ContentFilteringAction|null
    */
    public function getContentFilterAction(
        ContentType $showingContent,
        ?ContentFilteringStrategy $strategy,
    ): ?ContentFilteringAction {
        if ($strategy === null) {
            return null;
        }
        return $strategy::getAction($this->targetContent, $showingContent);
    }
}
