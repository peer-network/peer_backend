# WalletServiceTest Documentation

## Overview

The `WalletServiceTest` class ensures that the `WalletService` class correctly handles core wallet operations, such as checking balances, managing coin deductions, liquidity operations, and retrieving user-related financial data. This suite tests various scenarios to ensure the expected behavior of these services.

## Features Tested

### Wallet Balance
- **Valid Balances:** Verifies that the correct balance is returned for a user.
- **Edge Cases:** Tests for cases where the balance is zero or negative.

### Coin Deduction
- **Successful Deductions:** Validates that coins are correctly deducted from the wallet.
- **Insufficient Funds:** Ensures proper handling when there are not enough coins in the wallet.
- **Error Handling:** Verifies that invalid deduction amounts or actions are handled appropriately.

### Liquidity Management
- **Liquidity Loading:** Tests that liquidity is loaded correctly when requested.
- **Error Handling:** Ensures the system correctly handles errors when liquidity loading fails.

### Data Fetching
- **Wallet Data Retrieval:** Ensures that the `fetchAll`, `fetchPool`, and `fetchWalletById` methods return correct data.
- **Argument Validation:** Verifies that invalid UUIDs or arguments are handled gracefully.

### Logs and Statistics
- **Transaction Logs:** Ensures that transaction logs are retrieved and handled correctly.
- **Global Statistics:** Validates that global statistics are calculated and returned as expected.

### Error and Authorization Handling
- **Unauthorized Access:** Tests that unauthorized access is blocked.
- **Internal Errors:** Verifies that unexpected errors are handled properly and do not affect system stability.

## Running the Tests

To run the tests for `WalletServiceTest`, use the following command from the root of your project:

```bash
vendor/bin/phpunit tests/Service/WalletServiceTest.php

## To generate a code coverage report, run:

bash
Copy
vendor/bin/phpunit --coverage-html coverage tests/Service/WalletServiceTest.php