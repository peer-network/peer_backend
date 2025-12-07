<?php

declare(strict_types=1);

namespace Fawaz\Utils;

use Monolog\Logger;
use Monolog\Processor\UidProcessor;

// @phpstan-ignore class.extendsFinalByPhpDoc
class PeerLogger extends Logger implements PeerLoggerInterface
{
    private ?string $defaultUserId = null;

    public function __construct($o)
    {
        parent::__construct($o);

        $processor = new UidProcessor();
        $this->pushProcessor($processor);
    }

    public function getRequestUid(): ?string
    {
        foreach ($this->getProcessors() as $processor) {
            if ($processor instanceof UidProcessor) {
                return $processor->getUid();
            }
        }
        return null;
    }

    private function withUserContext(array $context, ?string $currentUserId): array
    {
        if ($currentUserId === null && $this->defaultUserId !== null) {
            $currentUserId = $this->defaultUserId;
        }
        if ($currentUserId !== null) {
            // Do not overwrite if explicitly provided in context
            if (!array_key_exists('currentUserId', $context)) {
                $context['currentUserId'] = $currentUserId;
            }
        }
        return $context;
    }

    public function logWithUser($level, string $message, array $context = [], ?string $currentUserId = null): void
    {
        parent::log($level, $message, $this->withUserContext($context, $currentUserId));
    }

    public function emergencyWithUser(string $message, ?string $currentUserId = null, array $context = []): void
    { $this->logWithUser(self::EMERGENCY, $message, $context, $currentUserId); }

    public function alertWithUser(string $message, ?string $currentUserId = null, array $context = []): void
    { $this->logWithUser(self::ALERT, $message, $context, $currentUserId); }

    public function criticalWithUser(string $message, ?string $currentUserId = null, array $context = []): void
    { $this->logWithUser(self::CRITICAL, $message, $context, $currentUserId); }

    public function errorWithUser(string $message, ?string $currentUserId = null, array $context = []): void
    { $this->logWithUser(self::ERROR, $message, $context, $currentUserId); }

    public function warningWithUser(string $message, ?string $currentUserId = null, array $context = []): void
    { $this->logWithUser(self::WARNING, $message, $context, $currentUserId); }

    public function noticeWithUser(string $message, ?string $currentUserId = null, array $context = []): void
    { $this->logWithUser(self::NOTICE, $message, $context, $currentUserId); }

    public function infoWithUser(string $message, ?string $currentUserId = null, array $context = []): void
    { $this->logWithUser(self::INFO, $message, $context, $currentUserId); }

    public function debugWithUser(string $message, ?string $currentUserId = null, array $context = []): void
    { $this->logWithUser(self::DEBUG, $message, $context, $currentUserId); }
}
