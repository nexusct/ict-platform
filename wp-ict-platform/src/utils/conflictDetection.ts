/**
 * Conflict Detection Utility
 *
 * Helper functions for detecting and resolving resource allocation conflicts
 *
 * @package ICT_Platform
 * @since   1.0.0
 */

import { ProjectResource, ResourceConflict, ResourceType } from '../types';

/**
 * Check if two date ranges overlap
 */
export const doDateRangesOverlap = (
  start1: string | Date,
  end1: string | Date,
  start2: string | Date,
  end2: string | Date
): boolean => {
  const s1 = new Date(start1).getTime();
  const e1 = new Date(end1).getTime();
  const s2 = new Date(start2).getTime();
  const e2 = new Date(end2).getTime();

  return s1 < e2 && e1 > s2;
};

/**
 * Calculate overlap hours between two date ranges
 */
export const calculateOverlapHours = (
  start1: string | Date,
  end1: string | Date,
  start2: string | Date,
  end2: string | Date
): number => {
  const s1 = new Date(start1).getTime();
  const e1 = new Date(end1).getTime();
  const s2 = new Date(start2).getTime();
  const e2 = new Date(end2).getTime();

  if (!doDateRangesOverlap(start1, end1, start2, end2)) {
    return 0;
  }

  const overlapStart = Math.max(s1, s2);
  const overlapEnd = Math.min(e1, e2);
  const overlapMs = overlapEnd - overlapStart;

  return overlapMs / (1000 * 60 * 60); // Convert ms to hours
};

/**
 * Check if an allocation conflicts with existing allocations
 */
export const detectConflicts = (
  newAllocation: {
    resource_type: ResourceType;
    resource_id: number;
    allocation_start: string;
    allocation_end: string;
  },
  existingAllocations: ProjectResource[],
  excludeId?: number
): ResourceConflict[] => {
  const conflicts: ResourceConflict[] = [];

  const relevantAllocations = existingAllocations.filter((allocation) => {
    // Skip if this is the same allocation (when editing)
    if (excludeId && allocation.id === excludeId) {
      return false;
    }

    // Only check allocations for the same resource
    return (
      allocation.resource_type === newAllocation.resource_type &&
      allocation.resource_id === newAllocation.resource_id
    );
  });

  relevantAllocations.forEach((existing) => {
    if (
      doDateRangesOverlap(
        newAllocation.allocation_start,
        newAllocation.allocation_end,
        existing.allocation_start,
        existing.allocation_end
      )
    ) {
      const overlapHours = calculateOverlapHours(
        newAllocation.allocation_start,
        newAllocation.allocation_end,
        existing.allocation_start,
        existing.allocation_end
      );

      conflicts.push({
        resource_type: existing.resource_type,
        resource_id: existing.resource_id,
        existing_allocation: existing,
        requested_allocation: {
          allocation_start: newAllocation.allocation_start,
          allocation_end: newAllocation.allocation_end,
        },
        overlap_hours: overlapHours,
      });
    }
  });

  return conflicts;
};

/**
 * Get conflict severity level
 */
export const getConflictSeverity = (
  conflict: ResourceConflict
): 'high' | 'medium' | 'low' => {
  const overlapPercentage =
    (conflict.overlap_hours /
      Math.abs(
        new Date(conflict.existing_allocation.allocation_end).getTime() -
          new Date(conflict.existing_allocation.allocation_start).getTime()
      )) *
    (1000 * 60 * 60) *
    100;

  if (overlapPercentage >= 80) {
    return 'high';
  } else if (overlapPercentage >= 40) {
    return 'medium';
  } else {
    return 'low';
  }
};

/**
 * Get total working hours between two dates (excluding weekends and non-working hours)
 */
export const calculateWorkingHours = (
  start: string | Date,
  end: string | Date,
  workingHoursPerDay: number = 8,
  includeWeekends: boolean = false
): number => {
  const startDate = new Date(start);
  const endDate = new Date(end);

  let totalHours = 0;
  const currentDate = new Date(startDate);

  while (currentDate <= endDate) {
    const dayOfWeek = currentDate.getDay();

    // Skip weekends if not included
    if (!includeWeekends && (dayOfWeek === 0 || dayOfWeek === 6)) {
      currentDate.setDate(currentDate.getDate() + 1);
      continue;
    }

    totalHours += workingHoursPerDay;
    currentDate.setDate(currentDate.getDate() + 1);
  }

  return totalHours;
};

/**
 * Calculate resource availability percentage for a date range
 */
export const calculateAvailabilityPercentage = (
  totalHours: number,
  allocatedHours: number
): number => {
  if (totalHours === 0) return 0;
  return ((totalHours - allocatedHours) / totalHours) * 100;
};

/**
 * Find available time slots for a resource
 */
export const findAvailableSlots = (
  existingAllocations: ProjectResource[],
  searchStart: string,
  searchEnd: string,
  requiredHours: number
): Array<{ start: string; end: string; availableHours: number }> => {
  const slots: Array<{ start: string; end: string; availableHours: number }> = [];

  // Sort allocations by start date
  const sortedAllocations = [...existingAllocations].sort(
    (a, b) =>
      new Date(a.allocation_start).getTime() - new Date(b.allocation_start).getTime()
  );

  let currentStart = new Date(searchStart);
  const searchEndDate = new Date(searchEnd);

  sortedAllocations.forEach((allocation) => {
    const allocStart = new Date(allocation.allocation_start);
    const allocEnd = new Date(allocation.allocation_end);

    // Check gap between current position and this allocation
    if (currentStart < allocStart) {
      const gapHours = calculateWorkingHours(currentStart, allocStart);

      if (gapHours >= requiredHours) {
        slots.push({
          start: currentStart.toISOString(),
          end: allocStart.toISOString(),
          availableHours: gapHours,
        });
      }
    }

    // Move current position to end of this allocation
    if (allocEnd > currentStart) {
      currentStart = new Date(allocEnd);
    }
  });

  // Check remaining gap after last allocation
  if (currentStart < searchEndDate) {
    const gapHours = calculateWorkingHours(currentStart, searchEndDate);

    if (gapHours >= requiredHours) {
      slots.push({
        start: currentStart.toISOString(),
        end: searchEndDate.toISOString(),
        availableHours: gapHours,
      });
    }
  }

  return slots;
};

/**
 * Suggest alternative resources based on availability
 */
export const suggestAlternativeResources = (
  allAllocations: ProjectResource[],
  resourceType: ResourceType,
  requiredStart: string,
  requiredEnd: string,
  excludeResourceIds: number[] = []
): Array<{ resource_id: number; availableHours: number; conflicts: number }> => {
  const suggestions: Map<
    number,
    { resource_id: number; availableHours: number; conflicts: number }
  > = new Map();

  const requiredHours = calculateWorkingHours(requiredStart, requiredEnd);

  // Group allocations by resource
  const allocationsByResource = new Map<number, ProjectResource[]>();
  allAllocations
    .filter((a) => a.resource_type === resourceType)
    .forEach((allocation) => {
      if (!allocationsByResource.has(allocation.resource_id)) {
        allocationsByResource.set(allocation.resource_id, []);
      }
      allocationsByResource.get(allocation.resource_id)!.push(allocation);
    });

  // Calculate availability for each resource
  allocationsByResource.forEach((allocations, resourceId) => {
    if (excludeResourceIds.includes(resourceId)) {
      return;
    }

    const conflicts = detectConflicts(
      {
        resource_type: resourceType,
        resource_id: resourceId,
        allocation_start: requiredStart,
        allocation_end: requiredEnd,
      },
      allocations
    );

    const totalAllocatedHours = conflicts.reduce(
      (sum, c) => sum + c.overlap_hours,
      0
    );
    const availableHours = requiredHours - totalAllocatedHours;

    suggestions.set(resourceId, {
      resource_id: resourceId,
      availableHours: Math.max(0, availableHours),
      conflicts: conflicts.length,
    });
  });

  // Sort by availability (descending)
  return Array.from(suggestions.values()).sort(
    (a, b) => b.availableHours - a.availableHours
  );
};

/**
 * Check if a resource is over-allocated on a specific date
 */
export const isResourceOverAllocated = (
  allocations: ProjectResource[],
  date: string,
  maxHoursPerDay: number = 8
): boolean => {
  const dateObj = new Date(date);
  const startOfDay = new Date(dateObj.setHours(0, 0, 0, 0));
  const endOfDay = new Date(dateObj.setHours(23, 59, 59, 999));

  let totalHours = 0;

  allocations.forEach((allocation) => {
    if (
      doDateRangesOverlap(
        allocation.allocation_start,
        allocation.allocation_end,
        startOfDay,
        endOfDay
      )
    ) {
      totalHours += calculateOverlapHours(
        allocation.allocation_start,
        allocation.allocation_end,
        startOfDay,
        endOfDay
      );
    }
  });

  return totalHours > maxHoursPerDay;
};

/**
 * Get conflict resolution suggestions
 */
export const getConflictResolutionSuggestions = (
  conflict: ResourceConflict
): string[] => {
  const suggestions: string[] = [];
  const severity = getConflictSeverity(conflict);

  if (severity === 'high') {
    suggestions.push('Consider using a different resource for this time period');
    suggestions.push('Split the allocation into non-overlapping time slots');
    suggestions.push('Reschedule one of the allocations to a different date');
  } else if (severity === 'medium') {
    suggestions.push('Adjust allocation times to minimize overlap');
    suggestions.push('Reduce allocation percentage for better resource sharing');
    suggestions.push('Consider partial overlap if tasks can be performed simultaneously');
  } else {
    suggestions.push('Minor overlap detected - verify if acceptable');
    suggestions.push('Adjust end time of earlier allocation');
    suggestions.push('Adjust start time of later allocation');
  }

  return suggestions;
};

/**
 * Format conflict description for display
 */
export const formatConflictDescription = (conflict: ResourceConflict): string => {
  const severity = getConflictSeverity(conflict);
  const overlapHours = conflict.overlap_hours.toFixed(2);

  return `${severity.toUpperCase()} conflict: ${conflict.resource_type} #${conflict.resource_id} has ${overlapHours} hours of overlap with existing allocation on project #${conflict.existing_allocation.project_id}`;
};

export default {
  doDateRangesOverlap,
  calculateOverlapHours,
  detectConflicts,
  getConflictSeverity,
  calculateWorkingHours,
  calculateAvailabilityPercentage,
  findAvailableSlots,
  suggestAlternativeResources,
  isResourceOverAllocated,
  getConflictResolutionSuggestions,
  formatConflictDescription,
};
