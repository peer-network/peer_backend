# Base64FileHandlerTest Documentation

## Overview

The `Base64FileHandlerTest` class validates the correctness of base64 encoding and decoding functionality, which is essential for data transmission, file uploads, and maintaining data integrity within the application.

## Features Tested

### Standard Encoding and Decoding
- **Text and Binary Data:** Verifies that both text and binary data can be correctly encoded and decoded using base64 without loss of data integrity.
- **Data Integrity:** Ensures that the original data matches the decoded result after encoding and decoding cycles.

### Invalid Input Handling
- **Malformed Base64:** Tests that invalid or corrupted base64 strings are correctly rejected.
- **Whitespace Handling:** Ensures that base64 strings with extra spaces are correctly handled and do not affect the result.

### Special Characters and UTF-8
- **Multilingual Content:** Verifies that non-ASCII characters, such as UTF-8 encoded text and special characters, are correctly encoded and decoded.
- **Binary Data:** Ensures that raw binary data is correctly processed by the base64 functions.

### Data Integrity Checks
- **Re-encoding and Decoding:** Verifies that data remains intact after multiple encoding and decoding operations.
- **Padding:** Ensures correct handling of base64 padding (`=` and `==`).

### MIME and Data URIs
- **MIME-Type Handling:** Validates that base64 data with MIME-type headers (e.g., images) is decoded correctly.
- **Data URI Handling:** Verifies that data URIs, which are commonly used for embedded images or file data, are parsed and decoded correctly.

### Utility Method
- **`isProbablyBase64(string $input): bool`**: This method checks whether a given string is a valid base64-encoded string.

## Running the Tests

To run the tests for `Base64FileHandlerTest`, use the following command:

```bash
vendor/bin/phpunit tests/Service/Base64FileHandlerTest.php
