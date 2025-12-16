/**
 * useDebounce Hook Tests
 *
 * @package ICT_Platform
 * @since   1.0.0
 */

import { renderHook, act } from '@testing-library/react';
import { useDebounce, useDebouncedCallback, useDebouncedSearch } from '../useDebounce';

// Use fake timers
jest.useFakeTimers();

describe('useDebounce', () => {
  it('returns initial value immediately', () => {
    const { result } = renderHook(() => useDebounce('initial', 500));

    expect(result.current).toBe('initial');
  });

  it('updates value after delay', () => {
    const { result, rerender } = renderHook(
      ({ value, delay }) => useDebounce(value, delay),
      { initialProps: { value: 'initial', delay: 500 } }
    );

    expect(result.current).toBe('initial');

    // Update value
    rerender({ value: 'updated', delay: 500 });

    // Value should still be initial
    expect(result.current).toBe('initial');

    // Fast forward time
    act(() => {
      jest.advanceTimersByTime(500);
    });

    // Now value should be updated
    expect(result.current).toBe('updated');
  });

  it('cancels previous timeout when value changes', () => {
    const { result, rerender } = renderHook(
      ({ value, delay }) => useDebounce(value, delay),
      { initialProps: { value: 'initial', delay: 500 } }
    );

    // First update
    rerender({ value: 'first', delay: 500 });

    // Advance halfway
    act(() => {
      jest.advanceTimersByTime(250);
    });

    // Second update (should cancel first)
    rerender({ value: 'second', delay: 500 });

    // Advance past when first would have fired
    act(() => {
      jest.advanceTimersByTime(300);
    });

    // Should still be initial (first was cancelled)
    expect(result.current).toBe('initial');

    // Advance to complete second debounce
    act(() => {
      jest.advanceTimersByTime(200);
    });

    // Now should be second
    expect(result.current).toBe('second');
  });

  it('works with different delay values', () => {
    const { result, rerender } = renderHook(
      ({ value, delay }) => useDebounce(value, delay),
      { initialProps: { value: 'initial', delay: 100 } }
    );

    rerender({ value: 'updated', delay: 100 });

    act(() => {
      jest.advanceTimersByTime(100);
    });

    expect(result.current).toBe('updated');
  });
});

describe('useDebouncedCallback', () => {
  it('calls callback after delay', () => {
    const callback = jest.fn();
    const { result } = renderHook(() => useDebouncedCallback(callback, 500));

    act(() => {
      result.current('arg1', 'arg2');
    });

    // Should not be called immediately
    expect(callback).not.toHaveBeenCalled();

    // Advance time
    act(() => {
      jest.advanceTimersByTime(500);
    });

    // Now should be called
    expect(callback).toHaveBeenCalledTimes(1);
    expect(callback).toHaveBeenCalledWith('arg1', 'arg2');
  });

  it('cancels previous calls', () => {
    const callback = jest.fn();
    const { result } = renderHook(() => useDebouncedCallback(callback, 500));

    act(() => {
      result.current('call1');
      result.current('call2');
      result.current('call3');
    });

    act(() => {
      jest.advanceTimersByTime(500);
    });

    // Should only be called once with last arguments
    expect(callback).toHaveBeenCalledTimes(1);
    expect(callback).toHaveBeenCalledWith('call3');
  });
});

describe('useDebouncedSearch', () => {
  it('returns search term and debounced search term', () => {
    const { result } = renderHook(() => useDebouncedSearch('', 300));

    expect(result.current.searchTerm).toBe('');
    expect(result.current.debouncedSearchTerm).toBe('');
    expect(result.current.isSearching).toBe(false);
  });

  it('updates search term immediately', () => {
    const { result } = renderHook(() => useDebouncedSearch('', 300));

    act(() => {
      result.current.setSearchTerm('test');
    });

    expect(result.current.searchTerm).toBe('test');
    expect(result.current.debouncedSearchTerm).toBe('');
    expect(result.current.isSearching).toBe(true);
  });

  it('updates debounced search term after delay', () => {
    const { result } = renderHook(() => useDebouncedSearch('', 300));

    act(() => {
      result.current.setSearchTerm('test');
    });

    act(() => {
      jest.advanceTimersByTime(300);
    });

    expect(result.current.searchTerm).toBe('test');
    expect(result.current.debouncedSearchTerm).toBe('test');
    expect(result.current.isSearching).toBe(false);
  });
});
