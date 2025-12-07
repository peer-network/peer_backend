<?php

declare(strict_types=1);

namespace Fawaz\GraphQL\Response;

use Fawaz\Utils\PeerLoggerInterface;
use Fawaz\Utils\ResponseMessagesProvider;

class MetaBuilder
{
    public function __construct(
        private ResponseMessagesProvider $messagesProvider,
        private PeerLoggerInterface $logger
    ) {
    }

    /**
     * Build a standard meta payload for GraphQL responses.
     * Expected keys in $root: status, ResponseCode.
     */
    public function build(array $root): array
    {
        $code = $root['ResponseCode'] ?? '';

        return [
            'status' => $root['status'] ?? '',
            'ResponseCode' => isset($root['ResponseCode']) ? (string) $code : '',
            'ResponseMessage' => $this->messagesProvider->getMessage($code) ?? '',
            'RequestId' => $this->logger->getRequestUid(),
        ];
    }
}

