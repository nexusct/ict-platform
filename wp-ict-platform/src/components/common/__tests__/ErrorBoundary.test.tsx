/**
 * ErrorBoundary Component Tests
 *
 * @package ICT_Platform
 * @since   1.0.0
 */

import React from 'react';
import { render, screen, fireEvent, waitFor } from '@testing-library/react';
import { ErrorBoundary } from '../ErrorBoundary';

// Mock the errorLogger
jest.mock('../../../utils/errorLogger', () => ({
  errorLogger: {
    log: jest.fn(),
  },
}));

// Component that throws an error
const ThrowError: React.FC<{ shouldThrow?: boolean }> = ({ shouldThrow = true }) => {
  if (shouldThrow) {
    throw new Error('Test error');
  }
  return <div>No error</div>;
};

describe('ErrorBoundary', () => {
  // Suppress console.error for expected errors
  const originalError = console.error;
  beforeEach(() => {
    console.error = jest.fn();
  });
  afterEach(() => {
    console.error = originalError;
  });

  it('renders children when there is no error', () => {
    render(
      <ErrorBoundary>
        <div>Test content</div>
      </ErrorBoundary>
    );

    expect(screen.getByText('Test content')).toBeInTheDocument();
  });

  it('renders fallback UI when there is an error', () => {
    render(
      <ErrorBoundary>
        <ThrowError />
      </ErrorBoundary>
    );

    expect(screen.getByText('Something went wrong')).toBeInTheDocument();
  });

  it('renders custom fallback when provided', () => {
    render(
      <ErrorBoundary fallback={<div>Custom error message</div>}>
        <ThrowError />
      </ErrorBoundary>
    );

    expect(screen.getByText('Custom error message')).toBeInTheDocument();
  });

  it('calls onError callback when error occurs', () => {
    const onError = jest.fn();
    render(
      <ErrorBoundary onError={onError}>
        <ThrowError />
      </ErrorBoundary>
    );

    expect(onError).toHaveBeenCalled();
    expect(onError.mock.calls[0][0]).toBeInstanceOf(Error);
    expect(onError.mock.calls[0][0].message).toBe('Test error');
  });

  it('shows error details when showDetails is true', () => {
    render(
      <ErrorBoundary showDetails>
        <ThrowError />
      </ErrorBoundary>
    );

    expect(screen.getByText('Technical Details')).toBeInTheDocument();
  });

  it('provides try again button that resets error state', async () => {
    const TestComponent: React.FC<{ throwError: boolean }> = ({ throwError }) => {
      if (throwError) {
        throw new Error('Test error');
      }
      return <div>No error</div>;
    };

    let throwError = true;
    const { rerender } = render(
      <ErrorBoundary>
        <TestComponent throwError={throwError} />
      </ErrorBoundary>
    );

    // Error boundary should show error UI
    expect(screen.getByText('Something went wrong')).toBeInTheDocument();

    // First, update the component to not throw
    throwError = false;
    rerender(
      <ErrorBoundary>
        <TestComponent throwError={throwError} />
      </ErrorBoundary>
    );

    // The error UI should still be shown (error state persists)
    expect(screen.getByText('Something went wrong')).toBeInTheDocument();

    // Now click try again - this resets the error state and re-renders children
    fireEvent.click(screen.getByText('Try Again'));

    // Now the component should render without error
    await waitFor(() => {
      expect(screen.getByText('No error')).toBeInTheDocument();
    });
  });

  it('is accessible with proper ARIA attributes', () => {
    render(
      <ErrorBoundary>
        <ThrowError />
      </ErrorBoundary>
    );

    const alert = screen.getByRole('alert');
    expect(alert).toBeInTheDocument();
    expect(alert).toHaveAttribute('aria-live', 'assertive');
  });
});
