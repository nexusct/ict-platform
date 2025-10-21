/**
 * Time Tracker Component
 *
 * Shows active time entry with live timer
 *
 * @package ICT_Platform
 * @since   1.0.0
 */

import React, { useState, useEffect } from 'react';
import { useAppDispatch, useAppSelector } from '../../hooks/useAppDispatch';
import { fetchActiveEntry, clockOut, updateActiveEntryTime } from '../../store/slices/timeEntriesSlice';

export const TimeTracker: React.FC = () => {
  const dispatch = useAppDispatch();
  const { activeEntry, loading } = useAppSelector((state) => state.timeEntries);
  const [elapsed, setElapsed] = useState(0);
  const [clockOutNotes, setClockOutNotes] = useState('');

  // Fetch active entry on mount
  useEffect(() => {
    dispatch(fetchActiveEntry());
  }, [dispatch]);

  // Live timer
  useEffect(() => {
    if (!activeEntry) {
      setElapsed(0);
      return;
    }

    const clockInTime = new Date(activeEntry.clock_in).getTime();

    const updateTimer = () => {
      const now = Date.now();
      const elapsedMs = now - clockInTime;
      const elapsedHours = elapsedMs / (1000 * 60 * 60);
      setElapsed(elapsedHours);

      // Update Redux state for other components
      dispatch(updateActiveEntryTime({ elapsed: elapsedHours }));
    };

    // Update immediately
    updateTimer();

    // Update every second
    const interval = setInterval(updateTimer, 1000);

    return () => clearInterval(interval);
  }, [activeEntry, dispatch]);

  const handleClockOut = async () => {
    if (!activeEntry) return;

    // Get GPS if available
    let gps: { latitude?: number; longitude?: number } = {};
    if (navigator.geolocation) {
      try {
        const position = await new Promise<GeolocationPosition>((resolve, reject) => {
          navigator.geolocation.getCurrentPosition(resolve, reject, { timeout: 5000 });
        });
        gps = {
          latitude: position.coords.latitude,
          longitude: position.coords.longitude,
        };
      } catch (error) {
        console.warn('Could not get GPS location:', error);
      }
    }

    await dispatch(clockOut({
      entry_id: activeEntry.id,
      notes: clockOutNotes || undefined,
      gps_latitude: gps.latitude,
      gps_longitude: gps.longitude,
    }));

    setClockOutNotes('');
  };

  const formatTime = (hours: number): string => {
    const totalSeconds = Math.floor(hours * 3600);
    const h = Math.floor(totalSeconds / 3600);
    const m = Math.floor((totalSeconds % 3600) / 60);
    const s = totalSeconds % 60;
    return `${h.toString().padStart(2, '0')}:${m.toString().padStart(2, '0')}:${s.toString().padStart(2, '0')}`;
  };

  const formatCurrency = (amount: number): string => {
    return new Intl.NumberFormat('en-US', {
      style: 'currency',
      currency: 'USD',
    }).format(amount);
  };

  if (loading && !activeEntry) {
    return (
      <div className="ict-time-tracker ict-time-tracker--loading">
        <div className="ict-time-tracker__spinner">Loading...</div>
      </div>
    );
  }

  if (!activeEntry) {
    return (
      <div className="ict-time-tracker ict-time-tracker--idle">
        <div className="ict-time-tracker__idle-message">
          <div className="ict-time-tracker__idle-icon">
            <span className="dashicons dashicons-clock"></span>
          </div>
          <h3>Not Clocked In</h3>
          <p>You don't have an active time entry. Use the Time Clock to clock in to a project.</p>
        </div>
      </div>
    );
  }

  const estimatedCost = elapsed * (activeEntry.hourly_rate || 0);

  return (
    <div className="ict-time-tracker ict-time-tracker--active">
      <div className="ict-time-tracker__header">
        <h2>Currently Tracking</h2>
        <span className="ict-time-tracker__status-badge ict-status-badge ict-status-badge--success">
          <span className="ict-time-tracker__pulse"></span>
          Active
        </span>
      </div>

      <div className="ict-time-tracker__timer">
        <div className="ict-time-tracker__timer-display">
          {formatTime(elapsed)}
        </div>
        <div className="ict-time-tracker__timer-label">Elapsed Time</div>
      </div>

      <div className="ict-time-tracker__details">
        <div className="ict-time-tracker__detail-row">
          <span className="ict-time-tracker__detail-label">Project:</span>
          <span className="ict-time-tracker__detail-value">
            Project #{activeEntry.project_id}
          </span>
        </div>

        <div className="ict-time-tracker__detail-row">
          <span className="ict-time-tracker__detail-label">Task Type:</span>
          <span className="ict-time-tracker__detail-value">
            {activeEntry.task_type || 'General'}
          </span>
        </div>

        <div className="ict-time-tracker__detail-row">
          <span className="ict-time-tracker__detail-label">Clock In:</span>
          <span className="ict-time-tracker__detail-value">
            {new Date(activeEntry.clock_in).toLocaleString()}
          </span>
        </div>

        {activeEntry.hourly_rate > 0 && (
          <div className="ict-time-tracker__detail-row">
            <span className="ict-time-tracker__detail-label">Estimated Cost:</span>
            <span className="ict-time-tracker__detail-value ict-time-tracker__detail-value--currency">
              {formatCurrency(estimatedCost)}
            </span>
          </div>
        )}

        {activeEntry.notes && (
          <div className="ict-time-tracker__detail-row ict-time-tracker__detail-row--full">
            <span className="ict-time-tracker__detail-label">Notes:</span>
            <span className="ict-time-tracker__detail-value">
              {activeEntry.notes}
            </span>
          </div>
        )}
      </div>

      <div className="ict-time-tracker__actions">
        <div className="ict-time-tracker__notes-field">
          <label htmlFor="clock-out-notes">
            <span className="dashicons dashicons-edit"></span>
            Add Clock Out Notes (Optional)
          </label>
          <textarea
            id="clock-out-notes"
            value={clockOutNotes}
            onChange={(e) => setClockOutNotes(e.target.value)}
            placeholder="Add any notes about the work completed..."
            rows={3}
          />
        </div>

        <button
          onClick={handleClockOut}
          disabled={loading}
          className="ict-button ict-button--danger ict-button--large"
        >
          <span className="dashicons dashicons-marker"></span>
          {loading ? 'Clocking Out...' : 'Clock Out'}
        </button>
      </div>

      {activeEntry.gps_latitude && activeEntry.gps_longitude && (
        <div className="ict-time-tracker__gps">
          <span className="dashicons dashicons-location"></span>
          <small>
            Clock In Location: {activeEntry.gps_latitude.toFixed(6)}, {activeEntry.gps_longitude.toFixed(6)}
          </small>
        </div>
      )}
    </div>
  );
};
