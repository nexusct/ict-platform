/**
 * Global Search Component
 *
 * Universal search with saved filters and quick access.
 *
 * @package ICT_Platform
 * @since   2.1.0
 */

import React, { useState, useEffect, useRef, useCallback } from 'react';
import { useDebounce } from '../../hooks/useDebounce';
import type { GlobalSearchResult, SavedFilter } from '../../types';

interface GlobalSearchProps {
  onResultSelect?: (result: GlobalSearchResult) => void;
  placeholder?: string;
  searchEndpoint?: string;
}

const GlobalSearch: React.FC<GlobalSearchProps> = ({
  onResultSelect,
  placeholder = 'Search projects, time entries, inventory...',
  searchEndpoint = '/wp-json/ict/v1/search',
}) => {
  const [query, setQuery] = useState('');
  const [isOpen, setIsOpen] = useState(false);
  const [isLoading, setIsLoading] = useState(false);
  const [results, setResults] = useState<GlobalSearchResult[]>([]);
  const [recentSearches, setRecentSearches] = useState<string[]>([]);
  const [savedFilters, setSavedFilters] = useState<SavedFilter[]>([]);
  const [activeIndex, setActiveIndex] = useState(-1);

  const inputRef = useRef<HTMLInputElement>(null);
  const containerRef = useRef<HTMLDivElement>(null);
  const debouncedQuery = useDebounce(query, 300);

  // Load recent searches from localStorage
  useEffect(() => {
    const saved = localStorage.getItem('ict_recent_searches');
    if (saved) {
      setRecentSearches(JSON.parse(saved));
    }
  }, []);

  // Perform search
  useEffect(() => {
    if (debouncedQuery.length < 2) {
      setResults([]);
      return;
    }

    const performSearch = async () => {
      setIsLoading(true);
      try {
        const response = await fetch(
          `${searchEndpoint}?q=${encodeURIComponent(debouncedQuery)}`,
          {
            headers: {
              'X-WP-Nonce': (window as any).ictSettings?.nonce || '',
            },
          }
        );
        const data = await response.json();
        setResults(data.data || []);
      } catch (error) {
        console.error('Search error:', error);
        setResults([]);
      } finally {
        setIsLoading(false);
      }
    };

    performSearch();
  }, [debouncedQuery, searchEndpoint]);

  // Handle click outside
  useEffect(() => {
    const handleClickOutside = (event: MouseEvent) => {
      if (containerRef.current && !containerRef.current.contains(event.target as Node)) {
        setIsOpen(false);
      }
    };

    document.addEventListener('mousedown', handleClickOutside);
    return () => document.removeEventListener('mousedown', handleClickOutside);
  }, []);

  // Keyboard shortcuts
  useEffect(() => {
    const handleKeyDown = (event: KeyboardEvent) => {
      // Cmd/Ctrl + K to focus search
      if ((event.metaKey || event.ctrlKey) && event.key === 'k') {
        event.preventDefault();
        inputRef.current?.focus();
        setIsOpen(true);
      }

      // Escape to close
      if (event.key === 'Escape') {
        setIsOpen(false);
        inputRef.current?.blur();
      }
    };

    document.addEventListener('keydown', handleKeyDown);
    return () => document.removeEventListener('keydown', handleKeyDown);
  }, []);

  // Handle keyboard navigation in results
  const handleKeyDown = useCallback(
    (event: React.KeyboardEvent) => {
      if (!isOpen) return;

      switch (event.key) {
        case 'ArrowDown':
          event.preventDefault();
          setActiveIndex((prev) => Math.min(prev + 1, results.length - 1));
          break;
        case 'ArrowUp':
          event.preventDefault();
          setActiveIndex((prev) => Math.max(prev - 1, -1));
          break;
        case 'Enter':
          event.preventDefault();
          if (activeIndex >= 0 && results[activeIndex]) {
            handleResultSelect(results[activeIndex]);
          }
          break;
      }
    },
    [isOpen, results, activeIndex]
  );

  // Handle result selection
  const handleResultSelect = (result: GlobalSearchResult) => {
    // Save to recent searches
    const newRecent = [query, ...recentSearches.filter((s) => s !== query)].slice(0, 5);
    setRecentSearches(newRecent);
    localStorage.setItem('ict_recent_searches', JSON.stringify(newRecent));

    // Close and clear
    setIsOpen(false);
    setQuery('');
    setActiveIndex(-1);

    // Navigate or callback
    if (onResultSelect) {
      onResultSelect(result);
    } else if (result.url) {
      window.location.href = result.url;
    }
  };

  // Get icon for result type
  const getTypeIcon = (type: string): string => {
    const icons: Record<string, string> = {
      project: '📁',
      'time-entry': '⏱️',
      inventory: '📦',
      equipment: '🔧',
      document: '📄',
      vehicle: '🚗',
      expense: '💰',
      user: '👤',
    };
    return icons[type] || '🔍';
  };

  return (
    <div className="ict-global-search" ref={containerRef}>
      <div className="ict-search-input-wrapper">
        <span className="ict-search-icon">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
            <circle cx="11" cy="11" r="8" />
            <path d="M21 21l-4.35-4.35" />
          </svg>
        </span>
        <input
          ref={inputRef}
          type="text"
          className="ict-search-input"
          placeholder={placeholder}
          value={query}
          onChange={(e) => {
            setQuery(e.target.value);
            setIsOpen(true);
            setActiveIndex(-1);
          }}
          onFocus={() => setIsOpen(true)}
          onKeyDown={handleKeyDown}
        />
        <span className="ict-search-shortcut">
          <kbd>⌘</kbd>
          <kbd>K</kbd>
        </span>
        {isLoading && (
          <span className="ict-search-spinner">
            <svg className="animate-spin" width="16" height="16" viewBox="0 0 24 24">
              <circle cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" fill="none" opacity="0.3" />
              <path d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" fill="currentColor" />
            </svg>
          </span>
        )}
      </div>

      {isOpen && (
        <div className="ict-search-dropdown">
          {query.length < 2 && recentSearches.length > 0 && (
            <div className="ict-search-section">
              <div className="ict-search-section-header">Recent Searches</div>
              {recentSearches.map((search, index) => (
                <div
                  key={index}
                  className="ict-search-recent-item"
                  onClick={() => {
                    setQuery(search);
                    inputRef.current?.focus();
                  }}
                >
                  <span className="ict-search-recent-icon">🕐</span>
                  <span>{search}</span>
                </div>
              ))}
            </div>
          )}

          {results.length > 0 && (
            <div className="ict-search-section">
              <div className="ict-search-section-header">
                Results ({results.length})
              </div>
              {results.map((result, index) => (
                <div
                  key={`${result.type}-${result.id}`}
                  className={`ict-search-result ${activeIndex === index ? 'active' : ''}`}
                  onClick={() => handleResultSelect(result)}
                  onMouseEnter={() => setActiveIndex(index)}
                >
                  <span className="ict-search-result-icon">
                    {getTypeIcon(result.type)}
                  </span>
                  <div className="ict-search-result-content">
                    <div className="ict-search-result-title">
                      {result.highlight ? (
                        <span dangerouslySetInnerHTML={{ __html: result.highlight }} />
                      ) : (
                        result.title
                      )}
                    </div>
                    {result.subtitle && (
                      <div className="ict-search-result-subtitle">{result.subtitle}</div>
                    )}
                  </div>
                  <span className="ict-search-result-type">{result.type}</span>
                </div>
              ))}
            </div>
          )}

          {query.length >= 2 && results.length === 0 && !isLoading && (
            <div className="ict-search-empty">
              <span>No results found for "{query}"</span>
            </div>
          )}

          <div className="ict-search-footer">
            <span>Navigate with <kbd>↑</kbd> <kbd>↓</kbd></span>
            <span>Select with <kbd>Enter</kbd></span>
            <span>Close with <kbd>Esc</kbd></span>
          </div>
        </div>
      )}

      <style>{`
        .ict-global-search {
          position: relative;
          width: 100%;
          max-width: 600px;
        }

        .ict-search-input-wrapper {
          position: relative;
          display: flex;
          align-items: center;
        }

        .ict-search-icon {
          position: absolute;
          left: 12px;
          color: var(--ict-text-muted, #9ca3af);
        }

        .ict-search-input {
          width: 100%;
          padding: 10px 40px 10px 40px;
          font-size: 14px;
          border: 1px solid var(--ict-border-color, #e5e7eb);
          border-radius: 8px;
          background: var(--ict-bg-color, #fff);
          color: var(--ict-text-color, #1f2937);
          transition: border-color 0.2s, box-shadow 0.2s;
        }

        .ict-search-input:focus {
          outline: none;
          border-color: var(--ict-primary, #3b82f6);
          box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }

        .ict-search-shortcut {
          position: absolute;
          right: 12px;
          display: flex;
          gap: 4px;
        }

        .ict-search-shortcut kbd {
          padding: 2px 6px;
          font-size: 11px;
          font-family: inherit;
          background: var(--ict-bg-secondary, #f3f4f6);
          border: 1px solid var(--ict-border-color, #e5e7eb);
          border-radius: 4px;
          color: var(--ict-text-muted, #9ca3af);
        }

        .ict-search-spinner {
          position: absolute;
          right: 80px;
          color: var(--ict-primary, #3b82f6);
        }

        .animate-spin {
          animation: spin 1s linear infinite;
        }

        @keyframes spin {
          from { transform: rotate(0deg); }
          to { transform: rotate(360deg); }
        }

        .ict-search-dropdown {
          position: absolute;
          top: calc(100% + 8px);
          left: 0;
          right: 0;
          background: var(--ict-bg-color, #fff);
          border: 1px solid var(--ict-border-color, #e5e7eb);
          border-radius: 12px;
          box-shadow: 0 10px 40px rgba(0, 0, 0, 0.15);
          max-height: 400px;
          overflow-y: auto;
          z-index: var(--ict-zindex-dropdown, 89200);
        }

        .ict-search-section {
          padding: 8px 0;
        }

        .ict-search-section-header {
          padding: 8px 16px;
          font-size: 11px;
          font-weight: 600;
          text-transform: uppercase;
          letter-spacing: 0.5px;
          color: var(--ict-text-muted, #9ca3af);
        }

        .ict-search-recent-item {
          padding: 8px 16px;
          display: flex;
          align-items: center;
          gap: 12px;
          cursor: pointer;
          transition: background 0.15s;
        }

        .ict-search-recent-item:hover {
          background: var(--ict-bg-hover, #f3f4f6);
        }

        .ict-search-result {
          padding: 10px 16px;
          display: flex;
          align-items: center;
          gap: 12px;
          cursor: pointer;
          transition: background 0.15s;
        }

        .ict-search-result:hover,
        .ict-search-result.active {
          background: var(--ict-bg-hover, #f3f4f6);
        }

        .ict-search-result-icon {
          font-size: 18px;
          flex-shrink: 0;
        }

        .ict-search-result-content {
          flex: 1;
          min-width: 0;
        }

        .ict-search-result-title {
          font-size: 14px;
          font-weight: 500;
          color: var(--ict-text-color, #1f2937);
          white-space: nowrap;
          overflow: hidden;
          text-overflow: ellipsis;
        }

        .ict-search-result-title mark {
          background: #fef3c7;
          color: inherit;
          padding: 0 2px;
          border-radius: 2px;
        }

        .ict-search-result-subtitle {
          font-size: 12px;
          color: var(--ict-text-muted, #6b7280);
          white-space: nowrap;
          overflow: hidden;
          text-overflow: ellipsis;
        }

        .ict-search-result-type {
          font-size: 11px;
          padding: 2px 8px;
          background: var(--ict-bg-secondary, #f3f4f6);
          border-radius: 4px;
          color: var(--ict-text-muted, #6b7280);
          flex-shrink: 0;
        }

        .ict-search-empty {
          padding: 32px 16px;
          text-align: center;
          color: var(--ict-text-muted, #6b7280);
        }

        .ict-search-footer {
          padding: 8px 16px;
          display: flex;
          gap: 16px;
          font-size: 11px;
          color: var(--ict-text-muted, #9ca3af);
          border-top: 1px solid var(--ict-border-color, #e5e7eb);
          background: var(--ict-bg-secondary, #f9fafb);
          border-radius: 0 0 12px 12px;
        }

        .ict-search-footer kbd {
          padding: 1px 4px;
          font-size: 10px;
          background: var(--ict-bg-color, #fff);
          border: 1px solid var(--ict-border-color, #e5e7eb);
          border-radius: 3px;
        }
      `}</style>
    </div>
  );
};

export default GlobalSearch;
