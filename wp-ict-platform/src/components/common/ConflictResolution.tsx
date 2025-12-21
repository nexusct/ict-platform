/**
 * Conflict Resolution Component
 *
 * UI for resolving resource scheduling conflicts with suggestions
 *
 * @package ICT_Platform
 * @since   1.0.0
 */

import React, { useState, useMemo } from 'react';
import { Icon } from './Icon';
import type { ResourceConflict } from '../../types';

interface ConflictResolutionProps {
  conflicts: ResourceConflict[];
  onResolve: (resolution: ConflictResolutionResult) => void;
  onCancel: () => void;
  suggestions?: AlternativeSlot[];
}

interface AlternativeSlot {
  resource_type: string;
  resource_id: number;
  resource_name: string;
  start: string;
  end: string;
  available_hours: number;
}

interface ConflictResolutionResult {
  action: 'override' | 'reschedule' | 'alternative' | 'cancel';
  conflictId?: number;
  newAllocation?: {
    resource_type: string;
    resource_id: number;
    start: string;
    end: string;
  };
}

export const ConflictResolution: React.FC<ConflictResolutionProps> = ({
  conflicts,
  onResolve,
  onCancel,
  suggestions = [],
}) => {
  const [selectedAction, setSelectedAction] = useState<string>('');
  const [selectedAlternative, setSelectedAlternative] = useState<AlternativeSlot | null>(null);

  const totalOverlapHours = useMemo(
    () => conflicts.reduce((sum, c) => sum + c.overlap_hours, 0),
    [conflicts]
  );

  const handleResolve = () => {
    if (selectedAction === 'override') {
      onResolve({ action: 'override' });
    } else if (selectedAction === 'alternative' && selectedAlternative) {
      onResolve({
        action: 'alternative',
        newAllocation: {
          resource_type: selectedAlternative.resource_type,
          resource_id: selectedAlternative.resource_id,
          start: selectedAlternative.start,
          end: selectedAlternative.end,
        },
      });
    } else if (selectedAction === 'cancel') {
      onResolve({ action: 'cancel' });
    }
  };

  const formatDateTime = (dateStr: string) => {
    return new Date(dateStr).toLocaleString(undefined, {
      month: 'short',
      day: 'numeric',
      hour: '2-digit',
      minute: '2-digit',
    });
  };

  return (
    <div className="ict-conflict-resolution" role="alertdialog" aria-labelledby="conflict-title">
      <div className="ict-conflict-resolution__header">
        <div className="ict-conflict-resolution__icon">
          <Icon name="alert-triangle" size={24} />
        </div>
        <div>
          <h3 id="conflict-title" className="ict-conflict-resolution__title">
            Scheduling Conflict Detected
          </h3>
          <p className="ict-conflict-resolution__subtitle">
            {conflicts.length} conflict{conflicts.length > 1 ? 's' : ''} found with{' '}
            {totalOverlapHours.toFixed(1)} hours of overlap
          </p>
        </div>
      </div>

      <div className="ict-conflict-resolution__conflicts">
        <h4 className="ict-conflict-resolution__section-title">Conflicts</h4>
        {conflicts.map((conflict, index) => (
          <div key={index} className="ict-conflict-resolution__conflict-item">
            <div className="ict-conflict-resolution__conflict-info">
              <span className="ict-conflict-resolution__conflict-type">
                {conflict.resource_type}
              </span>
              <span className="ict-conflict-resolution__conflict-details">
                {formatDateTime(conflict.existing_allocation.allocation_start)} -{' '}
                {formatDateTime(conflict.existing_allocation.allocation_end)}
              </span>
            </div>
            <span className="ict-conflict-resolution__conflict-hours">
              {conflict.overlap_hours.toFixed(1)}h overlap
            </span>
          </div>
        ))}
      </div>

      <div className="ict-conflict-resolution__options">
        <h4 className="ict-conflict-resolution__section-title">Resolution Options</h4>

        <label className="ict-conflict-resolution__option">
          <input
            type="radio"
            name="resolution"
            value="override"
            checked={selectedAction === 'override'}
            onChange={(e) => setSelectedAction(e.target.value)}
          />
          <div className="ict-conflict-resolution__option-content">
            <span className="ict-conflict-resolution__option-title">
              Override (Allow Over-allocation)
            </span>
            <span className="ict-conflict-resolution__option-desc">
              Proceed with the allocation despite the conflict. The resource will be double-booked.
            </span>
          </div>
        </label>

        {suggestions.length > 0 && (
          <div className="ict-conflict-resolution__alternatives">
            <label className="ict-conflict-resolution__option">
              <input
                type="radio"
                name="resolution"
                value="alternative"
                checked={selectedAction === 'alternative'}
                onChange={(e) => setSelectedAction(e.target.value)}
              />
              <div className="ict-conflict-resolution__option-content">
                <span className="ict-conflict-resolution__option-title">
                  Use Alternative Resource/Time
                </span>
                <span className="ict-conflict-resolution__option-desc">
                  Choose from available alternatives below
                </span>
              </div>
            </label>

            {selectedAction === 'alternative' && (
              <div className="ict-conflict-resolution__alternative-list">
                {suggestions.map((alt, index) => (
                  <label
                    key={index}
                    className={`ict-conflict-resolution__alternative-item ${selectedAlternative === alt ? 'ict-conflict-resolution__alternative-item--selected' : ''}`}
                  >
                    <input
                      type="radio"
                      name="alternative"
                      checked={selectedAlternative === alt}
                      onChange={() => setSelectedAlternative(alt)}
                    />
                    <div className="ict-conflict-resolution__alternative-content">
                      <span className="ict-conflict-resolution__alternative-name">
                        {alt.resource_name}
                      </span>
                      <span className="ict-conflict-resolution__alternative-time">
                        {formatDateTime(alt.start)} - {formatDateTime(alt.end)}
                      </span>
                      <span className="ict-conflict-resolution__alternative-hours">
                        {alt.available_hours}h available
                      </span>
                    </div>
                  </label>
                ))}
              </div>
            )}
          </div>
        )}

        <label className="ict-conflict-resolution__option">
          <input
            type="radio"
            name="resolution"
            value="cancel"
            checked={selectedAction === 'cancel'}
            onChange={(e) => setSelectedAction(e.target.value)}
          />
          <div className="ict-conflict-resolution__option-content">
            <span className="ict-conflict-resolution__option-title">Cancel Allocation</span>
            <span className="ict-conflict-resolution__option-desc">
              Do not create this allocation
            </span>
          </div>
        </label>
      </div>

      <div className="ict-conflict-resolution__actions">
        <button
          onClick={onCancel}
          className="ict-button ict-button--secondary"
          type="button"
        >
          Go Back
        </button>
        <button
          onClick={handleResolve}
          className="ict-button ict-button--primary"
          type="button"
          disabled={!selectedAction || (selectedAction === 'alternative' && !selectedAlternative)}
        >
          {selectedAction === 'override'
            ? 'Proceed Anyway'
            : selectedAction === 'alternative'
              ? 'Use Alternative'
              : selectedAction === 'cancel'
                ? 'Cancel Allocation'
                : 'Select an Option'}
        </button>
      </div>
    </div>
  );
};

// Modal wrapper for conflict resolution
interface ConflictResolutionModalProps extends ConflictResolutionProps {
  isOpen: boolean;
}

export const ConflictResolutionModal: React.FC<ConflictResolutionModalProps> = ({
  isOpen,
  ...props
}) => {
  if (!isOpen) return null;

  return (
    <div className="ict-modal ict-modal--active">
      <div className="ict-modal__overlay" onClick={props.onCancel} aria-hidden="true" />
      <div className="ict-modal__content ict-modal__content--large">
        <ConflictResolution {...props} />
      </div>
    </div>
  );
};

export default ConflictResolution;
