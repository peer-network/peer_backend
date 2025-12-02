<?php

declare(strict_types=1);

namespace Tests\utils\ConfigGeneration;

class MessageEntry
{
    public function __construct(public string $comment, public string $userFriendlyComment)
    {
    }
}
