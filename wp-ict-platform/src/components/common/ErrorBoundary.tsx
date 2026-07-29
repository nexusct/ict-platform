/**
 * Error Boundary Component
 *
 * Catches JavaScript errors anywhere in child component tree and displays fallback UI
 *
 * @package ICT_Platform
 * @since   1.0.0
 */

import { Component, ErrorInfo, ReactNode } from 'react';
import { errorLogger } from '../../utils/errorLogger';

interface Props {
  children: ReactNode;
  fallback?: ReactNode;
  onError?: (error: Error, errorInfo: ErrorInfo) => void;
  showDetails?: boolean;
}

interface State {
  hasError: boolean;
  error: Error | null;
  errorInfo: ErrorInfo | null;
}

export class ErrorBoundary extends Component<Props, State> {
  constructor(props: Props) {
    super(props);
    this.state = {
      hasError: false,
      error: null,
      errorInfo: null,
    };
  }

  static getDerivedStateFromError(error: Error): Partial<State> {
    return { hasError: true, error };
  }

  componentDidCatch(error: Error, errorInfo: ErrorInfo): void {
    this.setState({ errorInfo });

    // Log to error service
    errorLogger.log({
      type: 'react_error',
      message: error.message,
      stack: error.stack ?? undefined,
      componentStack: errorInfo.componentStack ?? undefined,
      timestamp: new Date().toISOString(),
    });

    // Call custom error handler if provided
    this.props.onError?.(error, errorInfo);
  }

  handleRetry = (): void => {
    this.setState({
      hasError: false,
      error: null,
      errorInfo: null,
    });
  };

  render(): ReactNode {
    const { hasError, error, errorInfo } = this.state;
    const { children, fallback, showDetails } = this.props;

    if (hasError) {
      if (fallback) {
        return fallback;
      }

      return (
        <div className="ict-error-boundary" role="alert" aria-live="assertive">
          <div className="ict-error-boundary__content">
            <div className="ict-error-boundary__icon" aria-hidden="true">
              <svg
                width="64"
                height="64"
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                strokeWidth="2"
              >
                <circle cx="12" cy="12" r="10" />
                <line x1="12" y1="8" x2="12" y2="12" />
                <line x1="12" y1="16" x2="12.01" y2="16" />
              </svg>
            </div>
            <h2 className="ict-error-boundary__title">Something went wrong</h2>
            <p className="ict-error-boundary__message">
              We encountered an unexpected error. Please try again or contact support if the problem
              persists.
            </p>

            {showDetails && error && (
              <details className="ict-error-boundary__details">
                <summary>Technical Details</summary>
                <pre className="ict-error-boundary__stack">
                  <strong>Error:</strong> {error.message}
                  {'\n\n'}
                  <strong>Stack:</strong>
                  {'\n'}
                  {error.stack}
                  {errorInfo?.componentStack && (
                    <>
                      {'\n\n'}
                      <strong>Component Stack:</strong>
                      {'\n'}
                      {errorInfo.componentStack}
                    </>
                  )}
                </pre>
              </details>
            )}

            <div className="ict-error-boundary__actions">
              <button
                onClick={this.handleRetry}
                className="ict-button ict-button--primary"
                type="button"
              >
                <svg
                  width="16"
                  height="16"
                  viewBox="0 0 24 24"
                  fill="none"
                  stroke="currentColor"
                  strokeWidth="2"
                  aria-hidden="true"
                >
                  <path d="M23 4v6h-6" />
                  <path d="M1 20v-6h6" />
                  <path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15" />
                </svg>
                Try Again
              </button>
              <button
                onClick={() => window.location.reload()}
                className="ict-button ict-button--secondary"
                type="button"
              >
                Reload Page
              </button>
            </div>
          </div>
        </div>
      );
    }

    return children;
  }
}

export default ErrorBoundary;
