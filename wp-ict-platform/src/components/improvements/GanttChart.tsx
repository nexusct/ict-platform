/**
 * Gantt Chart Component
 *
 * Visual timeline with dependencies and milestones for project management.
 *
 * @package ICT_Platform
 * @since   2.1.0
 */

import React, { useState, useRef, useMemo } from 'react';
import type { GanttTask } from '../../types';

interface GanttChartProps {
  tasks: GanttTask[];
  startDate?: Date;
  endDate?: Date;
  onTaskClick?: (task: GanttTask) => void;
  onTaskUpdate?: (task: GanttTask) => void;
  viewMode?: 'day' | 'week' | 'month';
  showDependencies?: boolean;
}

interface DateRange {
  start: Date;
  end: Date;
  days: number;
}

const GanttChart: React.FC<GanttChartProps> = ({
  tasks,
  startDate,
  endDate,
  onTaskClick,
  onTaskUpdate: _onTaskUpdate,
  viewMode = 'week',
  showDependencies = true,
}) => {
  const [hoveredTask, setHoveredTask] = useState<string | null>(null);
  const containerRef = useRef<HTMLDivElement>(null);
  const [_scrollLeft, _setScrollLeft] = useState(0);

  // Calculate date range
  const dateRange = useMemo<DateRange>(() => {
    const allDates = tasks.flatMap((t) => [new Date(t.start), new Date(t.end)]);
    const minDate = startDate || new Date(Math.min(...allDates.map((d) => d.getTime())));
    const maxDate = endDate || new Date(Math.max(...allDates.map((d) => d.getTime())));

    // Add padding
    minDate.setDate(minDate.getDate() - 7);
    maxDate.setDate(maxDate.getDate() + 7);

    const days = Math.ceil((maxDate.getTime() - minDate.getTime()) / (1000 * 60 * 60 * 24));

    return { start: minDate, end: maxDate, days };
  }, [tasks, startDate, endDate]);

  // Calculate cell width based on view mode
  const cellWidth = useMemo(() => {
    switch (viewMode) {
      case 'day':
        return 40;
      case 'week':
        return 20;
      case 'month':
        return 6;
      default:
        return 20;
    }
  }, [viewMode]);

  // Generate date headers
  const dateHeaders = useMemo(() => {
    const headers: { date: Date; label: string; isWeekend: boolean }[] = [];
    const current = new Date(dateRange.start);

    while (current <= dateRange.end) {
      const dayOfWeek = current.getDay();
      headers.push({
        date: new Date(current),
        label: formatDateLabel(current, viewMode),
        isWeekend: dayOfWeek === 0 || dayOfWeek === 6,
      });
      current.setDate(current.getDate() + 1);
    }

    return headers;
  }, [dateRange, viewMode]);

  // Calculate task position
  const getTaskPosition = (task: GanttTask) => {
    const taskStart = new Date(task.start);
    const taskEnd = new Date(task.end);
    const startOffset = Math.floor(
      (taskStart.getTime() - dateRange.start.getTime()) / (1000 * 60 * 60 * 24)
    );
    const duration = Math.ceil(
      (taskEnd.getTime() - taskStart.getTime()) / (1000 * 60 * 60 * 24)
    );

    return {
      left: startOffset * cellWidth,
      width: Math.max(duration * cellWidth, cellWidth),
    };
  };

  // Get task color
  const getTaskColor = (task: GanttTask) => {
    if (task.color) return task.color;
    if (task.type === 'milestone') return '#f59e0b';
    if (task.progress === 100) return '#10b981';
    if (task.progress > 0) return '#3b82f6';
    return '#6b7280';
  };

  // Draw dependency lines
  const renderDependencies = () => {
    if (!showDependencies) return null;

    return tasks
      .filter((task) => task.dependencies && task.dependencies.length > 0)
      .flatMap((task) =>
        task.dependencies!.map((depId) => {
          const depTask = tasks.find((t) => t.id === depId);
          if (!depTask) return null;

          const depPos = getTaskPosition(depTask);
          const taskPos = getTaskPosition(task);
          const depIndex = tasks.findIndex((t) => t.id === depId);
          const taskIndex = tasks.findIndex((t) => t.id === task.id);

          const startX = depPos.left + depPos.width;
          const startY = depIndex * 40 + 20;
          const endX = taskPos.left;
          const endY = taskIndex * 40 + 20;

          const midX = startX + (endX - startX) / 2;

          return (
            <path
              key={`${depId}-${task.id}`}
              d={`M ${startX} ${startY}
                  C ${midX} ${startY}, ${midX} ${endY}, ${endX} ${endY}`}
              fill="none"
              stroke="#94a3b8"
              strokeWidth="1.5"
              strokeDasharray="4,2"
              markerEnd="url(#arrowhead)"
            />
          );
        })
      );
  };

  return (
    <div className="ict-gantt-chart" ref={containerRef}>
      {/* Header */}
      <div className="ict-gantt-header">
        <div className="ict-gantt-task-list-header">Task Name</div>
        <div
          className="ict-gantt-timeline-header"
          style={{ width: dateRange.days * cellWidth }}
        >
          {dateHeaders.map((header, index) => (
            <div
              key={index}
              className={`ict-gantt-date-cell ${header.isWeekend ? 'weekend' : ''}`}
              style={{ width: cellWidth }}
            >
              {index % (viewMode === 'day' ? 1 : viewMode === 'week' ? 7 : 30) === 0 && (
                <span>{header.label}</span>
              )}
            </div>
          ))}
        </div>
      </div>

      {/* Body */}
      <div className="ict-gantt-body">
        {/* Task list */}
        <div className="ict-gantt-task-list">
          {tasks.map((task) => (
            <div
              key={task.id}
              className={`ict-gantt-task-row ${hoveredTask === task.id ? 'hovered' : ''}`}
              onClick={() => onTaskClick?.(task)}
              onMouseEnter={() => setHoveredTask(task.id)}
              onMouseLeave={() => setHoveredTask(null)}
            >
              <span
                className="ict-gantt-task-name"
                style={{ paddingLeft: task.parent ? 20 : 0 }}
              >
                {task.type === 'milestone' && <span className="milestone-icon">&#9670;</span>}
                {task.name}
              </span>
            </div>
          ))}
        </div>

        {/* Timeline */}
        <div
          className="ict-gantt-timeline"
          style={{ width: dateRange.days * cellWidth }}
          onScroll={(e) => _setScrollLeft(e.currentTarget.scrollLeft)}
        >
          {/* Grid lines */}
          <div className="ict-gantt-grid">
            {dateHeaders.map((header, index) => (
              <div
                key={index}
                className={`ict-gantt-grid-cell ${header.isWeekend ? 'weekend' : ''}`}
                style={{ width: cellWidth }}
              />
            ))}
          </div>

          {/* Today line */}
          {(() => {
            const today = new Date();
            if (today >= dateRange.start && today <= dateRange.end) {
              const offset = Math.floor(
                (today.getTime() - dateRange.start.getTime()) / (1000 * 60 * 60 * 24)
              );
              return (
                <div
                  className="ict-gantt-today-line"
                  style={{ left: offset * cellWidth }}
                />
              );
            }
            return null;
          })()}

          {/* Dependency lines */}
          <svg className="ict-gantt-dependencies" width="100%" height={tasks.length * 40}>
            <defs>
              <marker
                id="arrowhead"
                markerWidth="10"
                markerHeight="7"
                refX="9"
                refY="3.5"
                orient="auto"
              >
                <polygon points="0 0, 10 3.5, 0 7" fill="#94a3b8" />
              </marker>
            </defs>
            {renderDependencies()}
          </svg>

          {/* Task bars */}
          {tasks.map((task, index) => {
            const position = getTaskPosition(task);
            const color = getTaskColor(task);

            return (
              <div
                key={task.id}
                className={`ict-gantt-task-bar ${task.type} ${
                  hoveredTask === task.id ? 'hovered' : ''
                }`}
                style={{
                  left: position.left,
                  width: position.width,
                  top: index * 40 + 8,
                  backgroundColor: color,
                }}
                onClick={() => onTaskClick?.(task)}
                onMouseEnter={() => setHoveredTask(task.id)}
                onMouseLeave={() => setHoveredTask(null)}
              >
                {task.type !== 'milestone' && (
                  <>
                    <div
                      className="ict-gantt-progress"
                      style={{
                        width: `${task.progress}%`,
                        backgroundColor: adjustColor(color, -20),
                      }}
                    />
                    {position.width > 60 && (
                      <span className="ict-gantt-task-label">{task.name}</span>
                    )}
                  </>
                )}
              </div>
            );
          })}
        </div>
      </div>

      <style>{`
        .ict-gantt-chart {
          display: flex;
          flex-direction: column;
          border: 1px solid var(--ict-border-color, #e5e7eb);
          border-radius: 8px;
          overflow: hidden;
          background: var(--ict-bg-color, #fff);
        }

        .ict-gantt-header {
          display: flex;
          border-bottom: 1px solid var(--ict-border-color, #e5e7eb);
          background: var(--ict-bg-secondary, #f9fafb);
        }

        .ict-gantt-task-list-header {
          width: 200px;
          min-width: 200px;
          padding: 8px 12px;
          font-weight: 600;
          font-size: 12px;
          color: var(--ict-text-secondary, #6b7280);
          border-right: 1px solid var(--ict-border-color, #e5e7eb);
        }

        .ict-gantt-timeline-header {
          display: flex;
          overflow: hidden;
        }

        .ict-gantt-date-cell {
          flex-shrink: 0;
          text-align: center;
          font-size: 10px;
          padding: 4px 0;
          color: var(--ict-text-secondary, #6b7280);
          border-right: 1px solid var(--ict-border-light, #f3f4f6);
        }

        .ict-gantt-date-cell.weekend {
          background: var(--ict-bg-weekend, #f9fafb);
        }

        .ict-gantt-body {
          display: flex;
          flex: 1;
          overflow: auto;
        }

        .ict-gantt-task-list {
          width: 200px;
          min-width: 200px;
          border-right: 1px solid var(--ict-border-color, #e5e7eb);
        }

        .ict-gantt-task-row {
          height: 40px;
          padding: 0 12px;
          display: flex;
          align-items: center;
          border-bottom: 1px solid var(--ict-border-light, #f3f4f6);
          cursor: pointer;
          transition: background 0.15s;
        }

        .ict-gantt-task-row:hover,
        .ict-gantt-task-row.hovered {
          background: var(--ict-bg-hover, #f3f4f6);
        }

        .ict-gantt-task-name {
          font-size: 13px;
          white-space: nowrap;
          overflow: hidden;
          text-overflow: ellipsis;
        }

        .milestone-icon {
          color: #f59e0b;
          margin-right: 6px;
        }

        .ict-gantt-timeline {
          position: relative;
          overflow-x: auto;
        }

        .ict-gantt-grid {
          display: flex;
          position: absolute;
          top: 0;
          left: 0;
          right: 0;
          bottom: 0;
        }

        .ict-gantt-grid-cell {
          flex-shrink: 0;
          border-right: 1px solid var(--ict-border-light, #f3f4f6);
          height: 100%;
        }

        .ict-gantt-grid-cell.weekend {
          background: var(--ict-bg-weekend, rgba(0, 0, 0, 0.02));
        }

        .ict-gantt-today-line {
          position: absolute;
          top: 0;
          bottom: 0;
          width: 2px;
          background: #ef4444;
          z-index: 10;
        }

        .ict-gantt-dependencies {
          position: absolute;
          top: 0;
          left: 0;
          pointer-events: none;
          z-index: 5;
        }

        .ict-gantt-task-bar {
          position: absolute;
          height: 24px;
          border-radius: 4px;
          cursor: pointer;
          transition: transform 0.15s, box-shadow 0.15s;
          z-index: 10;
          overflow: hidden;
        }

        .ict-gantt-task-bar:hover,
        .ict-gantt-task-bar.hovered {
          transform: scaleY(1.1);
          box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15);
          z-index: 15;
        }

        .ict-gantt-task-bar.milestone {
          width: 16px !important;
          height: 16px;
          transform: rotate(45deg);
          border-radius: 2px;
          margin-top: 4px;
        }

        .ict-gantt-task-bar.milestone:hover {
          transform: rotate(45deg) scale(1.2);
        }

        .ict-gantt-progress {
          position: absolute;
          top: 0;
          left: 0;
          height: 100%;
          border-radius: 4px 0 0 4px;
        }

        .ict-gantt-task-label {
          position: relative;
          z-index: 1;
          padding: 0 8px;
          font-size: 11px;
          color: #fff;
          white-space: nowrap;
          overflow: hidden;
          text-overflow: ellipsis;
          line-height: 24px;
        }
      `}</style>
    </div>
  );
};

// Helper functions
function formatDateLabel(date: Date, viewMode: string): string {
  const options: Intl.DateTimeFormatOptions =
    viewMode === 'day'
      ? { day: 'numeric', month: 'short' }
      : viewMode === 'week'
      ? { day: 'numeric', month: 'short' }
      : { month: 'short', year: '2-digit' };

  return date.toLocaleDateString('en-US', options);
}

function adjustColor(color: string, amount: number): string {
  const hex = color.replace('#', '');
  const num = parseInt(hex, 16);
  const r = Math.max(0, Math.min(255, (num >> 16) + amount));
  const g = Math.max(0, Math.min(255, ((num >> 8) & 0x00ff) + amount));
  const b = Math.max(0, Math.min(255, (num & 0x0000ff) + amount));
  return `#${((1 << 24) + (r << 16) + (g << 8) + b).toString(16).slice(1)}`;
}

export default GanttChart;
