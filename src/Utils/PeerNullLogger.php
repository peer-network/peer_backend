<?php
declare(strict_types=1);

namespace Fawaz\Utils;

use Monolog\Processor\UidProcessor;
use Psr\Log\NullLogger;

// @phpstan-ignore class.extendsFinalByPhpDoc
class PeerNullLogger extends NullLogger implements PeerLoggerInterface {

    public function getRequestUid(): ?string {
        return null;
    }
}