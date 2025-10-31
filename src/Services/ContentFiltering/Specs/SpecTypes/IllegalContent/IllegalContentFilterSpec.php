<?php

namespace Fawaz\App\Services\ContentFiltering\Specs\SpecTypes\IllegalContent;

use Fawaz\App\Services\ContentFiltering\Specs\IllegalContentFilteringSpecificationFactory;
use Fawaz\App\Services\ContentFiltering\Specs\Specification;
use Fawaz\App\Services\ContentFiltering\Specs\SpecificationSQLData;
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

    public function toSql(ContentType $targetContent): ?SpecificationSQLData
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
