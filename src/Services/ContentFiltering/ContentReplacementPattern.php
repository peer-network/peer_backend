<?php

namespace Fawaz\Services\ContentFiltering;

enum ContentReplacementPattern: string
{
    case suspended = "suspended";
    case flagged = "flagged";

    public function postTitle(string $title): string
    {
        return match ($this) {
            self::flagged   => "this post is hidden",
            self::suspended => "this post is deleted",
        };
    }

    public function postDescription(string $desc): string
    {
        return match ($this) {
            self::flagged   => "",
            self::suspended => "",
        };
    }

    public function postMedia(string $media): string
    {
        return match ($this) {
            self::flagged   => "some_pic_to_be_here_some_text_to_pass_validation",
            self::suspended => "some_pic_to_be_here_some_text_to_pass_validation",
        };
    }

    public function commentContent(string $content): string
    {
        return match ($this) {
            self::flagged   => "this comment is hidden",
            self::suspended => "this comment is flagged",
        };
    }

    public function username(string $username): string
    {
        return match ($this) {
            self::flagged   => "hidden_account",
            self::suspended => "deleted_account",
        };
    }

    public function userBiography(string $bio): string
    {
        return match ($this) {
            self::flagged   => "",
            self::suspended => "",
        };
    }

    public function profilePicturePath(string $path): string
    {
        return match ($this) {
            self::flagged   => "some_pic_to_be_here_some_text_to_pass_validation",
            self::suspended => "some_pic_to_be_here_some_text_to_pass_validation",
        };
    }
}
