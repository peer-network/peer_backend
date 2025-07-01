<?php

namespace Fawaz\Services\ContentFiltering;

enum ContentReplacementPattern: string {
    case suspended = "suspended";
    case flagged = "flagged";

    public function postTitle(string $title): string {
        return match ($this) {
            self::flagged   => "this post is flagged",
            self::suspended => "this post is deleted",
            default => $title
        };
    }

    public function postDescription(string $desc): string {
        return match ($this) {
            self::flagged   => "",
            self::suspended => "",
            default => $desc
        };
    }

    public function postMedia(string $media): string {
        return match ($this) {
            self::flagged   => "",
            self::suspended => "",
            default => $media
        };
    }

    public function commentContent(string $content): string {
        return match ($this) {
            self::flagged   => "this comment was deleted",
            self::suspended => "this comment is flagged",
            default => $content
        };
    }

    public function username(string $username): string {
        return match ($this) {
            self::flagged   => "restricted user",
            self::suspended => "deleted account",
            default => $username
        };
    }

    public function userBiography(string $bio): string {
        return match ($this) {
            self::flagged   => "",
            self::suspended => "",
            default => $bio
        };
    }

    public function profilePicturePath(string $path): string {
        return match ($this) {
            self::flagged   => "",
            self::suspended => "",
            default => $path
        };
    }
}