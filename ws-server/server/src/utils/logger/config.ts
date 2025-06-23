import { LoggerConfig } from './types';
import { getEnumEnv, getBoolEnv } from '../config/validators';

// Default logger configuration
const DEFAULT_CONFIG: LoggerConfig = {
  level: 'info',
  format: 'json',
  enableConsole: true,
  enableFile: false
};

// Create logger configuration
export function createLoggerConfig(): LoggerConfig {
  return {
    level: getEnumEnv('LOG_LEVEL', DEFAULT_CONFIG.level, ['debug', 'info', 'warn', 'error']),
    format: getEnumEnv('LOG_FORMAT', DEFAULT_CONFIG.format, ['json', 'text']),
    enableConsole: getBoolEnv('LOG_ENABLE_CONSOLE', DEFAULT_CONFIG.enableConsole),
    enableFile: getBoolEnv('LOG_ENABLE_FILE', DEFAULT_CONFIG.enableFile),
    filePath: process.env.LOG_FILE_PATH
  };
}

// Export the logger configuration
export const loggerConfig = createLoggerConfig(); 