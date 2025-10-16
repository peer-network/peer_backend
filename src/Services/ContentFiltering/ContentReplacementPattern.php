<?php

declare(strict_types=1);

namespace Fawaz\Services\ContentFiltering;

enum ContentReplacementPattern: string
{
    case suspended = "suspended";
    case flagged = "flagged";
    case illegal = "illegal";

    public function postTitle(string $title): string
    {
        return match ($this) {
            self::flagged   => "this post is hidden",
            self::suspended => "this post is deleted",
            self::illegal => "this post is illegal",
        };
    }

    public function postDescription(string $desc): string
    {
        return match ($this) {
            self::flagged   => "",
            self::suspended => "",
            self::illegal => "",
        };
    }

    public function postMedia(string $media): string
    {
        return match ($this) {
            self::flagged   => "some_pic_to_be_here_some_text_to_pass_validation",
            self::suspended => "some_pic_to_be_here_some_text_to_pass_validation",
            self::illegal => "some_pic_to_be_here_some_text_to_pass_validation",
        };
    }

    public function commentContent(string $content): string
    {
        return match ($this) {
            self::flagged   => "this comment is hidden",
            self::suspended => "this comment is flagged",
            self::illegal => "this comment is illegal",
        };
    }

    public function username(string $username): string
    {
        return match ($this) {
            self::flagged   => "hidden_account",
            self::suspended => "deleted_account",
            self::illegal => "illegal_account",
        };
    }

    public function userBiography(string $bio): string
    {
        return match ($this) {
            self::flagged   => "",
            self::suspended => "",
            self::illegal => "",
        };
    }

    public function profilePicturePath(string $path): string
    {
        return match ($this) {
            self::flagged   => "some_pic_to_be_here_some_text_to_pass_validation",
            self::suspended => "some_pic_to_be_here_some_text_to_pass_validation",
            self::illegal => "some_pic_to_be_here_some_text_to_pass_validation",
        };
    }
}
