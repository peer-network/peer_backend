<?php

declare(strict_types=1);

namespace Fawaz\config;

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
            self::deleted => "this comment is deleted",
            self::illegal => "this comment is illegal",
        };
    }

    public function username(): string
    {
        return match ($this) {
            self::hidden   => "hidden_account",
            self::deleted => "Deleted_Account",
            self::illegal => "Illegal_Account",
        };
    }

    public function userBiography(): string
    {
        return match ($this) {
            self::hidden   => "/userData/fb08b055-511a-4f92-8bb4-eb8da9ddf746.txt",
            self::deleted => "/userData/fb08b055-511a-4f92-8bb4-eb8da9ddf746.txt",
            self::illegal => "/userData/fb08b055-511a-4f92-8bb4-eb8da9ddf746.txt",
        };
    }

    public function profilePicturePath(): string
    {
        return match ($this) {
            self::hidden   => "/profile/2e855a7b-2b88-47bc-b4dd-e110c14e9acf.jpeg",
            self::deleted => "/profile/2e855a7b-2b88-47bc-b4dd-e110c14e9acf.jpeg",
            self::illegal => "/profile/2e855a7b-2b88-47bc-b4dd-e110c14e9acf.jpeg",
        };
    }
}
