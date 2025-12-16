/**
 * Offline Context
 *
 * @package ICT_Platform_Mobile
 * @since   1.0.0
 */

import React, { createContext, useContext, useEffect, useCallback } from 'react';
import { useDispatch, useSelector } from 'react-redux';
import NetInfo from '@react-native-community/netinfo';
import type { RootState, AppDispatch } from '../store';
import {
  loadQueue,
  addToQueue,
  syncQueue,
  setOnline,
  clearQueue,
} from '../store/slices/offlineSlice';
import type { OfflineQueueItem } from '../types';

interface OfflineContextType {
  isOnline: boolean;
  queueLength: number;
  isSyncing: boolean;
  lastSyncAt: string | null;
  queueAction: (item: Omit<OfflineQueueItem, 'id' | 'timestamp' | 'retries' | 'status'>) => Promise<void>;
  syncNow: () => Promise<void>;
  clearOfflineQueue: () => void;
}

const OfflineContext = createContext<OfflineContextType | undefined>(undefined);

export const OfflineProvider: React.FC<{ children: React.ReactNode }> = ({ children }) => {
  const dispatch = useDispatch<AppDispatch>();
  const { isOnline, queue, isSyncing, lastSyncAt } = useSelector((state: RootState) => state.offline);

  // Load queue and set up network listener
  useEffect(() => {
    dispatch(loadQueue());

    const unsubscribe = NetInfo.addEventListener((state) => {
      const online = state.isConnected ?? false;
      dispatch(setOnline(online));

      // Auto-sync when coming back online
      if (online && queue.length > 0) {
        dispatch(syncQueue());
      }
    });

    return () => unsubscribe();
  }, [dispatch]);

  // Periodic sync when online
  useEffect(() => {
    if (!isOnline) return;

    const intervalId = setInterval(() => {
      if (queue.some((item) => item.status === 'pending')) {
        dispatch(syncQueue());
      }
    }, 30000); // Every 30 seconds

    return () => clearInterval(intervalId);
  }, [isOnline, queue, dispatch]);

  const queueAction = useCallback(
    async (item: Omit<OfflineQueueItem, 'id' | 'timestamp' | 'retries' | 'status'>) => {
      await dispatch(addToQueue(item));

      // Try to sync immediately if online
      if (isOnline) {
        dispatch(syncQueue());
      }
    },
    [dispatch, isOnline]
  );

  const syncNow = useCallback(async () => {
    await dispatch(syncQueue());
  }, [dispatch]);

  const clearOfflineQueue = useCallback(() => {
    dispatch(clearQueue());
  }, [dispatch]);

  return (
    <OfflineContext.Provider
      value={{
        isOnline,
        queueLength: queue.length,
        isSyncing,
        lastSyncAt,
        queueAction,
        syncNow,
        clearOfflineQueue,
      }}
    >
      {children}
    </OfflineContext.Provider>
  );
};

export const useOffline = () => {
  const context = useContext(OfflineContext);
  if (!context) {
    throw new Error('useOffline must be used within OfflineProvider');
  }
  return context;
};
