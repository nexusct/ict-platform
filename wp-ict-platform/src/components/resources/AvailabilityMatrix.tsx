/**
 * AvailabilityMatrix Component
 *
 * Heatmap-style grid showing resource availability across dates
 * Features:
 * - Visual heatmap with color coding based on availability percentage
 * - Date range selector
 * - Resource filtering by type
 * - Hover tooltips with detailed info
 * - Click to view/edit allocations
 *
 * @package ICT_Platform
 * @since   1.0.0
 */

import React, { useEffect, useState } from 'react';
import { useAppDispatch, useAppSelector } from '../../store/hooks';
import {
  fetchResourceAvailability,
  selectResourceAvailability,
  selectResourcesLoading,
  selectResourcesError,
} from '../../store/slices/resourcesSlice';
import { ResourceType, ResourceAvailability } from '../../types';

interface AvailabilityMatrixProps {
  resourceType?: ResourceType;
  dateFrom?: string;
  dateTo?: string;
  onCellClick?: (resourceId: number, date: string) => void;
}

const AvailabilityMatrix: React.FC<AvailabilityMatrixProps> = ({
  resourceType = 'technician',
  dateFrom,
  dateTo,
  onCellClick,
}) => {
  const dispatch = useAppDispatch();

  const availability = useAppSelector(selectResourceAvailability);
  const loading = useAppSelector(selectResourcesLoading);
  const error = useAppSelector(selectResourcesError);

  const [dateRange, setDateRange] = useState({
    from: dateFrom || new Date().toISOString().split('T')[0],
    to: dateTo || new Date(Date.now() + 14 * 24 * 60 * 60 * 1000).toISOString().split('T')[0],
  });

  const [selectedResourceType, setSelectedResourceType] = useState<ResourceType>(resourceType);

  // Fetch availability data when component mounts or filters change
  useEffect(() => {
    dispatch(
      fetchResourceAvailability({
        resource_type: selectedResourceType,
        date_from: dateRange.from,
        date_to: dateRange.to,
      })
    );
  }, [dispatch, selectedResourceType, dateRange.from, dateRange.to]);

  // Generate date range array
  const generateDateRange = (): string[] => {
    const dates: string[] = [];
    const start = new Date(dateRange.from);
    const end = new Date(dateRange.to);

    for (let d = new Date(start); d <= end; d.setDate(d.getDate() + 1)) {
      dates.push(new Date(d).toISOString().split('T')[0]);
    }

    return dates;
  };

  // Group availability by resource
  const groupByResource = (): Map<number, Map<string, ResourceAvailability>> => {
    const grouped = new Map<number, Map<string, ResourceAvailability>>();

    availability.forEach((item) => {
      if (!grouped.has(item.resource_id)) {
        grouped.set(item.resource_id, new Map());
      }
      grouped.get(item.resource_id)!.set(item.date, item);
    });

    return grouped;
  };

  // Get availability color based on percentage
  const getAvailabilityColor = (percentage: number): string => {
    if (percentage >= 80) return 'ict-availability-matrix__cell--high';
    if (percentage >= 50) return 'ict-availability-matrix__cell--medium';
    if (percentage >= 20) return 'ict-availability-matrix__cell--low';
    return 'ict-availability-matrix__cell--none';
  };

  // Handle cell click
  const handleCellClick = (resourceId: number, date: string) => {
    if (onCellClick) {
      onCellClick(resourceId, date);
    }
  };

  // Handle date range change
  const handleDateRangeChange = (field: 'from' | 'to', value: string) => {
    setDateRange((prev) => ({
      ...prev,
      [field]: value,
    }));
  };

  // Handle quick date range presets
  const handlePreset = (days: number) => {
    const today = new Date();
    const endDate = new Date(today.getTime() + days * 24 * 60 * 60 * 1000);

    setDateRange({
      from: today.toISOString().split('T')[0],
      to: endDate.toISOString().split('T')[0],
    });
  };

  const dates = generateDateRange();
  const groupedData = groupByResource();
  const resourceIds = Array.from(groupedData.keys());

  return (
    <div className="ict-availability-matrix">
      {/* Header */}
      <div className="ict-availability-matrix__header">
        <h2 className="ict-availability-matrix__title">Resource Availability Matrix</h2>

        <div className="ict-availability-matrix__controls">
          {/* Resource type selector */}
          <div className="ict-availability-matrix__control-group">
            <label>Resource Type:</label>
            <select
              value={selectedResourceType}
              onChange={(e) => setSelectedResourceType(e.target.value as ResourceType)}
              className="ict-select"
            >
              <option value="technician">Technicians</option>
              <option value="equipment">Equipment</option>
              <option value="vehicle">Vehicles</option>
            </select>
          </div>

          {/* Date range */}
          <div className="ict-availability-matrix__control-group">
            <label>From:</label>
            <input
              type="date"
              value={dateRange.from}
              onChange={(e) => handleDateRangeChange('from', e.target.value)}
              className="ict-input"
            />
          </div>

          <div className="ict-availability-matrix__control-group">
            <label>To:</label>
            <input
              type="date"
              value={dateRange.to}
              onChange={(e) => handleDateRangeChange('to', e.target.value)}
              className="ict-input"
            />
          </div>

          {/* Quick presets */}
          <div className="ict-availability-matrix__presets">
            <button
              className="ict-button ict-button--small ict-button--secondary"
              onClick={() => handlePreset(7)}
            >
              Next 7 Days
            </button>
            <button
              className="ict-button ict-button--small ict-button--secondary"
              onClick={() => handlePreset(14)}
            >
              Next 2 Weeks
            </button>
            <button
              className="ict-button ict-button--small ict-button--secondary"
              onClick={() => handlePreset(30)}
            >
              Next 30 Days
            </button>
          </div>
        </div>
      </div>

      {/* Error message */}
      {error && (
        <div className="ict-notice ict-notice--error">
          <p>Error loading availability: {error}</p>
        </div>
      )}

      {/* Loading indicator */}
      {loading && (
        <div className="ict-availability-matrix__loading">
          <div className="ict-spinner"></div>
          <p>Loading availability data...</p>
        </div>
      )}

      {/* Matrix grid */}
      {!loading && !error && (
        <div className="ict-availability-matrix__grid-wrapper">
          <table className="ict-availability-matrix__table">
            <thead>
              <tr>
                <th className="ict-availability-matrix__header-cell ict-availability-matrix__header-cell--resource">
                  Resource
                </th>
                {dates.map((date) => {
                  const dateObj = new Date(date);
                  const dayName = dateObj.toLocaleDateString('en-US', { weekday: 'short' });
                  const dayNum = dateObj.getDate();

                  return (
                    <th
                      key={date}
                      className="ict-availability-matrix__header-cell ict-availability-matrix__header-cell--date"
                    >
                      <div className="ict-availability-matrix__date-label">
                        <div className="ict-availability-matrix__day-name">{dayName}</div>
                        <div className="ict-availability-matrix__day-number">{dayNum}</div>
                      </div>
                    </th>
                  );
                })}
              </tr>
            </thead>
            <tbody>
              {resourceIds.length === 0 ? (
                <tr>
                  <td colSpan={dates.length + 1} className="ict-availability-matrix__no-data">
                    No {selectedResourceType}s found
                  </td>
                </tr>
              ) : (
                resourceIds.map((resourceId) => {
                  const resourceData = groupedData.get(resourceId)!;

                  return (
                    <tr key={resourceId} className="ict-availability-matrix__row">
                      <td className="ict-availability-matrix__cell ict-availability-matrix__cell--resource">
                        <div className="ict-availability-matrix__resource-info">
                          <span className="ict-availability-matrix__resource-id">
                            {selectedResourceType} #{resourceId}
                          </span>
                        </div>
                      </td>
                      {dates.map((date) => {
                        const dayData = resourceData.get(date);

                        if (!dayData) {
                          return (
                            <td
                              key={date}
                              className="ict-availability-matrix__cell ict-availability-matrix__cell--empty"
                              onClick={() => handleCellClick(resourceId, date)}
                            >
                              <div className="ict-availability-matrix__cell-content">
                                <span className="ict-availability-matrix__percentage">-</span>
                              </div>
                            </td>
                          );
                        }

                        const colorClass = getAvailabilityColor(dayData.availability_percentage);

                        return (
                          <td
                            key={date}
                            className={`ict-availability-matrix__cell ${colorClass}`}
                            onClick={() => handleCellClick(resourceId, date)}
                            title={`Available: ${dayData.available_hours.toFixed(1)}h / ${(dayData.available_hours + dayData.allocated_hours).toFixed(1)}h\nAllocated: ${dayData.allocated_hours.toFixed(1)}h\nAvailability: ${dayData.availability_percentage.toFixed(0)}%`}
                          >
                            <div className="ict-availability-matrix__cell-content">
                              <span className="ict-availability-matrix__percentage">
                                {dayData.availability_percentage.toFixed(0)}%
                              </span>
                              <span className="ict-availability-matrix__hours">
                                {dayData.available_hours.toFixed(1)}h
                              </span>
                            </div>
                          </td>
                        );
                      })}
                    </tr>
                  );
                })
              )}
            </tbody>
          </table>
        </div>
      )}

      {/* Legend */}
      <div className="ict-availability-matrix__legend">
        <h4>Availability Legend</h4>
        <div className="ict-availability-matrix__legend-items">
          <div className="ict-availability-matrix__legend-item">
            <span className="ict-availability-matrix__legend-color ict-availability-matrix__legend-color--high"></span>
            <span>80-100% Available</span>
          </div>
          <div className="ict-availability-matrix__legend-item">
            <span className="ict-availability-matrix__legend-color ict-availability-matrix__legend-color--medium"></span>
            <span>50-79% Available</span>
          </div>
          <div className="ict-availability-matrix__legend-item">
            <span className="ict-availability-matrix__legend-color ict-availability-matrix__legend-color--low"></span>
            <span>20-49% Available</span>
          </div>
          <div className="ict-availability-matrix__legend-item">
            <span className="ict-availability-matrix__legend-color ict-availability-matrix__legend-color--none"></span>
            <span>0-19% Available</span>
          </div>
        </div>
      </div>

      {/* Summary */}
      {!loading && resourceIds.length > 0 && (
        <div className="ict-availability-matrix__summary">
          <p>
            Showing availability for <strong>{resourceIds.length}</strong> {selectedResourceType}
            {resourceIds.length !== 1 ? 's' : ''} across <strong>{dates.length}</strong> days
          </p>
        </div>
      )}
    </div>
  );
};

export default AvailabilityMatrix;
