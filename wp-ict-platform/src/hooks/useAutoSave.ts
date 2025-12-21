/**
 * Auto-Save Hook
 *
 * Automatically saves form data after changes with debouncing
 *
 * @package ICT_Platform
 * @since   1.0.0
 */

import { useState, useEffect, useRef, useCallback } from 'react';
import { useDebouncedCallback } from './useDebounce';

interface UseAutoSaveOptions<T> {
  data: T;
  onSave: (data: T) => Promise<void> | void;
  delay?: number;
  enabled?: boolean;
  storageKey?: string;
  onError?: (error: Error) => void;
  onSuccess?: () => void;
}

interface UseAutoSaveReturn {
  isSaving: boolean;
  lastSaved: Date | null;
  error: Error | null;
  saveNow: () => Promise<void>;
  clearDraft: () => void;
  hasDraft: boolean;
}

export function useAutoSave<T>({
  data,
  onSave,
  delay = 2000,
  enabled = true,
  storageKey,
  onError,
  onSuccess,
}: UseAutoSaveOptions<T>): UseAutoSaveReturn {
  const [isSaving, setIsSaving] = useState(false);
  const [lastSaved, setLastSaved] = useState<Date | null>(null);
  const [error, setError] = useState<Error | null>(null);
  const [hasDraft, setHasDraft] = useState(false);

  const previousDataRef = useRef<string>('');
  const isFirstRenderRef = useRef(true);

  // Check for existing draft on mount
  useEffect(() => {
    if (storageKey) {
      const draft = localStorage.getItem(`autosave_${storageKey}`);
      setHasDraft(!!draft);
    }
  }, [storageKey]);

  // Save to localStorage
  const saveToStorage = useCallback(
    (dataToSave: T) => {
      if (storageKey) {
        try {
          localStorage.setItem(`autosave_${storageKey}`, JSON.stringify(dataToSave));
          setHasDraft(true);
        } catch (e) {
          console.warn('Failed to save draft to localStorage:', e);
        }
      }
    },
    [storageKey]
  );

  // Clear draft from localStorage
  const clearDraft = useCallback(() => {
    if (storageKey) {
      localStorage.removeItem(`autosave_${storageKey}`);
      setHasDraft(false);
    }
  }, [storageKey]);

  // Perform save
  const performSave = useCallback(
    async (dataToSave: T) => {
      setIsSaving(true);
      setError(null);

      try {
        await onSave(dataToSave);
        setLastSaved(new Date());
        clearDraft(); // Clear draft after successful save
        onSuccess?.();
      } catch (e) {
        const err = e instanceof Error ? e : new Error('Auto-save failed');
        setError(err);
        saveToStorage(dataToSave); // Keep draft on error
        onError?.(err);
      } finally {
        setIsSaving(false);
      }
    },
    [onSave, onSuccess, onError, clearDraft, saveToStorage]
  );

  // Debounced save
  // eslint-disable-next-line @typescript-eslint/no-explicit-any
  const debouncedSave = useDebouncedCallback((...args: any[]) => {
    performSave(args[0] as T);
  }, delay) as (data: T) => void;

  // Auto-save on data change
  useEffect(() => {
    if (!enabled) return;

    // Skip first render
    if (isFirstRenderRef.current) {
      isFirstRenderRef.current = false;
      previousDataRef.current = JSON.stringify(data);
      return;
    }

    const currentDataStr = JSON.stringify(data);

    // Only save if data actually changed
    if (currentDataStr !== previousDataRef.current) {
      previousDataRef.current = currentDataStr;
      saveToStorage(data); // Immediately save to localStorage
      debouncedSave(data);
    }
  }, [data, enabled, debouncedSave, saveToStorage]);

  // Immediate save function
  const saveNow = useCallback(async () => {
    await performSave(data);
  }, [data, performSave]);

  return {
    isSaving,
    lastSaved,
    error,
    saveNow,
    clearDraft,
    hasDraft,
  };
}

/**
 * Hook to restore auto-saved draft
 */
export function useRestoreDraft<T>(
  storageKey: string,
  onRestore?: (data: T) => void
): {
  draft: T | null;
  hasDraft: boolean;
  restoreDraft: () => T | null;
  clearDraft: () => void;
} {
  const [draft, setDraft] = useState<T | null>(null);
  const [hasDraft, setHasDraft] = useState(false);

  useEffect(() => {
    try {
      const saved = localStorage.getItem(`autosave_${storageKey}`);
      if (saved) {
        const parsed = JSON.parse(saved) as T;
        setDraft(parsed);
        setHasDraft(true);
      }
    } catch {
      // Invalid JSON or storage error
      setHasDraft(false);
    }
  }, [storageKey]);

  const restoreDraft = useCallback(() => {
    if (draft) {
      onRestore?.(draft);
    }
    return draft;
  }, [draft, onRestore]);

  const clearDraft = useCallback(() => {
    localStorage.removeItem(`autosave_${storageKey}`);
    setDraft(null);
    setHasDraft(false);
  }, [storageKey]);

  return {
    draft,
    hasDraft,
    restoreDraft,
    clearDraft,
  };
}

export default useAutoSave;
