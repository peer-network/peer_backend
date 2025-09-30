<?php
declare(strict_types=1);

namespace Fawaz\Utils;

use Psr\Log\LoggerInterface;

interface PeerLoggerInterface extends LoggerInterface {
    public function getRequestUid(): ?string;
}