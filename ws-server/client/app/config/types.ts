// GraphQL configuration interface

export interface GraphQLConfig {
  APP_NAME: string
  APP_URL: string
  APP_PORT: number
  GRAPHQL_ENDPOINT: string
  PEER_BACKEND_GRAPHQL_ENDPOINT: string
  PEER_BACKEND_API_TOKEN: string
  // API_TOKEN: string;          // Authentication token
  // REQUEST_TIMEOUT: number;    // Request timeout in milliseconds
  // MAX_RETRIES: number;        // Maximum number of retry attempts
}

// App configuration interface
export interface AppConfig {
//   NODE_ENV: 'development' | 'production' | 'test';  // Environment mode
//   LOG_LEVEL: 'debug' | 'info' | 'warn' | 'error';  // Logging level
}

// Main configuration interface
export interface Config {
  APP: AppConfig;
  GRAPHQL: GraphQLConfig;
}