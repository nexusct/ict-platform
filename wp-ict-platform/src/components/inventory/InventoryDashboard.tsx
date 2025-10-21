/**
 * Inventory Dashboard Component
 *
 * Displays comprehensive inventory overview including:
 * - Key metrics cards (total items, total value, low stock)
 * - Category breakdown chart
 * - Recent stock movements
 * - Low stock alerts
 * - Quick actions
 *
 * @package ICT_Platform
 * @since   1.0.0
 */

import React, { useEffect, useState } from 'react';
import { useDispatch, useSelector } from 'react-redux';
import {
  fetchInventory,
  fetchLowStockItems,
  fetchCategories,
  fetchStockHistory,
  selectInventory,
  selectLowStockItems,
  selectInventoryCategories,
  selectInventoryLoading,
  selectInventoryError,
  selectStockHistory,
} from '../../store/slices/inventorySlice';
import type { InventoryItem } from '../../types';

interface CategoryStats {
  category: string;
  itemCount: number;
  totalValue: number;
  lowStockCount: number;
}

interface MetricCard {
  title: string;
  value: string | number;
  subtitle?: string;
  trend?: 'up' | 'down' | 'neutral';
  icon: string;
  color: string;
}

const InventoryDashboard: React.FC = () => {
  const dispatch = useDispatch();
  const inventory = useSelector(selectInventory);
  const lowStockItems = useSelector(selectLowStockItems);
  const categories = useSelector(selectInventoryCategories);
  const stockHistory = useSelector(selectStockHistory);
  const loading = useSelector(selectInventoryLoading);
  const error = useSelector(selectInventoryError);

  const [selectedMetric, setSelectedMetric] = useState<'value' | 'quantity'>('value');
  const [categoryStats, setCategoryStats] = useState<CategoryStats[]>([]);

  // Load initial data
  useEffect(() => {
    dispatch(fetchInventory({ per_page: 100 }) as any);
    dispatch(fetchLowStockItems() as any);
    dispatch(fetchCategories() as any);
  }, [dispatch]);

  // Calculate category statistics
  useEffect(() => {
    if (inventory.length > 0) {
      const statsMap = new Map<string, CategoryStats>();

      inventory.forEach((item: InventoryItem) => {
        const category = item.category || 'Uncategorized';
        const existing = statsMap.get(category) || {
          category,
          itemCount: 0,
          totalValue: 0,
          lowStockCount: 0,
        };

        existing.itemCount += 1;
        existing.totalValue += item.quantity_on_hand * item.unit_cost;

        if (item.quantity_on_hand <= item.reorder_level) {
          existing.lowStockCount += 1;
        }

        statsMap.set(category, existing);
      });

      setCategoryStats(Array.from(statsMap.values()).sort((a, b) => b.totalValue - a.totalValue));
    }
  }, [inventory]);

  // Calculate key metrics
  const totalItems = inventory.length;
  const totalValue = inventory.reduce(
    (sum: number, item: InventoryItem) => sum + (item.quantity_on_hand * item.unit_cost),
    0
  );
  const totalQuantity = inventory.reduce(
    (sum: number, item: InventoryItem) => sum + item.quantity_on_hand,
    0
  );
  const activeItems = inventory.filter((item: InventoryItem) => item.is_active).length;
  const lowStockCount = lowStockItems.length;
  const totalAllocated = inventory.reduce(
    (sum: number, item: InventoryItem) => sum + item.quantity_allocated,
    0
  );
  const availabilityRate = totalQuantity > 0
    ? ((totalQuantity - totalAllocated) / totalQuantity * 100).toFixed(1)
    : 0;

  const metricCards: MetricCard[] = [
    {
      title: 'Total Items',
      value: totalItems,
      subtitle: `${activeItems} active`,
      icon: '📦',
      color: 'blue',
    },
    {
      title: 'Total Value',
      value: `$${totalValue.toLocaleString('en-US', { maximumFractionDigits: 0 })}`,
      subtitle: `${totalQuantity} units`,
      icon: '💰',
      color: 'green',
    },
    {
      title: 'Low Stock Items',
      value: lowStockCount,
      subtitle: lowStockCount > 0 ? 'Requires attention' : 'All good',
      trend: lowStockCount > 0 ? 'down' : 'neutral',
      icon: '⚠️',
      color: lowStockCount > 0 ? 'red' : 'gray',
    },
    {
      title: 'Availability',
      value: `${availabilityRate}%`,
      subtitle: `${totalQuantity - totalAllocated} units available`,
      trend: Number(availabilityRate) > 80 ? 'up' : 'neutral',
      icon: '📊',
      color: 'purple',
    },
  ];

  const formatCurrency = (value: number): string => {
    return new Intl.NumberFormat('en-US', {
      style: 'currency',
      currency: 'USD',
      maximumFractionDigits: 0,
    }).format(value);
  };

  const formatDate = (dateString: string): string => {
    return new Date(dateString).toLocaleDateString('en-US', {
      month: 'short',
      day: 'numeric',
      hour: '2-digit',
      minute: '2-digit',
    });
  };

  const getAdjustmentTypeLabel = (type: string): string => {
    const labels: Record<string, string> = {
      received: 'Received',
      consumed: 'Consumed',
      damaged: 'Damaged',
      lost: 'Lost',
      returned: 'Returned',
      transfer: 'Transfer',
      correction: 'Correction',
      initial: 'Initial Stock',
    };
    return labels[type] || type;
  };

  const getAdjustmentTypeColor = (type: string): string => {
    const colors: Record<string, string> = {
      received: 'green',
      consumed: 'blue',
      damaged: 'orange',
      lost: 'red',
      returned: 'purple',
      transfer: 'teal',
      correction: 'gray',
      initial: 'indigo',
    };
    return colors[type] || 'gray';
  };

  if (error) {
    return (
      <div className="inventory-dashboard">
        <div className="error-message">
          <span className="error-icon">❌</span>
          <p>Error loading inventory data: {error}</p>
          <button
            onClick={() => dispatch(fetchInventory({ per_page: 100 }) as any)}
            className="btn btn-primary"
          >
            Retry
          </button>
        </div>
      </div>
    );
  }

  return (
    <div className="inventory-dashboard">
      <div className="dashboard-header">
        <h1>Inventory Dashboard</h1>
        <div className="dashboard-actions">
          <button className="btn btn-secondary">
            <span className="icon">📥</span>
            Import
          </button>
          <button className="btn btn-secondary">
            <span className="icon">📤</span>
            Export
          </button>
          <button className="btn btn-primary">
            <span className="icon">➕</span>
            Add Item
          </button>
        </div>
      </div>

      {/* Metric Cards */}
      <div className="metrics-grid">
        {metricCards.map((metric, index) => (
          <div key={index} className={`metric-card metric-card--${metric.color}`}>
            <div className="metric-card__icon">{metric.icon}</div>
            <div className="metric-card__content">
              <h3 className="metric-card__title">{metric.title}</h3>
              <div className="metric-card__value">
                {metric.value}
                {metric.trend && (
                  <span className={`trend-indicator trend-indicator--${metric.trend}`}>
                    {metric.trend === 'up' ? '↑' : metric.trend === 'down' ? '↓' : '−'}
                  </span>
                )}
              </div>
              {metric.subtitle && (
                <p className="metric-card__subtitle">{metric.subtitle}</p>
              )}
            </div>
          </div>
        ))}
      </div>

      <div className="dashboard-content">
        {/* Category Breakdown */}
        <div className="dashboard-section">
          <div className="section-header">
            <h2>Category Breakdown</h2>
            <div className="section-actions">
              <button
                className={`btn-toggle ${selectedMetric === 'value' ? 'active' : ''}`}
                onClick={() => setSelectedMetric('value')}
              >
                Value
              </button>
              <button
                className={`btn-toggle ${selectedMetric === 'quantity' ? 'active' : ''}`}
                onClick={() => setSelectedMetric('quantity')}
              >
                Quantity
              </button>
            </div>
          </div>

          {loading && categoryStats.length === 0 ? (
            <div className="loading-state">
              <div className="spinner"></div>
              <p>Loading categories...</p>
            </div>
          ) : categoryStats.length === 0 ? (
            <div className="empty-state">
              <p>No inventory data available</p>
            </div>
          ) : (
            <div className="category-list">
              {categoryStats.map((stat, index) => {
                const maxValue = selectedMetric === 'value'
                  ? Math.max(...categoryStats.map(s => s.totalValue))
                  : Math.max(...categoryStats.map(s => s.itemCount));
                const percentage = selectedMetric === 'value'
                  ? (stat.totalValue / maxValue * 100)
                  : (stat.itemCount / maxValue * 100);

                return (
                  <div key={index} className="category-item">
                    <div className="category-item__header">
                      <span className="category-item__name">{stat.category}</span>
                      <span className="category-item__value">
                        {selectedMetric === 'value'
                          ? formatCurrency(stat.totalValue)
                          : `${stat.itemCount} items`}
                      </span>
                    </div>
                    <div className="category-item__bar">
                      <div
                        className="category-item__bar-fill"
                        style={{ width: `${percentage}%` }}
                      ></div>
                    </div>
                    <div className="category-item__meta">
                      <span>{stat.itemCount} items</span>
                      {stat.lowStockCount > 0 && (
                        <span className="low-stock-badge">
                          {stat.lowStockCount} low stock
                        </span>
                      )}
                    </div>
                  </div>
                );
              })}
            </div>
          )}
        </div>

        {/* Low Stock Alerts */}
        {lowStockCount > 0 && (
          <div className="dashboard-section">
            <div className="section-header">
              <h2>Low Stock Alerts</h2>
              <a href="#" className="section-link">View All</a>
            </div>

            <div className="low-stock-list">
              {lowStockItems.slice(0, 5).map((item: InventoryItem) => (
                <div key={item.id} className="low-stock-item">
                  <div className="low-stock-item__main">
                    <div className="low-stock-item__info">
                      <h4>{item.item_name}</h4>
                      <p className="sku">SKU: {item.sku}</p>
                    </div>
                    <div className="low-stock-item__quantity">
                      <span className="current-qty">{item.quantity_on_hand}</span>
                      <span className="separator">/</span>
                      <span className="reorder-level">{item.reorder_level}</span>
                      <span className="unit">{item.unit_of_measure}</span>
                    </div>
                  </div>
                  <div className="low-stock-item__actions">
                    <button className="btn btn-sm btn-primary">
                      Reorder ({item.reorder_quantity})
                    </button>
                  </div>
                </div>
              ))}
            </div>

            {lowStockCount > 5 && (
              <div className="section-footer">
                <a href="#" className="view-all-link">
                  View {lowStockCount - 5} more low stock items →
                </a>
              </div>
            )}
          </div>
        )}

        {/* Recent Activity */}
        <div className="dashboard-section">
          <div className="section-header">
            <h2>Recent Stock Movements</h2>
            <button
              className="btn btn-secondary btn-sm"
              onClick={() => dispatch(fetchStockHistory({ id: inventory[0]?.id, per_page: 10 }) as any)}
            >
              Refresh
            </button>
          </div>

          {stockHistory.length === 0 ? (
            <div className="empty-state">
              <p>No recent stock movements</p>
            </div>
          ) : (
            <div className="activity-list">
              {stockHistory.slice(0, 10).map((entry: any) => (
                <div key={entry.id} className="activity-item">
                  <div className={`activity-badge activity-badge--${getAdjustmentTypeColor(entry.adjustment_type)}`}>
                    {entry.quantity_change > 0 ? '+' : ''}{entry.quantity_change}
                  </div>
                  <div className="activity-content">
                    <div className="activity-header">
                      <span className={`activity-type activity-type--${getAdjustmentTypeColor(entry.adjustment_type)}`}>
                        {getAdjustmentTypeLabel(entry.adjustment_type)}
                      </span>
                      <span className="activity-time">{formatDate(entry.created_at)}</span>
                    </div>
                    <p className="activity-reason">{entry.reason}</p>
                    {entry.reference && (
                      <p className="activity-reference">Ref: {entry.reference}</p>
                    )}
                  </div>
                </div>
              ))}
            </div>
          )}
        </div>
      </div>

      {/* Quick Stats Footer */}
      <div className="dashboard-footer">
        <div className="quick-stat">
          <span className="label">Categories:</span>
          <span className="value">{categories.length}</span>
        </div>
        <div className="quick-stat">
          <span className="label">Avg. Item Value:</span>
          <span className="value">
            {formatCurrency(totalItems > 0 ? totalValue / totalItems : 0)}
          </span>
        </div>
        <div className="quick-stat">
          <span className="label">Total Allocated:</span>
          <span className="value">{totalAllocated} units</span>
        </div>
        <div className="quick-stat">
          <span className="label">Turnover Rate:</span>
          <span className="value">N/A</span>
        </div>
      </div>
    </div>
  );
};

export default InventoryDashboard;
