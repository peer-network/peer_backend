<?php

declare(strict_types=1);

namespace Fawaz\Utils;

use InvalidArgumentException;
use RuntimeException;

class ResponseMessagesProviderImpl implements ResponseMessagesProvider
{
    /**
     * @var string
     */
    private string $filePath;

    /**
     * @var array|null
     */
    private ?array $messages = null;

    /**
     * Constructor accepts path to JSON file
     */
    public function __construct(string $filePath)
    {
        if (!is_file($filePath)) {
            throw new InvalidArgumentException("File not found: {$filePath}");
        }

        $this->filePath = $filePath;
        $this->loadMessages();
    }

    /**
     * Load and parse the JSON file into associative array
     */
    private function loadMessages(): void
    {
        if ($this->messages !== null) {
            return; // already loaded
        }

        $json = file_get_contents($this->filePath);
        if ($json === false) {
            throw new RuntimeException("Failed to read file: {$this->filePath}");
        }

        $data = json_decode($json, true);
        if ($data === null) {
            throw new RuntimeException("Invalid JSON in file: {$this->filePath}");
        }

        // Extract "data" block and flatten to code => userFriendlyComment
        $this->messages = [];
        foreach ($data['data'] ?? [] as $code => $entry) {
            $this->messages[$code] = $entry['comment'] ?? "oops, no message";
        }
    }

    /**
     * Get a specific message by code
     */
    public function getMessage(string $code): ?string
    {
        $this->loadMessages();
        return $this->messages[$code] ?? null;
    }
}
