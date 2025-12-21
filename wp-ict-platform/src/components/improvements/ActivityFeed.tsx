/**
 * Activity Feed Component
 *
 * Real-time activity stream with filtering and notifications.
 *
 * @package ICT_Platform
 * @since   2.1.0
 */

import React, { useState, useEffect, useCallback, useRef } from 'react';
import type { ActivityLogEntry } from '../../types';

interface ActivityFeedProps {
  apiEndpoint?: string;
  entityType?: string;
  entityId?: number;
  limit?: number;
  autoRefresh?: boolean;
  refreshInterval?: number;
  showFilters?: boolean;
}

const ActivityFeed: React.FC<ActivityFeedProps> = ({
  apiEndpoint = '/wp-json/ict/v1/activity',
  entityType,
  entityId,
  limit = 50,
  autoRefresh = true,
  refreshInterval = 30000,
  showFilters = true,
}) => {
  const [activities, setActivities] = useState<ActivityLogEntry[]>([]);
  const [isLoading, setIsLoading] = useState(true);
  const [hasMore, setHasMore] = useState(true);
  const [page, setPage] = useState(1);
  const [filterAction, setFilterAction] = useState<string>('all');
  const [filterEntity, setFilterEntity] = useState<string>('all');
  const [expandedId, setExpandedId] = useState<number | null>(null);

  const containerRef = useRef<HTMLDivElement>(null);
  const lastRefreshRef = useRef<number>(Date.now());

  // Fetch activities
  const fetchActivities = useCallback(
    async (pageNum: number, append = false) => {
      try {
        const params = new URLSearchParams({
          page: pageNum.toString(),
          per_page: limit.toString(),
        });

        if (entityType) params.append('entity_type', entityType);
        if (entityId) params.append('entity_id', entityId.toString());
        if (filterAction !== 'all') params.append('action', filterAction);
        if (filterEntity !== 'all') params.append('entity_filter', filterEntity);

        const response = await fetch(`${apiEndpoint}?${params.toString()}`, {
          headers: {
            'X-WP-Nonce': (window as any).ictSettings?.nonce || '',
          },
        });

        const data = await response.json();
        const newActivities = data.data || [];

        if (append) {
          setActivities((prev) => [...prev, ...newActivities]);
        } else {
          setActivities(newActivities);
        }

        setHasMore(newActivities.length === limit);
        lastRefreshRef.current = Date.now();
      } catch (error) {
        console.error('Failed to fetch activities:', error);
      } finally {
        setIsLoading(false);
      }
    },
    [apiEndpoint, entityType, entityId, limit, filterAction, filterEntity]
  );

  // Initial load and filter changes
  useEffect(() => {
    setIsLoading(true);
    setPage(1);
    fetchActivities(1, false);
  }, [fetchActivities]);

  // Auto-refresh
  useEffect(() => {
    if (!autoRefresh) return;

    const intervalId = setInterval(() => {
      fetchActivities(1, false);
    }, refreshInterval);

    return () => clearInterval(intervalId);
  }, [autoRefresh, refreshInterval, fetchActivities]);

  // Load more
  const loadMore = () => {
    const nextPage = page + 1;
    setPage(nextPage);
    fetchActivities(nextPage, true);
  };

  // Get action icon
  const getActionIcon = (action: string) => {
    const icons: Record<string, JSX.Element> = {
      created: (
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
          <circle cx="12" cy="12" r="10" />
          <path d="M12 8v8M8 12h8" />
        </svg>
      ),
      updated: (
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
          <path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7" />
          <path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z" />
        </svg>
      ),
      deleted: (
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
          <path d="M3 6h18M19 6v14a2 2 0 01-2 2H7a2 2 0 01-2-2V6m3 0V4a2 2 0 012-2h4a2 2 0 012 2v2" />
        </svg>
      ),
      synced: (
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
          <path d="M23 4v6h-6M1 20v-6h6" />
          <path d="M3.51 9a9 9 0 0114.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0020.49 15" />
        </svg>
      ),
      approved: (
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
          <path d="M22 11.08V12a10 10 0 11-5.93-9.14" />
          <path d="M22 4L12 14.01l-3-3" />
        </svg>
      ),
      rejected: (
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
          <circle cx="12" cy="12" r="10" />
          <path d="M15 9l-6 6M9 9l6 6" />
        </svg>
      ),
      assigned: (
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
          <path d="M16 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2" />
          <circle cx="8.5" cy="7" r="4" />
          <path d="M20 8v6M23 11h-6" />
        </svg>
      ),
      comment: (
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
          <path d="M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2z" />
        </svg>
      ),
    };
    return icons[action] || icons.updated;
  };

  // Get action color
  const getActionColor = (action: string): string => {
    const colors: Record<string, string> = {
      created: '#10b981',
      updated: '#3b82f6',
      deleted: '#ef4444',
      synced: '#8b5cf6',
      approved: '#10b981',
      rejected: '#ef4444',
      assigned: '#f59e0b',
      comment: '#6b7280',
    };
    return colors[action] || '#6b7280';
  };

  // Get entity icon
  const getEntityIcon = (type: string): string => {
    const icons: Record<string, string> = {
      project: '📁',
      'time-entry': '⏱️',
      inventory: '📦',
      equipment: '🔧',
      expense: '💰',
      document: '📄',
      user: '👤',
      vehicle: '🚗',
    };
    return icons[type] || '📋';
  };

  // Format relative time
  const formatRelativeTime = (timestamp: string): string => {
    const now = new Date();
    const date = new Date(timestamp);
    const diffMs = now.getTime() - date.getTime();
    const diffMins = Math.floor(diffMs / 60000);
    const diffHours = Math.floor(diffMs / 3600000);
    const diffDays = Math.floor(diffMs / 86400000);

    if (diffMins < 1) return 'Just now';
    if (diffMins < 60) return `${diffMins}m ago`;
    if (diffHours < 24) return `${diffHours}h ago`;
    if (diffDays < 7) return `${diffDays}d ago`;
    return date.toLocaleDateString();
  };

  // Render changes
  const renderChanges = (changes: ActivityLogEntry['changes']) => {
    if (!changes || Object.keys(changes).length === 0) return null;

    return (
      <div className="ict-activity-changes">
        {Object.entries(changes).map(([field, change]) => (
          <div key={field} className="ict-activity-change">
            <span className="ict-activity-change-field">{field}:</span>
            {change.old && (
              <span className="ict-activity-change-old">{String(change.old)}</span>
            )}
            <span className="ict-activity-change-arrow">→</span>
            <span className="ict-activity-change-new">{String(change.new)}</span>
          </div>
        ))}
      </div>
    );
  };

  return (
    <div className="ict-activity-feed" ref={containerRef}>
      {/* Header */}
      <div className="ict-activity-header">
        <h3>Activity Feed</h3>
        {autoRefresh && (
          <span className="ict-activity-refresh-indicator">
            <span className="ict-activity-refresh-dot" />
            Live
          </span>
        )}
      </div>

      {/* Filters */}
      {showFilters && (
        <div className="ict-activity-filters">
          <select
            value={filterAction}
            onChange={(e) => setFilterAction(e.target.value)}
            className="ict-activity-filter"
          >
            <option value="all">All Actions</option>
            <option value="created">Created</option>
            <option value="updated">Updated</option>
            <option value="deleted">Deleted</option>
            <option value="synced">Synced</option>
            <option value="approved">Approved</option>
            <option value="assigned">Assigned</option>
          </select>

          <select
            value={filterEntity}
            onChange={(e) => setFilterEntity(e.target.value)}
            className="ict-activity-filter"
          >
            <option value="all">All Types</option>
            <option value="project">Projects</option>
            <option value="time-entry">Time Entries</option>
            <option value="inventory">Inventory</option>
            <option value="equipment">Equipment</option>
            <option value="expense">Expenses</option>
            <option value="document">Documents</option>
          </select>
        </div>
      )}

      {/* Activity list */}
      <div className="ict-activity-list">
        {isLoading && activities.length === 0 ? (
          <div className="ict-activity-loading">
            <div className="ict-activity-spinner" />
            Loading activities...
          </div>
        ) : activities.length === 0 ? (
          <div className="ict-activity-empty">
            <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.5">
              <circle cx="12" cy="12" r="10" />
              <path d="M12 6v6l4 2" />
            </svg>
            <span>No activities yet</span>
          </div>
        ) : (
          <>
            {activities.map((activity) => (
              <div
                key={activity.id}
                className={`ict-activity-item ${expandedId === activity.id ? 'expanded' : ''}`}
                onClick={() => setExpandedId(expandedId === activity.id ? null : activity.id)}
              >
                <div
                  className="ict-activity-icon"
                  style={{ backgroundColor: getActionColor(activity.action) }}
                >
                  {getActionIcon(activity.action)}
                </div>

                <div className="ict-activity-content">
                  <div className="ict-activity-main">
                    <span className="ict-activity-entity-icon">
                      {getEntityIcon(activity.entity_type)}
                    </span>
                    <span className="ict-activity-text">
                      <strong>{activity.user_name || 'System'}</strong>
                      {' '}
                      <span className="ict-activity-action">{activity.action}</span>
                      {' '}
                      <span className="ict-activity-entity">
                        {activity.entity_type.replace('-', ' ')}
                      </span>
                      {activity.entity_name && (
                        <span className="ict-activity-entity-name">
                          &quot;{activity.entity_name}&quot;
                        </span>
                      )}
                    </span>
                  </div>

                  <div className="ict-activity-meta">
                    <span className="ict-activity-time">
                      {formatRelativeTime(activity.created_at)}
                    </span>
                    {activity.ip_address && (
                      <span className="ict-activity-ip">{activity.ip_address}</span>
                    )}
                  </div>

                  {expandedId === activity.id && activity.changes && (
                    renderChanges(activity.changes)
                  )}
                </div>

                {activity.changes && Object.keys(activity.changes).length > 0 && (
                  <span className="ict-activity-expand-icon">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                      <path d={expandedId === activity.id ? 'M18 15l-6-6-6 6' : 'M6 9l6 6 6-6'} />
                    </svg>
                  </span>
                )}
              </div>
            ))}

            {hasMore && (
              <button className="ict-activity-load-more" onClick={loadMore}>
                Load More
              </button>
            )}
          </>
        )}
      </div>

      <style>{`
        .ict-activity-feed {
          background: var(--ict-bg-color, #fff);
          border: 1px solid var(--ict-border-color, #e5e7eb);
          border-radius: 12px;
          overflow: hidden;
        }

        .ict-activity-header {
          display: flex;
          justify-content: space-between;
          align-items: center;
          padding: 16px 20px;
          border-bottom: 1px solid var(--ict-border-color, #e5e7eb);
          background: var(--ict-bg-secondary, #f9fafb);
        }

        .ict-activity-header h3 {
          margin: 0;
          font-size: 16px;
          font-weight: 600;
          color: var(--ict-text-color, #1f2937);
        }

        .ict-activity-refresh-indicator {
          display: flex;
          align-items: center;
          gap: 6px;
          font-size: 12px;
          color: #10b981;
        }

        .ict-activity-refresh-dot {
          width: 8px;
          height: 8px;
          background: #10b981;
          border-radius: 50%;
          animation: pulse 2s infinite;
        }

        @keyframes pulse {
          0%, 100% { opacity: 1; transform: scale(1); }
          50% { opacity: 0.5; transform: scale(0.8); }
        }

        .ict-activity-filters {
          display: flex;
          gap: 12px;
          padding: 12px 20px;
          border-bottom: 1px solid var(--ict-border-light, #f3f4f6);
        }

        .ict-activity-filter {
          padding: 8px 12px;
          font-size: 13px;
          border: 1px solid var(--ict-border-color, #e5e7eb);
          border-radius: 6px;
          background: var(--ict-bg-color, #fff);
          color: var(--ict-text-color, #1f2937);
        }

        .ict-activity-list {
          max-height: 600px;
          overflow-y: auto;
        }

        .ict-activity-loading,
        .ict-activity-empty {
          display: flex;
          flex-direction: column;
          align-items: center;
          justify-content: center;
          padding: 48px 20px;
          color: var(--ict-text-muted, #6b7280);
          gap: 12px;
        }

        .ict-activity-spinner {
          width: 24px;
          height: 24px;
          border: 2px solid var(--ict-border-color, #e5e7eb);
          border-top-color: var(--ict-primary, #3b82f6);
          border-radius: 50%;
          animation: spin 1s linear infinite;
        }

        @keyframes spin {
          to { transform: rotate(360deg); }
        }

        .ict-activity-item {
          display: flex;
          gap: 12px;
          padding: 16px 20px;
          border-bottom: 1px solid var(--ict-border-light, #f3f4f6);
          cursor: pointer;
          transition: background 0.2s;
        }

        .ict-activity-item:hover {
          background: var(--ict-bg-hover, #f9fafb);
        }

        .ict-activity-icon {
          flex-shrink: 0;
          width: 32px;
          height: 32px;
          border-radius: 50%;
          display: flex;
          align-items: center;
          justify-content: center;
          color: #fff;
        }

        .ict-activity-content {
          flex: 1;
          min-width: 0;
        }

        .ict-activity-main {
          display: flex;
          align-items: flex-start;
          gap: 8px;
          line-height: 1.5;
        }

        .ict-activity-entity-icon {
          font-size: 14px;
        }

        .ict-activity-text {
          font-size: 14px;
          color: var(--ict-text-color, #1f2937);
        }

        .ict-activity-action {
          color: var(--ict-text-secondary, #4b5563);
        }

        .ict-activity-entity {
          color: var(--ict-text-secondary, #4b5563);
        }

        .ict-activity-entity-name {
          color: var(--ict-primary, #3b82f6);
        }

        .ict-activity-meta {
          display: flex;
          gap: 12px;
          margin-top: 4px;
          font-size: 12px;
          color: var(--ict-text-muted, #6b7280);
        }

        .ict-activity-changes {
          margin-top: 12px;
          padding: 12px;
          background: var(--ict-bg-secondary, #f9fafb);
          border-radius: 6px;
          font-size: 13px;
        }

        .ict-activity-change {
          display: flex;
          align-items: center;
          gap: 8px;
          margin-bottom: 4px;
        }

        .ict-activity-change:last-child {
          margin-bottom: 0;
        }

        .ict-activity-change-field {
          font-weight: 500;
          color: var(--ict-text-secondary, #4b5563);
          min-width: 80px;
        }

        .ict-activity-change-old {
          color: #ef4444;
          text-decoration: line-through;
        }

        .ict-activity-change-arrow {
          color: var(--ict-text-muted, #9ca3af);
        }

        .ict-activity-change-new {
          color: #10b981;
        }

        .ict-activity-expand-icon {
          flex-shrink: 0;
          color: var(--ict-text-muted, #9ca3af);
          transition: transform 0.2s;
        }

        .ict-activity-item.expanded .ict-activity-expand-icon {
          transform: rotate(180deg);
        }

        .ict-activity-load-more {
          display: block;
          width: 100%;
          padding: 16px;
          font-size: 14px;
          font-weight: 500;
          color: var(--ict-primary, #3b82f6);
          background: none;
          border: none;
          cursor: pointer;
          transition: background 0.2s;
        }

        .ict-activity-load-more:hover {
          background: var(--ict-bg-hover, #f9fafb);
        }
      `}</style>
    </div>
  );
};

export default ActivityFeed;
