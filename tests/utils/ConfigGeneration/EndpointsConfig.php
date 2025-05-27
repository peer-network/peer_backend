<?php
declare(strict_types=1);

namespace Tests\Utils\ConfigGeneration;

class EndpointsConfigConstants {
    static $testBackendServerEndpoint = "https://peer-network.eu/graphql";
    static $testProductionServerEndpoint = "https://getpeer.eu/graphql";
    static $productionServerEndpoint = "https://peernetwork.eu/graphql";

    static $requiredPlatforms = ["ios","android","web"];
}

class EndpointsConfig implements DataGeneratable {
    /** @var array<string, MessageEntry> */
    private array $data = [];


    public function __construct($filePath)
    {  
        
        $decoded = JSONHandler::parseInputJson($filePath);

        foreach ($decoded as $code => $entry) {
            $targets = [];
            foreach ($entry as $target) {
                $targets[] = new TargetEntry(
                    $target['version'],
                    $target['url']
                );
            }
            $this->data[$code] = $targets;
        }
    }

    public function getData(): array {
        return $this->data;
    }
}

class TargetEntry {
    public string $version;
    public string $url;

    public function __construct(string $version, string $url) {
        $this->version = $version;
        $this->url = $url;
    }
}