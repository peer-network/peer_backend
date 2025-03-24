<?php

namespace Fawaz\Bench;

use RuntimeException;

class BenchException extends RuntimeException {}

class Bench
{
    private const EXISTING_STEP_EXCEPTION = "Step label already exists: ";

    /**
     * @var array
     */
    private array $steps = [];

    /**
     * Get the current time in microseconds
     */
    public function getTime(): float
    {
        return microtime(true);
    }

    /**
     * Get the current memory usage of PHP
     */
    public function getMemory(): int
    {
        return memory_get_usage();
    }

    /**
     * Get the peak memory usage of PHP
     */
    public function getMemoryPeak(): int
    {
        return memory_get_peak_usage();
    }

    /**
     * Get relevant usage stats from getrusage()
     */
    public function getUsage(): array
    {
        $usage = getrusage();
        return [
            'user_time' => ($usage['ru_utime.tv_usec'] / 1_000_000) + $usage['ru_utime.tv_sec'],
            'system_time' => ($usage['ru_stime.tv_usec'] / 1_000_000) + $usage['ru_stime.tv_sec'],
            'max_resident_size' => $usage['ru_maxrss']
        ];
    }

    /**
     * Add a new step
     */
    public function step(string $label): array
    {
        if (isset($this->steps[$label])) {
            throw new BenchException(self::EXISTING_STEP_EXCEPTION . $label);
        }

        return $this->steps[$label] = [
            'time' => $this->getTime(),
            'memory' => $this->getMemory(),
            'peak_memory' => $this->getMemoryPeak(),
            'usage' => $this->getUsage()
        ];
    }

    /**
     * Get all recorded steps
     */
    public function getSteps(): array
    {
        return $this->steps;
    }

    /**
     * Get time difference between two steps
     */
    public function getTimeDifference(string $primaryLabel, string $secondaryLabel): float
    {
        $this->validateLabels($primaryLabel, $secondaryLabel);
        return $this->steps[$secondaryLabel]['time'] - $this->steps[$primaryLabel]['time'];
    }

    /**
     * Get memory difference between two steps
     */
    public function getMemoryDifference(string $primaryLabel, string $secondaryLabel): int
    {
        $this->validateLabels($primaryLabel, $secondaryLabel);
        return $this->steps[$secondaryLabel]['memory'] - $this->steps[$primaryLabel]['memory'];
    }

    /**
     * Get usage difference between two steps
     */
    public function getUsageDifference(string $primaryLabel, string $secondaryLabel): array
    {
        $this->validateLabels($primaryLabel, $secondaryLabel);
        return [
            'user_time' => $this->steps[$secondaryLabel]['usage']['user_time'] - $this->steps[$primaryLabel]['usage']['user_time'],
            'system_time' => $this->steps[$secondaryLabel]['usage']['system_time'] - $this->steps[$primaryLabel]['usage']['system_time'],
            'max_resident_size' => $this->steps[$secondaryLabel]['usage']['max_resident_size'] - $this->steps[$primaryLabel]['usage']['max_resident_size'],
        ];
    }

    /**
     * Generate a report comparing two steps
     */
    public function getReport(string $primaryLabel, string $secondaryLabel): array
    {
        $this->validateLabels($primaryLabel, $secondaryLabel);

        $time = $this->getTimeDifference($primaryLabel, $secondaryLabel);
        $usage = $this->getUsageDifference($primaryLabel, $secondaryLabel);
        $memory = $this->getMemoryDifference($primaryLabel, $secondaryLabel);
        $memoryPeak = $this->getMemoryPeak();

        return [
            'Clock time (s)' => $time,
            'User time (s)' => $usage['user_time'] ?? 'N/A',
            'System time (s)' => $usage['system_time'] ?? 'N/A',
            'Total kernel time (s)' => ($usage['system_time'] + $usage['user_time']) ?? 'N/A',
            'Memory limit (MB)' => str_replace('M', '', ini_get('memory_limit')),
            'Memory usage (MB)' => round($memory / 1024 / 1024, 2),
            'Peak memory usage (MB)' => round($memoryPeak / 1024 / 1024, 2),
            'Max resident size (KB)' => $usage['max_resident_size'] ?? 'N/A',
        ];
    }

    /**
     * Validate if labels exist before comparing
     */
    private function validateLabels(string $primaryLabel, string $secondaryLabel): void
    {
        foreach ([$primaryLabel, $secondaryLabel] as $label) {
            if (!isset($this->steps[$label])) {
                throw new BenchException("Step not found: $label");
            }
        }
    }
}
