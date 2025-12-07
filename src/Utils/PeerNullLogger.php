<?php

declare(strict_types=1);

namespace Fawaz\Utils;

use Monolog\Processor\UidProcessor;
use Psr\Log\NullLogger;

// @phpstan-ignore class.extendsFinalByPhpDoc
class PeerNullLogger extends NullLogger implements PeerLoggerInterface
{
    public function getRequestUid(): ?string
    {
        return null;
    }

    public function logWithUser($level, string $message, array $context = [], ?string $currentUserId = null): void
    {
        parent::log($level, $message, $context);
    }

    public function emergencyWithUser(string $message, ?string $currentUserId = null, array $context = []): void
    { $this->logWithUser('emergency', $message, $context, $currentUserId); }

    public function alertWithUser(string $message, ?string $currentUserId = null, array $context = []): void
    { $this->logWithUser('alert', $message, $context, $currentUserId); }

    public function criticalWithUser(string $message, ?string $currentUserId = null, array $context = []): void
    { $this->logWithUser('critical', $message, $context, $currentUserId); }

    public function errorWithUser(string $message, ?string $currentUserId = null, array $context = []): void
    { $this->logWithUser('error', $message, $context, $currentUserId); }

    public function warningWithUser(string $message, ?string $currentUserId = null, array $context = []): void
    { $this->logWithUser('warning', $message, $context, $currentUserId); }

    public function noticeWithUser(string $message, ?string $currentUserId = null, array $context = []): void
    { $this->logWithUser('notice', $message, $context, $currentUserId); }

    public function infoWithUser(string $message, ?string $currentUserId = null, array $context = []): void
    { $this->logWithUser('info', $message, $context, $currentUserId); }

    public function debugWithUser(string $message, ?string $currentUserId = null, array $context = []): void
    { $this->logWithUser('debug', $message, $context, $currentUserId); }
}
