<?php

declare(strict_types=1);

namespace Fawaz\Utils;

use Psr\Log\LoggerInterface;

interface PeerLoggerInterface extends LoggerInterface
{
    public function getRequestUid(): ?string;

    // Convenience methods that attach an optional currentUserId to context
    public function logWithUser($level, string $message, array $context = [], ?string $currentUserId = null): void;
    public function emergencyWithUser(string $message, ?string $currentUserId = null, array $context = []): void;
    public function alertWithUser(string $message, ?string $currentUserId = null, array $context = []): void;
    public function criticalWithUser(string $message, ?string $currentUserId = null, array $context = []): void;
    public function errorWithUser(string $message, ?string $currentUserId = null, array $context = []): void;
    public function warningWithUser(string $message, ?string $currentUserId = null, array $context = []): void;
    public function noticeWithUser(string $message, ?string $currentUserId = null, array $context = []): void;
    public function infoWithUser(string $message, ?string $currentUserId = null, array $context = []): void;
    public function debugWithUser(string $message, ?string $currentUserId = null, array $context = []): void;
}
