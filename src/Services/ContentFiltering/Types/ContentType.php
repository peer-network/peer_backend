<?php

namespace Fawaz\Services\ContentFiltering\Types;

enum ContentType: string {
    case user = 'user' ;
    case post = 'post';
    case comment = 'comment';

    public function constantsArrayKey(): string {
        return strtoupper($this->value);
    }
}