<?php
declare(strict_types=1);

namespace Fawaz\Utils\ConfigGeneration;

class Constants {
    static $testBackendServerEndpoint = "https://peer-network.eu/graphql";
    static $testProductionServerEndpoint = "https://getpeer.eu/graphql";
    static $productionServerEndpoint = "https://peernetwork.eu/graphql";

}

class EndpointsConfig {
    private int $generatedAt;
    private string $name;
    /** @var array<string, MessageEntry> */
    private array $data = [];


    public function __construct($filePath)
    {   
        $this->name = "Response Codes";
        $this->generatedAt = time();
        
        $decoded = $this->parseInputJson($filePath);

        foreach ($decoded as $code => $entry) { 
            $this->data[$code] = new MessageEntry(
                $entry['comment'],
                $entry['userFriendlyComment']
            );
        }
    }

    public function getMessage(string $code): ?MessageEntry {
        return $this->data[$code] ?? null;
    }

    private function parseInputJson(string $filePath): array {
        if (!file_exists($filePath)) {
            throw new \Exception("Response Code File is not found: $filePath");
        }
        
        $jsonContent = file_get_contents($filePath);
        $decoded = json_decode($jsonContent, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \Exception("JSON decode error: " . json_last_error_msg());
        }
        return $decoded;
    }

    private function generateJson()
    {
        $jsonObj['createdAt'] = $this->generatedAt;
        $jsonObj['name'] = $this->name;
        $jsonObj['data'] = $this->data;
        return json_encode($jsonObj, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }

    public function generateJSONtoFile($outputPath)
    {
        $jsonString = $this->generateJson();
        if (file_put_contents($outputPath, $jsonString) === false) {
            throw new \Exception("Failed to write JSON to file: $outputPath");
        }
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