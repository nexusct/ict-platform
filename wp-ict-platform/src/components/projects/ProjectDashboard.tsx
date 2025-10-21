/**
 * Project Dashboard Component
 *
 * @package ICT_Platform
 * @since   1.0.0
 */

import React, { useEffect, useState } from 'react';
import { useAppDispatch, useAppSelector } from '../../hooks/useAppDispatch';
import { fetchProjects, setFilters, setCurrentProject } from '../../store/slices/projectsSlice';
import { ProjectList } from './ProjectList';
import { ProjectForm } from './ProjectForm';
import { ProjectStats } from './ProjectStats';
import type { Project } from '../../types';

export const ProjectDashboard: React.FC = () => {
  const dispatch = useAppDispatch();
  const { items, loading, filters, pagination } = useAppSelector((state) => state.projects);
  const [showForm, setShowForm] = useState(false);
  const [editingProject, setEditingProject] = useState<Project | undefined>();

  useEffect(() => {
    dispatch(fetchProjects());
  }, [dispatch, filters, pagination.page]);

  const handleCreateNew = () => {
    setEditingProject(undefined);
    setShowForm(true);
  };

  const handleEdit = (project: Project) => {
    setEditingProject(project);
    setShowForm(true);
  };

  const handleFormClose = () => {
    setShowForm(false);
    setEditingProject(undefined);
  };

  const handleFormSuccess = () => {
    setShowForm(false);
    setEditingProject(undefined);
    dispatch(fetchProjects());
  };

  const handleViewDetails = (project: Project) => {
    dispatch(setCurrentProject(project));
    // Navigate to project details page or open modal
  };

  return (
    <div className="ict-project-dashboard">
      <div className="dashboard-header">
        <h1>Projects</h1>
        <button onClick={handleCreateNew} className="button button-primary">
          Add New Project
        </button>
      </div>

      <ProjectStats projects={items} />

      <div className="dashboard-content">
        {showForm && (
          <ProjectForm
            project={editingProject}
            onClose={handleFormClose}
            onSuccess={handleFormSuccess}
          />
        )}

        <ProjectList
          projects={items}
          loading={loading}
          onEdit={handleEdit}
          onView={handleViewDetails}
        />
      </div>
    </div>
  );
};
