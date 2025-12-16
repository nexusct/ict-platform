/**
 * Offline Manager Component
 *
 * Handles offline detection, queue management, and sync.
 *
 * @package ICT_Platform
 * @since   2.1.0
 */

import React, { createContext, useContext, useState, useEffect, useCallback, useRef } from 'react';
import type { OfflineQueueItem, OfflineState } from '../../types';

interface OfflineContextType {
  state: OfflineState;
  queueAction: (action: Omit<OfflineQueueItem, 'id' | 'timestamp' | 'retries' | 'status'>) => void;
  syncNow: () => Promise<void>;
  clearQueue: () => void;
}

const OfflineContext = createContext<OfflineContextType | null>(null);

export const useOffline = () => {
  const context = useContext(OfflineContext);
  if (!context) {
    throw new Error('useOffline must be used within OfflineProvider');
  }
  return context;
};

interface OfflineProviderProps {
  children: React.ReactNode;
  syncInterval?: number;
  maxRetries?: number;
  storageKey?: string;
}

export const OfflineProvider: React.FC<OfflineProviderProps> = ({
  children,
  syncInterval = 30000,
  maxRetries = 3,
  storageKey = 'ict_offline_queue',
}) => {
  const [queue, setQueue] = useState<OfflineQueueItem[]>(() => {
    const saved = localStorage.getItem(storageKey);
    return saved ? JSON.parse(saved) : [];
  });

  const [state, setState] = useState<OfflineState>({
    isOnline: navigator.onLine,
    lastSyncAt: undefined,
    queuedActions: 0,
    syncInProgress: false,
  });

  const syncInProgressRef = useRef(false);

  // Update queued actions count
  useEffect(() => {
    setState((prev) => ({
      ...prev,
      queuedActions: queue.filter((item) => item.status === 'pending').length,
    }));
    localStorage.setItem(storageKey, JSON.stringify(queue));
  }, [queue, storageKey]);

  // Listen for online/offline events
  useEffect(() => {
    const handleOnline = () => {
      setState((prev) => ({ ...prev, isOnline: true }));
      syncNow();
    };

    const handleOffline = () => {
      setState((prev) => ({ ...prev, isOnline: false }));
    };

    window.addEventListener('online', handleOnline);
    window.addEventListener('offline', handleOffline);

    return () => {
      window.removeEventListener('online', handleOnline);
      window.removeEventListener('offline', handleOffline);
    };
  }, []);

  // Periodic sync when online
  useEffect(() => {
    if (!state.isOnline) return;

    const intervalId = setInterval(() => {
      if (queue.some((item) => item.status === 'pending')) {
        syncNow();
      }
    }, syncInterval);

    return () => clearInterval(intervalId);
  }, [state.isOnline, syncInterval, queue]);

  // Queue an action for later sync
  const queueAction = useCallback(
    (action: Omit<OfflineQueueItem, 'id' | 'timestamp' | 'retries' | 'status'>) => {
      const newItem: OfflineQueueItem = {
        ...action,
        id: `${Date.now()}-${Math.random().toString(36).slice(2)}`,
        timestamp: Date.now(),
        retries: 0,
        status: 'pending',
      };

      setQueue((prev) => [...prev, newItem]);

      // Try to sync immediately if online
      if (navigator.onLine) {
        setTimeout(syncNow, 100);
      }
    },
    []
  );

  // Sync all pending items
  const syncNow = useCallback(async () => {
    if (syncInProgressRef.current || !navigator.onLine) return;

    syncInProgressRef.current = true;
    setState((prev) => ({ ...prev, syncInProgress: true }));

    const pendingItems = queue.filter(
      (item) => item.status === 'pending' || (item.status === 'failed' && item.retries < maxRetries)
    );

    for (const item of pendingItems) {
      try {
        // Mark as syncing
        setQueue((prev) =>
          prev.map((q) => (q.id === item.id ? { ...q, status: 'syncing' as const } : q))
        );

        // Make API request
        const response = await fetch(item.endpoint, {
          method: item.method,
          headers: {
            'Content-Type': 'application/json',
            'X-WP-Nonce': (window as any).ictSettings?.nonce || '',
          },
          body: item.data ? JSON.stringify(item.data) : undefined,
        });

        if (response.ok) {
          // Remove from queue on success
          setQueue((prev) => prev.filter((q) => q.id !== item.id));
        } else {
          throw new Error(`HTTP ${response.status}`);
        }
      } catch (error) {
        console.error('Sync error:', error);

        // Mark as failed, increment retries
        setQueue((prev) =>
          prev.map((q) =>
            q.id === item.id
              ? { ...q, status: 'failed' as const, retries: q.retries + 1 }
              : q
          )
        );
      }
    }

    setState((prev) => ({
      ...prev,
      syncInProgress: false,
      lastSyncAt: new Date().toISOString(),
    }));

    syncInProgressRef.current = false;
  }, [queue, maxRetries]);

  // Clear entire queue
  const clearQueue = useCallback(() => {
    setQueue([]);
    localStorage.removeItem(storageKey);
  }, [storageKey]);

  return (
    <OfflineContext.Provider value={{ state, queueAction, syncNow, clearQueue }}>
      {children}
    </OfflineContext.Provider>
  );
};

// Offline indicator component
interface OfflineIndicatorProps {
  position?: 'top' | 'bottom';
  showQueueCount?: boolean;
}

const OfflineIndicator: React.FC<OfflineIndicatorProps> = ({
  position = 'bottom',
  showQueueCount = true,
}) => {
  const { state, syncNow } = useOffline();
  const [visible, setVisible] = useState(false);

  useEffect(() => {
    if (!state.isOnline || state.queuedActions > 0) {
      setVisible(true);
    } else {
      const timer = setTimeout(() => setVisible(false), 3000);
      return () => clearTimeout(timer);
    }
  }, [state.isOnline, state.queuedActions]);

  if (!visible && state.isOnline && state.queuedActions === 0) {
    return null;
  }

  return (
    <div className={`ict-offline-indicator ${position} ${!state.isOnline ? 'offline' : ''}`}>
      <div className="ict-offline-content">
        <span className="ict-offline-icon">
          {state.isOnline ? (
            <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor">
              <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z" />
            </svg>
          ) : (
            <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor">
              <path d="M23.64 7c-.45-.34-4.93-4-11.64-4-1.5 0-2.89.19-4.15.48L18.18 13.8 23.64 7zm-6.6 8.22L3.27 1.44 2 2.72l2.05 2.06C1.91 5.76.59 6.82.36 7L12 21.5l3.07-4.04 3.21 3.21 1.27-1.27-2.51-2.5z" />
            </svg>
          )}
        </span>

        <span className="ict-offline-text">
          {state.isOnline ? (
            state.queuedActions > 0 ? (
              <>
                {showQueueCount && `${state.queuedActions} pending`}
                {state.syncInProgress ? ' - Syncing...' : ''}
              </>
            ) : (
              'All synced'
            )
          ) : (
            'You are offline'
          )}
        </span>

        {state.isOnline && state.queuedActions > 0 && !state.syncInProgress && (
          <button className="ict-offline-sync-btn" onClick={syncNow}>
            Sync now
          </button>
        )}
      </div>

      <style>{`
        .ict-offline-indicator {
          position: fixed;
          left: 50%;
          transform: translateX(-50%);
          z-index: var(--ict-zindex-toast, 89900);
          padding: 10px 20px;
          background: var(--ict-bg-color, #fff);
          border: 1px solid var(--ict-border-color, #e5e7eb);
          border-radius: 24px;
          box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
          transition: all 0.3s;
        }

        .ict-offline-indicator.top {
          top: 16px;
        }

        .ict-offline-indicator.bottom {
          bottom: 16px;
        }

        .ict-offline-indicator.offline {
          background: #fef2f2;
          border-color: #fecaca;
          color: #dc2626;
        }

        .ict-offline-content {
          display: flex;
          align-items: center;
          gap: 10px;
          font-size: 13px;
          font-weight: 500;
        }

        .ict-offline-icon {
          display: flex;
          align-items: center;
          justify-content: center;
          color: var(--ict-success, #10b981);
        }

        .offline .ict-offline-icon {
          color: #dc2626;
        }

        .ict-offline-text {
          color: var(--ict-text-color, #1f2937);
        }

        .offline .ict-offline-text {
          color: #dc2626;
        }

        .ict-offline-sync-btn {
          background: var(--ict-primary, #3b82f6);
          color: #fff;
          border: none;
          padding: 4px 12px;
          border-radius: 12px;
          font-size: 12px;
          font-weight: 500;
          cursor: pointer;
          transition: background 0.2s;
        }

        .ict-offline-sync-btn:hover {
          background: var(--ict-primary-hover, #2563eb);
        }
      `}</style>
    </div>
  );
};

export default OfflineIndicator;
