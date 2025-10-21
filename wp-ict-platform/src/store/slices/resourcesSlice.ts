/**
 * Resources Redux Slice
 *
 * Manages resource allocation state including:
 * - Resource CRUD operations
 * - Conflict detection
 * - Availability checking
 * - Calendar events for FullCalendar
 * - Technician skills management
 *
 * @package ICT_Platform
 * @since   1.0.0
 */

import { createSlice, createAsyncThunk, PayloadAction } from '@reduxjs/toolkit';
import {
  ResourcesState,
  ProjectResource,
  ResourceFormData,
  ResourceFilters,
  ResourceConflict,
  CalendarEvent,
  ResourceAvailability,
  TechnicianSkill,
  ResourceType,
  PaginatedResponse,
} from '../../types';
import { resourceAPI } from '../../services/api';

// Initial state
const initialState: ResourcesState = {
  items: [],
  loading: false,
  error: undefined,
  filters: {},
  conflicts: [],
  calendarEvents: [],
  availability: [],
  technicianSkills: {},
  pagination: {
    page: 1,
    per_page: 20,
    total: 0,
    total_pages: 0,
  },
};

// Async thunks

/**
 * Fetch resource allocations with filtering and pagination
 */
export const fetchResources = createAsyncThunk(
  'resources/fetchResources',
  async (params?: { filters?: ResourceFilters; page?: number; per_page?: number }) => {
    const response = await resourceAPI.getAll({
      ...params?.filters,
      page: params?.page,
      per_page: params?.per_page,
    });
    return response.data!;
  }
);

/**
 * Fetch single resource allocation by ID
 */
export const fetchResourceById = createAsyncThunk(
  'resources/fetchResourceById',
  async (id: number) => {
    const response = await resourceAPI.getOne(id);
    return response.data!;
  }
);

/**
 * Create new resource allocation
 */
export const createResourceAllocation = createAsyncThunk(
  'resources/createResourceAllocation',
  async (data: ResourceFormData) => {
    const response = await resourceAPI.create(data);
    return response.data!;
  }
);

/**
 * Update existing resource allocation
 */
export const updateResourceAllocation = createAsyncThunk(
  'resources/updateResourceAllocation',
  async ({ id, data }: { id: number; data: Partial<ResourceFormData> }) => {
    const response = await resourceAPI.update(id, data);
    return response.data!;
  }
);

/**
 * Delete resource allocation
 */
export const deleteResourceAllocation = createAsyncThunk(
  'resources/deleteResourceAllocation',
  async (id: number) => {
    await resourceAPI.delete(id);
    return id;
  }
);

/**
 * Check for resource conflicts before allocation
 */
export const checkResourceConflicts = createAsyncThunk(
  'resources/checkConflicts',
  async (params: {
    resource_type: ResourceType;
    resource_id: number;
    allocation_start: string;
    allocation_end: string;
    exclude_id?: number;
  }) => {
    const response = await resourceAPI.checkConflicts(params);
    return response.data!;
  }
);

/**
 * Fetch resource availability for date range
 */
export const fetchResourceAvailability = createAsyncThunk(
  'resources/fetchAvailability',
  async (params: {
    resource_type?: ResourceType;
    resource_id?: number;
    date_from: string;
    date_to: string;
  }) => {
    const response = await resourceAPI.getAvailability(params);
    return response.data!;
  }
);

/**
 * Fetch calendar events for FullCalendar
 */
export const fetchCalendarEvents = createAsyncThunk(
  'resources/fetchCalendarEvents',
  async (params: {
    start: string;
    end: string;
    resource_type?: ResourceType;
    resource_id?: number;
  }) => {
    const response = await resourceAPI.getCalendarEvents(params);
    return response.data!;
  }
);

/**
 * Fetch technician skills
 */
export const fetchTechnicianSkills = createAsyncThunk(
  'resources/fetchTechnicianSkills',
  async (technicianId: number) => {
    const response = await resourceAPI.getTechnicianSkills(technicianId);
    return { technicianId, skills: response.data! };
  }
);

/**
 * Update technician skills
 */
export const updateTechnicianSkills = createAsyncThunk(
  'resources/updateTechnicianSkills',
  async ({ technicianId, skills }: { technicianId: number; skills: Partial<TechnicianSkill>[] }) => {
    const response = await resourceAPI.updateTechnicianSkills(technicianId, skills);
    return { technicianId, skills: response.data! };
  }
);

/**
 * Batch create resource allocations
 */
export const batchCreateAllocations = createAsyncThunk(
  'resources/batchCreate',
  async (allocations: ResourceFormData[]) => {
    const response = await resourceAPI.batchCreate(allocations);
    return response.data!;
  }
);

/**
 * Batch update resource allocations
 */
export const batchUpdateAllocations = createAsyncThunk(
  'resources/batchUpdate',
  async (updates: { id: number; data: Partial<ResourceFormData> }[]) => {
    const response = await resourceAPI.batchUpdate(updates);
    return response.data!;
  }
);

/**
 * Batch delete resource allocations
 */
export const batchDeleteAllocations = createAsyncThunk(
  'resources/batchDelete',
  async (ids: number[]) => {
    const response = await resourceAPI.batchDelete(ids);
    return ids;
  }
);

// Slice definition
const resourcesSlice = createSlice({
  name: 'resources',
  initialState,
  reducers: {
    // Set filters
    setFilters: (state, action: PayloadAction<ResourceFilters>) => {
      state.filters = action.payload;
      state.pagination.page = 1; // Reset to first page when filters change
    },

    // Clear filters
    clearFilters: (state) => {
      state.filters = {};
      state.pagination.page = 1;
    },

    // Set pagination
    setPagination: (state, action: PayloadAction<{ page?: number; per_page?: number }>) => {
      if (action.payload.page !== undefined) {
        state.pagination.page = action.payload.page;
      }
      if (action.payload.per_page !== undefined) {
        state.pagination.per_page = action.payload.per_page;
      }
    },

    // Clear conflicts
    clearConflicts: (state) => {
      state.conflicts = [];
    },

    // Clear calendar events
    clearCalendarEvents: (state) => {
      state.calendarEvents = [];
    },

    // Clear availability data
    clearAvailability: (state) => {
      state.availability = [];
    },

    // Clear error
    clearError: (state) => {
      state.error = undefined;
    },
  },
  extraReducers: (builder) => {
    // Fetch resources
    builder
      .addCase(fetchResources.pending, (state) => {
        state.loading = true;
        state.error = undefined;
      })
      .addCase(fetchResources.fulfilled, (state, action: PayloadAction<PaginatedResponse<ProjectResource>>) => {
        state.loading = false;
        state.items = action.payload.data;
        state.pagination = {
          page: action.payload.page,
          per_page: action.payload.per_page,
          total: action.payload.total,
          total_pages: action.payload.total_pages,
        };
      })
      .addCase(fetchResources.rejected, (state, action) => {
        state.loading = false;
        state.error = action.error.message || 'Failed to fetch resources';
      });

    // Fetch resource by ID
    builder
      .addCase(fetchResourceById.pending, (state) => {
        state.loading = true;
        state.error = undefined;
      })
      .addCase(fetchResourceById.fulfilled, (state, action: PayloadAction<ProjectResource>) => {
        state.loading = false;
        // Update item in list if exists, otherwise add it
        const index = state.items.findIndex(item => item.id === action.payload.id);
        if (index >= 0) {
          state.items[index] = action.payload;
        } else {
          state.items.push(action.payload);
        }
      })
      .addCase(fetchResourceById.rejected, (state, action) => {
        state.loading = false;
        state.error = action.error.message || 'Failed to fetch resource';
      });

    // Create resource allocation
    builder
      .addCase(createResourceAllocation.pending, (state) => {
        state.loading = true;
        state.error = undefined;
      })
      .addCase(createResourceAllocation.fulfilled, (state, action: PayloadAction<ProjectResource>) => {
        state.loading = false;
        state.items.unshift(action.payload);
        state.pagination.total += 1;
      })
      .addCase(createResourceAllocation.rejected, (state, action) => {
        state.loading = false;
        state.error = action.error.message || 'Failed to create resource allocation';
      });

    // Update resource allocation
    builder
      .addCase(updateResourceAllocation.pending, (state) => {
        state.loading = true;
        state.error = undefined;
      })
      .addCase(updateResourceAllocation.fulfilled, (state, action: PayloadAction<ProjectResource>) => {
        state.loading = false;
        const index = state.items.findIndex(item => item.id === action.payload.id);
        if (index >= 0) {
          state.items[index] = action.payload;
        }
      })
      .addCase(updateResourceAllocation.rejected, (state, action) => {
        state.loading = false;
        state.error = action.error.message || 'Failed to update resource allocation';
      });

    // Delete resource allocation
    builder
      .addCase(deleteResourceAllocation.pending, (state) => {
        state.loading = true;
        state.error = undefined;
      })
      .addCase(deleteResourceAllocation.fulfilled, (state, action: PayloadAction<number>) => {
        state.loading = false;
        state.items = state.items.filter(item => item.id !== action.payload);
        state.pagination.total -= 1;
      })
      .addCase(deleteResourceAllocation.rejected, (state, action) => {
        state.loading = false;
        state.error = action.error.message || 'Failed to delete resource allocation';
      });

    // Check conflicts
    builder
      .addCase(checkResourceConflicts.pending, (state) => {
        state.loading = true;
        state.error = undefined;
        state.conflicts = []; // Clear previous conflicts
      })
      .addCase(checkResourceConflicts.fulfilled, (state, action: PayloadAction<ResourceConflict[]>) => {
        state.loading = false;
        state.conflicts = action.payload;
      })
      .addCase(checkResourceConflicts.rejected, (state, action) => {
        state.loading = false;
        state.error = action.error.message || 'Failed to check conflicts';
      });

    // Fetch availability
    builder
      .addCase(fetchResourceAvailability.pending, (state) => {
        state.loading = true;
        state.error = undefined;
      })
      .addCase(fetchResourceAvailability.fulfilled, (state, action: PayloadAction<ResourceAvailability[]>) => {
        state.loading = false;
        state.availability = action.payload;
      })
      .addCase(fetchResourceAvailability.rejected, (state, action) => {
        state.loading = false;
        state.error = action.error.message || 'Failed to fetch availability';
      });

    // Fetch calendar events
    builder
      .addCase(fetchCalendarEvents.pending, (state) => {
        state.loading = true;
        state.error = undefined;
      })
      .addCase(fetchCalendarEvents.fulfilled, (state, action: PayloadAction<CalendarEvent[]>) => {
        state.loading = false;
        state.calendarEvents = action.payload;
      })
      .addCase(fetchCalendarEvents.rejected, (state, action) => {
        state.loading = false;
        state.error = action.error.message || 'Failed to fetch calendar events';
      });

    // Fetch technician skills
    builder
      .addCase(fetchTechnicianSkills.pending, (state) => {
        state.loading = true;
        state.error = undefined;
      })
      .addCase(fetchTechnicianSkills.fulfilled, (state, action: PayloadAction<{ technicianId: number; skills: TechnicianSkill[] }>) => {
        state.loading = false;
        state.technicianSkills[action.payload.technicianId] = action.payload.skills;
      })
      .addCase(fetchTechnicianSkills.rejected, (state, action) => {
        state.loading = false;
        state.error = action.error.message || 'Failed to fetch technician skills';
      });

    // Update technician skills
    builder
      .addCase(updateTechnicianSkills.pending, (state) => {
        state.loading = true;
        state.error = undefined;
      })
      .addCase(updateTechnicianSkills.fulfilled, (state, action: PayloadAction<{ technicianId: number; skills: TechnicianSkill[] }>) => {
        state.loading = false;
        state.technicianSkills[action.payload.technicianId] = action.payload.skills;
      })
      .addCase(updateTechnicianSkills.rejected, (state, action) => {
        state.loading = false;
        state.error = action.error.message || 'Failed to update technician skills';
      });

    // Batch create
    builder
      .addCase(batchCreateAllocations.pending, (state) => {
        state.loading = true;
        state.error = undefined;
      })
      .addCase(batchCreateAllocations.fulfilled, (state, action: PayloadAction<ProjectResource[]>) => {
        state.loading = false;
        state.items = [...action.payload, ...state.items];
        state.pagination.total += action.payload.length;
      })
      .addCase(batchCreateAllocations.rejected, (state, action) => {
        state.loading = false;
        state.error = action.error.message || 'Failed to batch create allocations';
      });

    // Batch update
    builder
      .addCase(batchUpdateAllocations.pending, (state) => {
        state.loading = true;
        state.error = undefined;
      })
      .addCase(batchUpdateAllocations.fulfilled, (state, action: PayloadAction<ProjectResource[]>) => {
        state.loading = false;
        action.payload.forEach(updated => {
          const index = state.items.findIndex(item => item.id === updated.id);
          if (index >= 0) {
            state.items[index] = updated;
          }
        });
      })
      .addCase(batchUpdateAllocations.rejected, (state, action) => {
        state.loading = false;
        state.error = action.error.message || 'Failed to batch update allocations';
      });

    // Batch delete
    builder
      .addCase(batchDeleteAllocations.pending, (state) => {
        state.loading = true;
        state.error = undefined;
      })
      .addCase(batchDeleteAllocations.fulfilled, (state, action: PayloadAction<number[]>) => {
        state.loading = false;
        state.items = state.items.filter(item => !action.payload.includes(item.id));
        state.pagination.total -= action.payload.length;
      })
      .addCase(batchDeleteAllocations.rejected, (state, action) => {
        state.loading = false;
        state.error = action.error.message || 'Failed to batch delete allocations';
      });
  },
});

// Export actions
export const {
  setFilters,
  clearFilters,
  setPagination,
  clearConflicts,
  clearCalendarEvents,
  clearAvailability,
  clearError,
} = resourcesSlice.actions;

// Selectors
export const selectResources = (state: { resources: ResourcesState }) => state.resources.items;
export const selectResourcesLoading = (state: { resources: ResourcesState }) => state.resources.loading;
export const selectResourcesError = (state: { resources: ResourcesState }) => state.resources.error;
export const selectResourceFilters = (state: { resources: ResourcesState }) => state.resources.filters;
export const selectResourceConflicts = (state: { resources: ResourcesState }) => state.resources.conflicts;
export const selectCalendarEvents = (state: { resources: ResourcesState }) => state.resources.calendarEvents;
export const selectResourceAvailability = (state: { resources: ResourcesState }) => state.resources.availability;
export const selectTechnicianSkills = (state: { resources: ResourcesState }) => state.resources.technicianSkills;
export const selectResourcePagination = (state: { resources: ResourcesState }) => state.resources.pagination;

// Helper selector to get skills for a specific technician
export const selectTechnicianSkillsById = (technicianId: number) => (state: { resources: ResourcesState }) =>
  state.resources.technicianSkills[technicianId] || [];

// Helper selector to check if there are any conflicts
export const selectHasConflicts = (state: { resources: ResourcesState }) => state.resources.conflicts.length > 0;

// Export reducer
export default resourcesSlice.reducer;
