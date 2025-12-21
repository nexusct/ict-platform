/**
 * Error Logging Service
 *
 * Centralized error logging with optional remote reporting
 *
 * @package ICT_Platform
 * @since   1.0.0
 */

interface ErrorLogEntry {
  type: 'api_error' | 'react_error' | 'validation_error' | 'network_error' | 'unknown';
  message: string;
  stack?: string;
  componentStack?: string;
  timestamp: string;
  context?: Record<string, unknown>;
  userId?: number;
  url?: string;
  userAgent?: string;
}

interface ErrorLoggerConfig {
  maxLogs?: number;
  enableConsole?: boolean;
  enableRemote?: boolean;
  remoteEndpoint?: string;
  sampleRate?: number;
}

class ErrorLogger {
  private logs: ErrorLogEntry[] = [];
  private config: Required<ErrorLoggerConfig>;

  constructor(config: ErrorLoggerConfig = {}) {
    this.config = {
      maxLogs: config.maxLogs ?? 100,
      enableConsole: config.enableConsole ?? true,
      enableRemote: config.enableRemote ?? false,
      remoteEndpoint: config.remoteEndpoint ?? '/wp-json/ict-platform/v1/errors',
      sampleRate: config.sampleRate ?? 1.0, // 100% by default
    };

    // Catch unhandled errors
    this.setupGlobalHandlers();
  }

  private setupGlobalHandlers(): void {
    // Unhandled promise rejections
    window.addEventListener('unhandledrejection', (event) => {
      this.log({
        type: 'unknown',
        message: event.reason?.message || 'Unhandled Promise Rejection',
        stack: event.reason?.stack,
        timestamp: new Date().toISOString(),
      });
    });

    // Global errors
    window.addEventListener('error', (event) => {
      this.log({
        type: 'unknown',
        message: event.message,
        stack: event.error?.stack,
        timestamp: new Date().toISOString(),
        context: {
          filename: event.filename,
          lineno: event.lineno,
          colno: event.colno,
        },
      });
    });
  }

  log(entry: ErrorLogEntry): void {
    // Apply sampling
    if (Math.random() > this.config.sampleRate) {
      return;
    }

    // Enrich with additional context
    const enrichedEntry: ErrorLogEntry = {
      ...entry,
      url: window.location.href,
      userAgent: navigator.userAgent,
      userId: this.getCurrentUserId(),
    };

    // Console logging
    if (this.config.enableConsole) {
      console.error('[ICT Error]', enrichedEntry);
    }

    // Store in memory
    this.logs.unshift(enrichedEntry);
    if (this.logs.length > this.config.maxLogs) {
      this.logs.pop();
    }

    // Store in localStorage for persistence
    this.persistLogs();

    // Remote logging
    if (this.config.enableRemote) {
      this.sendToRemote(enrichedEntry);
    }
  }

  private getCurrentUserId(): number | undefined {
    try {
      // @ts-expect-error - WordPress localized variable
      return window.ictPlatform?.currentUser;
    } catch {
      return undefined;
    }
  }

  private persistLogs(): void {
    try {
      const recentLogs = this.logs.slice(0, 20); // Keep only 20 most recent
      localStorage.setItem('ict_error_logs', JSON.stringify(recentLogs));
    } catch {
      // Storage might be full or disabled
    }
  }

  private async sendToRemote(entry: ErrorLogEntry): Promise<void> {
    try {
      // @ts-expect-error - WordPress localized variable
      const nonce = window.ictPlatform?.nonce;

      await fetch(this.config.remoteEndpoint, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          ...(nonce ? { 'X-WP-Nonce': nonce } : {}),
        },
        body: JSON.stringify(entry),
      });
    } catch {
      // Silently fail to avoid infinite loops
    }
  }

  getLogs(): ErrorLogEntry[] {
    return [...this.logs];
  }

  getPersistedLogs(): ErrorLogEntry[] {
    try {
      const stored = localStorage.getItem('ict_error_logs');
      return stored ? JSON.parse(stored) : [];
    } catch {
      return [];
    }
  }

  clearLogs(): void {
    this.logs = [];
    localStorage.removeItem('ict_error_logs');
  }

  // Helper methods for specific error types
  logApiError(
    message: string,
    context?: { endpoint?: string; status?: number; response?: unknown }
  ): void {
    this.log({
      type: 'api_error',
      message,
      timestamp: new Date().toISOString(),
      context,
    });
  }

  logNetworkError(message: string, context?: Record<string, unknown>): void {
    this.log({
      type: 'network_error',
      message,
      timestamp: new Date().toISOString(),
      context,
    });
  }

  logValidationError(message: string, context?: { field?: string; value?: unknown }): void {
    this.log({
      type: 'validation_error',
      message,
      timestamp: new Date().toISOString(),
      context,
    });
  }
}

// Export singleton instance
export const errorLogger = new ErrorLogger({
  enableConsole: process.env.NODE_ENV !== 'production',
  enableRemote: process.env.NODE_ENV === 'production',
});

export default errorLogger;
