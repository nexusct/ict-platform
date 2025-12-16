/**
 * Time Entries Redux Slice
 *
 * @package ICT_Platform_Mobile
 * @since   1.0.0
 */

import { createSlice, createAsyncThunk, PayloadAction } from '@reduxjs/toolkit';
import AsyncStorage from '@react-native-async-storage/async-storage';
import { apiService } from '../../services/api';
import type { TimeEntry, TimeBreak } from '../../types';

interface TimeEntriesState {
  items: TimeEntry[];
  activeEntry: TimeEntry | null;
  isLoading: boolean;
  error: string | null;
  weeklyHours: number;
  dailyHours: number;
}

const initialState: TimeEntriesState = {
  items: [],
  activeEntry: null,
  isLoading: false,
  error: null,
  weeklyHours: 0,
  dailyHours: 0,
};

export const fetchTimeEntries = createAsyncThunk(
  'timeEntries/fetchTimeEntries',
  async (params: { project_id?: number; start_date?: string; end_date?: string } = {}, { rejectWithValue }) => {
    try {
      const response = await apiService.get('/time-entries', { params });
      return response.data.data;
    } catch (error: any) {
      return rejectWithValue(error.response?.data?.message || 'Failed to fetch time entries');
    }
  }
);

export const startTimeEntry = createAsyncThunk(
  'timeEntries/startTimeEntry',
  async (data: { project_id: number; hourly_rate: number; latitude?: number; longitude?: number }, { rejectWithValue }) => {
    try {
      const entry: TimeEntry = {
        id: Date.now(),
        project_id: data.project_id,
        user_id: 0, // Will be set by server
        start_time: new Date().toISOString(),
        breaks: [],
        hourly_rate: data.hourly_rate,
        status: 'active',
        latitude: data.latitude,
        longitude: data.longitude,
        synced: false,
      };

      // Store locally first for offline support
      await AsyncStorage.setItem('active_time_entry', JSON.stringify(entry));

      // Try to sync with server
      try {
        const response = await apiService.post('/time-entries', entry);
        const serverEntry = { ...response.data.data, synced: true };
        await AsyncStorage.setItem('active_time_entry', JSON.stringify(serverEntry));
        return serverEntry;
      } catch {
        // Keep local entry if offline
        return entry;
      }
    } catch (error: any) {
      return rejectWithValue(error.message || 'Failed to start time entry');
    }
  }
);

export const stopTimeEntry = createAsyncThunk(
  'timeEntries/stopTimeEntry',
  async (notes: string, { getState, rejectWithValue }) => {
    try {
      const state = getState() as { timeEntries: TimeEntriesState };
      const activeEntry = state.timeEntries.activeEntry;

      if (!activeEntry) {
        return rejectWithValue('No active time entry');
      }

      const completedEntry: TimeEntry = {
        ...activeEntry,
        end_time: new Date().toISOString(),
        notes,
        status: 'completed',
      };

      // Clear local storage
      await AsyncStorage.removeItem('active_time_entry');

      // Try to sync with server
      try {
        if (activeEntry.synced) {
          const response = await apiService.put(`/time-entries/${activeEntry.id}`, completedEntry);
          return { ...response.data.data, synced: true };
        } else {
          const response = await apiService.post('/time-entries', completedEntry);
          return { ...response.data.data, synced: true };
        }
      } catch {
        // Queue for later sync
        const queue = JSON.parse(await AsyncStorage.getItem('offline_queue') || '[]');
        queue.push({
          id: `time-entry-${Date.now()}`,
          action: 'create',
          endpoint: '/time-entries',
          method: 'POST',
          data: completedEntry,
          timestamp: Date.now(),
          retries: 0,
          status: 'pending',
        });
        await AsyncStorage.setItem('offline_queue', JSON.stringify(queue));
        return completedEntry;
      }
    } catch (error: any) {
      return rejectWithValue(error.message || 'Failed to stop time entry');
    }
  }
);

export const startBreak = createAsyncThunk(
  'timeEntries/startBreak',
  async (type: 'short' | 'lunch' | 'other', { getState, rejectWithValue }) => {
    try {
      const state = getState() as { timeEntries: TimeEntriesState };
      const activeEntry = state.timeEntries.activeEntry;

      if (!activeEntry) {
        return rejectWithValue('No active time entry');
      }

      const newBreak: TimeBreak = {
        id: `break-${Date.now()}`,
        start_time: new Date().toISOString(),
        type,
      };

      const updatedEntry: TimeEntry = {
        ...activeEntry,
        status: 'paused',
        breaks: [...activeEntry.breaks, newBreak],
      };

      await AsyncStorage.setItem('active_time_entry', JSON.stringify(updatedEntry));

      return updatedEntry;
    } catch (error: any) {
      return rejectWithValue(error.message || 'Failed to start break');
    }
  }
);

export const endBreak = createAsyncThunk(
  'timeEntries/endBreak',
  async (_, { getState, rejectWithValue }) => {
    try {
      const state = getState() as { timeEntries: TimeEntriesState };
      const activeEntry = state.timeEntries.activeEntry;

      if (!activeEntry) {
        return rejectWithValue('No active time entry');
      }

      const updatedBreaks = activeEntry.breaks.map((brk, index) => {
        if (index === activeEntry.breaks.length - 1 && !brk.end_time) {
          return { ...brk, end_time: new Date().toISOString() };
        }
        return brk;
      });

      const updatedEntry: TimeEntry = {
        ...activeEntry,
        status: 'active',
        breaks: updatedBreaks,
      };

      await AsyncStorage.setItem('active_time_entry', JSON.stringify(updatedEntry));

      return updatedEntry;
    } catch (error: any) {
      return rejectWithValue(error.message || 'Failed to end break');
    }
  }
);

export const loadActiveEntry = createAsyncThunk(
  'timeEntries/loadActiveEntry',
  async () => {
    const stored = await AsyncStorage.getItem('active_time_entry');
    return stored ? JSON.parse(stored) : null;
  }
);

const timeEntriesSlice = createSlice({
  name: 'timeEntries',
  initialState,
  reducers: {
    setWeeklyHours: (state, action: PayloadAction<number>) => {
      state.weeklyHours = action.payload;
    },
    setDailyHours: (state, action: PayloadAction<number>) => {
      state.dailyHours = action.payload;
    },
    clearError: (state) => {
      state.error = null;
    },
  },
  extraReducers: (builder) => {
    builder
      // Fetch Time Entries
      .addCase(fetchTimeEntries.pending, (state) => {
        state.isLoading = true;
        state.error = null;
      })
      .addCase(fetchTimeEntries.fulfilled, (state, action) => {
        state.isLoading = false;
        state.items = action.payload;
      })
      .addCase(fetchTimeEntries.rejected, (state, action) => {
        state.isLoading = false;
        state.error = action.payload as string;
      })
      // Start Time Entry
      .addCase(startTimeEntry.fulfilled, (state, action) => {
        state.activeEntry = action.payload;
      })
      // Stop Time Entry
      .addCase(stopTimeEntry.fulfilled, (state, action) => {
        state.activeEntry = null;
        state.items.unshift(action.payload);
      })
      // Start/End Break
      .addCase(startBreak.fulfilled, (state, action) => {
        state.activeEntry = action.payload;
      })
      .addCase(endBreak.fulfilled, (state, action) => {
        state.activeEntry = action.payload;
      })
      // Load Active Entry
      .addCase(loadActiveEntry.fulfilled, (state, action) => {
        state.activeEntry = action.payload;
      });
  },
});

export const { setWeeklyHours, setDailyHours, clearError } = timeEntriesSlice.actions;
export default timeEntriesSlice.reducer;
