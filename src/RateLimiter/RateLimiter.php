<?php

namespace Fawaz\RateLimiter;

class RateLimiter
{
    private int $rateLimit;
    private int $timeWindow;
    private string $storageFile;

    public function __construct(int $rateLimit, int $timeWindow, string $ratepath)
    {
        $this->rateLimit = $rateLimit;
        $this->timeWindow = $timeWindow;
        $this->storageFile = $ratepath . date('Y-m-d') . '_rate_limiter_storage.json';

        if (!file_exists($this->storageFile)) {
            file_put_contents($this->storageFile, json_encode([]));
        }
    }

    private function loadRequests(): array
    {
        $data = file_get_contents($this->storageFile);
        $decodedData = json_decode($data, true);

        if ($decodedData === null && json_last_error() !== JSON_ERROR_NONE) {
            error_log("Failed to decode JSON from storage file: " . json_last_error_msg());
            return [];
        }

        return is_array($decodedData) ? $decodedData : [];
    }

    private function saveRequests(array $requests): void
    {
        file_put_contents($this->storageFile, json_encode($requests));
    }

    public function isAllowed(string $identifier): bool
    {
        $currentTime = time();
        $requests = $this->loadRequests();

        if (isset($requests[$identifier])) {
            $requests[$identifier] = array_filter(
                $requests[$identifier],
                function (int $timestamp) use ($currentTime): bool {
                    return ($currentTime - $timestamp) < $this->timeWindow;
                }
            );
        }

        if (!isset($requests[$identifier])) {
            $requests[$identifier] = [];
        }

        if (count($requests[$identifier]) >= $this->rateLimit) {
            error_log("Rate limit exceeded for identifier: $identifier");
            return false;
        }

        $requests[$identifier][] = $currentTime;
        $this->saveRequests($requests);

        return true;
    }
}
