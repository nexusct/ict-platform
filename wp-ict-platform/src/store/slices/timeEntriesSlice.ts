/**
 * Time Entries Redux Slice
 *
 * @package ICT_Platform
 * @since   1.0.0
 */

import { createSlice, createAsyncThunk, PayloadAction } from '@reduxjs/toolkit';
import type { TimeEntriesState, TimeEntry, TimeEntryFormData, TimeEntryFilters } from '../../types';
import { timeEntryAPI } from '../../services/api';

const initialState: TimeEntriesState = {
  items: [],
  activeEntry: undefined,
  loading: false,
  error: undefined,
  filters: {},
  pagination: {
    page: 1,
    per_page: 20,
    total: 0,
    total_pages: 0,
  },
};

// Async thunks
export const fetchTimeEntries = createAsyncThunk(
  'timeEntries/fetchAll',
  async (params?: Record<string, any>, { getState }: any) => {
    const { filters, pagination } = getState().timeEntries;
    const response = await timeEntryAPI.getAll({
      page: pagination.page,
      per_page: pagination.per_page,
      ...filters,
      ...params,
    });
    return response;
  }
);

export const fetchTimeEntry = createAsyncThunk(
  'timeEntries/fetchOne',
  async (id: number) => {
    const response = await timeEntryAPI.getOne(id);
    return response.data!;
  }
);

export const createTimeEntry = createAsyncThunk(
  'timeEntries/create',
  async (data: TimeEntryFormData) => {
    const response = await timeEntryAPI.create(data);
    return response.data!;
  }
);

export const updateTimeEntry = createAsyncThunk(
  'timeEntries/update',
  async ({ id, data }: { id: number; data: Partial<TimeEntryFormData> }) => {
    const response = await timeEntryAPI.update(id, data);
    return response.data!;
  }
);

export const deleteTimeEntry = createAsyncThunk(
  'timeEntries/delete',
  async (id: number) => {
    await timeEntryAPI.delete(id);
    return id;
  }
);

export const clockIn = createAsyncThunk(
  'timeEntries/clockIn',
  async (data: {
    project_id: number;
    task_type?: string;
    notes?: string;
    gps_latitude?: number;
    gps_longitude?: number;
  }) => {
    const response = await timeEntryAPI.clockIn(data);
    return response.data!;
  }
);

export const clockOut = createAsyncThunk(
  'timeEntries/clockOut',
  async (data?: {
    entry_id?: number;
    notes?: string;
    gps_latitude?: number;
    gps_longitude?: number;
  }) => {
    const response = await timeEntryAPI.clockOut(data);
    return response.data!;
  }
);

export const fetchActiveEntry = createAsyncThunk(
  'timeEntries/fetchActive',
  async () => {
    const response = await timeEntryAPI.getActive();
    return response.data || null;
  }
);

export const approveTimeEntry = createAsyncThunk(
  'timeEntries/approve',
  async ({ id, notes }: { id: number; notes?: string }) => {
    const response = await timeEntryAPI.approve(id, notes);
    return response.data!;
  }
);

export const rejectTimeEntry = createAsyncThunk(
  'timeEntries/reject',
  async ({ id, reason }: { id: number; reason: string }) => {
    const response = await timeEntryAPI.reject(id, reason);
    return response.data!;
  }
);

export const syncTimeEntry = createAsyncThunk(
  'timeEntries/sync',
  async (id: number) => {
    await timeEntryAPI.syncToZoho(id);
    // Re-fetch the entry to get updated sync status
    const response = await timeEntryAPI.getOne(id);
    return response.data!;
  }
);

const timeEntriesSlice = createSlice({
  name: 'timeEntries',
  initialState,
  reducers: {
    setFilters: (state, action: PayloadAction<Partial<TimeEntryFilters>>) => {
      state.filters = { ...state.filters, ...action.payload };
      state.pagination.page = 1; // Reset to first page
    },
    setPage: (state, action: PayloadAction<number>) => {
      state.pagination.page = action.payload;
    },
    setPerPage: (state, action: PayloadAction<number>) => {
      state.pagination.per_page = action.payload;
      state.pagination.page = 1; // Reset to first page
    },
    clearFilters: (state) => {
      state.filters = {};
      state.pagination.page = 1;
    },
    clearError: (state) => {
      state.error = undefined;
    },
    updateActiveEntryTime: (state, action: PayloadAction<{ elapsed: number }>) => {
      // Used for live timer updates without refetching
      if (state.activeEntry) {
        state.activeEntry.total_hours = action.payload.elapsed;
      }
    },
  },
  extraReducers: (builder) => {
    // Fetch all time entries
    builder.addCase(fetchTimeEntries.pending, (state) => {
      state.loading = true;
      state.error = undefined;
    });
    builder.addCase(fetchTimeEntries.fulfilled, (state, action) => {
      state.loading = false;
      state.items = action.payload.data;
      state.pagination.total = action.payload.total || 0;
      state.pagination.total_pages = action.payload.total_pages || 0;
    });
    builder.addCase(fetchTimeEntries.rejected, (state, action) => {
      state.loading = false;
      state.error = action.error.message;
    });

    // Fetch single time entry
    builder.addCase(fetchTimeEntry.pending, (state) => {
      state.loading = true;
      state.error = undefined;
    });
    builder.addCase(fetchTimeEntry.fulfilled, (state, action) => {
      state.loading = false;
      // Update in items array if exists
      const index = state.items.findIndex((item) => item.id === action.payload.id);
      if (index !== -1) {
        state.items[index] = action.payload;
      }
    });
    builder.addCase(fetchTimeEntry.rejected, (state, action) => {
      state.loading = false;
      state.error = action.error.message;
    });

    // Create time entry
    builder.addCase(createTimeEntry.pending, (state) => {
      state.loading = true;
      state.error = undefined;
    });
    builder.addCase(createTimeEntry.fulfilled, (state, action) => {
      state.loading = false;
      state.items.unshift(action.payload);
    });
    builder.addCase(createTimeEntry.rejected, (state, action) => {
      state.loading = false;
      state.error = action.error.message;
    });

    // Update time entry
    builder.addCase(updateTimeEntry.pending, (state) => {
      state.loading = true;
      state.error = undefined;
    });
    builder.addCase(updateTimeEntry.fulfilled, (state, action) => {
      state.loading = false;
      const index = state.items.findIndex((item) => item.id === action.payload.id);
      if (index !== -1) {
        state.items[index] = action.payload;
      }
      if (state.activeEntry?.id === action.payload.id) {
        state.activeEntry = action.payload;
      }
    });
    builder.addCase(updateTimeEntry.rejected, (state, action) => {
      state.loading = false;
      state.error = action.error.message;
    });

    // Delete time entry
    builder.addCase(deleteTimeEntry.pending, (state) => {
      state.loading = true;
      state.error = undefined;
    });
    builder.addCase(deleteTimeEntry.fulfilled, (state, action) => {
      state.loading = false;
      state.items = state.items.filter((item) => item.id !== action.payload);
    });
    builder.addCase(deleteTimeEntry.rejected, (state, action) => {
      state.loading = false;
      state.error = action.error.message;
    });

    // Clock in
    builder.addCase(clockIn.pending, (state) => {
      state.loading = true;
      state.error = undefined;
    });
    builder.addCase(clockIn.fulfilled, (state, action) => {
      state.loading = false;
      state.activeEntry = action.payload;
      state.items.unshift(action.payload);
    });
    builder.addCase(clockIn.rejected, (state, action) => {
      state.loading = false;
      state.error = action.error.message;
    });

    // Clock out
    builder.addCase(clockOut.pending, (state) => {
      state.loading = true;
      state.error = undefined;
    });
    builder.addCase(clockOut.fulfilled, (state, action) => {
      state.loading = false;
      state.activeEntry = undefined;
      const index = state.items.findIndex((item) => item.id === action.payload.id);
      if (index !== -1) {
        state.items[index] = action.payload;
      }
    });
    builder.addCase(clockOut.rejected, (state, action) => {
      state.loading = false;
      state.error = action.error.message;
    });

    // Fetch active entry
    builder.addCase(fetchActiveEntry.pending, (state) => {
      state.loading = true;
      state.error = undefined;
    });
    builder.addCase(fetchActiveEntry.fulfilled, (state, action) => {
      state.loading = false;
      state.activeEntry = action.payload || undefined;
    });
    builder.addCase(fetchActiveEntry.rejected, (state, action) => {
      state.loading = false;
      state.error = action.error.message;
    });

    // Approve time entry
    builder.addCase(approveTimeEntry.pending, (state) => {
      state.loading = true;
      state.error = undefined;
    });
    builder.addCase(approveTimeEntry.fulfilled, (state, action) => {
      state.loading = false;
      const index = state.items.findIndex((item) => item.id === action.payload.id);
      if (index !== -1) {
        state.items[index] = action.payload;
      }
    });
    builder.addCase(approveTimeEntry.rejected, (state, action) => {
      state.loading = false;
      state.error = action.error.message;
    });

    // Reject time entry
    builder.addCase(rejectTimeEntry.pending, (state) => {
      state.loading = true;
      state.error = undefined;
    });
    builder.addCase(rejectTimeEntry.fulfilled, (state, action) => {
      state.loading = false;
      const index = state.items.findIndex((item) => item.id === action.payload.id);
      if (index !== -1) {
        state.items[index] = action.payload;
      }
    });
    builder.addCase(rejectTimeEntry.rejected, (state, action) => {
      state.loading = false;
      state.error = action.error.message;
    });

    // Sync time entry
    builder.addCase(syncTimeEntry.pending, (state) => {
      state.loading = true;
      state.error = undefined;
    });
    builder.addCase(syncTimeEntry.fulfilled, (state, action) => {
      state.loading = false;
      const index = state.items.findIndex((item) => item.id === action.payload.id);
      if (index !== -1) {
        state.items[index] = action.payload;
      }
    });
    builder.addCase(syncTimeEntry.rejected, (state, action) => {
      state.loading = false;
      state.error = action.error.message;
    });
  },
});

export const {
  setFilters,
  setPage,
  setPerPage,
  clearFilters,
  clearError,
  updateActiveEntryTime,
} = timeEntriesSlice.actions;

export default timeEntriesSlice.reducer;
