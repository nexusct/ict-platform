/**
 * Project List Component
 *
 * @package ICT_Platform
 * @since   1.0.0
 */

import React, { useState } from 'react';
import { useAppDispatch } from '../../hooks/useAppDispatch';
import { deleteProject, syncProjectToZoho } from '../../store/slices/projectsSlice';
import { showToast } from '../../store/slices/uiSlice';
import type { Project } from '../../types';

interface ProjectListProps {
  projects: Project[];
  loading: boolean;
  onEdit: (project: Project) => void;
  onView: (project: Project) => void;
}

export const ProjectList: React.FC<ProjectListProps> = ({ projects, loading, onEdit, onView }) => {
  const dispatch = useAppDispatch();
  const [searchTerm, setSearchTerm] = useState('');
  const [statusFilter, setStatusFilter] = useState<string>('all');

  const handleDelete = async (id: number) => {
    if (!confirm('Are you sure you want to delete this project?')) {
      return;
    }

    try {
      await dispatch(deleteProject(id)).unwrap();
      dispatch(
        showToast({
          type: 'success',
          message: 'Project deleted successfully',
        })
      );
    } catch (error) {
      dispatch(
        showToast({
          type: 'error',
          message: 'Failed to delete project',
        })
      );
    }
  };

  const handleSync = async (id: number) => {
    try {
      await dispatch(syncProjectToZoho(id)).unwrap();
      dispatch(
        showToast({
          type: 'success',
          message: 'Project sync initiated',
        })
      );
    } catch (error) {
      dispatch(
        showToast({
          type: 'error',
          message: 'Failed to sync project',
        })
      );
    }
  };

  const filteredProjects = projects.filter((project) => {
    const matchesSearch =
      project.project_name.toLowerCase().includes(searchTerm.toLowerCase()) ||
      project.project_number?.toLowerCase().includes(searchTerm.toLowerCase());

    const matchesStatus = statusFilter === 'all' || project.status === statusFilter;

    return matchesSearch && matchesStatus;
  });

  const getStatusBadgeClass = (status: string) => {
    const classes: Record<string, string> = {
      pending: 'badge-warning',
      'in-progress': 'badge-info',
      completed: 'badge-success',
      'on-hold': 'badge-secondary',
      cancelled: 'badge-danger',
    };
    return classes[status] || 'badge-secondary';
  };

  const getSyncStatusIcon = (status: string) => {
    const icons: Record<string, string> = {
      synced: '✓',
      syncing: '↻',
      error: '✗',
      pending: '⋯',
    };
    return icons[status] || '⋯';
  };

  if (loading) {
    return <div className="loading-spinner">Loading projects...</div>;
  }

  return (
    <div className="ict-project-list">
      <div className="list-filters">
        <input
          type="text"
          placeholder="Search projects..."
          value={searchTerm}
          onChange={(e) => setSearchTerm(e.target.value)}
          className="search-input"
        />

        <select
          value={statusFilter}
          onChange={(e) => setStatusFilter(e.target.value)}
          className="status-filter"
        >
          <option value="all">All Statuses</option>
          <option value="pending">Pending</option>
          <option value="in-progress">In Progress</option>
          <option value="on-hold">On Hold</option>
          <option value="completed">Completed</option>
          <option value="cancelled">Cancelled</option>
        </select>
      </div>

      <div className="projects-table">
        <table className="widefat">
          <thead>
            <tr>
              <th>Project</th>
              <th>Status</th>
              <th>Priority</th>
              <th>Budget</th>
              <th>Progress</th>
              <th>Sync</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            {filteredProjects.length === 0 ? (
              <tr>
                <td colSpan={7} className="text-center">
                  No projects found
                </td>
              </tr>
            ) : (
              filteredProjects.map((project) => (
                <tr key={project.id}>
                  <td>
                    <div className="project-info">
                      <strong>{project.project_name}</strong>
                      {project.project_number && (
                        <small className="project-number">#{project.project_number}</small>
                      )}
                    </div>
                  </td>
                  <td>
                    <span className={`badge ${getStatusBadgeClass(project.status)}`}>
                      {project.status}
                    </span>
                  </td>
                  <td>
                    <span className={`priority-${project.priority}`}>{project.priority}</span>
                  </td>
                  <td>
                    <div className="budget-info">
                      <div>${project.budget_amount.toLocaleString()}</div>
                      <small className="budget-spent">
                        ${project.actual_cost.toLocaleString()} spent
                      </small>
                    </div>
                  </td>
                  <td>
                    <div className="progress-bar">
                      <div
                        className="progress-fill"
                        style={{ width: `${project.progress_percentage}%` }}
                      />
                      <span className="progress-text">{project.progress_percentage}%</span>
                    </div>
                  </td>
                  <td>
                    <span
                      className={`sync-status sync-${project.sync_status}`}
                      title={project.sync_status}
                    >
                      {getSyncStatusIcon(project.sync_status)}
                    </span>
                  </td>
                  <td className="actions">
                    <button
                      onClick={() => onView(project)}
                      className="button button-small"
                      title="View Details"
                    >
                      View
                    </button>
                    <button
                      onClick={() => onEdit(project)}
                      className="button button-small"
                      title="Edit"
                    >
                      Edit
                    </button>
                    <button
                      onClick={() => handleSync(project.id)}
                      className="button button-small"
                      title="Sync to Zoho"
                      disabled={project.sync_status === 'syncing'}
                    >
                      Sync
                    </button>
                    <button
                      onClick={() => handleDelete(project.id)}
                      className="button button-small button-link-delete"
                      title="Delete"
                    >
                      Delete
                    </button>
                  </td>
                </tr>
              ))
            )}
          </tbody>
        </table>
      </div>
    </div>
  );
};
