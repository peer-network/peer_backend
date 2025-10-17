<?php

declare(strict_types=1);

namespace Fawaz\Services\ContentFiltering;

enum ContentReplacementPattern: string
{
    case suspended = "suspended";
    case hidden = "hidden";
    case illegal = "illegal";

    public function postTitle(): string
    {
        return match ($this) {
            self::hidden   => "this post is hidden",
            self::suspended => "this post is deleted",
            self::illegal => "this post is illegal",
        };
    }

    public function postDescription(): string
    {
        return match ($this) {
            self::hidden   => "",
            self::suspended => "",
            self::illegal => "",
        };
    }

    public function postMedia(): string
    {
        return match ($this) {
            self::hidden   => "some_pic_to_be_here_some_text_to_pass_validation",
            self::suspended => "some_pic_to_be_here_some_text_to_pass_validation",
            self::illegal => "some_pic_to_be_here_some_text_to_pass_validation",
        };
    }

    public function commentContent(): string
    {
        return match ($this) {
            self::hidden   => "this comment is hidden",
            self::suspended => "this comment is hidden",
            self::illegal => "this comment is illegal",
        };
    }

    public function username(): string
    {
        return match ($this) {
            self::hidden   => "hidden_account",
            self::suspended => "deleted_account",
            self::illegal => "illegal_account",
        };
    }

    public function userBiography(): string
    {
        return match ($this) {
            self::hidden   => "",
            self::suspended => "",
            self::illegal => "",
        };
    }

    public function profilePicturePath(): string
    {
        return match ($this) {
            self::hidden   => "some_pic_to_be_here_some_text_to_pass_validation",
            self::suspended => "some_pic_to_be_here_some_text_to_pass_validation",
            self::illegal => "some_pic_to_be_here_some_text_to_pass_validation",
        };
    }
}
