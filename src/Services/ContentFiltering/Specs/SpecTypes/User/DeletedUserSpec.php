<?php

namespace Fawaz\App\Services\ContentFiltering\Specs\SpecTypes\User;

use Fawaz\App\Services\ContentFiltering\Specs\Specification;
use Fawaz\App\Services\ContentFiltering\Specs\SpecificationSQLData;
use Fawaz\App\Status;
use Fawaz\config\ContentReplacementPattern;
use Fawaz\Services\ContentFiltering\Replaceables\ProfileReplaceable;
use Fawaz\Services\ContentFiltering\Replaceables\PostReplaceable;
use Fawaz\Services\ContentFiltering\Replaceables\CommentReplaceable;
use Fawaz\Services\ContentFiltering\Types\ContentFilteringAction;
use Fawaz\Services\ContentFiltering\Types\ContentType;

final class DeletedUserSpec implements Specification
{
    public function __construct(
        private ContentFilteringAction $action,
    ) {}

    public function toSql(ContentType $targetContent): ?SpecificationSQLData
    {
        if ($this->action === ContentFilteringAction::hideContent) {
            match ($targetContent) {
                ContentType::user => new SpecificationSQLData(
                [
                    "EXISTS (
                        SELECT 1
                            FROM users DeletedUserSpec_users
                            WHERE DeletedUserSpec_users.uid = u.uid
                            AND DeletedUserSpec_users.status IN (0)
                    )" ],[]),
                ContentType::post => new SpecificationSQLData(
                [
                    "EXISTS (
                        SELECT 1
                            FROM users DeletedUserSpec_users
                            WHERE DeletedUserSpec_users.uid = p.userid
                            AND DeletedUserSpec_users.status IN (0)
                    )" ],[]),
                ContentType::comment => new SpecificationSQLData(
                [
                    "EXISTS (
                        SELECT 1
                            FROM users DeletedUserSpec_users
                            WHERE DeletedUserSpec_users.uid = c.userid
                            AND DeletedUserSpec_users.status IN (0)
                    )" ],[]),
            };
        }
        return null;
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
