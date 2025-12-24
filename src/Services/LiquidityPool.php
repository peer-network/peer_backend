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

        return ['status' => 'success', 'response' => $response];
    }
}
