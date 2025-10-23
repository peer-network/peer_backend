<?php

namespace Fawaz\App\Specs\SpecTypes\IllegalContent;

use Fawaz\App\Specs\IllegalContentFilteringSpecificationFactory;
use Fawaz\App\Specs\Specification;
use Fawaz\App\Specs\SpecificationSQLData;
use Fawaz\config\ContentReplacementPattern;
use Fawaz\Services\ContentFiltering\Replaceables\ProfileReplaceable;
use Fawaz\Services\ContentFiltering\Replaceables\PostReplaceable;
use Fawaz\Services\ContentFiltering\Replaceables\CommentReplaceable;
use Fawaz\Services\ContentFiltering\Types\ContentFilteringAction;
use Fawaz\Services\ContentFiltering\Types\ContentType;


final class IllegalContentFilterSpec implements Specification
{
    public function __construct(
        private ContentFilteringAction $action,
        private ContentType $showingContent,
    ) {}

    public function toSql(): ?SpecificationSQLData
    {
        if ($this->action === ContentFilteringAction::hideContent) {
            return IllegalContentFilteringSpecificationFactory::build(
                $this->showingContent,
                $this->action
            );
        }
        return null;
    }

    public function toReplacer(ProfileReplaceable|PostReplaceable|CommentReplaceable $subject): ?ContentReplacementPattern
    {
        if ($this->action === ContentFilteringAction::hideContent) {
            if ($subject->visibilityStatus() === 'illegal') {
                return ContentReplacementPattern::illegal;
            }
        }
        return null;
    }
}
