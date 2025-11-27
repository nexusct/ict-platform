/**
 * Advanced Time Tracking Component
 *
 * Enhanced time tracking with breaks, multiple rates, and overtime.
 *
 * @package ICT_Platform
 * @since   2.1.0
 */

import React, { useState, useEffect, useCallback, useMemo } from 'react';

interface TimeEntry {
  id: string;
  projectId: number;
  projectName: string;
  startTime: Date;
  endTime?: Date;
  breaks: Break[];
  rate: Rate;
  notes: string;
  status: 'active' | 'paused' | 'completed';
  location?: GeolocationCoordinates;
}

interface Break {
  id: string;
  startTime: Date;
  endTime?: Date;
  type: 'lunch' | 'short' | 'other';
}

interface Rate {
  id: string;
  name: string;
  hourlyRate: number;
  overtimeMultiplier: number;
  isDefault: boolean;
}

interface AdvancedTimeTrackingProps {
  projectId?: number;
  projectName?: string;
  availableRates?: Rate[];
  dailyOvertimeThreshold?: number;
  weeklyOvertimeThreshold?: number;
  onSave?: (entry: TimeEntry) => void;
  apiEndpoint?: string;
}

const defaultRates: Rate[] = [
  { id: 'standard', name: 'Standard', hourlyRate: 45, overtimeMultiplier: 1.5, isDefault: true },
  { id: 'emergency', name: 'Emergency', hourlyRate: 75, overtimeMultiplier: 2, isDefault: false },
  { id: 'training', name: 'Training', hourlyRate: 35, overtimeMultiplier: 1, isDefault: false },
];

const AdvancedTimeTracking: React.FC<AdvancedTimeTrackingProps> = ({
  projectId,
  projectName = 'Select Project',
  availableRates = defaultRates,
  dailyOvertimeThreshold = 8,
  weeklyOvertimeThreshold = 40,
  onSave,
  apiEndpoint = '/wp-json/ict/v1/time-entries',
}) => {
  const [currentEntry, setCurrentEntry] = useState<TimeEntry | null>(null);
  const [selectedRate, setSelectedRate] = useState<Rate>(
    availableRates.find((r) => r.isDefault) || availableRates[0]
  );
  const [notes, setNotes] = useState('');
  const [weeklyHours, setWeeklyHours] = useState(0);
  const [dailyHours, setDailyHours] = useState(0);
  const [elapsedTime, setElapsedTime] = useState(0);
  const [showRateSelector, setShowRateSelector] = useState(false);
  const [trackLocation, setTrackLocation] = useState(true);

  // Timer for elapsed time
  useEffect(() => {
    if (!currentEntry || currentEntry.status !== 'active') return;

    const intervalId = setInterval(() => {
      const now = new Date();
      const start = new Date(currentEntry.startTime);
      const breakTime = calculateBreakTime(currentEntry.breaks);
      const elapsed = Math.floor((now.getTime() - start.getTime()) / 1000) - breakTime;
      setElapsedTime(Math.max(0, elapsed));
    }, 1000);

    return () => clearInterval(intervalId);
  }, [currentEntry]);

  // Calculate break time in seconds
  const calculateBreakTime = (breaks: Break[]): number => {
    return breaks.reduce((total, brk) => {
      const start = new Date(brk.startTime).getTime();
      const end = brk.endTime ? new Date(brk.endTime).getTime() : Date.now();
      return total + Math.floor((end - start) / 1000);
    }, 0);
  };

  // Format time display
  const formatTime = (seconds: number): string => {
    const hrs = Math.floor(seconds / 3600);
    const mins = Math.floor((seconds % 3600) / 60);
    const secs = seconds % 60;
    return `${hrs.toString().padStart(2, '0')}:${mins.toString().padStart(2, '0')}:${secs.toString().padStart(2, '0')}`;
  };

  // Format currency
  const formatCurrency = (amount: number): string => {
    return new Intl.NumberFormat('en-US', {
      style: 'currency',
      currency: 'USD',
    }).format(amount);
  };

  // Calculate earnings
  const calculateEarnings = useMemo(() => {
    const hours = elapsedTime / 3600;
    const regularHours = Math.min(hours, dailyOvertimeThreshold - dailyHours);
    const overtimeHours = Math.max(0, hours - regularHours);

    const regularPay = regularHours * selectedRate.hourlyRate;
    const overtimePay = overtimeHours * selectedRate.hourlyRate * selectedRate.overtimeMultiplier;

    return {
      regularHours: Math.max(0, regularHours),
      overtimeHours,
      regularPay,
      overtimePay,
      totalPay: regularPay + overtimePay,
    };
  }, [elapsedTime, selectedRate, dailyHours, dailyOvertimeThreshold]);

  // Get current location
  const getCurrentLocation = (): Promise<GeolocationCoordinates | undefined> => {
    return new Promise((resolve) => {
      if (!trackLocation || !navigator.geolocation) {
        resolve(undefined);
        return;
      }

      navigator.geolocation.getCurrentPosition(
        (position) => resolve(position.coords),
        () => resolve(undefined),
        { enableHighAccuracy: true, timeout: 5000 }
      );
    });
  };

  // Start tracking
  const handleStart = async () => {
    const location = await getCurrentLocation();

    const newEntry: TimeEntry = {
      id: `${Date.now()}-${Math.random().toString(36).slice(2)}`,
      projectId: projectId || 0,
      projectName,
      startTime: new Date(),
      breaks: [],
      rate: selectedRate,
      notes: '',
      status: 'active',
      location,
    };

    setCurrentEntry(newEntry);
    setElapsedTime(0);
  };

  // Pause (start break)
  const handlePause = (breakType: Break['type'] = 'short') => {
    if (!currentEntry) return;

    const newBreak: Break = {
      id: `${Date.now()}-${Math.random().toString(36).slice(2)}`,
      startTime: new Date(),
      type: breakType,
    };

    setCurrentEntry({
      ...currentEntry,
      status: 'paused',
      breaks: [...currentEntry.breaks, newBreak],
    });
  };

  // Resume (end break)
  const handleResume = () => {
    if (!currentEntry) return;

    const updatedBreaks = currentEntry.breaks.map((brk, index) => {
      if (index === currentEntry.breaks.length - 1 && !brk.endTime) {
        return { ...brk, endTime: new Date() };
      }
      return brk;
    });

    setCurrentEntry({
      ...currentEntry,
      status: 'active',
      breaks: updatedBreaks,
    });
  };

  // Stop tracking
  const handleStop = async () => {
    if (!currentEntry) return;

    const completedEntry: TimeEntry = {
      ...currentEntry,
      endTime: new Date(),
      notes,
      status: 'completed',
    };

    // End any active break
    if (currentEntry.status === 'paused') {
      const lastBreakIndex = completedEntry.breaks.length - 1;
      if (lastBreakIndex >= 0 && !completedEntry.breaks[lastBreakIndex].endTime) {
        completedEntry.breaks[lastBreakIndex].endTime = new Date();
      }
    }

    // Save to API
    try {
      await fetch(apiEndpoint, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-WP-Nonce': (window as any).ictSettings?.nonce || '',
        },
        body: JSON.stringify({
          project_id: completedEntry.projectId,
          start_time: completedEntry.startTime.toISOString(),
          end_time: completedEntry.endTime?.toISOString(),
          breaks: completedEntry.breaks,
          rate_id: completedEntry.rate.id,
          hourly_rate: completedEntry.rate.hourlyRate,
          notes: completedEntry.notes,
          latitude: completedEntry.location?.latitude,
          longitude: completedEntry.location?.longitude,
        }),
      });

      onSave?.(completedEntry);
    } catch (error) {
      console.error('Failed to save time entry:', error);
    }

    // Reset
    setCurrentEntry(null);
    setElapsedTime(0);
    setNotes('');
  };

  // Check if in overtime
  const isOvertime = dailyHours + elapsedTime / 3600 > dailyOvertimeThreshold;

  return (
    <div className="ict-time-tracking">
      {/* Timer display */}
      <div className={`ict-timer-display ${currentEntry?.status || ''} ${isOvertime ? 'overtime' : ''}`}>
        <div className="ict-timer-time">{formatTime(elapsedTime)}</div>
        <div className="ict-timer-status">
          {!currentEntry && 'Ready to track'}
          {currentEntry?.status === 'active' && 'Tracking...'}
          {currentEntry?.status === 'paused' && 'On Break'}
        </div>
        {isOvertime && <span className="ict-overtime-badge">OVERTIME</span>}
      </div>

      {/* Project & Rate Info */}
      <div className="ict-tracking-info">
        <div className="ict-tracking-project">
          <span className="ict-tracking-label">Project</span>
          <span className="ict-tracking-value">{projectName}</span>
        </div>

        <div className="ict-tracking-rate" onClick={() => !currentEntry && setShowRateSelector(!showRateSelector)}>
          <span className="ict-tracking-label">Rate</span>
          <span className="ict-tracking-value">
            {selectedRate.name} ({formatCurrency(selectedRate.hourlyRate)}/hr)
          </span>
          {!currentEntry && (
            <svg className="ict-tracking-rate-arrow" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
              <path d="M6 9l6 6 6-6" />
            </svg>
          )}
        </div>

        {showRateSelector && !currentEntry && (
          <div className="ict-rate-selector">
            {availableRates.map((rate) => (
              <div
                key={rate.id}
                className={`ict-rate-option ${selectedRate.id === rate.id ? 'selected' : ''}`}
                onClick={() => {
                  setSelectedRate(rate);
                  setShowRateSelector(false);
                }}
              >
                <span className="ict-rate-name">{rate.name}</span>
                <span className="ict-rate-price">{formatCurrency(rate.hourlyRate)}/hr</span>
                {rate.overtimeMultiplier > 1 && (
                  <span className="ict-rate-overtime">OT: {rate.overtimeMultiplier}x</span>
                )}
              </div>
            ))}
          </div>
        )}
      </div>

      {/* Earnings summary */}
      {currentEntry && (
        <div className="ict-earnings-summary">
          <div className="ict-earnings-row">
            <span>Regular ({calculateEarnings.regularHours.toFixed(2)} hrs)</span>
            <span>{formatCurrency(calculateEarnings.regularPay)}</span>
          </div>
          {calculateEarnings.overtimeHours > 0 && (
            <div className="ict-earnings-row overtime">
              <span>Overtime ({calculateEarnings.overtimeHours.toFixed(2)} hrs @ {selectedRate.overtimeMultiplier}x)</span>
              <span>{formatCurrency(calculateEarnings.overtimePay)}</span>
            </div>
          )}
          <div className="ict-earnings-row total">
            <span>Current Total</span>
            <span>{formatCurrency(calculateEarnings.totalPay)}</span>
          </div>
        </div>
      )}

      {/* Breaks summary */}
      {currentEntry && currentEntry.breaks.length > 0 && (
        <div className="ict-breaks-summary">
          <h4>Breaks ({currentEntry.breaks.length})</h4>
          {currentEntry.breaks.map((brk) => (
            <div key={brk.id} className="ict-break-item">
              <span className="ict-break-type">{brk.type}</span>
              <span className="ict-break-time">
                {new Date(brk.startTime).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })}
                {brk.endTime && ` - ${new Date(brk.endTime).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })}`}
              </span>
              <span className="ict-break-duration">
                {formatTime(Math.floor(
                  ((brk.endTime ? new Date(brk.endTime).getTime() : Date.now()) - new Date(brk.startTime).getTime()) / 1000
                ))}
              </span>
            </div>
          ))}
        </div>
      )}

      {/* Notes */}
      {currentEntry && (
        <div className="ict-tracking-notes">
          <textarea
            value={notes}
            onChange={(e) => setNotes(e.target.value)}
            placeholder="Add notes about this time entry..."
            rows={3}
          />
        </div>
      )}

      {/* Controls */}
      <div className="ict-tracking-controls">
        {!currentEntry ? (
          <>
            <label className="ict-tracking-location-toggle">
              <input
                type="checkbox"
                checked={trackLocation}
                onChange={(e) => setTrackLocation(e.target.checked)}
              />
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0118 0z" />
                <circle cx="12" cy="10" r="3" />
              </svg>
              Track location
            </label>
            <button className="ict-tracking-btn start" onClick={handleStart} disabled={!projectId}>
              <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                <polygon points="5,3 19,12 5,21" />
              </svg>
              Start Tracking
            </button>
          </>
        ) : currentEntry.status === 'active' ? (
          <>
            <div className="ict-break-buttons">
              <button className="ict-tracking-btn break" onClick={() => handlePause('short')}>
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                  <rect x="4" y="4" width="6" height="16" rx="1" />
                  <rect x="14" y="4" width="6" height="16" rx="1" />
                </svg>
                Short Break
              </button>
              <button className="ict-tracking-btn break lunch" onClick={() => handlePause('lunch')}>
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                  <path d="M18 8h1a4 4 0 010 8h-1" />
                  <path d="M2 8h16v9a4 4 0 01-4 4H6a4 4 0 01-4-4V8z" />
                  <line x1="6" y1="1" x2="6" y2="4" />
                  <line x1="10" y1="1" x2="10" y2="4" />
                  <line x1="14" y1="1" x2="14" y2="4" />
                </svg>
                Lunch
              </button>
            </div>
            <button className="ict-tracking-btn stop" onClick={handleStop}>
              <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                <rect x="4" y="4" width="16" height="16" rx="2" />
              </svg>
              Stop & Save
            </button>
          </>
        ) : (
          <>
            <button className="ict-tracking-btn resume" onClick={handleResume}>
              <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                <polygon points="5,3 19,12 5,21" />
              </svg>
              Resume
            </button>
            <button className="ict-tracking-btn stop" onClick={handleStop}>
              <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                <rect x="4" y="4" width="16" height="16" rx="2" />
              </svg>
              Stop & Save
            </button>
          </>
        )}
      </div>

      {/* Weekly summary */}
      <div className="ict-weekly-summary">
        <div className="ict-weekly-progress">
          <div className="ict-weekly-bar">
            <div
              className="ict-weekly-fill"
              style={{
                width: `${Math.min(100, (weeklyHours / weeklyOvertimeThreshold) * 100)}%`,
                background: weeklyHours >= weeklyOvertimeThreshold ? '#ef4444' : '#3b82f6',
              }}
            />
          </div>
          <span className="ict-weekly-text">
            {weeklyHours.toFixed(1)} / {weeklyOvertimeThreshold} hrs this week
          </span>
        </div>
      </div>

      <style>{`
        .ict-time-tracking {
          background: var(--ict-bg-color, #fff);
          border: 1px solid var(--ict-border-color, #e5e7eb);
          border-radius: 16px;
          padding: 24px;
          max-width: 400px;
        }

        .ict-timer-display {
          text-align: center;
          padding: 32px 0;
          margin-bottom: 24px;
          background: var(--ict-bg-secondary, #f9fafb);
          border-radius: 12px;
          position: relative;
        }

        .ict-timer-display.active {
          background: linear-gradient(135deg, #10b981 0%, #059669 100%);
          color: #fff;
        }

        .ict-timer-display.paused {
          background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
          color: #fff;
        }

        .ict-timer-display.overtime {
          animation: pulse-overtime 2s infinite;
        }

        @keyframes pulse-overtime {
          0%, 100% { box-shadow: 0 0 0 0 rgba(239, 68, 68, 0.4); }
          50% { box-shadow: 0 0 0 10px rgba(239, 68, 68, 0); }
        }

        .ict-timer-time {
          font-size: 48px;
          font-weight: 700;
          font-family: 'SF Mono', 'Consolas', monospace;
          letter-spacing: 2px;
        }

        .ict-timer-status {
          font-size: 14px;
          opacity: 0.8;
          margin-top: 8px;
        }

        .ict-overtime-badge {
          position: absolute;
          top: 12px;
          right: 12px;
          padding: 4px 8px;
          background: #ef4444;
          color: #fff;
          font-size: 10px;
          font-weight: 700;
          border-radius: 4px;
        }

        .ict-tracking-info {
          margin-bottom: 20px;
        }

        .ict-tracking-project,
        .ict-tracking-rate {
          display: flex;
          justify-content: space-between;
          align-items: center;
          padding: 12px 0;
          border-bottom: 1px solid var(--ict-border-light, #f3f4f6);
        }

        .ict-tracking-rate {
          cursor: pointer;
          position: relative;
        }

        .ict-tracking-label {
          font-size: 13px;
          color: var(--ict-text-muted, #6b7280);
        }

        .ict-tracking-value {
          font-size: 14px;
          font-weight: 500;
          color: var(--ict-text-color, #1f2937);
        }

        .ict-tracking-rate-arrow {
          margin-left: 8px;
          color: var(--ict-text-muted, #6b7280);
        }

        .ict-rate-selector {
          position: absolute;
          top: 100%;
          left: 0;
          right: 0;
          background: var(--ict-bg-color, #fff);
          border: 1px solid var(--ict-border-color, #e5e7eb);
          border-radius: 8px;
          box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
          z-index: 10;
          overflow: hidden;
        }

        .ict-rate-option {
          display: flex;
          align-items: center;
          gap: 12px;
          padding: 12px 16px;
          cursor: pointer;
          transition: background 0.15s;
        }

        .ict-rate-option:hover {
          background: var(--ict-bg-hover, #f3f4f6);
        }

        .ict-rate-option.selected {
          background: rgba(59, 130, 246, 0.1);
        }

        .ict-rate-name {
          flex: 1;
          font-weight: 500;
        }

        .ict-rate-price {
          color: var(--ict-text-secondary, #4b5563);
        }

        .ict-rate-overtime {
          font-size: 11px;
          padding: 2px 6px;
          background: #fef3c7;
          color: #92400e;
          border-radius: 4px;
        }

        .ict-earnings-summary {
          background: var(--ict-bg-secondary, #f9fafb);
          border-radius: 8px;
          padding: 16px;
          margin-bottom: 20px;
        }

        .ict-earnings-row {
          display: flex;
          justify-content: space-between;
          font-size: 13px;
          padding: 6px 0;
          color: var(--ict-text-secondary, #4b5563);
        }

        .ict-earnings-row.overtime {
          color: #f59e0b;
        }

        .ict-earnings-row.total {
          border-top: 1px solid var(--ict-border-color, #e5e7eb);
          margin-top: 8px;
          padding-top: 12px;
          font-weight: 600;
          font-size: 15px;
          color: var(--ict-text-color, #1f2937);
        }

        .ict-breaks-summary {
          margin-bottom: 20px;
        }

        .ict-breaks-summary h4 {
          font-size: 13px;
          font-weight: 600;
          color: var(--ict-text-secondary, #4b5563);
          margin: 0 0 12px;
        }

        .ict-break-item {
          display: flex;
          align-items: center;
          gap: 12px;
          padding: 8px 12px;
          background: var(--ict-bg-secondary, #f9fafb);
          border-radius: 6px;
          margin-bottom: 8px;
          font-size: 13px;
        }

        .ict-break-type {
          text-transform: capitalize;
          font-weight: 500;
          width: 60px;
        }

        .ict-break-time {
          flex: 1;
          color: var(--ict-text-muted, #6b7280);
        }

        .ict-break-duration {
          font-family: monospace;
          color: var(--ict-text-secondary, #4b5563);
        }

        .ict-tracking-notes textarea {
          width: 100%;
          padding: 12px;
          font-size: 14px;
          border: 1px solid var(--ict-border-color, #e5e7eb);
          border-radius: 8px;
          resize: vertical;
          margin-bottom: 20px;
          font-family: inherit;
        }

        .ict-tracking-notes textarea:focus {
          outline: none;
          border-color: var(--ict-primary, #3b82f6);
        }

        .ict-tracking-controls {
          display: flex;
          flex-direction: column;
          gap: 12px;
        }

        .ict-tracking-location-toggle {
          display: flex;
          align-items: center;
          gap: 8px;
          font-size: 13px;
          color: var(--ict-text-secondary, #4b5563);
          cursor: pointer;
          margin-bottom: 8px;
        }

        .ict-break-buttons {
          display: flex;
          gap: 8px;
        }

        .ict-tracking-btn {
          display: flex;
          align-items: center;
          justify-content: center;
          gap: 8px;
          padding: 14px 24px;
          font-size: 15px;
          font-weight: 600;
          border: none;
          border-radius: 10px;
          cursor: pointer;
          transition: all 0.2s;
        }

        .ict-tracking-btn:disabled {
          opacity: 0.5;
          cursor: not-allowed;
        }

        .ict-tracking-btn.start {
          background: #10b981;
          color: #fff;
        }

        .ict-tracking-btn.start:hover:not(:disabled) {
          background: #059669;
        }

        .ict-tracking-btn.stop {
          background: #ef4444;
          color: #fff;
        }

        .ict-tracking-btn.stop:hover {
          background: #dc2626;
        }

        .ict-tracking-btn.break {
          flex: 1;
          background: var(--ict-bg-secondary, #f3f4f6);
          color: var(--ict-text-color, #1f2937);
          border: 1px solid var(--ict-border-color, #e5e7eb);
        }

        .ict-tracking-btn.break:hover {
          background: var(--ict-bg-hover, #e5e7eb);
        }

        .ict-tracking-btn.break.lunch {
          background: #fef3c7;
          border-color: #fcd34d;
          color: #92400e;
        }

        .ict-tracking-btn.resume {
          background: #3b82f6;
          color: #fff;
        }

        .ict-tracking-btn.resume:hover {
          background: #2563eb;
        }

        .ict-weekly-summary {
          margin-top: 24px;
          padding-top: 20px;
          border-top: 1px solid var(--ict-border-color, #e5e7eb);
        }

        .ict-weekly-bar {
          height: 8px;
          background: var(--ict-bg-secondary, #e5e7eb);
          border-radius: 4px;
          overflow: hidden;
          margin-bottom: 8px;
        }

        .ict-weekly-fill {
          height: 100%;
          border-radius: 4px;
          transition: width 0.3s;
        }

        .ict-weekly-text {
          font-size: 12px;
          color: var(--ict-text-muted, #6b7280);
        }
      `}</style>
    </div>
  );
};

export default AdvancedTimeTracking;
