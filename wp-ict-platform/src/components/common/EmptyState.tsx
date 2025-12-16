/**
 * Empty State Component
 *
 * Provides helpful guidance when there's no data to display
 *
 * @package ICT_Platform
 * @since   1.0.0
 */

import React from 'react';
import { Icon, IconName } from './Icon';

interface EmptyStateProps {
  icon?: IconName;
  title: string;
  description?: string;
  action?: {
    label: string;
    onClick: () => void;
    variant?: 'primary' | 'secondary';
  };
  secondaryAction?: {
    label: string;
    onClick: () => void;
  };
  variant?: 'default' | 'compact' | 'large';
  className?: string;
}

export const EmptyState: React.FC<EmptyStateProps> = ({
  icon = 'folder',
  title,
  description,
  action,
  secondaryAction,
  variant = 'default',
  className = '',
}) => {
  return (
    <div
      className={`ict-empty-state ict-empty-state--${variant} ${className}`}
      role="status"
      aria-live="polite"
    >
      <div className="ict-empty-state__icon" aria-hidden="true">
        <Icon name={icon} size={variant === 'compact' ? 40 : 64} />
      </div>
      <h3 className="ict-empty-state__title">{title}</h3>
      {description && <p className="ict-empty-state__description">{description}</p>}
      {(action || secondaryAction) && (
        <div className="ict-empty-state__actions">
          {action && (
            <button
              onClick={action.onClick}
              className={`ict-button ict-button--${action.variant || 'primary'}`}
              type="button"
            >
              {action.label}
            </button>
          )}
          {secondaryAction && (
            <button
              onClick={secondaryAction.onClick}
              className="ict-button ict-button--link"
              type="button"
            >
              {secondaryAction.label}
            </button>
          )}
        </div>
      )}
    </div>
  );
};

// Pre-built empty states for common scenarios
export const NoProjectsEmpty: React.FC<{ onCreateClick?: () => void }> = ({ onCreateClick }) => (
  <EmptyState
    icon="folder"
    title="No projects found"
    description="Get started by creating your first project or adjusting your filters."
    action={
      onCreateClick
        ? {
            label: 'Create Project',
            onClick: onCreateClick,
          }
        : undefined
    }
  />
);

export const NoTimeEntriesEmpty: React.FC<{ onClockInClick?: () => void }> = ({
  onClockInClick,
}) => (
  <EmptyState
    icon="clock"
    title="No time entries"
    description="Start tracking your time by clocking in to a project."
    action={
      onClockInClick
        ? {
            label: 'Clock In',
            onClick: onClockInClick,
          }
        : undefined
    }
  />
);

export const NoInventoryEmpty: React.FC<{ onAddClick?: () => void }> = ({ onAddClick }) => (
  <EmptyState
    icon="package"
    title="No inventory items"
    description="Add inventory items to track stock levels and manage orders."
    action={
      onAddClick
        ? {
            label: 'Add Item',
            onClick: onAddClick,
          }
        : undefined
    }
  />
);

export const NoResultsEmpty: React.FC<{ searchTerm?: string; onClearClick?: () => void }> = ({
  searchTerm,
  onClearClick,
}) => (
  <EmptyState
    icon="search"
    title="No results found"
    description={
      searchTerm
        ? `No items match "${searchTerm}". Try adjusting your search or filters.`
        : 'No items match your current filters. Try adjusting your search criteria.'
    }
    action={
      onClearClick
        ? {
            label: 'Clear Filters',
            onClick: onClearClick,
            variant: 'secondary',
          }
        : undefined
    }
  />
);

export const ErrorEmpty: React.FC<{ onRetryClick?: () => void; message?: string }> = ({
  onRetryClick,
  message,
}) => (
  <EmptyState
    icon="alert-circle"
    title="Something went wrong"
    description={message || 'We encountered an error loading this data. Please try again.'}
    action={
      onRetryClick
        ? {
            label: 'Try Again',
            onClick: onRetryClick,
          }
        : undefined
    }
  />
);

export const OfflineEmpty: React.FC<{ onRetryClick?: () => void }> = ({ onRetryClick }) => (
  <EmptyState
    icon="wifi-off"
    title="You're offline"
    description="Please check your internet connection and try again."
    action={
      onRetryClick
        ? {
            label: 'Retry',
            onClick: onRetryClick,
          }
        : undefined
    }
  />
);

export const NoPendingApprovalsEmpty: React.FC = () => (
  <EmptyState
    icon="check-circle"
    title="All caught up!"
    description="There are no time entries pending approval."
    variant="compact"
  />
);

export const NoAlertsEmpty: React.FC = () => (
  <EmptyState
    icon="check-circle"
    title="No alerts"
    description="All inventory levels are within normal ranges."
    variant="compact"
  />
);

export default EmptyState;
