/**
 * Project Form Component
 *
 * @package ICT_Platform
 * @since   1.0.0
 */

import React, { useState, useEffect } from 'react';
import { useAppDispatch } from '../../hooks/useAppDispatch';
import { createProject, updateProject } from '../../store/slices/projectsSlice';
import { showToast } from '../../store/slices/uiSlice';
import type { Project, ProjectFormData } from '../../types';

interface ProjectFormProps {
  project?: Project;
  onClose: () => void;
  onSuccess: () => void;
}

export const ProjectForm: React.FC<ProjectFormProps> = ({ project, onClose, onSuccess }) => {
  const dispatch = useAppDispatch();
  const [loading, setLoading] = useState(false);
  const [formData, setFormData] = useState<ProjectFormData>({
    project_name: '',
    status: 'pending',
    priority: 'medium',
    estimated_hours: 0,
    budget_amount: 0,
  });

  useEffect(() => {
    if (project) {
      setFormData({
        project_name: project.project_name,
        client_id: project.client_id,
        site_address: project.site_address,
        status: project.status,
        priority: project.priority,
        start_date: project.start_date,
        end_date: project.end_date,
        estimated_hours: project.estimated_hours,
        budget_amount: project.budget_amount,
        project_manager_id: project.project_manager_id,
        notes: project.notes,
      });
    }
  }, [project]);

  const handleChange = (
    e: React.ChangeEvent<HTMLInputElement | HTMLTextAreaElement | HTMLSelectElement>
  ) => {
    const { name, value } = e.target;
    setFormData((prev) => ({
      ...prev,
      [name]: value,
    }));
  };

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setLoading(true);

    try {
      if (project) {
        await dispatch(updateProject({ id: project.id, data: formData })).unwrap();
        dispatch(
          showToast({
            type: 'success',
            message: 'Project updated successfully',
          })
        );
      } else {
        await dispatch(createProject(formData)).unwrap();
        dispatch(
          showToast({
            type: 'success',
            message: 'Project created successfully',
          })
        );
      }
      onSuccess();
    } catch (error) {
      dispatch(
        showToast({
          type: 'error',
          message: `Failed to ${project ? 'update' : 'create'} project`,
        })
      );
    } finally {
      setLoading(false);
    }
  };

  return (
    <div className="ict-project-form-modal">
      <div className="modal-overlay" onClick={onClose} />
      <div className="modal-content">
        <div className="modal-header">
          <h2>{project ? 'Edit Project' : 'New Project'}</h2>
          <button onClick={onClose} className="modal-close">
            ×
          </button>
        </div>

        <form onSubmit={handleSubmit} className="project-form">
          <div className="form-grid">
            <div className="form-group">
              <label htmlFor="project_name">
                Project Name <span className="required">*</span>
              </label>
              <input
                type="text"
                id="project_name"
                name="project_name"
                value={formData.project_name}
                onChange={handleChange}
                required
                className="regular-text"
              />
            </div>

            <div className="form-group">
              <label htmlFor="site_address">Site Address</label>
              <input
                type="text"
                id="site_address"
                name="site_address"
                value={formData.site_address || ''}
                onChange={handleChange}
                className="regular-text"
              />
            </div>

            <div className="form-group">
              <label htmlFor="status">Status</label>
              <select
                id="status"
                name="status"
                value={formData.status}
                onChange={handleChange}
                className="regular-text"
              >
                <option value="pending">Pending</option>
                <option value="in-progress">In Progress</option>
                <option value="on-hold">On Hold</option>
                <option value="completed">Completed</option>
                <option value="cancelled">Cancelled</option>
              </select>
            </div>

            <div className="form-group">
              <label htmlFor="priority">Priority</label>
              <select
                id="priority"
                name="priority"
                value={formData.priority}
                onChange={handleChange}
                className="regular-text"
              >
                <option value="low">Low</option>
                <option value="medium">Medium</option>
                <option value="high">High</option>
                <option value="urgent">Urgent</option>
              </select>
            </div>

            <div className="form-group">
              <label htmlFor="start_date">Start Date</label>
              <input
                type="date"
                id="start_date"
                name="start_date"
                value={formData.start_date || ''}
                onChange={handleChange}
                className="regular-text"
              />
            </div>

            <div className="form-group">
              <label htmlFor="end_date">End Date</label>
              <input
                type="date"
                id="end_date"
                name="end_date"
                value={formData.end_date || ''}
                onChange={handleChange}
                className="regular-text"
              />
            </div>

            <div className="form-group">
              <label htmlFor="estimated_hours">Estimated Hours</label>
              <input
                type="number"
                id="estimated_hours"
                name="estimated_hours"
                value={formData.estimated_hours || ''}
                onChange={handleChange}
                min="0"
                step="0.5"
                className="regular-text"
              />
            </div>

            <div className="form-group">
              <label htmlFor="budget_amount">Budget Amount ($)</label>
              <input
                type="number"
                id="budget_amount"
                name="budget_amount"
                value={formData.budget_amount || ''}
                onChange={handleChange}
                min="0"
                step="0.01"
                className="regular-text"
              />
            </div>

            <div className="form-group full-width">
              <label htmlFor="notes">Notes</label>
              <textarea
                id="notes"
                name="notes"
                value={formData.notes || ''}
                onChange={handleChange}
                rows={4}
                className="large-text"
              />
            </div>
          </div>

          <div className="form-actions">
            <button type="button" onClick={onClose} className="button" disabled={loading}>
              Cancel
            </button>
            <button type="submit" className="button button-primary" disabled={loading}>
              {loading ? 'Saving...' : project ? 'Update Project' : 'Create Project'}
            </button>
          </div>
        </form>
      </div>
    </div>
  );
};
