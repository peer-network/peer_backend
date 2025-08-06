<?php
declare(strict_types=1);

namespace Fawaz\Mail\Interface;

interface EmailInterface {

    public function send(string $email): array;
    public function content(): string;
}