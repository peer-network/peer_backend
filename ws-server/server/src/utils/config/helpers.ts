import { Config, GraphQLConfig, AppConfig } from './types';
import { getRequiredEnv, getIntEnv, getEnumEnv } from './validators';

// Default configuration values
const DEFAULT_CONFIG = {
  GRAPHQL: {
    REQUEST_TIMEOUT: 30000,  // 30 seconds
    MAX_RETRIES: 3
  },
  APP: {
    NODE_ENV: 'development' as const,
    LOG_LEVEL: 'info' as const
  }
};

// Create GraphQL configuration
export function createGraphQLConfig(): GraphQLConfig {
  return {
    ENDPOINT: getRequiredEnv('GRAPHQL_ENDPOINT'),
    API_TOKEN: getRequiredEnv('GRAPHQL_API_TOKEN'),
    REQUEST_TIMEOUT: getIntEnv('GRAPHQL_REQUEST_TIMEOUT', DEFAULT_CONFIG.GRAPHQL.REQUEST_TIMEOUT),
    MAX_RETRIES: getIntEnv('GRAPHQL_MAX_RETRIES', DEFAULT_CONFIG.GRAPHQL.MAX_RETRIES)
  };
}

// Create App configuration
export function createAppConfig(): AppConfig {
  return {
    NODE_ENV: getEnumEnv('NODE_ENV', DEFAULT_CONFIG.APP.NODE_ENV, ['development', 'production', 'test']),
    LOG_LEVEL: getEnumEnv('LOG_LEVEL', DEFAULT_CONFIG.APP.LOG_LEVEL, ['debug', 'info', 'warn', 'error'])
  };
}

// Create full configuration
export function createConfig(): Config {
  return {
    APP: createAppConfig(),
    GRAPHQL: createGraphQLConfig()
  };
}

export const sleep = (ms: number): Promise<void> => 
  new Promise(resolve => setTimeout(resolve, ms));

export const exponentialBackoff = (attempt: number, baseDelay: number = 1000): number => 
  Math.min(baseDelay * Math.pow(2, attempt - 1), 30000); // Max 30 seconds

export const formatError = (error: unknown): string => {
  if (error instanceof Error) {
    return `${error.name}: ${error.message}`;
  }
  return String(error);
}; 