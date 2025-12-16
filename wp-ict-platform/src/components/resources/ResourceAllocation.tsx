/**
 * ResourceAllocation Component
 *
 * Form for creating and editing resource allocations
 * Features:
 * - Create/edit/delete allocations
 * - Real-time conflict detection
 * - Project and resource selection
 * - Date/time pickers
 * - Allocation percentage and estimated hours
 * - Validation with error messages
 *
 * @package ICT_Platform
 * @since   1.0.0
 */

import React, { useState, useEffect } from 'react';
import { useAppDispatch, useAppSelector } from '../../store/hooks';
import {
  createResourceAllocation,
  updateResourceAllocation,
  deleteResourceAllocation,
  checkResourceConflicts,
  clearConflicts,
  selectResourceConflicts,
  selectResourcesLoading,
  selectResourcesError,
  selectHasConflicts,
} from '../../store/slices/resourcesSlice';
import {
  ProjectResource,
  ResourceFormData,
  ResourceType,
} from '../../types';

interface ResourceAllocationProps {
  allocation?: ProjectResource;
  projectId?: number;
  onSuccess?: () => void;
  onCancel?: () => void;
}

const ResourceAllocation: React.FC<ResourceAllocationProps> = ({
  allocation,
  projectId,
  onSuccess,
  onCancel,
}) => {
  const dispatch = useAppDispatch();

  const conflicts = useAppSelector(selectResourceConflicts);
  const hasConflicts = useAppSelector(selectHasConflicts);
  const loading = useAppSelector(selectResourcesLoading);
  const error = useAppSelector(selectResourcesError);

  const [formData, setFormData] = useState<ResourceFormData>({
    project_id: allocation?.project_id || projectId || 0,
    resource_type: allocation?.resource_type || 'technician',
    resource_id: allocation?.resource_id || 0,
    allocation_start: allocation?.allocation_start || '',
    allocation_end: allocation?.allocation_end || '',
    allocation_percentage: allocation?.allocation_percentage || 100,
    estimated_hours: allocation?.estimated_hours || 0,
    notes: allocation?.notes || '',
  });

  const [validationErrors, setValidationErrors] = useState<Record<string, string>>({});
  const [checkingConflicts, setCheckingConflicts] = useState(false);

  // Clear conflicts when component unmounts
  useEffect(() => {
    return () => {
      dispatch(clearConflicts());
    };
  }, [dispatch]);

  // Handle form field changes
  const handleChange = (field: keyof ResourceFormData, value: any) => {
    setFormData((prev) => ({
      ...prev,
      [field]: value,
    }));

    // Clear validation error for this field
    if (validationErrors[field]) {
      setValidationErrors((prev) => {
        const newErrors = { ...prev };
        delete newErrors[field];
        return newErrors;
      });
    }
  };

  // Validate form data
  const validate = (): boolean => {
    const errors: Record<string, string> = {};

    if (!formData.project_id) {
      errors.project_id = 'Project is required';
    }

    if (!formData.resource_id) {
      errors.resource_id = 'Resource is required';
    }

    if (!formData.allocation_start) {
      errors.allocation_start = 'Start date is required';
    }

    if (!formData.allocation_end) {
      errors.allocation_end = 'End date is required';
    }

    if (formData.allocation_start && formData.allocation_end) {
      const start = new Date(formData.allocation_start);
      const end = new Date(formData.allocation_end);

      if (end <= start) {
        errors.allocation_end = 'End date must be after start date';
      }
    }

    if (formData.allocation_percentage && (formData.allocation_percentage < 1 || formData.allocation_percentage > 100)) {
      errors.allocation_percentage = 'Allocation percentage must be between 1 and 100';
    }

    if (formData.estimated_hours && formData.estimated_hours < 0) {
      errors.estimated_hours = 'Estimated hours cannot be negative';
    }

    setValidationErrors(errors);
    return Object.keys(errors).length === 0;
  };

  // Check for conflicts
  const handleCheckConflicts = async () => {
    if (!formData.resource_type || !formData.resource_id || !formData.allocation_start || !formData.allocation_end) {
      return;
    }

    setCheckingConflicts(true);

    try {
      await dispatch(
        checkResourceConflicts({
          resource_type: formData.resource_type,
          resource_id: formData.resource_id,
          allocation_start: formData.allocation_start,
          allocation_end: formData.allocation_end,
          exclude_id: allocation?.id,
        })
      ).unwrap();
    } catch (err) {
      console.error('Failed to check conflicts:', err);
    } finally {
      setCheckingConflicts(false);
    }
  };

  // Handle form submission
  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();

    if (!validate()) {
      return;
    }

    // Check for conflicts before submitting
    await handleCheckConflicts();

    if (hasConflicts) {
      return;
    }

    try {
      if (allocation) {
        // Update existing allocation
        await dispatch(
          updateResourceAllocation({
            id: allocation.id,
            data: formData,
          })
        ).unwrap();
      } else {
        // Create new allocation
        await dispatch(createResourceAllocation(formData)).unwrap();
      }

      if (onSuccess) {
        onSuccess();
      }
    } catch (err) {
      console.error('Failed to save allocation:', err);
    }
  };

  // Handle delete
  const handleDelete = async () => {
    if (!allocation) return;

    if (!confirm('Are you sure you want to delete this resource allocation?')) {
      return;
    }

    try {
      await dispatch(deleteResourceAllocation(allocation.id)).unwrap();

      if (onSuccess) {
        onSuccess();
      }
    } catch (err) {
      console.error('Failed to delete allocation:', err);
    }
  };

  return (
    <div className="ict-resource-allocation">
      <div className="ict-resource-allocation__header">
        <h2 className="ict-resource-allocation__title">
          {allocation ? 'Edit Resource Allocation' : 'Create Resource Allocation'}
        </h2>
      </div>

      {/* Error message */}
      {error && (
        <div className="ict-notice ict-notice--error">
          <p>Error: {error}</p>
        </div>
      )}

      {/* Conflict warnings */}
      {hasConflicts && (
        <div className="ict-notice ict-notice--warning">
          <h4>Resource Conflicts Detected</h4>
          <p>The following conflicts exist for this allocation:</p>
          <ul>
            {conflicts.map((conflict, index) => (
              <li key={index}>
                <strong>
                  {conflict.resource_type} #{conflict.resource_id}
                </strong>
                {' '}has an existing allocation that overlaps by{' '}
                <strong>{conflict.overlap_hours.toFixed(2)} hours</strong>
                <br />
                <small>
                  Existing: {new Date(conflict.existing_allocation.allocation_start).toLocaleString()} to{' '}
                  {new Date(conflict.existing_allocation.allocation_end).toLocaleString()}
                </small>
              </li>
            ))}
          </ul>
          <button
            className="ict-button ict-button--small"
            onClick={() => dispatch(clearConflicts())}
          >
            Dismiss
          </button>
        </div>
      )}

      <form onSubmit={handleSubmit} className="ict-resource-allocation__form">
        {/* Project selection */}
        <div className="ict-form-group">
          <label htmlFor="project_id" className="ict-form-label">
            Project <span className="ict-form-label--required">*</span>
          </label>
          <select
            id="project_id"
            value={formData.project_id}
            onChange={(e) => handleChange('project_id', Number(e.target.value))}
            className={`ict-select ${validationErrors.project_id ? 'ict-select--error' : ''}`}
            disabled={!!projectId}
          >
            <option value="">Select Project</option>
            {/* Project options would be loaded from store */}
          </select>
          {validationErrors.project_id && (
            <span className="ict-form-error">{validationErrors.project_id}</span>
          )}
        </div>

        {/* Resource type */}
        <div className="ict-form-group">
          <label htmlFor="resource_type" className="ict-form-label">
            Resource Type <span className="ict-form-label--required">*</span>
          </label>
          <select
            id="resource_type"
            value={formData.resource_type}
            onChange={(e) => {
              handleChange('resource_type', e.target.value as ResourceType);
              handleChange('resource_id', 0); // Reset resource_id when type changes
            }}
            className="ict-select"
          >
            <option value="technician">Technician</option>
            <option value="equipment">Equipment</option>
            <option value="vehicle">Vehicle</option>
          </select>
        </div>

        {/* Resource ID */}
        <div className="ict-form-group">
          <label htmlFor="resource_id" className="ict-form-label">
            {formData.resource_type === 'technician' ? 'Technician' : formData.resource_type === 'equipment' ? 'Equipment' : 'Vehicle'}{' '}
            <span className="ict-form-label--required">*</span>
          </label>
          <select
            id="resource_id"
            value={formData.resource_id}
            onChange={(e) => handleChange('resource_id', Number(e.target.value))}
            className={`ict-select ${validationErrors.resource_id ? 'ict-select--error' : ''}`}
          >
            <option value="">Select {formData.resource_type}</option>
            {/* Resource options would be loaded based on type */}
          </select>
          {validationErrors.resource_id && (
            <span className="ict-form-error">{validationErrors.resource_id}</span>
          )}
        </div>

        {/* Allocation dates */}
        <div className="ict-form-row">
          <div className="ict-form-group">
            <label htmlFor="allocation_start" className="ict-form-label">
              Start Date & Time <span className="ict-form-label--required">*</span>
            </label>
            <input
              type="datetime-local"
              id="allocation_start"
              value={formData.allocation_start}
              onChange={(e) => handleChange('allocation_start', e.target.value)}
              className={`ict-input ${validationErrors.allocation_start ? 'ict-input--error' : ''}`}
            />
            {validationErrors.allocation_start && (
              <span className="ict-form-error">{validationErrors.allocation_start}</span>
            )}
          </div>

          <div className="ict-form-group">
            <label htmlFor="allocation_end" className="ict-form-label">
              End Date & Time <span className="ict-form-label--required">*</span>
            </label>
            <input
              type="datetime-local"
              id="allocation_end"
              value={formData.allocation_end}
              onChange={(e) => handleChange('allocation_end', e.target.value)}
              className={`ict-input ${validationErrors.allocation_end ? 'ict-input--error' : ''}`}
            />
            {validationErrors.allocation_end && (
              <span className="ict-form-error">{validationErrors.allocation_end}</span>
            )}
          </div>
        </div>

        {/* Allocation percentage and estimated hours */}
        <div className="ict-form-row">
          <div className="ict-form-group">
            <label htmlFor="allocation_percentage" className="ict-form-label">
              Allocation Percentage
            </label>
            <input
              type="number"
              id="allocation_percentage"
              value={formData.allocation_percentage}
              onChange={(e) => handleChange('allocation_percentage', Number(e.target.value))}
              min="1"
              max="100"
              className={`ict-input ${validationErrors.allocation_percentage ? 'ict-input--error' : ''}`}
            />
            {validationErrors.allocation_percentage && (
              <span className="ict-form-error">{validationErrors.allocation_percentage}</span>
            )}
            <small className="ict-form-help">Percentage of time allocated (1-100%)</small>
          </div>

          <div className="ict-form-group">
            <label htmlFor="estimated_hours" className="ict-form-label">
              Estimated Hours
            </label>
            <input
              type="number"
              id="estimated_hours"
              value={formData.estimated_hours}
              onChange={(e) => handleChange('estimated_hours', Number(e.target.value))}
              min="0"
              step="0.5"
              className={`ict-input ${validationErrors.estimated_hours ? 'ict-input--error' : ''}`}
            />
            {validationErrors.estimated_hours && (
              <span className="ict-form-error">{validationErrors.estimated_hours}</span>
            )}
          </div>
        </div>

        {/* Notes */}
        <div className="ict-form-group">
          <label htmlFor="notes" className="ict-form-label">
            Notes
          </label>
          <textarea
            id="notes"
            value={formData.notes}
            onChange={(e) => handleChange('notes', e.target.value)}
            rows={3}
            className="ict-textarea"
            placeholder="Add any additional notes about this allocation..."
          />
        </div>

        {/* Check conflicts button */}
        <div className="ict-resource-allocation__check-conflicts">
          <button
            type="button"
            className="ict-button ict-button--secondary"
            onClick={handleCheckConflicts}
            disabled={checkingConflicts || !formData.resource_id || !formData.allocation_start || !formData.allocation_end}
          >
            {checkingConflicts ? (
              <>
                <span className="ict-spinner ict-spinner--small"></span> Checking...
              </>
            ) : (
              <>
                <span className="dashicons dashicons-warning"></span> Check for Conflicts
              </>
            )}
          </button>
        </div>

        {/* Form actions */}
        <div className="ict-resource-allocation__actions">
          <button
            type="submit"
            className="ict-button ict-button--primary"
            disabled={loading || hasConflicts}
          >
            {loading ? (
              <>
                <span className="ict-spinner ict-spinner--small"></span> Saving...
              </>
            ) : allocation ? (
              'Update Allocation'
            ) : (
              'Create Allocation'
            )}
          </button>

          {allocation && (
            <button
              type="button"
              className="ict-button ict-button--danger"
              onClick={handleDelete}
              disabled={loading}
            >
              Delete
            </button>
          )}

          {onCancel && (
            <button
              type="button"
              className="ict-button ict-button--secondary"
              onClick={onCancel}
              disabled={loading}
            >
              Cancel
            </button>
          )}
        </div>
      </form>
    </div>
  );
};

export default ResourceAllocation;
