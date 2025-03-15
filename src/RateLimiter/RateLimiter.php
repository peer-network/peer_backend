<?php

namespace Fawaz\RateLimiter;

class RateLimiter
{
    private int $rateLimit;
    private int $timeWindow;
    private string $storageFile;

    public function __construct(int $rateLimit, int $timeWindow, string $ratePath)
    {
        $this->rateLimit = $rateLimit;
        $this->timeWindow = $timeWindow;
        $this->storageFile = rtrim($ratePath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . date('Y-m-d') . '_rate_limiter_storage.json';

        if (!file_exists($this->storageFile)) {
            file_put_contents($this->storageFile, json_encode([]), LOCK_EX);
        }
    }

    private function loadRequests(): array
    {
        $data = file_get_contents($this->storageFile);
        if ($data === false) {
            error_log("Failed to read rate limiter storage file.");
            return [];
        }

        $decodedData = json_decode($data, true);
        if (!is_array($decodedData)) {
            error_log("Failed to decode JSON from storage file: " . json_last_error_msg());
            return [];
        }

        return $decodedData;
    }

    private function saveRequests(array $requests): void
    {
        if (file_put_contents($this->storageFile, json_encode($requests, JSON_PRETTY_PRINT), LOCK_EX) === false) {
            error_log("Failed to write to rate limiter storage file.");
        }
    }

    public function isAllowed(string $identifier): bool
    {
        $currentTime = time();
        $requests = $this->loadRequests();

        $requests[$identifier] = array_filter($requests[$identifier] ?? [], fn(int $timestamp) => ($currentTime - $timestamp) < $this->timeWindow);

        if (count($requests[$identifier]) >= $this->rateLimit) {
            error_log("Rate limit exceeded for identifier: $identifier");
            return false;
        }

        $requests[$identifier][] = $currentTime;
        $this->saveRequests($requests);

        return true;
    }
}
