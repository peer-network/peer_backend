<?php
declare(strict_types=1);

namespace Fawaz\Utils\ConfigGeneration;
use Fawaz\Utils\ConfigGeneration\JSONHandler;

require __DIR__ . '../../../../vendor/autoload.php';

class Constants {
    static $pathResponseCodesFileToAssets = "/Users/fcody/Desktop/Peer/peer_backend/runtime-data/media/assets/";
    static $pathResponseCodesFileForEditing = "/Users/fcody/Desktop/Peer/peer_backend/src/Utils/ConfigGeneration/src/";
    static $outputResponseCodesFileName = "response-codes.json";
    static $inputResponseCodesFileName = "response-codes-editable.json";
}

class ResponseCodesStore {
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