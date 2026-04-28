<?php

declare(strict_types=1);

namespace Fawaz\ShadowDispatch;

/**
 * Disk-backed dead-letter queue for shadow dispatches that failed to
 * complete (peergamma timeout, OTLP collector down, fork exception).
 *
 * Writes one JSON file per failure to a configurable directory. A
 * separate cron job (out of scope for this commit — tracked as a
 * follow-up bead) is expected to drain entries by replaying the
 * recorded request against peergamma and emitting the deferred OTLP
 * span.
 *
 * The DLQ is intentionally crash-only: the only durable operation is
 * an atomic `tempnam → write → rename` pair. There is no in-memory
 * index, no compaction, no retention policy enforced here.
 *
 * Bounded-cardinality protection: when the queue exceeds maxEntries,
 * new writes are dropped (counted in stats only). This prevents a
 * sustained peergamma outage from filling the disk in the per-worker
 * runtime-data volume.
 */
final class Dlq
{
    public function __construct(
        private string $directory,
        private int $maxEntries = 10_000
    ) {}

    /**
     * Record a failed dispatch for later replay.
     *
     * @param array<string,mixed> $entry
     */
    public function write(array $entry): bool
    {
        if (!$this->ensureDirectory()) {
            return false;
        }
        if ($this->countEntries() >= $this->maxEntries) {
            return false;
        }

        $entry = ['enqueued_at' => date(\DateTimeInterface::RFC3339_EXTENDED)] + $entry;
        $json = json_encode($entry, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($json === false) {
            return false;
        }

        $tmp = @tempnam($this->directory, 'dlq.');
        if ($tmp === false) {
            return false;
        }
        if (@file_put_contents($tmp, $json, LOCK_EX) === false) {
            @unlink($tmp);
            return false;
        }
        $final = $this->directory . '/' . sprintf(
            'dlq-%s-%s.json',
            date('Ymd-His'),
            bin2hex(random_bytes(4))
        );
        if (!@rename($tmp, $final)) {
            @unlink($tmp);
            return false;
        }
        return true;
    }

    public function countEntries(): int
    {
        if (!is_dir($this->directory)) {
            return 0;
        }
        $count = 0;
        $dh = @opendir($this->directory);
        if ($dh === false) {
            return 0;
        }
        while (($f = readdir($dh)) !== false) {
            if (str_starts_with($f, 'dlq-') && str_ends_with($f, '.json')) {
                $count++;
            }
        }
        closedir($dh);
        return $count;
    }

    private function ensureDirectory(): bool
    {
        if (is_dir($this->directory)) {
            return true;
        }
        return @mkdir($this->directory, 0o755, true) || is_dir($this->directory);
    }
}
