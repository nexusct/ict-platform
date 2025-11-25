/**
 * API Utility Functions
 *
 * Retry logic, timeout handling, and error parsing
 *
 * @package ICT_Platform
 * @since   1.0.0
 */

import { AxiosError, AxiosRequestConfig, AxiosInstance } from 'axios';
import { errorLogger } from './errorLogger';

interface RetryConfig {
  maxRetries?: number;
  baseDelay?: number;
  maxDelay?: number;
  retryCondition?: (error: AxiosError) => boolean;
  onRetry?: (retryCount: number, error: AxiosError) => void;
}

const DEFAULT_RETRY_CONFIG: Required<RetryConfig> = {
  maxRetries: 3,
  baseDelay: 1000,
  maxDelay: 10000,
  retryCondition: (error: AxiosError) => {
    // Retry on network errors and 5xx server errors
    if (!error.response) return true; // Network error
    const status = error.response.status;
    return status >= 500 && status < 600;
  },
  onRetry: () => {},
};

/**
 * Calculate exponential backoff delay
 */
export function calculateBackoff(
  retryCount: number,
  baseDelay: number,
  maxDelay: number
): number {
  const delay = Math.min(baseDelay * Math.pow(2, retryCount), maxDelay);
  // Add jitter to prevent thundering herd
  const jitter = delay * 0.1 * Math.random();
  return delay + jitter;
}

/**
 * Sleep for specified milliseconds
 */
export function sleep(ms: number): Promise<void> {
  return new Promise((resolve) => setTimeout(resolve, ms));
}

/**
 * Add retry interceptor to axios instance
 */
export function addRetryInterceptor(
  axiosInstance: AxiosInstance,
  config: RetryConfig = {}
): void {
  const retryConfig = { ...DEFAULT_RETRY_CONFIG, ...config };

  axiosInstance.interceptors.response.use(
    (response) => response,
    async (error: AxiosError) => {
      const originalRequest = error.config as AxiosRequestConfig & { _retryCount?: number };

      if (!originalRequest) {
        return Promise.reject(error);
      }

      originalRequest._retryCount = originalRequest._retryCount || 0;

      const shouldRetry =
        originalRequest._retryCount < retryConfig.maxRetries &&
        retryConfig.retryCondition(error);

      if (shouldRetry) {
        originalRequest._retryCount += 1;

        const delay = calculateBackoff(
          originalRequest._retryCount,
          retryConfig.baseDelay,
          retryConfig.maxDelay
        );

        retryConfig.onRetry(originalRequest._retryCount, error);

        await sleep(delay);
        return axiosInstance(originalRequest);
      }

      return Promise.reject(error);
    }
  );
}

/**
 * Add timeout interceptor
 */
export function addTimeoutInterceptor(
  axiosInstance: AxiosInstance,
  defaultTimeout: number = 30000
): void {
  axiosInstance.interceptors.request.use((config) => {
    config.timeout = config.timeout || defaultTimeout;
    return config;
  });
}

/**
 * Parse API error into user-friendly message
 */
export interface ParsedError {
  message: string;
  details?: string;
  code?: string;
  field?: string;
  statusCode?: number;
  isNetworkError: boolean;
  isTimeoutError: boolean;
  isServerError: boolean;
  isClientError: boolean;
  isValidationError: boolean;
}

export function parseApiError(error: unknown): ParsedError {
  const parsed: ParsedError = {
    message: 'An unexpected error occurred',
    isNetworkError: false,
    isTimeoutError: false,
    isServerError: false,
    isClientError: false,
    isValidationError: false,
  };

  if (error instanceof AxiosError) {
    // Network error (no response)
    if (!error.response) {
      parsed.isNetworkError = true;
      parsed.message = 'Unable to connect to the server. Please check your internet connection.';

      if (error.code === 'ECONNABORTED' || error.message.includes('timeout')) {
        parsed.isTimeoutError = true;
        parsed.message = 'The request timed out. Please try again.';
      }

      return parsed;
    }

    const { status, data } = error.response;
    parsed.statusCode = status;

    // Server error (5xx)
    if (status >= 500) {
      parsed.isServerError = true;
      parsed.message = 'The server encountered an error. Please try again later.';
      parsed.code = `SERVER_ERROR_${status}`;
    }
    // Client error (4xx)
    else if (status >= 400) {
      parsed.isClientError = true;

      switch (status) {
        case 400:
          parsed.message = (data as { message?: string })?.message || 'Invalid request';
          parsed.isValidationError = true;
          break;
        case 401:
          parsed.message = 'Your session has expired. Please log in again.';
          break;
        case 403:
          parsed.message = 'You do not have permission to perform this action.';
          break;
        case 404:
          parsed.message = 'The requested resource was not found.';
          break;
        case 409:
          parsed.message = 'A conflict occurred. Please refresh and try again.';
          break;
        case 422:
          parsed.message = (data as { message?: string })?.message || 'Validation failed';
          parsed.isValidationError = true;
          // Extract field-level errors if available
          const errors = (data as { errors?: Record<string, string[]> })?.errors;
          if (errors) {
            const firstField = Object.keys(errors)[0];
            parsed.field = firstField;
            parsed.details = errors[firstField]?.[0];
          }
          break;
        case 429:
          parsed.message = 'Too many requests. Please wait a moment and try again.';
          break;
        default:
          parsed.message = (data as { message?: string })?.message || 'An error occurred';
      }
    }

    // Log error for debugging
    errorLogger.logApiError(parsed.message, {
      endpoint: error.config?.url,
      status,
      response: data,
    });
  } else if (error instanceof Error) {
    parsed.message = error.message;
  }

  return parsed;
}

/**
 * Create a cancellable request
 */
export function createCancellableRequest() {
  const controller = new AbortController();

  return {
    signal: controller.signal,
    cancel: () => controller.abort(),
    isCancelled: () => controller.signal.aborted,
  };
}

/**
 * Format validation errors for display
 */
export function formatValidationErrors(
  errors: Record<string, string[]>
): Record<string, string> {
  return Object.entries(errors).reduce(
    (acc, [field, messages]) => {
      acc[field] = messages[0] || 'Invalid value';
      return acc;
    },
    {} as Record<string, string>
  );
}

export default {
  addRetryInterceptor,
  addTimeoutInterceptor,
  parseApiError,
  createCancellableRequest,
  formatValidationErrors,
};
