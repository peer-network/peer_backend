<?php
declare(strict_types=1);

namespace Fawaz\Utils;

use Monolog\Logger;
use Monolog\Processor\UidProcessor;

class PeerLogger extends Logger implements PeerLoggerInterface {
    public function __construct($o)
    {
        parent::__construct($o);

        $processor = new UidProcessor();
        $this->pushProcessor($processor);
    }

    public function getRequestUid(): ?string {
        foreach ($this->getProcessors() as $processor) {
            if ($processor instanceof UidProcessor) {
                return $processor->getUid();
            }
        }
        return null;
    }
}