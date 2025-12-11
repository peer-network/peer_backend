<?php

namespace Fawaz\Services\ContentFiltering\Specs\SpecTypes\User;

use Fawaz\config\ContentReplacementPattern;
use Fawaz\Services\ContentFiltering\Replaceables\CommentReplaceable;
use Fawaz\Services\ContentFiltering\Replaceables\PostReplaceable;
use Fawaz\Services\ContentFiltering\Replaceables\ProfileReplaceable;
use Fawaz\Services\ContentFiltering\Specs\Specification;
use Fawaz\Services\ContentFiltering\Specs\SpecificationSQLData;
use Fawaz\Services\ContentFiltering\Types\ContentType;

final class CurrentUserIsBlockedUserSpec implements Specification
{
    public function __construct(
        private string $userid,
        private string $blockerUserId,
    ) {
    }

    public function toSql(ContentType $showingContent): SpecificationSQLData
    {
        return new SpecificationSQLData(
            [
                'NOT EXISTS (
                    SELECT 1
                    FROM user_block_user CurrentUserIsBlockedUserSpec_user_block_user
                    WHERE CurrentUserIsBlockedUserSpec_user_block_user.blockerid = :CurrentUserIsBlockedUserSpec_user_block_user_blockerUserId
                    AND CurrentUserIsBlockedUserSpec_user_block_user.blockedid = :CurrentUserIsBlockedUserSpec_user_block_user_userid
                )',
            ],
            [
                'CurrentUserIsBlockedUserSpec_user_block_user_userid'        => $this->userid,
                'CurrentUserIsBlockedUserSpec_user_block_user_blockerUserId' => $this->blockerUserId,
            ]
        );
    }

    public function toReplacer(ProfileReplaceable|PostReplaceable|CommentReplaceable $subject): ?ContentReplacementPattern
    {
        return null;
    }

    public function forbidInteractions(string $targetContentId): ?SpecificationSQLData
    {
        return null;
    }
}
