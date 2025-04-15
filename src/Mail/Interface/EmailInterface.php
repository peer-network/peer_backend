<?php

namespace Fawaz\Mail\Interface;

interface EmailInterface {

    public function send(string $email): bool;
    public function content(): string;
}