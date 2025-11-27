/**
 * Smart Resource Scheduler Component
 *
 * AI-powered resource allocation with conflict detection and optimization.
 *
 * @package ICT_Platform
 * @since   2.1.0
 */

import React, { useState, useMemo, useCallback } from 'react';

interface Resource {
  id: number;
  name: string;
  type: 'technician' | 'equipment' | 'vehicle';
  avatar?: string;
  skills?: string[];
  available: boolean;
  hourlyRate?: number;
}

interface Assignment {
  id: string;
  resourceId: number;
  projectId: number;
  projectName: string;
  start: Date;
  end: Date;
  hours: number;
  status: 'scheduled' | 'in_progress' | 'completed';
  priority: 'low' | 'normal' | 'high' | 'urgent';
}

interface TimeSlot {
  date: Date;
  hour: number;
  isAvailable: boolean;
  assignment?: Assignment;
}

interface ResourceSchedulerProps {
  resources: Resource[];
  assignments: Assignment[];
  startDate?: Date;
  daysToShow?: number;
  onAssignmentCreate?: (assignment: Omit<Assignment, 'id'>) => void;
  onAssignmentUpdate?: (assignment: Assignment) => void;
  onAssignmentDelete?: (assignmentId: string) => void;
}

const ResourceScheduler: React.FC<ResourceSchedulerProps> = ({
  resources,
  assignments,
  startDate = new Date(),
  daysToShow = 7,
  onAssignmentCreate,
  onAssignmentUpdate,
  onAssignmentDelete,
}) => {
  const [selectedResource, setSelectedResource] = useState<Resource | null>(null);
  const [selectedDate, setSelectedDate] = useState<Date | null>(null);
  const [viewMode, setViewMode] = useState<'week' | 'day'>('week');
  const [filterType, setFilterType] = useState<string>('all');
  const [showConflicts, setShowConflicts] = useState(true);
  const [draggedAssignment, setDraggedAssignment] = useState<Assignment | null>(null);

  // Generate date range
  const dateRange = useMemo(() => {
    const dates: Date[] = [];
    const start = new Date(startDate);
    start.setHours(0, 0, 0, 0);

    for (let i = 0; i < daysToShow; i++) {
      const date = new Date(start);
      date.setDate(start.getDate() + i);
      dates.push(date);
    }
    return dates;
  }, [startDate, daysToShow]);

  // Filter resources
  const filteredResources = useMemo(() => {
    if (filterType === 'all') return resources;
    return resources.filter((r) => r.type === filterType);
  }, [resources, filterType]);

  // Get assignments for a resource on a date
  const getAssignmentsForCell = useCallback(
    (resourceId: number, date: Date) => {
      return assignments.filter((a) => {
        const assignmentDate = new Date(a.start);
        return (
          a.resourceId === resourceId &&
          assignmentDate.toDateString() === date.toDateString()
        );
      });
    },
    [assignments]
  );

  // Detect conflicts
  const detectConflicts = useCallback(
    (resourceId: number, date: Date): Assignment[] => {
      const cellAssignments = getAssignmentsForCell(resourceId, date);
      if (cellAssignments.length < 2) return [];

      const conflicts: Assignment[] = [];
      for (let i = 0; i < cellAssignments.length; i++) {
        for (let j = i + 1; j < cellAssignments.length; j++) {
          const a = cellAssignments[i];
          const b = cellAssignments[j];
          const aStart = new Date(a.start).getTime();
          const aEnd = new Date(a.end).getTime();
          const bStart = new Date(b.start).getTime();
          const bEnd = new Date(b.end).getTime();

          if (aStart < bEnd && bStart < aEnd) {
            if (!conflicts.includes(a)) conflicts.push(a);
            if (!conflicts.includes(b)) conflicts.push(b);
          }
        }
      }
      return conflicts;
    },
    [getAssignmentsForCell]
  );

  // Calculate utilization
  const getUtilization = useCallback(
    (resourceId: number): number => {
      const resourceAssignments = assignments.filter(
        (a) => a.resourceId === resourceId
      );
      const totalHours = resourceAssignments.reduce((sum, a) => sum + a.hours, 0);
      const maxHours = daysToShow * 8; // 8 hours per day
      return Math.min(100, (totalHours / maxHours) * 100);
    },
    [assignments, daysToShow]
  );

  // Get priority color
  const getPriorityColor = (priority: Assignment['priority']): string => {
    const colors = {
      low: '#10b981',
      normal: '#3b82f6',
      high: '#f59e0b',
      urgent: '#ef4444',
    };
    return colors[priority];
  };

  // Format time
  const formatTime = (date: Date): string => {
    return date.toLocaleTimeString('en-US', {
      hour: 'numeric',
      minute: '2-digit',
      hour12: true,
    });
  };

  // Handle drag start
  const handleDragStart = (assignment: Assignment) => {
    setDraggedAssignment(assignment);
  };

  // Handle drop
  const handleDrop = (resourceId: number, date: Date) => {
    if (!draggedAssignment || !onAssignmentUpdate) return;

    const updatedAssignment: Assignment = {
      ...draggedAssignment,
      resourceId,
      start: new Date(date.setHours(new Date(draggedAssignment.start).getHours())),
      end: new Date(date.setHours(new Date(draggedAssignment.end).getHours())),
    };

    onAssignmentUpdate(updatedAssignment);
    setDraggedAssignment(null);
  };

  return (
    <div className="ict-resource-scheduler">
      {/* Header */}
      <div className="ict-scheduler-header">
        <div className="ict-scheduler-title">
          <h3>Resource Scheduler</h3>
          <span className="ict-scheduler-date-range">
            {dateRange[0]?.toLocaleDateString('en-US', { month: 'short', day: 'numeric' })}
            {' - '}
            {dateRange[dateRange.length - 1]?.toLocaleDateString('en-US', {
              month: 'short',
              day: 'numeric',
              year: 'numeric',
            })}
          </span>
        </div>

        <div className="ict-scheduler-controls">
          <select
            value={filterType}
            onChange={(e) => setFilterType(e.target.value)}
            className="ict-scheduler-filter"
          >
            <option value="all">All Resources</option>
            <option value="technician">Technicians</option>
            <option value="equipment">Equipment</option>
            <option value="vehicle">Vehicles</option>
          </select>

          <div className="ict-scheduler-view-toggle">
            <button
              className={viewMode === 'week' ? 'active' : ''}
              onClick={() => setViewMode('week')}
            >
              Week
            </button>
            <button
              className={viewMode === 'day' ? 'active' : ''}
              onClick={() => setViewMode('day')}
            >
              Day
            </button>
          </div>

          <label className="ict-scheduler-conflict-toggle">
            <input
              type="checkbox"
              checked={showConflicts}
              onChange={(e) => setShowConflicts(e.target.checked)}
            />
            Show Conflicts
          </label>
        </div>
      </div>

      {/* Grid */}
      <div className="ict-scheduler-grid">
        {/* Column headers (dates) */}
        <div className="ict-scheduler-row header">
          <div className="ict-scheduler-resource-cell header">Resource</div>
          {dateRange.map((date) => {
            const isToday = date.toDateString() === new Date().toDateString();
            const isWeekend = date.getDay() === 0 || date.getDay() === 6;

            return (
              <div
                key={date.toISOString()}
                className={`ict-scheduler-date-cell ${isToday ? 'today' : ''} ${
                  isWeekend ? 'weekend' : ''
                }`}
              >
                <span className="ict-scheduler-day-name">
                  {date.toLocaleDateString('en-US', { weekday: 'short' })}
                </span>
                <span className="ict-scheduler-day-num">{date.getDate()}</span>
              </div>
            );
          })}
        </div>

        {/* Resource rows */}
        {filteredResources.map((resource) => {
          const utilization = getUtilization(resource.id);

          return (
            <div key={resource.id} className="ict-scheduler-row">
              {/* Resource info */}
              <div
                className="ict-scheduler-resource-cell"
                onClick={() => setSelectedResource(resource)}
              >
                <div className="ict-scheduler-resource-info">
                  <div className="ict-scheduler-resource-avatar">
                    {resource.avatar ? (
                      <img src={resource.avatar} alt={resource.name} />
                    ) : (
                      <span>{resource.name.charAt(0)}</span>
                    )}
                    <span className={`ict-scheduler-status ${resource.available ? 'available' : 'busy'}`} />
                  </div>
                  <div className="ict-scheduler-resource-details">
                    <span className="ict-scheduler-resource-name">{resource.name}</span>
                    <span className="ict-scheduler-resource-type">{resource.type}</span>
                  </div>
                </div>
                <div className="ict-scheduler-utilization">
                  <div
                    className="ict-scheduler-utilization-bar"
                    style={{
                      width: `${utilization}%`,
                      background:
                        utilization > 90
                          ? '#ef4444'
                          : utilization > 70
                          ? '#f59e0b'
                          : '#10b981',
                    }}
                  />
                  <span>{Math.round(utilization)}%</span>
                </div>
              </div>

              {/* Assignment cells */}
              {dateRange.map((date) => {
                const cellAssignments = getAssignmentsForCell(resource.id, date);
                const conflicts = showConflicts
                  ? detectConflicts(resource.id, date)
                  : [];
                const isWeekend = date.getDay() === 0 || date.getDay() === 6;

                return (
                  <div
                    key={`${resource.id}-${date.toISOString()}`}
                    className={`ict-scheduler-assignment-cell ${isWeekend ? 'weekend' : ''}`}
                    onDragOver={(e) => e.preventDefault()}
                    onDrop={() => handleDrop(resource.id, date)}
                    onClick={() => setSelectedDate(date)}
                  >
                    {cellAssignments.map((assignment) => {
                      const isConflict = conflicts.includes(assignment);

                      return (
                        <div
                          key={assignment.id}
                          className={`ict-scheduler-assignment ${isConflict ? 'conflict' : ''}`}
                          style={{ borderLeftColor: getPriorityColor(assignment.priority) }}
                          draggable
                          onDragStart={() => handleDragStart(assignment)}
                        >
                          <span className="ict-scheduler-assignment-name">
                            {assignment.projectName}
                          </span>
                          <span className="ict-scheduler-assignment-time">
                            {formatTime(new Date(assignment.start))} - {formatTime(new Date(assignment.end))}
                          </span>
                          {isConflict && (
                            <span className="ict-scheduler-conflict-badge">!</span>
                          )}
                        </div>
                      );
                    })}

                    {cellAssignments.length === 0 && (
                      <div className="ict-scheduler-empty-cell">
                        <span>+</span>
                      </div>
                    )}
                  </div>
                );
              })}
            </div>
          );
        })}
      </div>

      {/* Legend */}
      <div className="ict-scheduler-legend">
        <div className="ict-scheduler-legend-item">
          <span className="ict-scheduler-legend-color" style={{ background: '#10b981' }} />
          Low Priority
        </div>
        <div className="ict-scheduler-legend-item">
          <span className="ict-scheduler-legend-color" style={{ background: '#3b82f6' }} />
          Normal
        </div>
        <div className="ict-scheduler-legend-item">
          <span className="ict-scheduler-legend-color" style={{ background: '#f59e0b' }} />
          High
        </div>
        <div className="ict-scheduler-legend-item">
          <span className="ict-scheduler-legend-color" style={{ background: '#ef4444' }} />
          Urgent
        </div>
        <div className="ict-scheduler-legend-item">
          <span className="ict-scheduler-legend-conflict" />
          Conflict
        </div>
      </div>

      <style>{`
        .ict-resource-scheduler {
          background: var(--ict-bg-color, #fff);
          border: 1px solid var(--ict-border-color, #e5e7eb);
          border-radius: 12px;
          overflow: hidden;
        }

        .ict-scheduler-header {
          display: flex;
          justify-content: space-between;
          align-items: center;
          padding: 16px 20px;
          border-bottom: 1px solid var(--ict-border-color, #e5e7eb);
          background: var(--ict-bg-secondary, #f9fafb);
        }

        .ict-scheduler-title h3 {
          margin: 0;
          font-size: 16px;
          font-weight: 600;
          color: var(--ict-text-color, #1f2937);
        }

        .ict-scheduler-date-range {
          font-size: 13px;
          color: var(--ict-text-muted, #6b7280);
        }

        .ict-scheduler-controls {
          display: flex;
          align-items: center;
          gap: 12px;
        }

        .ict-scheduler-filter {
          padding: 8px 12px;
          font-size: 13px;
          border: 1px solid var(--ict-border-color, #e5e7eb);
          border-radius: 6px;
          background: var(--ict-bg-color, #fff);
          color: var(--ict-text-color, #1f2937);
        }

        .ict-scheduler-view-toggle {
          display: flex;
          border: 1px solid var(--ict-border-color, #e5e7eb);
          border-radius: 6px;
          overflow: hidden;
        }

        .ict-scheduler-view-toggle button {
          padding: 8px 16px;
          font-size: 13px;
          border: none;
          background: var(--ict-bg-color, #fff);
          color: var(--ict-text-color, #1f2937);
          cursor: pointer;
          transition: all 0.2s;
        }

        .ict-scheduler-view-toggle button:not(:last-child) {
          border-right: 1px solid var(--ict-border-color, #e5e7eb);
        }

        .ict-scheduler-view-toggle button.active {
          background: var(--ict-primary, #3b82f6);
          color: #fff;
        }

        .ict-scheduler-conflict-toggle {
          display: flex;
          align-items: center;
          gap: 6px;
          font-size: 13px;
          color: var(--ict-text-secondary, #4b5563);
          cursor: pointer;
        }

        .ict-scheduler-grid {
          overflow-x: auto;
        }

        .ict-scheduler-row {
          display: flex;
          border-bottom: 1px solid var(--ict-border-light, #f3f4f6);
        }

        .ict-scheduler-row.header {
          background: var(--ict-bg-secondary, #f9fafb);
          position: sticky;
          top: 0;
          z-index: 10;
        }

        .ict-scheduler-resource-cell {
          width: 200px;
          min-width: 200px;
          padding: 12px 16px;
          border-right: 1px solid var(--ict-border-color, #e5e7eb);
        }

        .ict-scheduler-resource-cell.header {
          font-weight: 600;
          font-size: 12px;
          text-transform: uppercase;
          color: var(--ict-text-muted, #6b7280);
        }

        .ict-scheduler-resource-info {
          display: flex;
          align-items: center;
          gap: 10px;
          cursor: pointer;
        }

        .ict-scheduler-resource-avatar {
          position: relative;
          width: 36px;
          height: 36px;
          border-radius: 50%;
          background: var(--ict-primary, #3b82f6);
          display: flex;
          align-items: center;
          justify-content: center;
          color: #fff;
          font-weight: 600;
          font-size: 14px;
          overflow: hidden;
        }

        .ict-scheduler-resource-avatar img {
          width: 100%;
          height: 100%;
          object-fit: cover;
        }

        .ict-scheduler-status {
          position: absolute;
          bottom: 0;
          right: 0;
          width: 10px;
          height: 10px;
          border-radius: 50%;
          border: 2px solid var(--ict-bg-color, #fff);
        }

        .ict-scheduler-status.available {
          background: #10b981;
        }

        .ict-scheduler-status.busy {
          background: #ef4444;
        }

        .ict-scheduler-resource-details {
          display: flex;
          flex-direction: column;
        }

        .ict-scheduler-resource-name {
          font-size: 14px;
          font-weight: 500;
          color: var(--ict-text-color, #1f2937);
        }

        .ict-scheduler-resource-type {
          font-size: 12px;
          color: var(--ict-text-muted, #6b7280);
          text-transform: capitalize;
        }

        .ict-scheduler-utilization {
          display: flex;
          align-items: center;
          gap: 8px;
          margin-top: 8px;
        }

        .ict-scheduler-utilization-bar {
          flex: 1;
          height: 4px;
          border-radius: 2px;
          transition: width 0.3s;
        }

        .ict-scheduler-utilization span {
          font-size: 11px;
          color: var(--ict-text-muted, #6b7280);
        }

        .ict-scheduler-date-cell {
          flex: 1;
          min-width: 100px;
          padding: 8px;
          text-align: center;
          border-right: 1px solid var(--ict-border-light, #f3f4f6);
        }

        .ict-scheduler-date-cell.today {
          background: rgba(59, 130, 246, 0.1);
        }

        .ict-scheduler-date-cell.weekend {
          background: var(--ict-bg-weekend, #f9fafb);
        }

        .ict-scheduler-day-name {
          display: block;
          font-size: 11px;
          text-transform: uppercase;
          color: var(--ict-text-muted, #6b7280);
        }

        .ict-scheduler-day-num {
          display: block;
          font-size: 16px;
          font-weight: 600;
          color: var(--ict-text-color, #1f2937);
        }

        .ict-scheduler-assignment-cell {
          flex: 1;
          min-width: 100px;
          min-height: 80px;
          padding: 4px;
          border-right: 1px solid var(--ict-border-light, #f3f4f6);
          display: flex;
          flex-direction: column;
          gap: 4px;
        }

        .ict-scheduler-assignment-cell.weekend {
          background: var(--ict-bg-weekend, rgba(0, 0, 0, 0.02));
        }

        .ict-scheduler-assignment {
          padding: 6px 8px;
          background: var(--ict-bg-secondary, #f3f4f6);
          border-radius: 4px;
          border-left: 3px solid;
          cursor: grab;
          position: relative;
          transition: transform 0.2s, box-shadow 0.2s;
        }

        .ict-scheduler-assignment:hover {
          transform: scale(1.02);
          box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }

        .ict-scheduler-assignment.conflict {
          background: #fef2f2;
          animation: pulse 2s infinite;
        }

        @keyframes pulse {
          0%, 100% { opacity: 1; }
          50% { opacity: 0.7; }
        }

        .ict-scheduler-assignment-name {
          display: block;
          font-size: 12px;
          font-weight: 500;
          color: var(--ict-text-color, #1f2937);
          white-space: nowrap;
          overflow: hidden;
          text-overflow: ellipsis;
        }

        .ict-scheduler-assignment-time {
          display: block;
          font-size: 10px;
          color: var(--ict-text-muted, #6b7280);
        }

        .ict-scheduler-conflict-badge {
          position: absolute;
          top: -4px;
          right: -4px;
          width: 16px;
          height: 16px;
          background: #ef4444;
          color: #fff;
          border-radius: 50%;
          font-size: 10px;
          font-weight: 700;
          display: flex;
          align-items: center;
          justify-content: center;
        }

        .ict-scheduler-empty-cell {
          flex: 1;
          display: flex;
          align-items: center;
          justify-content: center;
          color: var(--ict-border-color, #e5e7eb);
          font-size: 20px;
          cursor: pointer;
          border-radius: 4px;
          border: 1px dashed transparent;
          transition: all 0.2s;
        }

        .ict-scheduler-empty-cell:hover {
          border-color: var(--ict-primary, #3b82f6);
          color: var(--ict-primary, #3b82f6);
          background: rgba(59, 130, 246, 0.05);
        }

        .ict-scheduler-legend {
          display: flex;
          gap: 20px;
          padding: 12px 20px;
          border-top: 1px solid var(--ict-border-color, #e5e7eb);
          background: var(--ict-bg-secondary, #f9fafb);
        }

        .ict-scheduler-legend-item {
          display: flex;
          align-items: center;
          gap: 6px;
          font-size: 12px;
          color: var(--ict-text-muted, #6b7280);
        }

        .ict-scheduler-legend-color {
          width: 12px;
          height: 12px;
          border-radius: 2px;
        }

        .ict-scheduler-legend-conflict {
          width: 12px;
          height: 12px;
          border-radius: 2px;
          background: #fef2f2;
          border: 1px solid #ef4444;
        }
      `}</style>
    </div>
  );
};

export default ResourceScheduler;
