<?php

declare(strict_types=1);

namespace Tests\utils\ConfigGeneration;

class EndpointsConfig implements DataGeneratable
{
    /** @var array<string, TargetEntry> */
    private array $data = [];

    public function __construct(string $filePath)
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

    public function getData(): array
    {
        return $this->data;
    }
}

class TargetEntry
{
    public string $version;
    public string $url;

    public function __construct(string $version, string $url)
    {
        $this->version = $version;
        $this->url = $url;
    }
}
