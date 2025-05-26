<?php
declare(strict_types=1);

namespace Fawaz\Utils\ConfigGeneration;
use Fawaz\Utils\ConfigGeneration\JSONHandler;

require __DIR__ . '../../../../vendor/autoload.php';

class ResponseCodesStore implements DataGeneratable {
    /** @var array<string, MessageEntry> */
    private array $data = [];


    public function __construct($filePath) {           
        $decoded = JSONHandler::parseInputJson($filePath);

        foreach ($decoded as $code => $entry) { 
            $this->data[$code] = new MessageEntry(
                $entry['comment'],
                $entry['userFriendlyComment']
            );
        }
    }

    public function getData(): array {
        return $this->data;
    }
}

class MessageEntry {
    public string $comment;
    public string $userFriendlyComment;

    public function __construct(string $comment, string $userFriendlyComment) {
        $this->comment = $comment;
        $this->userFriendlyComment = $userFriendlyComment;
    }
}