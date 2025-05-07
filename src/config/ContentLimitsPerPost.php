<?php

namespace Fawaz\Config;

enum ContentLimitsPerPost: string {
    case VIDEO = 'video';
    case AUDIO = 'audio';
    case TEXT = 'text';
    case IMAGE = 'image';

    public function limit(): string
    {
        return match($this) 
        {
            ContentLimitsPerPost::AUDIO => 1,
            ContentLimitsPerPost::IMAGE => 5,
            ContentLimitsPerPost::TEXT => 1,
            ContentLimitsPerPost::VIDEO => 2,
        };
    }    
}
