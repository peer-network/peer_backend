<?php

declare(strict_types=1);

namespace Fawaz\config;

enum ContentLimitsPerPost: string
{
    case VIDEO = 'video';
    case AUDIO = 'audio';
    case TEXT = 'text';
    case IMAGE = 'image';

    public function mediaLimit(): int
    {
        return match($this) {
            ContentLimitsPerPost::AUDIO => 1,
            ContentLimitsPerPost::IMAGE => 20,
            ContentLimitsPerPost::TEXT => 1,
            ContentLimitsPerPost::VIDEO => 2,
        };
    }
    public function coverLimit(): int
    {
        return match($this) {
            ContentLimitsPerPost::AUDIO => 1,
            ContentLimitsPerPost::IMAGE => 1,
            ContentLimitsPerPost::TEXT => 1,
            ContentLimitsPerPost::VIDEO => 1,
        };
    }
}
