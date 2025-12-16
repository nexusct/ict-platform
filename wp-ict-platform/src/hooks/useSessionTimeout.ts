/**
 * Session Timeout Hook
 *
 * Warns users before their session expires and handles re-authentication
 *
 * @package ICT_Platform
 * @since   1.0.0
 */

import { useState, useEffect, useCallback, useRef } from 'react';

interface UseSessionTimeoutOptions {
  timeout?: number; // Total session timeout in ms
  warningTime?: number; // Time before timeout to show warning in ms
  onTimeout?: () => void;
  onWarning?: (remainingTime: number) => void;
  onActivity?: () => void;
  activityEvents?: string[];
}

interface UseSessionTimeoutReturn {
  isWarningShown: boolean;
  remainingTime: number;
  extendSession: () => void;
  resetTimer: () => void;
}

export function useSessionTimeout(
  options: UseSessionTimeoutOptions = {}
): UseSessionTimeoutReturn {
  const {
    timeout = 30 * 60 * 1000, // 30 minutes default
    warningTime = 5 * 60 * 1000, // 5 minutes warning
    onTimeout,
    onWarning,
    onActivity,
    activityEvents = ['mousedown', 'keydown', 'scroll', 'touchstart'],
  } = options;

  const [isWarningShown, setIsWarningShown] = useState(false);
  const [remainingTime, setRemainingTime] = useState(timeout);

  const timeoutRef = useRef<ReturnType<typeof setTimeout> | null>(null);
  const warningRef = useRef<ReturnType<typeof setTimeout> | null>(null);
  const countdownRef = useRef<ReturnType<typeof setInterval> | null>(null);
  const lastActivityRef = useRef(Date.now());

  const clearAllTimers = useCallback(() => {
    if (timeoutRef.current) clearTimeout(timeoutRef.current);
    if (warningRef.current) clearTimeout(warningRef.current);
    if (countdownRef.current) clearInterval(countdownRef.current);
  }, []);

  const handleTimeout = useCallback(() => {
    clearAllTimers();
    setIsWarningShown(false);
    onTimeout?.();
  }, [clearAllTimers, onTimeout]);

  const startCountdown = useCallback(() => {
    const endTime = lastActivityRef.current + timeout;

    countdownRef.current = setInterval(() => {
      const remaining = Math.max(0, endTime - Date.now());
      setRemainingTime(remaining);

      if (remaining <= 0) {
        handleTimeout();
      }
    }, 1000);
  }, [timeout, handleTimeout]);

  const showWarning = useCallback(() => {
    setIsWarningShown(true);
    const remaining = Math.max(0, lastActivityRef.current + timeout - Date.now());
    setRemainingTime(remaining);
    onWarning?.(remaining);
    startCountdown();
  }, [timeout, onWarning, startCountdown]);

  const resetTimer = useCallback(() => {
    clearAllTimers();
    setIsWarningShown(false);
    lastActivityRef.current = Date.now();
    setRemainingTime(timeout);

    // Set warning timer
    warningRef.current = setTimeout(() => {
      showWarning();
    }, timeout - warningTime);

    // Set timeout timer
    timeoutRef.current = setTimeout(() => {
      handleTimeout();
    }, timeout);
  }, [clearAllTimers, timeout, warningTime, showWarning, handleTimeout]);

  const extendSession = useCallback(() => {
    resetTimer();
    onActivity?.();
  }, [resetTimer, onActivity]);

  // Handle user activity
  const handleActivity = useCallback(() => {
    // Only reset if warning hasn't been shown yet
    if (!isWarningShown) {
      lastActivityRef.current = Date.now();
      // Debounce activity resets
      clearAllTimers();
      resetTimer();
      onActivity?.();
    }
  }, [isWarningShown, clearAllTimers, resetTimer, onActivity]);

  // Set up activity listeners
  useEffect(() => {
    // Initial timer setup
    resetTimer();

    // Add activity listeners
    activityEvents.forEach((event) => {
      window.addEventListener(event, handleActivity, { passive: true });
    });

    return () => {
      clearAllTimers();
      activityEvents.forEach((event) => {
        window.removeEventListener(event, handleActivity);
      });
    };
  }, [activityEvents, handleActivity, resetTimer, clearAllTimers]);

  return {
    isWarningShown,
    remainingTime,
    extendSession,
    resetTimer,
  };
}

/**
 * Format remaining time for display
 */
export function formatRemainingTime(ms: number): string {
  const minutes = Math.floor(ms / 60000);
  const seconds = Math.floor((ms % 60000) / 1000);

  if (minutes > 0) {
    return `${minutes}m ${seconds}s`;
  }
  return `${seconds}s`;
}

export default useSessionTimeout;
