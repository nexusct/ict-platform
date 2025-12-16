/**
 * Offline Redux Slice
 *
 * @package ICT_Platform_Mobile
 * @since   1.0.0
 */

import { createSlice, createAsyncThunk, PayloadAction } from '@reduxjs/toolkit';
import AsyncStorage from '@react-native-async-storage/async-storage';
import NetInfo from '@react-native-community/netinfo';
import { apiService } from '../../services/api';
import type { OfflineQueueItem } from '../../types';

interface OfflineState {
  isOnline: boolean;
  queue: OfflineQueueItem[];
  isSyncing: boolean;
  lastSyncAt: string | null;
  syncErrors: string[];
}

const initialState: OfflineState = {
  isOnline: true,
  queue: [],
  isSyncing: false,
  lastSyncAt: null,
  syncErrors: [],
};

export const loadQueue = createAsyncThunk('offline/loadQueue', async () => {
  const stored = await AsyncStorage.getItem('offline_queue');
  return stored ? JSON.parse(stored) : [];
});

export const addToQueue = createAsyncThunk(
  'offline/addToQueue',
  async (item: Omit<OfflineQueueItem, 'id' | 'timestamp' | 'retries' | 'status'>) => {
    const queueItem: OfflineQueueItem = {
      ...item,
      id: `${Date.now()}-${Math.random().toString(36).slice(2)}`,
      timestamp: Date.now(),
      retries: 0,
      status: 'pending',
    };

    const stored = await AsyncStorage.getItem('offline_queue');
    const queue = stored ? JSON.parse(stored) : [];
    queue.push(queueItem);
    await AsyncStorage.setItem('offline_queue', JSON.stringify(queue));

    return queueItem;
  }
);

export const syncQueue = createAsyncThunk(
  'offline/syncQueue',
  async (_, { getState, dispatch, rejectWithValue }) => {
    const state = getState() as { offline: OfflineState };

    if (!state.offline.isOnline || state.offline.isSyncing) {
      return rejectWithValue('Cannot sync: offline or already syncing');
    }

    const queue = [...state.offline.queue];
    const errors: string[] = [];
    const synced: string[] = [];

    for (const item of queue) {
      if (item.status === 'pending' || (item.status === 'failed' && item.retries < 3)) {
        try {
          // Update status to syncing
          dispatch(updateQueueItemStatus({ id: item.id, status: 'syncing' }));

          // Make API request
          switch (item.method) {
            case 'POST':
              await apiService.post(item.endpoint, item.data);
              break;
            case 'PUT':
              await apiService.put(item.endpoint, item.data);
              break;
            case 'DELETE':
              await apiService.delete(item.endpoint);
              break;
          }

          synced.push(item.id);
        } catch (error: any) {
          errors.push(`Failed to sync ${item.action}: ${error.message}`);
          dispatch(
            updateQueueItemStatus({
              id: item.id,
              status: 'failed',
              retries: item.retries + 1,
            })
          );
        }
      }
    }

    // Remove synced items
    if (synced.length > 0) {
      dispatch(removeFromQueue(synced));
    }

    return { syncedCount: synced.length, errors };
  }
);

export const checkConnectivity = createAsyncThunk('offline/checkConnectivity', async () => {
  const state = await NetInfo.fetch();
  return state.isConnected ?? false;
});

const offlineSlice = createSlice({
  name: 'offline',
  initialState,
  reducers: {
    setOnline: (state, action: PayloadAction<boolean>) => {
      state.isOnline = action.payload;
    },
    updateQueueItemStatus: (
      state,
      action: PayloadAction<{ id: string; status: OfflineQueueItem['status']; retries?: number }>
    ) => {
      const item = state.queue.find((q) => q.id === action.payload.id);
      if (item) {
        item.status = action.payload.status;
        if (action.payload.retries !== undefined) {
          item.retries = action.payload.retries;
        }
      }
    },
    removeFromQueue: (state, action: PayloadAction<string[]>) => {
      state.queue = state.queue.filter((item) => !action.payload.includes(item.id));
      // Persist
      AsyncStorage.setItem('offline_queue', JSON.stringify(state.queue));
    },
    clearQueue: (state) => {
      state.queue = [];
      AsyncStorage.removeItem('offline_queue');
    },
    clearSyncErrors: (state) => {
      state.syncErrors = [];
    },
  },
  extraReducers: (builder) => {
    builder
      // Load Queue
      .addCase(loadQueue.fulfilled, (state, action) => {
        state.queue = action.payload;
      })
      // Add to Queue
      .addCase(addToQueue.fulfilled, (state, action) => {
        state.queue.push(action.payload);
      })
      // Sync Queue
      .addCase(syncQueue.pending, (state) => {
        state.isSyncing = true;
        state.syncErrors = [];
      })
      .addCase(syncQueue.fulfilled, (state, action) => {
        state.isSyncing = false;
        state.lastSyncAt = new Date().toISOString();
        state.syncErrors = action.payload.errors;
      })
      .addCase(syncQueue.rejected, (state) => {
        state.isSyncing = false;
      })
      // Check Connectivity
      .addCase(checkConnectivity.fulfilled, (state, action) => {
        state.isOnline = action.payload;
      });
  },
});

export const { setOnline, updateQueueItemStatus, removeFromQueue, clearQueue, clearSyncErrors } =
  offlineSlice.actions;
export default offlineSlice.reducer;
