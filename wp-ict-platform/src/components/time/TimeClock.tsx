/**
 * Time Clock Component
 *
 * Mobile-optimized clock in/out interface
 *
 * @package ICT_Platform
 * @since   1.0.0
 */

import React, { useState, useEffect } from 'react';
import { useAppDispatch, useAppSelector } from '../../hooks/useAppDispatch';
import { clockIn, clockOut, fetchActiveEntry } from '../../store/slices/timeEntriesSlice';
import { fetchProjects } from '../../store/slices/projectsSlice';

interface ClockInFormData {
  project_id: number;
  task_type: string;
  notes: string;
}

export const TimeClock: React.FC = () => {
  const dispatch = useAppDispatch();
  const { activeEntry, loading: timeLoading } = useAppSelector((state) => state.timeEntries);
  const { items: projects, loading: projectsLoading } = useAppSelector((state) => state.projects);

  const [formData, setFormData] = useState<ClockInFormData>({
    project_id: 0,
    task_type: 'general',
    notes: '',
  });

  const [clockOutNotes, setClockOutNotes] = useState('');
  const [gpsEnabled, setGpsEnabled] = useState(true);

  useEffect(() => {
    dispatch(fetchActiveEntry());
    dispatch(fetchProjects());
  }, [dispatch]);

  const getGPSLocation = async (): Promise<{ latitude?: number; longitude?: number }> => {
    if (!gpsEnabled || !navigator.geolocation) {
      return {};
    }

    try {
      const position = await new Promise<GeolocationPosition>((resolve, reject) => {
        navigator.geolocation.getCurrentPosition(resolve, reject, {
          timeout: 10000,
          enableHighAccuracy: true,
        });
      });

      return {
        latitude: position.coords.latitude,
        longitude: position.coords.longitude,
      };
    } catch (error) {
      console.warn('GPS location error:', error);
      return {};
    }
  };

  const handleClockIn = async (e: React.FormEvent) => {
    e.preventDefault();

    if (!formData.project_id) {
      alert('Please select a project');
      return;
    }

    const gps = await getGPSLocation();

    await dispatch(clockIn({
      project_id: formData.project_id,
      task_type: formData.task_type,
      notes: formData.notes || undefined,
      ...gps,
    }));

    // Reset form
    setFormData({
      project_id: 0,
      task_type: 'general',
      notes: '',
    });
  };

  const handleClockOut = async () => {
    const gps = await getGPSLocation();

    await dispatch(clockOut({
      notes: clockOutNotes || undefined,
      ...gps,
    }));

    setClockOutNotes('');
  };

  const taskTypes = [
    { value: 'general', label: 'General Work' },
    { value: 'installation', label: 'Installation' },
    { value: 'maintenance', label: 'Maintenance' },
    { value: 'repair', label: 'Repair' },
    { value: 'inspection', label: 'Inspection' },
    { value: 'troubleshooting', label: 'Troubleshooting' },
    { value: 'testing', label: 'Testing' },
    { value: 'documentation', label: 'Documentation' },
    { value: 'travel', label: 'Travel' },
  ];

  // Active view - showing clocked in state
  if (activeEntry) {
    const clockInTime = new Date(activeEntry.clock_in);
    const now = new Date();
    const elapsedMs = now.getTime() - clockInTime.getTime();
    const elapsedHours = elapsedMs / (1000 * 60 * 60);

    return (
      <div className="ict-time-clock ict-time-clock--active">
        <div className="ict-time-clock__status">
          <div className="ict-time-clock__pulse"></div>
          <div className="ict-time-clock__status-text">Clocked In</div>
        </div>

        <div className="ict-time-clock__timer">
          {Math.floor(elapsedHours)}h {Math.floor((elapsedHours % 1) * 60)}m
        </div>

        <div className="ict-time-clock__project-info">
          <div className="ict-time-clock__project-name">
            {projects.find((p) => p.id === activeEntry.project_id)?.project_name || `Project #${activeEntry.project_id}`}
          </div>
          <div className="ict-time-clock__task-type">
            {taskTypes.find((t) => t.value === activeEntry.task_type)?.label || activeEntry.task_type}
          </div>
          <div className="ict-time-clock__clock-in-time">
            Since {clockInTime.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })}
          </div>
        </div>

        <div className="ict-time-clock__notes-section">
          <label htmlFor="clock-out-notes" className="ict-time-clock__label">
            Add Notes (Optional)
          </label>
          <textarea
            id="clock-out-notes"
            className="ict-time-clock__textarea"
            value={clockOutNotes}
            onChange={(e) => setClockOutNotes(e.target.value)}
            placeholder="What did you accomplish?"
            rows={4}
          />
        </div>

        <button
          onClick={handleClockOut}
          disabled={timeLoading}
          className="ict-time-clock__button ict-time-clock__button--clock-out"
        >
          <span className="dashicons dashicons-marker"></span>
          <span className="ict-time-clock__button-text">
            {timeLoading ? 'Clocking Out...' : 'Clock Out'}
          </span>
        </button>

        <div className="ict-time-clock__gps-status">
          <label className="ict-time-clock__gps-toggle">
            <input
              type="checkbox"
              checked={gpsEnabled}
              onChange={(e) => setGpsEnabled(e.target.checked)}
            />
            <span>Track GPS Location</span>
          </label>
        </div>
      </div>
    );
  }

  // Clock in view
  return (
    <div className="ict-time-clock ict-time-clock--idle">
      <div className="ict-time-clock__header">
        <div className="ict-time-clock__icon">
          <span className="dashicons dashicons-clock"></span>
        </div>
        <h2 className="ict-time-clock__title">Time Clock</h2>
        <p className="ict-time-clock__subtitle">Select a project to clock in</p>
      </div>

      <form onSubmit={handleClockIn} className="ict-time-clock__form">
        <div className="ict-time-clock__field">
          <label htmlFor="project" className="ict-time-clock__label">
            Project *
          </label>
          <select
            id="project"
            className="ict-time-clock__select"
            value={formData.project_id}
            onChange={(e) => setFormData({ ...formData, project_id: parseInt(e.target.value) })}
            required
            disabled={projectsLoading}
          >
            <option value={0}>Select a project...</option>
            {projects
              .filter((p) => p.status === 'in-progress' || p.status === 'pending')
              .map((project) => (
                <option key={project.id} value={project.id}>
                  {project.project_name}
                </option>
              ))}
          </select>
        </div>

        <div className="ict-time-clock__field">
          <label htmlFor="task-type" className="ict-time-clock__label">
            Task Type
          </label>
          <select
            id="task-type"
            className="ict-time-clock__select"
            value={formData.task_type}
            onChange={(e) => setFormData({ ...formData, task_type: e.target.value })}
          >
            {taskTypes.map((type) => (
              <option key={type.value} value={type.value}>
                {type.label}
              </option>
            ))}
          </select>
        </div>

        <div className="ict-time-clock__field">
          <label htmlFor="notes" className="ict-time-clock__label">
            Notes (Optional)
          </label>
          <textarea
            id="notes"
            className="ict-time-clock__textarea"
            value={formData.notes}
            onChange={(e) => setFormData({ ...formData, notes: e.target.value })}
            placeholder="What will you be working on?"
            rows={3}
          />
        </div>

        <div className="ict-time-clock__gps-status">
          <label className="ict-time-clock__gps-toggle">
            <input
              type="checkbox"
              checked={gpsEnabled}
              onChange={(e) => setGpsEnabled(e.target.checked)}
            />
            <span>Track GPS Location</span>
          </label>
        </div>

        <button
          type="submit"
          disabled={timeLoading || !formData.project_id}
          className="ict-time-clock__button ict-time-clock__button--clock-in"
        >
          <span className="dashicons dashicons-yes"></span>
          <span className="ict-time-clock__button-text">
            {timeLoading ? 'Clocking In...' : 'Clock In'}
          </span>
        </button>
      </form>
    </div>
  );
};
