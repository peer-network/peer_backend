<?php

namespace Fawaz\App\Specs\SpecTypes;

use Fawaz\App\Specs\Specification;
use Fawaz\App\Specs\SpecificationSQLData;
use Fawaz\App\Status;
use Fawaz\config\ContentReplacementPattern;
use Fawaz\Services\ContentFiltering\Replaceables\ProfileReplaceable;
use Fawaz\Services\ContentFiltering\Replaceables\PostReplaceable;
use Fawaz\Services\ContentFiltering\Replaceables\CommentReplaceable;
use Fawaz\Services\ContentFiltering\Types\ContentFilteringAction;
use Fawaz\Services\ContentFiltering\Types\ContentType;

final class InactiveUserSpec implements Specification
{
    public function __construct(
        private string $userid,
        private ContentFilteringAction $action,
    ) {}

    
    public function toSql(): ?SpecificationSQLData
    {
        if ($this->action === ContentFilteringAction::hideContent) {
            return new SpecificationSQLData(
                [
                    "EXISTS (
                    SELECT 1
                    FROM users activeUserSpec_users
                    WHERE activeUserSpec_users.uid = :activeUserSpec_users_userid
                    AND activeUserSpec_users.status IN (0))"
                ],[
                    "activeUserSpec_users_userid" => $this->userid
                ]
            );
        } else {
            return null;
        }
    }

    public function toReplacer(
        ProfileReplaceable|PostReplaceable|CommentReplaceable $subject
    ): ?ContentReplacementPattern {
        if ($this->action === ContentFilteringAction::replaceWithPlaceholder) {
            if ($subject instanceof ProfileReplaceable) {
                if ($subject->getStatus() !== Status::NORMAL) {
                    return ContentReplacementPattern::deleted;
                }
            }
        }
        return null;
    }
}
