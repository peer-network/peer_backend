<?php

namespace Fawaz\App\Specs\SpecTypes\User;

use Fawaz\App\Specs\Specification;
use Fawaz\App\Specs\SpecificationSQLData;
use Fawaz\config\ContentReplacementPattern;
use Fawaz\Services\ContentFiltering\Replaceables\ProfileReplaceable;
use Fawaz\Services\ContentFiltering\Replaceables\PostReplaceable;
use Fawaz\Services\ContentFiltering\Replaceables\CommentReplaceable;
use Fawaz\Services\ContentFiltering\Types\ContentFilteringAction;
final class BasicUserSpec implements Specification
{

    public function __construct(
        private ContentFilteringAction $action,
    ) {}

    public function toSql(): ?SpecificationSQLData
    {
        if ($this->action === ContentFilteringAction::hideContent) {
            return new SpecificationSQLData(
                [
                    "EXISTS (
                        SELECT 1
                        FROM users basicUserSpec_users
                        WHERE basicUserSpec_users.uid = u.uid AND
                        basicUserSpec_users.roles_mask IN (0,2,16) AND
                        basicUserSpec_users.verified = 1
                    )"
                ],
                []
            );
        }
        return null;
    }

    public function toReplacer(ProfileReplaceable|PostReplaceable|CommentReplaceable $subject): ?ContentReplacementPattern
    {
        return null;
    }
}
