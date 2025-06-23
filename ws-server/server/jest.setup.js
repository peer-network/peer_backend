// Load environment variables from .env.test file if it exists
require('dotenv').config({ path: '.env.test' });

// Mock console methods to keep test output clean
global.console = {
  ...console,
  log: jest.fn(),
  info: jest.fn(),
  warn: jest.fn(),
  error: jest.fn(),
  debug: jest.fn(),
};

// Set default timeout for all tests
jest.setTimeout(10000);

// Mock environment variables
process.env.GRAPHQL_ENDPOINT = 'https://test-api.example.com/graphql';
process.env.API_TOKEN = 'test-token'; 