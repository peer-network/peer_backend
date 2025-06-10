<?php
declare(strict_types=1);

namespace Tests\Utils\ConfigGeneration;

class MessageEntry {
    public string $comment;
    public string $userFriendlyComment;

    public function __construct(string $comment, string $userFriendlyComment) {
        $this->comment = $comment;
        $this->userFriendlyComment = $userFriendlyComment;
    }
}