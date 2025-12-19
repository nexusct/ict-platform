/**
 * Progress Indicator Components
 *
 * Visual feedback for multi-step operations and loading states
 *
 * @package ICT_Platform
 * @since   1.0.0
 */

import React from 'react';
import { Icon } from './Icon';

interface ProgressBarProps {
  value: number;
  max?: number;
  label?: string;
  showPercentage?: boolean;
  variant?: 'default' | 'success' | 'warning' | 'danger';
  size?: 'small' | 'medium' | 'large';
  animated?: boolean;
  className?: string;
}

export const ProgressBar: React.FC<ProgressBarProps> = ({
  value,
  max = 100,
  label,
  showPercentage = true,
  variant = 'default',
  size = 'medium',
  animated = false,
  className = '',
}) => {
  const percentage = Math.min(100, Math.max(0, (value / max) * 100));

  return (
    <div
      className={`ict-progress-bar ict-progress-bar--${size} ict-progress-bar--${variant} ${className}`}
      role="progressbar"
      aria-valuenow={value}
      aria-valuemin={0}
      aria-valuemax={max}
      aria-label={label || `Progress: ${percentage.toFixed(0)}%`}
    >
      {label && <div className="ict-progress-bar__label">{label}</div>}
      <div className="ict-progress-bar__track">
        <div
          className={`ict-progress-bar__fill ${animated ? 'ict-progress-bar__fill--animated' : ''}`}
          style={{ width: `${percentage}%` }}
        />
        {showPercentage && (
          <span className="ict-progress-bar__percentage">{percentage.toFixed(0)}%</span>
        )}
      </div>
    </div>
  );
};

interface StepIndicatorProps {
  steps: Array<{
    label: string;
    description?: string;
  }>;
  currentStep: number;
  variant?: 'horizontal' | 'vertical';
  className?: string;
}

export const StepIndicator: React.FC<StepIndicatorProps> = ({
  steps,
  currentStep,
  variant = 'horizontal',
  className = '',
}) => {
  return (
    <nav
      className={`ict-step-indicator ict-step-indicator--${variant} ${className}`}
      aria-label="Progress steps"
    >
      <ol className="ict-step-indicator__list">
        {steps.map((step, index) => {
          const status =
            index < currentStep ? 'completed' : index === currentStep ? 'current' : 'upcoming';

          return (
            <li
              key={index}
              className={`ict-step-indicator__item ict-step-indicator__item--${status}`}
              aria-current={status === 'current' ? 'step' : undefined}
            >
              <div className="ict-step-indicator__marker">
                {status === 'completed' ? (
                  <Icon name="check" size={14} aria-hidden={true} />
                ) : (
                  <span aria-hidden={true}>{index + 1}</span>
                )}
              </div>
              <div className="ict-step-indicator__content">
                <span className="ict-step-indicator__label">{step.label}</span>
                {step.description && (
                  <span className="ict-step-indicator__description">{step.description}</span>
                )}
              </div>
              {index < steps.length - 1 && (
                <div className="ict-step-indicator__connector" aria-hidden={true} />
              )}
            </li>
          );
        })}
      </ol>
      <span className="screen-reader-text">
        Step {currentStep + 1} of {steps.length}: {steps[currentStep]?.label}
      </span>
    </nav>
  );
};

interface LoadingOverlayProps {
  isLoading: boolean;
  message?: string;
  children: React.ReactNode;
  blur?: boolean;
}

export const LoadingOverlay: React.FC<LoadingOverlayProps> = ({
  isLoading,
  message = 'Loading...',
  children,
  blur = true,
}) => {
  return (
    <div className="ict-loading-overlay-container">
      {children}
      {isLoading && (
        <div
          className={`ict-loading-overlay ${blur ? 'ict-loading-overlay--blur' : ''}`}
          role="status"
          aria-live="polite"
        >
          <div className="ict-loading-overlay__content">
            <div className="ict-loading-overlay__spinner" aria-hidden={true}>
              <Icon name="loader" size={32} />
            </div>
            <span className="ict-loading-overlay__message">{message}</span>
          </div>
        </div>
      )}
    </div>
  );
};

interface SpinnerProps {
  size?: 'small' | 'medium' | 'large';
  color?: string;
  label?: string;
  inline?: boolean;
}

export const Spinner: React.FC<SpinnerProps> = ({
  size = 'medium',
  color,
  label = 'Loading',
  inline = false,
}) => {
  const sizeMap = {
    small: 16,
    medium: 24,
    large: 40,
  };

  const spinner = (
    <span
      className={`ict-spinner ict-spinner--${size} ${inline ? 'ict-spinner--inline' : ''}`}
      role="status"
      aria-label={label}
    >
      <svg
        width={sizeMap[size]}
        height={sizeMap[size]}
        viewBox="0 0 24 24"
        fill="none"
        stroke={color || 'currentColor'}
        strokeWidth="2"
        className="ict-spinner__icon"
        aria-hidden={true}
      >
        <path d="M12 2v4m0 12v4m10-10h-4M6 12H2m15.5-6.5l-2.83 2.83M9.33 14.67l-2.83 2.83m11-2.83l-2.83-2.83M9.33 9.33L6.5 6.5" />
      </svg>
      <span className="screen-reader-text">{label}</span>
    </span>
  );

  if (inline) {
    return spinner;
  }

  return <div className="ict-spinner-container">{spinner}</div>;
};

interface OperationProgressProps {
  operation: string;
  progress?: number;
  status: 'pending' | 'in-progress' | 'completed' | 'error';
  error?: string;
}

export const OperationProgress: React.FC<OperationProgressProps> = ({
  operation,
  progress,
  status,
  error,
}) => {
  const getStatusIcon = () => {
    switch (status) {
      case 'completed':
        return <Icon name="check-circle" size={20} />;
      case 'error':
        return <Icon name="alert-circle" size={20} />;
      case 'in-progress':
        return <Spinner size="small" inline />;
      default:
        return <Icon name="clock" size={20} />;
    }
  };

  return (
    <div className={`ict-operation-progress ict-operation-progress--${status}`}>
      <div className="ict-operation-progress__header">
        <span className="ict-operation-progress__icon">{getStatusIcon()}</span>
        <span className="ict-operation-progress__operation">{operation}</span>
      </div>
      {status === 'in-progress' && progress !== undefined && (
        <ProgressBar value={progress} size="small" animated />
      )}
      {status === 'error' && error && (
        <p className="ict-operation-progress__error">{error}</p>
      )}
    </div>
  );
};

export default ProgressBar;
