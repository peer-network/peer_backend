import { LoggerManager } from './manager';
import { loggerConfig } from './config';
import { LoggerStats } from './types';

// Export types
export * from './types';

// Export configuration
export { loggerConfig };

// Create singleton instance
const loggerManager = LoggerManager.getInstance();

// Export logger operations
export const logger = {
  debug: (message: string, data?: any, context?: Record<string, any>) => 
    loggerManager.debug(message, data, context),
  info: (message: string, data?: any, context?: Record<string, any>) => 
    loggerManager.info(message, data, context),
  warn: (message: string, data?: any, context?: Record<string, any>) => 
    loggerManager.warn(message, data, context),
  error: (message: string, data?: any, context?: Record<string, any>) => 
    loggerManager.error(message, data, context),
  getStats: (): LoggerStats => loggerManager.getStats(),
  stop: () => loggerManager.stop()
}; 