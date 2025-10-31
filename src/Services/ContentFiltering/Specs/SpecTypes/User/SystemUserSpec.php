<?php

namespace Fawaz\App\Services\ContentFiltering\Specs\SpecTypes\User;

use Fawaz\App\Services\ContentFiltering\Specs\Specification;
use Fawaz\App\Services\ContentFiltering\Specs\SpecificationSQLData;
use Fawaz\config\ContentReplacementPattern;
use Fawaz\Services\ContentFiltering\Replaceables\ProfileReplaceable;
use Fawaz\Services\ContentFiltering\Replaceables\PostReplaceable;
use Fawaz\Services\ContentFiltering\Replaceables\CommentReplaceable;
use Fawaz\Services\ContentFiltering\Types\ContentFilteringAction;
use Fawaz\Services\ContentFiltering\Types\ContentType;
final class SystemUserSpec implements Specification
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
                        FROM users SystemUserSpec_users
                        WHERE SystemUserSpec_users.uid = u.uid AND
                        SystemUserSpec_users.roles_mask IN (0,2,16) AND
                        SystemUserSpec_users.verified = 1
                    )" ],[]),
                ContentType::post => new SpecificationSQLData(
                [
                    "EXISTS (
                        SELECT 1
                        FROM users SystemUserSpec_users
                        WHERE SystemUserSpec_users.uid = p.userid AND
                        SystemUserSpec_users.roles_mask IN (0,2,16) AND
                        SystemUserSpec_users.verified = 1
                    )" ],[]),
                ContentType::comment => new SpecificationSQLData(
                [
                    "EXISTS (
                        SELECT 1
                        FROM users SystemUserSpec_users
                        WHERE SystemUserSpec_users.uid = c.userid AND
                        SystemUserSpec_users.roles_mask IN (0,2,16) AND
                        SystemUserSpec_users.verified = 1
                    )" ],[]),
            };
        }
        return null;
    }

    public function toReplacer(ProfileReplaceable|PostReplaceable|CommentReplaceable $subject): ?ContentReplacementPattern
    {
        return null;
    }
}
