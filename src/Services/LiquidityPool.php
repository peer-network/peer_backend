<?php

declare(strict_types=1);

namespace Fawaz\Services;

class LiquidityPool
{
    private array $envi;

    // Constructor to initialize the envi property with an array
    public function __construct(array $envi)
    {
        $this->envi = $envi;
    }

    // Method to return accounts or environment data with a status
    public function returnAccounts(): array
    {
        $response = $this->envi;

        if (is_array($response)) {
            // Return success status along with the data
            return ['status' => 'success', 'response' => $response];
        } else {
            // Return error status with an empty array if not an array
            return ['status' => 'error', 'response' => []];
        }
    }
}
