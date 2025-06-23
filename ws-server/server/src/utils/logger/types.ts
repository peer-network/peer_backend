// Log level type
export type LogLevel = 'debug' | 'info' | 'warn' | 'error';

// Log message interface
export interface LogMessage {
  level: LogLevel;
  message: string;
  timestamp: string;
  data?: any;
  context?: Record<string, any>;
}

// Logger configuration interface
export interface LoggerConfig {
  level: LogLevel;
  format: 'json' | 'text';
  enableConsole: boolean;
  enableFile: boolean;
  filePath?: string;
}

// Logger statistics interface
export interface LoggerStats {
  debugCount: number;
  infoCount: number;
  warnCount: number;
  errorCount: number;
  lastLogTime: string;
} 