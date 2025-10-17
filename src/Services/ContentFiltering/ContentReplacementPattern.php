<?php

declare(strict_types=1);

namespace Fawaz\Services\ContentFiltering;

enum ContentReplacementPattern: string
{
    case deleted = "deleted";
    case hidden = "hidden";
    case illegal = "illegal";

    public function postTitle(): string
    {
        return match ($this) {
            self::hidden   => "this post is hidden",
            self::deleted => "this post is deleted",
            self::illegal => "this post is illegal",
        };
    }

    public function postDescription(): string
    {
        return match ($this) {
            self::hidden   => "",
            self::deleted => "",
            self::illegal => "",
        };
    }

    public function postMedia(): string
    {
        return match ($this) {
            self::hidden   => "some_pic_to_be_here_some_text_to_pass_validation",
            self::deleted => "some_pic_to_be_here_some_text_to_pass_validation",
            self::illegal => "some_pic_to_be_here_some_text_to_pass_validation",
        };
    }

    public function commentContent(): string
    {
        return match ($this) {
            self::hidden   => "this comment is hidden",
            self::deleted => "this comment is hidden",
            self::illegal => "this comment is illegal",
        };
    }

    public function username(): string
    {
        return match ($this) {
            self::hidden   => "hidden_account",
            self::deleted => "deleted_account",
            self::illegal => "illegal_account",
        };
    }

    public function userBiography(): string
    {
        return match ($this) {
            self::hidden   => "",
            self::deleted => "",
            self::illegal => "",
        };
    }

    public function profilePicturePath(): string
    {
        return match ($this) {
            self::hidden   => "some_pic_to_be_here_some_text_to_pass_validation",
            self::deleted => "some_pic_to_be_here_some_text_to_pass_validation",
            self::illegal => "some_pic_to_be_here_some_text_to_pass_validation",
        };
    }
}
