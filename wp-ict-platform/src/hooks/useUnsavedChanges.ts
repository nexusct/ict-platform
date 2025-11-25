/**
 * Unsaved Changes Hook
 *
 * Warns users before leaving a page with unsaved changes
 *
 * @package ICT_Platform
 * @since   1.0.0
 */

import { useState, useEffect, useCallback, useRef } from 'react';

interface UseUnsavedChangesOptions {
  enabled?: boolean;
  message?: string;
  onBeforeUnload?: () => boolean;
}

interface UseUnsavedChangesReturn {
  hasUnsavedChanges: boolean;
  setHasUnsavedChanges: (value: boolean) => void;
  markAsChanged: () => void;
  markAsSaved: () => void;
  confirmNavigation: (callback: () => void) => void;
}

export function useUnsavedChanges(
  options: UseUnsavedChangesOptions = {}
): UseUnsavedChangesReturn {
  const {
    enabled = true,
    message = 'You have unsaved changes. Are you sure you want to leave?',
    onBeforeUnload,
  } = options;

  const [hasUnsavedChanges, setHasUnsavedChanges] = useState(false);
  const messageRef = useRef(message);

  // Update message ref
  useEffect(() => {
    messageRef.current = message;
  }, [message]);

  // Browser beforeunload handler
  useEffect(() => {
    if (!enabled || !hasUnsavedChanges) return;

    const handleBeforeUnload = (e: BeforeUnloadEvent) => {
      // Allow custom check
      if (onBeforeUnload && !onBeforeUnload()) {
        return;
      }

      e.preventDefault();
      e.returnValue = messageRef.current;
      return messageRef.current;
    };

    window.addEventListener('beforeunload', handleBeforeUnload);
    return () => window.removeEventListener('beforeunload', handleBeforeUnload);
  }, [enabled, hasUnsavedChanges, onBeforeUnload]);

  const markAsChanged = useCallback(() => {
    setHasUnsavedChanges(true);
  }, []);

  const markAsSaved = useCallback(() => {
    setHasUnsavedChanges(false);
  }, []);

  const confirmNavigation = useCallback(
    (callback: () => void) => {
      if (!hasUnsavedChanges) {
        callback();
        return;
      }

      const confirmed = window.confirm(messageRef.current);
      if (confirmed) {
        setHasUnsavedChanges(false);
        callback();
      }
    },
    [hasUnsavedChanges]
  );

  return {
    hasUnsavedChanges,
    setHasUnsavedChanges,
    markAsChanged,
    markAsSaved,
    confirmNavigation,
  };
}

/**
 * Track changes in form data automatically
 */
export function useFormChanges<T extends Record<string, unknown>>(
  initialData: T,
  currentData: T
): {
  hasChanges: boolean;
  changedFields: (keyof T)[];
  resetChanges: () => void;
} {
  const [baseline, setBaseline] = useState<T>(initialData);

  const changedFields = Object.keys(currentData).filter((key) => {
    const k = key as keyof T;
    return JSON.stringify(currentData[k]) !== JSON.stringify(baseline[k]);
  }) as (keyof T)[];

  const hasChanges = changedFields.length > 0;

  const resetChanges = useCallback(() => {
    setBaseline(currentData);
  }, [currentData]);

  return {
    hasChanges,
    changedFields,
    resetChanges,
  };
}

export default useUnsavedChanges;
