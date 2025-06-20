import { LogLevel, LogMessage, LoggerConfig, LoggerStats } from './types';
import { loggerConfig } from './config';
import fs from 'fs';

export class LoggerManager {
  private static instance: LoggerManager;
  private config: LoggerConfig;
  private stats: LoggerStats;
  private logStream?: fs.WriteStream;

  private constructor() {
    this.config = loggerConfig;
    this.stats = {
      debugCount: 0,
      infoCount: 0,
      warnCount: 0,
      errorCount: 0,
      lastLogTime: new Date().toISOString()
    };
    this.initialize();
  }

  public static getInstance(): LoggerManager {
    if (!LoggerManager.instance) {
      LoggerManager.instance = new LoggerManager();
    }
    return LoggerManager.instance;
  }

  private initialize(): void {
    if (this.config.enableFile && this.config.filePath) {
      this.logStream = fs.createWriteStream(this.config.filePath, { flags: 'a' });
    }
  }

  private formatMessage(level: LogLevel, message: string, data?: any, context?: Record<string, any>): LogMessage {
    return {
      level,
      message,
      timestamp: new Date().toISOString(),
      data,
      context
    };
  }

  private shouldLog(level: LogLevel): boolean {
    const levels: LogLevel[] = ['debug', 'info', 'warn', 'error'];
    return levels.indexOf(level) >= levels.indexOf(this.config.level);
  }

  private writeLog(logMessage: LogMessage): void {
    const formattedMessage = this.config.format === 'json'
      ? JSON.stringify(logMessage)
      : `[${logMessage.timestamp}] ${logMessage.level.toUpperCase()}: ${logMessage.message}`;

    if (this.config.enableConsole) {
      console.log(formattedMessage);
    }

    if (this.config.enableFile && this.logStream) {
      this.logStream.write(formattedMessage + '\n');
    }

    // Update stats
    this.stats[`${logMessage.level}Count`]++;
    this.stats.lastLogTime = logMessage.timestamp;
  }

  public log(level: LogLevel, message: string, data?: any, context?: Record<string, any>): void {
    if (!this.shouldLog(level)) return;

    const logMessage = this.formatMessage(level, message, data, context);
    this.writeLog(logMessage);
  }

  public debug(message: string, data?: any, context?: Record<string, any>): void {
    this.log('debug', message, data, context);
  }

  public info(message: string, data?: any, context?: Record<string, any>): void {
    this.log('info', message, data, context);
  }

  public warn(message: string, data?: any, context?: Record<string, any>): void {
    this.log('warn', message, data, context);
  }

  public error(message: string, data?: any, context?: Record<string, any>): void {
    this.log('error', message, data, context);
  }

  public getStats(): LoggerStats {
    return { ...this.stats };
  }

  public stop(): void {
    if (this.logStream) {
      this.logStream.end();
    }
  }
} 