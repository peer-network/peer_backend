<?php

declare(strict_types=1);

namespace Fawaz\config;

enum ContentReplacementPattern: string
{
    case deleted = "deleted";
    case hidden = "hidden";
    case illegal = "illegal";

    // we are setting visibilityStatus as HIDDEN for the case when contetnt is NORMAL but activeReports > X(normally 5)
    public function visibilityStatus(): ?string
    {
        return match ($this) {
            self::hidden   => "hidden",
            self::deleted => null,
            self::illegal => null,
        };
    }
    public function postTitle(): ?string
    {
        return match ($this) {
            self::hidden => "this post is hidden",
            self::deleted => "this post is deleted",
            self::illegal => "this post is illegal",
        };
    }

    public function postDescription(): ?string
    {
        return match ($this) {
            self::hidden   => "",
            self::deleted => "",
            self::illegal => "",
        };
    }

    public function postMedia(): ?string
    {
        return match ($this) {
            self::hidden   => "some_pic_to_be_here_some_text_to_pass_validation",
            self::deleted => "some_pic_to_be_here_some_text_to_pass_validation",
            self::illegal => "some_pic_to_be_here_some_text_to_pass_validation",
        };
    }

    public function commentContent(): ?string
    {
        return match ($this) {
            self::hidden   => "this comment is hidden",
            self::deleted => "this comment is deleted",
            self::illegal => "this comment is illegal",
        };
    }

    public function username(): ?string
    {
        return match ($this) {
            self::hidden   => "hidden_account",
            self::deleted => "Deleted_Account",
            self::illegal => "illegal_account",
        };
    }

    public function userBiography(): ?string
    {
        return match ($this) {
            self::hidden   => "/userData/00000000-0000-0000-0000-000000000000.txt",
            self::deleted => "/userData/00000000-0000-0000-0000-000000000000.txt",
            self::illegal => "/userData/00000000-0000-0000-0000-000000000000.txt",
        };
    }

    public function profilePicturePath(): ?string
    {
        return match ($this) {
            self::hidden   => "/profile/00000000-0000-0000-0000-000000000000.jpeg",
            self::deleted => "/profile/00000000-0000-0000-0000-000000000000.jpeg",
            self::illegal => "/profile/00000000-0000-0000-0000-000000000000.jpeg",
        };
    }
}
