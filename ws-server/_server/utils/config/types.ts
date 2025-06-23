// GraphQL configuration interface
export interface GraphQLConfig {
  ENDPOINT: string;           // GraphQL API endpoint
  API_TOKEN: string;          // Authentication token
  REQUEST_TIMEOUT: number;    // Request timeout in milliseconds
  MAX_RETRIES: number;        // Maximum number of retry attempts
}

// App configuration interface
export interface AppConfig {
  NODE_ENV: 'development' | 'production' | 'test';  // Environment mode
  LOG_LEVEL: 'debug' | 'info' | 'warn' | 'error';  // Logging level
}

// Environment variable types for type safety
export type EnvVars = {
  GRAPHQL_ENDPOINT: string;
  GRAPHQL_API_TOKEN: string;
  GRAPHQL_REQUEST_TIMEOUT?: string;
  GRAPHQL_MAX_RETRIES?: string;
  NODE_ENV?: string;
  LOG_LEVEL?: string;
}

// Main configuration interface
export interface Config {
  APP: AppConfig;
  GRAPHQL: GraphQLConfig;
} 