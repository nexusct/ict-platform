/**
 * Timesheet Approval Component
 *
 * Interface for managers to approve/reject time entries
 *
 * @package ICT_Platform
 * @since   1.0.0
 */

import React, { useEffect, useState } from 'react';
import { useAppDispatch, useAppSelector } from '../../hooks/useAppDispatch';
import {
  fetchTimeEntries,
  approveTimeEntry,
  rejectTimeEntry,
  setFilters,
} from '../../store/slices/timeEntriesSlice';
import type { TimeEntry } from '../../types';

export const TimesheetApproval: React.FC = () => {
  const dispatch = useAppDispatch();
  const { items, loading } = useAppSelector((state) => state.timeEntries);
  const { items: projects } = useAppSelector((state) => state.projects);

  const [selectedEntry, setSelectedEntry] = useState<TimeEntry | null>(null);
  const [approvalNotes, setApprovalNotes] = useState('');
  const [rejectReason, setRejectReason] = useState('');
  const [showRejectModal, setShowRejectModal] = useState(false);
  const [dateFilter, setDateFilter] = useState('week');

  useEffect(() => {
    // Filter for submitted entries only
    dispatch(setFilters({ status: 'submitted' }));
    dispatch(fetchTimeEntries());
  }, [dispatch]);

  const handleApprove = async (entry: TimeEntry) => {
    setSelectedEntry(entry);

    // Quick approve if no notes
    if (!approvalNotes) {
      await dispatch(approveTimeEntry({ id: entry.id }));
      dispatch(fetchTimeEntries());
      return;
    }

    await dispatch(approveTimeEntry({ id: entry.id, notes: approvalNotes }));
    setApprovalNotes('');
    setSelectedEntry(null);
    dispatch(fetchTimeEntries());
  };

  const handleReject = async () => {
    if (!selectedEntry || !rejectReason.trim()) {
      alert('Please provide a reason for rejection');
      return;
    }

    await dispatch(rejectTimeEntry({ id: selectedEntry.id, reason: rejectReason }));
    setRejectReason('');
    setSelectedEntry(null);
    setShowRejectModal(false);
    dispatch(fetchTimeEntries());
  };

  const openRejectModal = (entry: TimeEntry) => {
    setSelectedEntry(entry);
    setShowRejectModal(true);
  };

  const closeRejectModal = () => {
    setSelectedEntry(null);
    setRejectReason('');
    setShowRejectModal(false);
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

  const groupByTechnician = () => {
    const grouped: Record<number, TimeEntry[]> = {};
    items.forEach((entry) => {
      if (!grouped[entry.technician_id]) {
        grouped[entry.technician_id] = [];
      }
      grouped[entry.technician_id].push(entry);
    });
    return grouped;
  };

  const calculateTotals = (entries: TimeEntry[]) => {
    return entries.reduce(
      (acc, entry) => ({
        hours: acc.hours + entry.total_hours,
        billable: acc.billable + entry.billable_hours,
        cost: acc.cost + (entry.billable_hours * (entry.hourly_rate || 0)),
      }),
      { hours: 0, billable: 0, cost: 0 }
    );
  };

  if (loading && items.length === 0) {
    return (
      <div className="ict-approval ict-approval--loading">
        <div className="ict-spinner">Loading...</div>
      </div>
    );
  }

  const groupedEntries = groupByTechnician();

  return (
    <div className="ict-approval">
      <div className="ict-approval__header">
        <h2>Timesheet Approval</h2>
        <div className="ict-approval__stats">
          <div className="ict-approval__stat">
            <span className="ict-approval__stat-label">Pending</span>
            <span className="ict-approval__stat-value">{items.length}</span>
          </div>
          <div className="ict-approval__stat">
            <span className="ict-approval__stat-label">Total Hours</span>
            <span className="ict-approval__stat-value">
              {formatTime(items.reduce((sum, e) => sum + e.total_hours, 0))}
            </span>
          </div>
        </div>
      </div>

      {items.length === 0 ? (
        <div className="ict-approval__empty">
          <span className="dashicons dashicons-yes-alt"></span>
          <p>No timesheets pending approval</p>
        </div>
      ) : (
        <div className="ict-approval__groups">
          {Object.entries(groupedEntries).map(([technicianId, entries]) => {
            const totals = calculateTotals(entries);

            return (
              <div key={technicianId} className="ict-approval__group">
                <div className="ict-approval__group-header">
                  <h3>Technician #{technicianId}</h3>
                  <div className="ict-approval__group-totals">
                    <span>{formatTime(totals.hours)} total</span>
                    <span>{formatTime(totals.billable)} billable</span>
                    <span className="ict-approval__cost">{formatCurrency(totals.cost)}</span>
                  </div>
                </div>

                <div className="ict-approval__entries">
                  {entries.map((entry) => {
                    const project = projects.find((p) => p.id === entry.project_id);
                    const cost = entry.billable_hours * (entry.hourly_rate || 0);

                    return (
                      <div key={entry.id} className="ict-approval__entry">
                        <div className="ict-approval__entry-header">
                          <div className="ict-approval__entry-date">
                            {new Date(entry.clock_in).toLocaleDateString('en-US', {
                              weekday: 'short',
                              month: 'short',
                              day: 'numeric',
                            })}
                          </div>
                          <div className="ict-approval__entry-project">
                            {project?.project_name || `Project #${entry.project_id}`}
                          </div>
                        </div>

                        <div className="ict-approval__entry-details">
                          <div className="ict-approval__entry-row">
                            <span className="ict-approval__entry-label">Task:</span>
                            <span className="ict-approval__entry-value">{entry.task_type}</span>
                          </div>
                          <div className="ict-approval__entry-row">
                            <span className="ict-approval__entry-label">Time:</span>
                            <span className="ict-approval__entry-value">
                              {new Date(entry.clock_in).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })}
                              {' - '}
                              {entry.clock_out
                                ? new Date(entry.clock_out).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })
                                : 'In Progress'
                              }
                            </span>
                          </div>
                          <div className="ict-approval__entry-row">
                            <span className="ict-approval__entry-label">Hours:</span>
                            <span className="ict-approval__entry-value ict-approval__entry-value--hours">
                              {formatTime(entry.total_hours)}
                              {entry.billable_hours !== entry.total_hours && (
                                <span className="ict-approval__billable">
                                  ({formatTime(entry.billable_hours)} billable)
                                </span>
                              )}
                            </span>
                          </div>
                          {entry.hourly_rate > 0 && (
                            <div className="ict-approval__entry-row">
                              <span className="ict-approval__entry-label">Cost:</span>
                              <span className="ict-approval__entry-value ict-approval__entry-value--cost">
                                {formatCurrency(cost)}
                              </span>
                            </div>
                          )}
                          {entry.notes && (
                            <div className="ict-approval__entry-row ict-approval__entry-row--notes">
                              <span className="ict-approval__entry-label">Notes:</span>
                              <span className="ict-approval__entry-value">{entry.notes}</span>
                            </div>
                          )}
                          {(entry.gps_latitude || entry.gps_longitude) && (
                            <div className="ict-approval__entry-row">
                              <span className="ict-approval__entry-label">Location:</span>
                              <span className="ict-approval__entry-value">
                                <span className="dashicons dashicons-location"></span>
                                Tracked
                              </span>
                            </div>
                          )}
                        </div>

                        <div className="ict-approval__entry-actions">
                          <button
                            onClick={() => handleApprove(entry)}
                            disabled={loading}
                            className="ict-button ict-button--success"
                          >
                            <span className="dashicons dashicons-yes"></span>
                            Approve
                          </button>
                          <button
                            onClick={() => openRejectModal(entry)}
                            disabled={loading}
                            className="ict-button ict-button--danger"
                          >
                            <span className="dashicons dashicons-no"></span>
                            Reject
                          </button>
                        </div>
                      </div>
                    );
                  })}
                </div>
              </div>
            );
          })}
        </div>
      )}

      {/* Reject Modal */}
      {showRejectModal && selectedEntry && (
        <div className="ict-modal ict-modal--active">
          <div className="ict-modal__overlay" onClick={closeRejectModal}></div>
          <div className="ict-modal__content">
            <div className="ict-modal__header">
              <h3>Reject Time Entry</h3>
              <button onClick={closeRejectModal} className="ict-modal__close">
                <span className="dashicons dashicons-no-alt"></span>
              </button>
            </div>

            <div className="ict-modal__body">
              <p>
                Please provide a reason for rejecting this time entry.
                The technician will be notified of your decision.
              </p>

              <div className="ict-modal__field">
                <label htmlFor="reject-reason">Rejection Reason *</label>
                <textarea
                  id="reject-reason"
                  value={rejectReason}
                  onChange={(e) => setRejectReason(e.target.value)}
                  placeholder="e.g., Hours exceed project allocation, Invalid clock times, Missing required details..."
                  rows={4}
                  required
                />
              </div>
            </div>

            <div className="ict-modal__footer">
              <button
                onClick={closeRejectModal}
                className="ict-button ict-button--secondary"
              >
                Cancel
              </button>
              <button
                onClick={handleReject}
                disabled={!rejectReason.trim() || loading}
                className="ict-button ict-button--danger"
              >
                {loading ? 'Rejecting...' : 'Reject Entry'}
              </button>
            </div>
          </div>
        </div>
      )}
    </div>
  );
};
