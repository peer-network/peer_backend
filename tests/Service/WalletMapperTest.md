# WalletMapperTest Documentation

## Overview

The `WalletMapperTest` class is designed to validate the expected behavior of the `WalletMapper` class. These tests check that the `WalletMapper` methods return the expected data structures and handle edge cases and errors appropriately.

## Features Tested

### Response Structures
- **Method Validation:** Ensures that methods like `callUserMove`, `callGlobalWins`, `fetchPool`, and `fetchAll` return the correct data formats, with the expected response codes and data structure.

### Data Handling
- **Empty Data Sets:** Verifies the correct handling of empty datasets or missing data.
- **Unusual Response Codes:** Ensures that the mapper class handles unexpected or unusual response codes properly.

### Exception Handling
- **Error Simulation:** Simulates database-related errors and checks that the system responds correctly.
- **Edge Case Handling:** Tests the system’s behavior when unusual or unexpected responses are encountered.

## Important Notes

- These tests use mocks to simulate interactions with the database. No real database queries are made in this test suite.
- Integration tests should be written separately to verify the actual database logic.

## Running the Tests

To run the tests for `WalletMapperTest`, execute the following command from the project root:

```bash
vendor/bin/phpunit tests/Service/WalletMapperTest.php
