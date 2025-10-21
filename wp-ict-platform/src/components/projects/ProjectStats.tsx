/**
 * Project Statistics Component
 *
 * @package ICT_Platform
 * @since   1.0.0
 */

import React from 'react';
import type { Project } from '../../types';

interface ProjectStatsProps {
  projects: Project[];
}

export const ProjectStats: React.FC<ProjectStatsProps> = ({ projects }) => {
  const stats = {
    total: projects.length,
    active: projects.filter((p) => p.status === 'in-progress').length,
    completed: projects.filter((p) => p.status === 'completed').length,
    pending: projects.filter((p) => p.status === 'pending').length,
    totalBudget: projects.reduce((sum, p) => sum + p.budget_amount, 0),
    totalSpent: projects.reduce((sum, p) => sum + p.actual_cost, 0),
  };

  const budgetUtilization = stats.totalBudget > 0
    ? ((stats.totalSpent / stats.totalBudget) * 100).toFixed(1)
    : '0';

  return (
    <div className="project-stats-grid">
      <div className="stat-card">
        <div className="stat-icon">📊</div>
        <div className="stat-content">
          <div className="stat-value">{stats.total}</div>
          <div className="stat-label">Total Projects</div>
        </div>
      </div>

      <div className="stat-card stat-active">
        <div className="stat-icon">🚀</div>
        <div className="stat-content">
          <div className="stat-value">{stats.active}</div>
          <div className="stat-label">Active Projects</div>
        </div>
      </div>

      <div className="stat-card stat-completed">
        <div className="stat-icon">✓</div>
        <div className="stat-content">
          <div className="stat-value">{stats.completed}</div>
          <div className="stat-label">Completed</div>
        </div>
      </div>

      <div className="stat-card stat-pending">
        <div className="stat-icon">⏳</div>
        <div className="stat-content">
          <div className="stat-value">{stats.pending}</div>
          <div className="stat-label">Pending</div>
        </div>
      </div>

      <div className="stat-card stat-budget">
        <div className="stat-icon">💰</div>
        <div className="stat-content">
          <div className="stat-value">${stats.totalBudget.toLocaleString()}</div>
          <div className="stat-label">Total Budget</div>
        </div>
      </div>

      <div className="stat-card stat-spent">
        <div className="stat-icon">💸</div>
        <div className="stat-content">
          <div className="stat-value">${stats.totalSpent.toLocaleString()}</div>
          <div className="stat-label">Total Spent ({budgetUtilization}%)</div>
        </div>
      </div>
    </div>
  );
};
