/**
 * Low Stock Alerts Component
 *
 * Widget displaying inventory items at or below reorder level with:
 * - Real-time alerts
 * - Priority indicators
 * - Quick reorder actions
 * - Expandable details
 * - Auto-refresh capability
 *
 * @package ICT_Platform
 * @since   1.0.0
 */

import React, { useEffect, useState } from 'react';
import { useDispatch, useSelector } from 'react-redux';
import {
  fetchLowStockItems,
  selectLowStockItems,
  selectInventoryLoading,
  selectInventoryError,
} from '../../store/slices/inventorySlice';
import type { InventoryItem } from '../../types';

interface LowStockAlertsProps {
  maxItems?: number;
  autoRefresh?: boolean;
  refreshInterval?: number; // in milliseconds
  showHeader?: boolean;
  compact?: boolean;
}

interface AlertPriority {
  level: 'critical' | 'warning' | 'low';
  label: string;
  color: string;
  icon: string;
}

const LowStockAlerts: React.FC<LowStockAlertsProps> = ({
  maxItems = 10,
  autoRefresh = false,
  refreshInterval = 300000, // 5 minutes
  showHeader = true,
  compact = false,
}) => {
  const dispatch = useDispatch();
  const lowStockItems = useSelector(selectLowStockItems);
  const loading = useSelector(selectInventoryLoading);
  const error = useSelector(selectInventoryError);

  const [expandedItems, setExpandedItems] = useState<Set<number>>(new Set());
  const [sortBy, setSortBy] = useState<'priority' | 'name' | 'quantity'>('priority');

  // Load low stock items on mount
  useEffect(() => {
    dispatch(fetchLowStockItems() as any);
  }, [dispatch]);

  // Auto-refresh if enabled
  useEffect(() => {
    if (autoRefresh) {
      const interval = setInterval(() => {
        dispatch(fetchLowStockItems() as any);
      }, refreshInterval);

      return () => clearInterval(interval);
    }
    return undefined; // Return undefined when autoRefresh is false
  }, [autoRefresh, refreshInterval, dispatch]);

  const calculatePriority = (item: InventoryItem): AlertPriority => {
    const stockPercentage = (item.quantity_on_hand / item.reorder_level) * 100;

    if (item.quantity_on_hand === 0) {
      return {
        level: 'critical',
        label: 'Out of Stock',
        color: 'red',
        icon: '🚨',
      };
    } else if (stockPercentage <= 25) {
      return {
        level: 'critical',
        label: 'Critical',
        color: 'red',
        icon: '⚠️',
      };
    } else if (stockPercentage <= 50) {
      return {
        level: 'warning',
        label: 'Warning',
        color: 'orange',
        icon: '⚡',
      };
    } else {
      return {
        level: 'low',
        label: 'Low',
        color: 'yellow',
        icon: '📊',
      };
    }
  };

  const toggleItemExpanded = (itemId: number) => {
    const newExpanded = new Set(expandedItems);
    if (newExpanded.has(itemId)) {
      newExpanded.delete(itemId);
    } else {
      newExpanded.add(itemId);
    }
    setExpandedItems(newExpanded);
  };

  const sortItems = (items: InventoryItem[]): InventoryItem[] => {
    const sorted = [...items];

    switch (sortBy) {
      case 'priority':
        return sorted.sort((a, b) => {
          const aPriority = calculatePriority(a);
          const bPriority = calculatePriority(b);
          const priorityOrder = { critical: 0, warning: 1, low: 2 };
          return priorityOrder[aPriority.level] - priorityOrder[bPriority.level];
        });
      case 'name':
        return sorted.sort((a, b) => a.item_name.localeCompare(b.item_name));
      case 'quantity':
        return sorted.sort((a, b) => a.quantity_on_hand - b.quantity_on_hand);
      default:
        return sorted;
    }
  };

  const handleRefresh = () => {
    dispatch(fetchLowStockItems() as any);
  };

  const handleReorder = (item: InventoryItem) => {
    // This would typically open a purchase order form or modal
    console.log('Reorder item:', item);
    // Could dispatch action to create PO or open modal
  };

  const sortedItems = sortItems(lowStockItems);
  const displayItems = sortedItems.slice(0, maxItems);

  if (error) {
    return (
      <div className={`low-stock-alerts ${compact ? 'low-stock-alerts--compact' : ''}`}>
        {showHeader && (
          <div className="low-stock-alerts__header">
            <h3>Low Stock Alerts</h3>
          </div>
        )}
        <div className="alert alert--error">
          <span>Error loading alerts: {error}</span>
          <button onClick={handleRefresh} className="btn btn-sm btn-secondary">
            Retry
          </button>
        </div>
      </div>
    );
  }

  return (
    <div className={`low-stock-alerts ${compact ? 'low-stock-alerts--compact' : ''}`}>
      {showHeader && (
        <div className="low-stock-alerts__header">
          <div className="header-left">
            <h3>Low Stock Alerts</h3>
            {lowStockItems.length > 0 && (
              <span className="alert-count">{lowStockItems.length}</span>
            )}
          </div>
          <div className="header-right">
            <select
              value={sortBy}
              onChange={(e) => setSortBy(e.target.value as any)}
              className="sort-select"
            >
              <option value="priority">Priority</option>
              <option value="name">Name</option>
              <option value="quantity">Quantity</option>
            </select>
            <button
              onClick={handleRefresh}
              disabled={loading}
              className="btn-icon"
              title="Refresh"
            >
              {loading ? '⏳' : '🔄'}
            </button>
          </div>
        </div>
      )}

      <div className="low-stock-alerts__content">
        {loading && lowStockItems.length === 0 ? (
          <div className="loading-state">
            <div className="spinner"></div>
            <p>Loading alerts...</p>
          </div>
        ) : lowStockItems.length === 0 ? (
          <div className="empty-state">
            <div className="empty-state__icon">✅</div>
            <h4>All Good!</h4>
            <p>No items are currently low on stock</p>
          </div>
        ) : (
          <div className="alerts-list">
            {displayItems.map((item) => {
              const priority = calculatePriority(item);
              const isExpanded = expandedItems.has(item.id);
              const stockPercentage = item.reorder_level > 0
                ? (item.quantity_on_hand / item.reorder_level) * 100
                : 0;

              return (
                <div
                  key={item.id}
                  className={`alert-item alert-item--${priority.color} ${
                    isExpanded ? 'alert-item--expanded' : ''
                  }`}
                >
                  <div
                    className="alert-item__main"
                    onClick={() => !compact && toggleItemExpanded(item.id)}
                  >
                    <div className={`priority-badge priority-badge--${priority.color}`}>
                      <span className="priority-icon">{priority.icon}</span>
                      {!compact && <span className="priority-label">{priority.label}</span>}
                    </div>

                    <div className="alert-item__info">
                      <h4 className="item-name">{item.item_name}</h4>
                      {!compact && <p className="item-sku">SKU: {item.sku}</p>}
                    </div>

                    <div className="alert-item__quantity">
                      <div className="quantity-bar">
                        <div
                          className={`quantity-bar__fill quantity-bar__fill--${priority.color}`}
                          style={{ width: `${Math.min(stockPercentage, 100)}%` }}
                        ></div>
                      </div>
                      <div className="quantity-text">
                        <span className="current">{item.quantity_on_hand}</span>
                        <span className="separator">/</span>
                        <span className="reorder">{item.reorder_level}</span>
                        <span className="unit">{item.unit_of_measure}</span>
                      </div>
                    </div>

                    {!compact && (
                      <button
                        className="expand-button"
                        onClick={(e) => {
                          e.stopPropagation();
                          toggleItemExpanded(item.id);
                        }}
                      >
                        {isExpanded ? '▼' : '▶'}
                      </button>
                    )}
                  </div>

                  {!compact && isExpanded && (
                    <div className="alert-item__details">
                      <div className="details-grid">
                        <div className="detail-item">
                          <span className="label">Available:</span>
                          <span className="value">
                            {item.quantity_available} {item.unit_of_measure}
                          </span>
                        </div>
                        <div className="detail-item">
                          <span className="label">Allocated:</span>
                          <span className="value">
                            {item.quantity_allocated} {item.unit_of_measure}
                          </span>
                        </div>
                        <div className="detail-item">
                          <span className="label">Reorder Qty:</span>
                          <span className="value">
                            {item.reorder_quantity} {item.unit_of_measure}
                          </span>
                        </div>
                        <div className="detail-item">
                          <span className="label">Unit Cost:</span>
                          <span className="value">${item.unit_cost.toFixed(2)}</span>
                        </div>
                        {item.category && (
                          <div className="detail-item">
                            <span className="label">Category:</span>
                            <span className="value">{item.category}</span>
                          </div>
                        )}
                        {item.location && (
                          <div className="detail-item">
                            <span className="label">Location:</span>
                            <span className="value">{item.location}</span>
                          </div>
                        )}
                      </div>

                      <div className="alert-item__actions">
                        <button
                          onClick={() => handleReorder(item)}
                          className="btn btn-primary btn-sm"
                        >
                          <span className="icon">📋</span>
                          Reorder ({item.reorder_quantity})
                        </button>
                        <button className="btn btn-secondary btn-sm">
                          <span className="icon">✏️</span>
                          Adjust Stock
                        </button>
                        <button className="btn btn-secondary btn-sm">
                          <span className="icon">📊</span>
                          View History
                        </button>
                      </div>
                    </div>
                  )}
                </div>
              );
            })}
          </div>
        )}

        {lowStockItems.length > maxItems && (
          <div className="alerts-footer">
            <p className="footer-text">
              Showing {maxItems} of {lowStockItems.length} low stock items
            </p>
            <a href="#" className="footer-link">
              View All →
            </a>
          </div>
        )}
      </div>

      {/* Summary Stats */}
      {!compact && lowStockItems.length > 0 && (
        <div className="low-stock-alerts__summary">
          <div className="summary-stat">
            <span className="stat-icon">🚨</span>
            <div className="stat-content">
              <span className="stat-value">
                {lowStockItems.filter(item => calculatePriority(item).level === 'critical').length}
              </span>
              <span className="stat-label">Critical</span>
            </div>
          </div>
          <div className="summary-stat">
            <span className="stat-icon">⚠️</span>
            <div className="stat-content">
              <span className="stat-value">
                {lowStockItems.filter(item => calculatePriority(item).level === 'warning').length}
              </span>
              <span className="stat-label">Warning</span>
            </div>
          </div>
          <div className="summary-stat">
            <span className="stat-icon">📊</span>
            <div className="stat-content">
              <span className="stat-value">
                {lowStockItems.filter(item => calculatePriority(item).level === 'low').length}
              </span>
              <span className="stat-label">Low</span>
            </div>
          </div>
        </div>
      )}
    </div>
  );
};

export default LowStockAlerts;
