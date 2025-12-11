<?php

declare(strict_types=1);

namespace Fawaz\Services\ContentFiltering;

use Fawaz\Services\ContentFiltering\Strategies\ContentFilteringStrategy;
use Fawaz\Services\ContentFiltering\Types\ContentFilteringAction;
use Fawaz\Services\ContentFiltering\Types\ContentType;

class ContentFilterServiceImpl
{
    public function __construct(
        private ContentType $targetContent,
    ) {
    }

    public function getContentFilterAction(
        ContentType $showingContent,
        ?ContentFilteringStrategy $strategy,
    ): ?ContentFilteringAction {
        if (null === $strategy) {
            return null;
        }

        return $strategy::getAction($this->targetContent, $showingContent);
    }
}
