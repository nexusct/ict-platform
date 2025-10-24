/**
 * Timesheet List Component
 *
 * Displays time entries with filtering and actions
 *
 * @package ICT_Platform
 * @since   1.0.0
 */

import React, { useEffect, useState } from 'react';
import { useAppDispatch, useAppSelector } from '../../hooks/useAppDispatch';
import {
  fetchTimeEntries,
  deleteTimeEntry,
  syncTimeEntry,
  setFilters,
  setPage,
  clearFilters,
} from '../../store/slices/timeEntriesSlice';
import type { TimeEntryStatus } from '../../types';

export const TimesheetList: React.FC = () => {
  const dispatch = useAppDispatch();
  const { items, loading, filters, pagination } = useAppSelector((state) => state.timeEntries);
  const { items: projects } = useAppSelector((state) => state.projects);

  const [searchQuery, setSearchQuery] = useState('');
  const [statusFilter, setStatusFilter] = useState<string>('all');
  const [projectFilter, setProjectFilter] = useState<number>(0);

  useEffect(() => {
    dispatch(fetchTimeEntries());
  }, [dispatch, filters, pagination.page]);

  const handleSearch = (e: React.FormEvent) => {
    e.preventDefault();
    dispatch(setFilters({ search: searchQuery || undefined }));
  };

  const handleStatusChange = (status: string) => {
    setStatusFilter(status);
    dispatch(setFilters({ status: status === 'all' ? undefined : status }));
  };

  const handleProjectChange = (projectId: number) => {
    setProjectFilter(projectId);
    dispatch(setFilters({ project_id: projectId || undefined }));
  };

  const handleClearFilters = () => {
    setSearchQuery('');
    setStatusFilter('all');
    setProjectFilter(0);
    dispatch(clearFilters());
  };

  const handleDelete = async (id: number) => {
    if (confirm('Are you sure you want to delete this time entry?')) {
      await dispatch(deleteTimeEntry(id));
      dispatch(fetchTimeEntries());
    }
  };

  const handleSync = async (id: number) => {
    await dispatch(syncTimeEntry(id));
  };

  const formatTime = (hours: number): string => {
    const h = Math.floor(hours);
    const m = Math.round((hours - h) * 60);
    return `${h}h ${m}m`;
  };

  const formatCurrency = (amount: number): string => {
    return new Intl.NumberFormat('en-US', {
      style: 'currency',
      currency: 'USD',
    }).format(amount);
  };

  const getStatusBadgeClass = (status: TimeEntryStatus): string => {
    const baseClass = 'ict-status-badge';
    const statusMap: Record<TimeEntryStatus, string> = {
      'in-progress': `${baseClass} ${baseClass}--info`,
      'submitted': `${baseClass} ${baseClass}--warning`,
      'approved': `${baseClass} ${baseClass}--success`,
      'rejected': `${baseClass} ${baseClass}--danger`,
    };
    return statusMap[status] || baseClass;
  };

  const getSyncStatusIcon = (status: string): string => {
    const iconMap: Record<string, string> = {
      'pending': 'dashicons-clock',
      'syncing': 'dashicons-update',
      'synced': 'dashicons-yes',
      'error': 'dashicons-warning',
      'conflict': 'dashicons-warning',
    };
    return iconMap[status] || 'dashicons-marker';
  };

  if (loading && items.length === 0) {
    return (
      <div className="ict-timesheet-list ict-timesheet-list--loading">
        <div className="ict-spinner">Loading...</div>
      </div>
    );
  }

  return (
    <div className="ict-timesheet-list">
      <div className="ict-timesheet-list__header">
        <h2>Timesheet Entries</h2>
        <button
          onClick={() => dispatch(fetchTimeEntries())}
          className="ict-button ict-button--secondary"
          disabled={loading}
        >
          <span className="dashicons dashicons-update"></span>
          Refresh
        </button>
      </div>

      <div className="ict-timesheet-list__filters">
        <form onSubmit={handleSearch} className="ict-timesheet-list__search">
          <input
            type="text"
            placeholder="Search notes or task type..."
            value={searchQuery}
            onChange={(e) => setSearchQuery(e.target.value)}
            className="ict-timesheet-list__search-input"
          />
          <button type="submit" className="ict-button ict-button--primary">
            <span className="dashicons dashicons-search"></span>
            Search
          </button>
        </form>

        <div className="ict-timesheet-list__filter-row">
          <select
            value={statusFilter}
            onChange={(e) => handleStatusChange(e.target.value)}
            className="ict-timesheet-list__select"
          >
            <option value="all">All Statuses</option>
            <option value="in-progress">In Progress</option>
            <option value="submitted">Submitted</option>
            <option value="approved">Approved</option>
            <option value="rejected">Rejected</option>
          </select>

          <select
            value={projectFilter}
            onChange={(e) => handleProjectChange(parseInt(e.target.value))}
            className="ict-timesheet-list__select"
          >
            <option value={0}>All Projects</option>
            {projects.map((project) => (
              <option key={project.id} value={project.id}>
                {project.project_name}
              </option>
            ))}
          </select>

          <button
            onClick={handleClearFilters}
            className="ict-button ict-button--link"
          >
            Clear Filters
          </button>
        </div>
      </div>

      {items.length === 0 ? (
        <div className="ict-timesheet-list__empty">
          <span className="dashicons dashicons-clipboard"></span>
          <p>No time entries found</p>
        </div>
      ) : (
        <>
          <div className="ict-timesheet-list__table-wrapper">
            <table className="ict-timesheet-list__table">
              <thead>
                <tr>
                  <th>Date</th>
                  <th>Project</th>
                  <th>Task Type</th>
                  <th>Clock In</th>
                  <th>Clock Out</th>
                  <th>Hours</th>
                  <th>Billable</th>
                  <th>Status</th>
                  <th>Sync</th>
                  <th>Actions</th>
                </tr>
              </thead>
              <tbody>
                {items.map((entry) => {
                  const project = projects.find((p) => p.id === entry.project_id);
                  const cost = entry.billable_hours * (entry.hourly_rate || 0);

                  return (
                    <tr key={entry.id}>
                      <td>
                        {new Date(entry.clock_in).toLocaleDateString()}
                      </td>
                      <td>
                        <strong>{project?.project_name || `#${entry.project_id}`}</strong>
                      </td>
                      <td>{entry.task_type}</td>
                      <td>
                        {new Date(entry.clock_in).toLocaleTimeString([], {
                          hour: '2-digit',
                          minute: '2-digit',
                        })}
                      </td>
                      <td>
                        {entry.clock_out
                          ? new Date(entry.clock_out).toLocaleTimeString([], {
                              hour: '2-digit',
                              minute: '2-digit',
                            })
                          : <em className="ict-timesheet-list__active">Active</em>
                        }
                      </td>
                      <td>
                        <strong>{formatTime(entry.total_hours)}</strong>
                      </td>
                      <td>
                        {formatTime(entry.billable_hours)}
                        {entry.hourly_rate > 0 && (
                          <div className="ict-timesheet-list__cost">
                            {formatCurrency(cost)}
                          </div>
                        )}
                      </td>
                      <td>
                        <span className={getStatusBadgeClass(entry.status)}>
                          {entry.status}
                        </span>
                      </td>
                      <td>
                        <span
                          className={`ict-timesheet-list__sync-status ict-timesheet-list__sync-status--${entry.sync_status}`}
                          title={entry.sync_status}
                        >
                          <span className={`dashicons ${getSyncStatusIcon(entry.sync_status)}`}></span>
                        </span>
                      </td>
                      <td>
                        <div className="ict-timesheet-list__actions">
                          {entry.sync_status !== 'synced' && (
                            <button
                              onClick={() => handleSync(entry.id)}
                              className="ict-button ict-button--small ict-button--link"
                              title="Sync to Zoho"
                            >
                              <span className="dashicons dashicons-update"></span>
                            </button>
                          )}
                          <button
                            onClick={() => handleDelete(entry.id)}
                            className="ict-button ict-button--small ict-button--link ict-button--danger"
                            title="Delete"
                          >
                            <span className="dashicons dashicons-trash"></span>
                          </button>
                        </div>
                      </td>
                    </tr>
                  );
                })}
              </tbody>
            </table>
          </div>

          {pagination.total_pages > 1 && (
            <div className="ict-timesheet-list__pagination">
              <button
                onClick={() => dispatch(setPage(pagination.page - 1))}
                disabled={pagination.page === 1 || loading}
                className="ict-button ict-button--secondary"
              >
                Previous
              </button>
              <span className="ict-timesheet-list__page-info">
                Page {pagination.page} of {pagination.total_pages}
              </span>
              <button
                onClick={() => dispatch(setPage(pagination.page + 1))}
                disabled={pagination.page >= pagination.total_pages || loading}
                className="ict-button ict-button--secondary"
              >
                Next
              </button>
            </div>
          )}
        </>
      )}
    </div>
  );
};
