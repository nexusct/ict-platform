/**
 * ResourceCalendar Component
 *
 * Calendar view for resource allocations using FullCalendar
 * Features:
 * - Multiple view types (month, week, day, resource timeline)
 * - Drag and drop allocation editing
 * - Conflict detection
 * - Resource filtering
 * - Color coding by resource type
 *
 * @package ICT_Platform
 * @since   1.0.0
 */

import React, { useEffect, useRef, useState } from 'react';
import { useAppDispatch, useAppSelector } from '../../store/hooks';
import {
  fetchCalendarEvents,
  updateResourceAllocation,
  deleteResourceAllocation,
  checkResourceConflicts,
  clearConflicts,
  selectCalendarEvents,
  selectResourcesLoading,
  selectResourceConflicts,
  selectHasConflicts,
} from '../../store/slices/resourcesSlice';
import { ResourceType, CalendarEvent } from '../../types';
import FullCalendar from '@fullcalendar/react';
import dayGridPlugin from '@fullcalendar/daygrid';
import timeGridPlugin from '@fullcalendar/timegrid';
import resourceTimelinePlugin from '@fullcalendar/resource-timeline';
import interactionPlugin from '@fullcalendar/interaction';
import type { EventClickArg, EventDropArg, DateSelectArg, EventContentArg } from '@fullcalendar/core';

interface ResourceCalendarProps {
  projectId?: number;
  resourceType?: ResourceType;
  resourceId?: number;
  editable?: boolean;
  onEventClick?: (eventId: number) => void;
  onEventCreate?: (start: string, end: string, resourceId?: number) => void;
}

const ResourceCalendar: React.FC<ResourceCalendarProps> = ({
  projectId,
  resourceType,
  resourceId,
  editable = false,
  onEventClick,
  onEventCreate,
}) => {
  const dispatch = useAppDispatch();
  const calendarRef = useRef<FullCalendar>(null);

  const events = useAppSelector(selectCalendarEvents);
  const loading = useAppSelector(selectResourcesLoading);
  const conflicts = useAppSelector(selectResourceConflicts);
  const hasConflicts = useAppSelector(selectHasConflicts);

  const [selectedView, setSelectedView] = useState<string>('dayGridMonth');
  const [showFilters, setShowFilters] = useState(false);
  const [filters, setFilters] = useState({
    resourceType: resourceType || undefined,
    resourceId: resourceId || undefined,
  });

  // Fetch calendar events when date range changes
  useEffect(() => {
    if (calendarRef.current) {
      const calendarApi = calendarRef.current.getApi();
      const view = calendarApi.view;

      fetchEvents(view.activeStart.toISOString(), view.activeEnd.toISOString());
    }
  }, [filters.resourceType, filters.resourceId]);

  const fetchEvents = (start: string, end: string) => {
    dispatch(
      fetchCalendarEvents({
        start,
        end,
        resource_type: filters.resourceType,
        resource_id: filters.resourceId,
      })
    );
  };

  // Handle date range change
  const handleDatesSet = (dateInfo: any) => {
    fetchEvents(dateInfo.start.toISOString(), dateInfo.end.toISOString());
  };

  // Handle event click
  const handleEventClick = (clickInfo: EventClickArg) => {
    if (onEventClick) {
      onEventClick(Number(clickInfo.event.id));
    }
  };

  // Handle event drag and drop
  const handleEventDrop = async (dropInfo: EventDropArg) => {
    const eventId = Number(dropInfo.event.id);
    const newStart = dropInfo.event.start!.toISOString();
    const newEnd = dropInfo.event.end!.toISOString();

    // Check for conflicts
    const resourceType = dropInfo.event.extendedProps.resourceType;
    const resourceId = dropInfo.event.extendedProps.resourceId;

    if (resourceType && resourceId) {
      await dispatch(
        checkResourceConflicts({
          resource_type: resourceType,
          resource_id: resourceId,
          allocation_start: newStart,
          allocation_end: newEnd,
          exclude_id: eventId,
        })
      ).unwrap();

      // If conflicts detected, revert the move
      if (hasConflicts) {
        dropInfo.revert();
        return;
      }
    }

    // Update the allocation
    try {
      await dispatch(
        updateResourceAllocation({
          id: eventId,
          data: {
            project_id: dropInfo.event.extendedProps.project_id,
            resource_type: resourceType,
            resource_id: resourceId,
            allocation_start: newStart,
            allocation_end: newEnd,
          },
        })
      ).unwrap();
    } catch (error) {
      dropInfo.revert();
      console.error('Failed to update allocation:', error);
    }
  };

  // Handle event resize
  const handleEventResize = async (resizeInfo: any) => {
    const eventId = Number(resizeInfo.event.id);
    const newStart = resizeInfo.event.start!.toISOString();
    const newEnd = resizeInfo.event.end!.toISOString();

    const resourceType = resizeInfo.event.extendedProps.resourceType;
    const resourceId = resizeInfo.event.extendedProps.resourceId;

    // Check for conflicts
    if (resourceType && resourceId) {
      await dispatch(
        checkResourceConflicts({
          resource_type: resourceType,
          resource_id: resourceId,
          allocation_start: newStart,
          allocation_end: newEnd,
          exclude_id: eventId,
        })
      ).unwrap();

      if (hasConflicts) {
        resizeInfo.revert();
        return;
      }
    }

    // Update the allocation
    try {
      await dispatch(
        updateResourceAllocation({
          id: eventId,
          data: {
            project_id: resizeInfo.event.extendedProps.project_id,
            resource_type: resourceType,
            resource_id: resourceId,
            allocation_start: newStart,
            allocation_end: newEnd,
          },
        })
      ).unwrap();
    } catch (error) {
      resizeInfo.revert();
      console.error('Failed to update allocation:', error);
    }
  };

  // Handle date selection for creating new allocation
  const handleDateSelect = (selectInfo: DateSelectArg) => {
    if (onEventCreate) {
      onEventCreate(
        selectInfo.start.toISOString(),
        selectInfo.end.toISOString(),
        selectInfo.resource?.id ? Number(selectInfo.resource.id) : undefined
      );
    }
  };

  // Custom event content renderer
  const renderEventContent = (eventInfo: EventContentArg) => {
    return (
      <div className="ict-calendar-event">
        <div className="ict-calendar-event__time">
          <b>{eventInfo.timeText}</b>
        </div>
        <div className="ict-calendar-event__title">{eventInfo.event.title}</div>
        {eventInfo.event.extendedProps.status && (
          <span className={`ict-calendar-event__status ict-calendar-event__status--${eventInfo.event.extendedProps.status}`}>
            {eventInfo.event.extendedProps.status}
          </span>
        )}
      </div>
    );
  };

  // Handle view change
  const handleViewChange = (view: string) => {
    if (calendarRef.current) {
      const calendarApi = calendarRef.current.getApi();
      calendarApi.changeView(view);
      setSelectedView(view);
    }
  };

  // Handle filter changes
  const handleFilterChange = (filterType: string, value: any) => {
    setFilters((prev) => ({
      ...prev,
      [filterType]: value || undefined,
    }));
  };

  // Clear all filters
  const handleClearFilters = () => {
    setFilters({
      resourceType: undefined,
      resourceId: undefined,
    });
    dispatch(clearConflicts());
  };

  return (
    <div className="ict-resource-calendar">
      {/* Header */}
      <div className="ict-resource-calendar__header">
        <h2 className="ict-resource-calendar__title">Resource Calendar</h2>

        <div className="ict-resource-calendar__actions">
          {/* View switcher */}
          <div className="ict-resource-calendar__view-switcher">
            <button
              className={`ict-button ict-button--small ${selectedView === 'dayGridMonth' ? 'ict-button--primary' : 'ict-button--secondary'}`}
              onClick={() => handleViewChange('dayGridMonth')}
            >
              Month
            </button>
            <button
              className={`ict-button ict-button--small ${selectedView === 'timeGridWeek' ? 'ict-button--primary' : 'ict-button--secondary'}`}
              onClick={() => handleViewChange('timeGridWeek')}
            >
              Week
            </button>
            <button
              className={`ict-button ict-button--small ${selectedView === 'timeGridDay' ? 'ict-button--primary' : 'ict-button--secondary'}`}
              onClick={() => handleViewChange('timeGridDay')}
            >
              Day
            </button>
            <button
              className={`ict-button ict-button--small ${selectedView === 'resourceTimelineWeek' ? 'ict-button--primary' : 'ict-button--secondary'}`}
              onClick={() => handleViewChange('resourceTimelineWeek')}
            >
              Timeline
            </button>
          </div>

          {/* Filter toggle */}
          <button
            className="ict-button ict-button--small ict-button--secondary"
            onClick={() => setShowFilters(!showFilters)}
          >
            <span className="dashicons dashicons-filter"></span> Filters
          </button>

          {/* Today button */}
          <button
            className="ict-button ict-button--small ict-button--secondary"
            onClick={() => calendarRef.current?.getApi().today()}
          >
            Today
          </button>
        </div>
      </div>

      {/* Filters panel */}
      {showFilters && (
        <div className="ict-resource-calendar__filters">
          <div className="ict-resource-calendar__filter-group">
            <label>Resource Type:</label>
            <select
              value={filters.resourceType || ''}
              onChange={(e) => handleFilterChange('resourceType', e.target.value)}
            >
              <option value="">All Types</option>
              <option value="technician">Technicians</option>
              <option value="equipment">Equipment</option>
              <option value="vehicle">Vehicles</option>
            </select>
          </div>

          <button
            className="ict-button ict-button--small ict-button--secondary"
            onClick={handleClearFilters}
          >
            Clear Filters
          </button>
        </div>
      )}

      {/* Conflict warnings */}
      {hasConflicts && (
        <div className="ict-resource-calendar__conflicts">
          <div className="ict-notice ict-notice--error">
            <p>
              <strong>Conflicts detected:</strong> {conflicts.length} resource allocation
              conflict{conflicts.length > 1 ? 's' : ''} found. Please resolve before saving.
            </p>
            <ul>
              {conflicts.map((conflict, index) => (
                <li key={index}>
                  {conflict.resource_type} #{conflict.resource_id} has overlapping allocation
                  ({conflict.overlap_hours.toFixed(2)} hours overlap)
                </li>
              ))}
            </ul>
            <button
              className="ict-button ict-button--small"
              onClick={() => dispatch(clearConflicts())}
            >
              Dismiss
            </button>
          </div>
        </div>
      )}

      {/* Loading indicator */}
      {loading && (
        <div className="ict-resource-calendar__loading">
          <div className="ict-spinner"></div>
          <p>Loading calendar events...</p>
        </div>
      )}

      {/* FullCalendar */}
      <div className="ict-resource-calendar__calendar">
        <FullCalendar
          ref={calendarRef}
          plugins={[dayGridPlugin, timeGridPlugin, resourceTimelinePlugin, interactionPlugin]}
          initialView={selectedView}
          headerToolbar={{
            left: 'prev,next',
            center: 'title',
            right: '',
          }}
          events={events}
          editable={editable}
          selectable={editable}
          selectMirror={true}
          dayMaxEvents={true}
          weekends={true}
          eventClick={handleEventClick}
          eventDrop={handleEventDrop}
          eventResize={handleEventResize}
          select={handleDateSelect}
          datesSet={handleDatesSet}
          eventContent={renderEventContent}
          height="auto"
          contentHeight="auto"
          aspectRatio={1.8}
          slotMinTime="06:00:00"
          slotMaxTime="20:00:00"
          allDaySlot={false}
          nowIndicator={true}
          eventTimeFormat={{
            hour: '2-digit',
            minute: '2-digit',
            meridiem: false,
          }}
        />
      </div>

      {/* Legend */}
      <div className="ict-resource-calendar__legend">
        <h4>Legend</h4>
        <div className="ict-resource-calendar__legend-items">
          <div className="ict-resource-calendar__legend-item">
            <span className="ict-resource-calendar__legend-color" style={{ backgroundColor: '#3788d8' }}></span>
            <span>Technician</span>
          </div>
          <div className="ict-resource-calendar__legend-item">
            <span className="ict-resource-calendar__legend-color" style={{ backgroundColor: '#f39c12' }}></span>
            <span>Equipment</span>
          </div>
          <div className="ict-resource-calendar__legend-item">
            <span className="ict-resource-calendar__legend-color" style={{ backgroundColor: '#27ae60' }}></span>
            <span>Vehicle</span>
          </div>
        </div>
      </div>
    </div>
  );
};

export default ResourceCalendar;
