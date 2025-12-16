/**
 * Online Status Hook
 *
 * Tracks network connectivity and provides offline notification
 *
 * @package ICT_Platform
 * @since   1.0.0
 */

import { useState, useEffect, useCallback } from 'react';

interface UseOnlineStatusOptions {
  onOnline?: () => void;
  onOffline?: () => void;
  pingUrl?: string;
  pingInterval?: number;
}

interface OnlineStatus {
  isOnline: boolean;
  wasOffline: boolean;
  lastOnline: Date | null;
  checkConnection: () => Promise<boolean>;
}

export function useOnlineStatus(options: UseOnlineStatusOptions = {}): OnlineStatus {
  const { onOnline, onOffline, pingUrl, pingInterval = 30000 } = options;

  const [isOnline, setIsOnline] = useState(navigator.onLine);
  const [wasOffline, setWasOffline] = useState(false);
  const [lastOnline, setLastOnline] = useState<Date | null>(
    navigator.onLine ? new Date() : null
  );

  // Check actual connectivity by pinging a URL
  const checkConnection = useCallback(async (): Promise<boolean> => {
    if (!pingUrl) {
      return navigator.onLine;
    }

    try {
      const response = await fetch(pingUrl, {
        method: 'HEAD',
        cache: 'no-store',
        mode: 'no-cors',
      });
      return true;
    } catch {
      return false;
    }
  }, [pingUrl]);

  useEffect(() => {
    const handleOnline = () => {
      setIsOnline(true);
      setLastOnline(new Date());
      onOnline?.();
    };

    const handleOffline = () => {
      setIsOnline(false);
      setWasOffline(true);
      onOffline?.();
    };

    window.addEventListener('online', handleOnline);
    window.addEventListener('offline', handleOffline);

    // Optional: Periodic connectivity check
    let intervalId: ReturnType<typeof setInterval> | null = null;
    if (pingUrl && pingInterval > 0) {
      intervalId = setInterval(async () => {
        const connected = await checkConnection();
        if (connected !== isOnline) {
          if (connected) {
            handleOnline();
          } else {
            handleOffline();
          }
        }
      }, pingInterval);
    }

    return () => {
      window.removeEventListener('online', handleOnline);
      window.removeEventListener('offline', handleOffline);
      if (intervalId) {
        clearInterval(intervalId);
      }
    };
  }, [onOnline, onOffline, pingUrl, pingInterval, checkConnection, isOnline]);

  return {
    isOnline,
    wasOffline,
    lastOnline,
    checkConnection,
  };
}

// Simple online/offline hook
export function useIsOnline(): boolean {
  const [isOnline, setIsOnline] = useState(navigator.onLine);

  useEffect(() => {
    const handleOnline = () => setIsOnline(true);
    const handleOffline = () => setIsOnline(false);

    window.addEventListener('online', handleOnline);
    window.addEventListener('offline', handleOffline);

    return () => {
      window.removeEventListener('online', handleOnline);
      window.removeEventListener('offline', handleOffline);
    };
  }, []);

  return isOnline;
}

export default useOnlineStatus;
