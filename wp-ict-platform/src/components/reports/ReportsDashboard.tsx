/**
 * Reports Dashboard Component
 *
 * Main reports landing page displaying:
 * - Key metrics from all modules
 * - Quick access to detailed reports
 * - Summary charts and statistics
 * - Recent activity overview
 *
 * @package ICT_Platform
 * @since   1.0.0
 */

import React, { useEffect } from 'react';
import { useDispatch, useSelector } from 'react-redux';
import {
  fetchDashboardSummary,
  selectDashboardSummary,
  selectReportsLoading,
  selectReportsError,
  selectLastUpdated,
  clearError,
} from '../../store/slices/reportsSlice';
import PieChart from '../charts/PieChart';
import BarChart from '../charts/BarChart';

const ReportsDashboard: React.FC = () => {
  const dispatch = useDispatch();
  const summary = useSelector(selectDashboardSummary);
  const loading = useSelector(selectReportsLoading);
  const error = useSelector(selectReportsError);
  const lastUpdated = useSelector(selectLastUpdated);

  useEffect(() => {
    dispatch(fetchDashboardSummary() as any);
  }, [dispatch]);

  const handleRefresh = () => {
    dispatch(fetchDashboardSummary() as any);
  };

  const formatCurrency = (value: number): string => {
    return new Intl.NumberFormat('en-US', {
      style: 'currency',
      currency: 'USD',
      maximumFractionDigits: 0,
    }).format(value || 0);
  };

  const formatDate = (dateString?: string): string => {
    if (!dateString) return 'Never';
    return new Date(dateString).toLocaleString('en-US', {
      month: 'short',
      day: 'numeric',
      hour: '2-digit',
      minute: '2-digit',
    });
  };

  if (error) {
    return (
      <div className="reports-dashboard">
        <div className="alert alert--error">
          <span className="alert__icon">❌</span>
          <span>Error loading dashboard: {error}</span>
          <button onClick={() => dispatch(clearError())} className="alert__close">×</button>
        </div>
        <button onClick={handleRefresh} className="btn btn-primary">
          Retry
        </button>
      </div>
    );
  }

  if (loading && !summary) {
    return (
      <div className="reports-dashboard">
        <div className="loading-state">
          <div className="spinner"></div>
          <p>Loading dashboard...</p>
        </div>
      </div>
    );
  }

  if (!summary) {
    return null;
  }

  // Prepare chart data
  const projectsChartData = [
    { label: 'Active', value: summary.projects.active, color: '#46b450' },
    { label: 'Completed', value: summary.projects.completed, color: '#0073aa' },
    { label: 'Other', value: summary.projects.total - summary.projects.active - summary.projects.completed, color: '#9ca3af' },
  ];

  const purchaseOrdersChartData = [
    { label: 'Pending', value: summary.purchase_orders.pending, color: '#ffb900' },
    { label: 'Approved', value: summary.purchase_orders.approved, color: '#46b450' },
    {
      label: 'Other',
      value: summary.purchase_orders.total - summary.purchase_orders.pending - summary.purchase_orders.approved,
      color: '#9ca3af',
    },
  ];

  return (
    <div className="reports-dashboard">
      {/* Header */}
      <div className="reports-dashboard__header">
        <div>
          <h1>Reports Dashboard</h1>
          <p className="subtitle">Overview of key metrics and performance indicators</p>
        </div>
        <div className="header-actions">
          <span className="last-updated">Last updated: {formatDate(lastUpdated)}</span>
          <button
            onClick={handleRefresh}
            disabled={loading}
            className="btn btn-secondary"
          >
            <span className="icon">{loading ? '⏳' : '🔄'}</span>
            Refresh
          </button>
        </div>
      </div>

      {/* Key Metrics Grid */}
      <div className="metrics-grid">
        {/* Projects Metric */}
        <div className="metric-card metric-card--primary">
          <div className="metric-card__icon">📊</div>
          <div className="metric-card__content">
            <h3 className="metric-card__title">Projects</h3>
            <div className="metric-card__value">{summary.projects.total}</div>
            <div className="metric-card__stats">
              <div className="stat">
                <span className="stat__label">Active:</span>
                <span className="stat__value stat__value--success">{summary.projects.active}</span>
              </div>
              <div className="stat">
                <span className="stat__label">Completed:</span>
                <span className="stat__value">{summary.projects.completed}</span>
              </div>
            </div>
            <div className="metric-card__footer">
              <span className="label">Total Budget:</span>
              <span className="value">{formatCurrency(summary.projects.total_budget)}</span>
            </div>
          </div>
        </div>

        {/* Time Tracking Metric */}
        <div className="metric-card metric-card--success">
          <div className="metric-card__icon">⏱️</div>
          <div className="metric-card__content">
            <h3 className="metric-card__title">Time Tracking</h3>
            <div className="metric-card__value">{summary.time.total_hours.toFixed(1)}h</div>
            <div className="metric-card__stats">
              <div className="stat">
                <span className="stat__label">Entries:</span>
                <span className="stat__value">{summary.time.total_entries}</span>
              </div>
              <div className="stat">
                <span className="stat__label">Revenue:</span>
                <span className="stat__value stat__value--success">{formatCurrency(summary.time.revenue)}</span>
              </div>
            </div>
            <div className="metric-card__footer">
              <span className="label">Last 30 days</span>
            </div>
          </div>
        </div>

        {/* Inventory Metric */}
        <div className="metric-card metric-card--warning">
          <div className="metric-card__icon">📦</div>
          <div className="metric-card__content">
            <h3 className="metric-card__title">Inventory</h3>
            <div className="metric-card__value">{summary.inventory.total_items}</div>
            <div className="metric-card__stats">
              <div className="stat">
                <span className="stat__label">Total Value:</span>
                <span className="stat__value">{formatCurrency(summary.inventory.total_value)}</span>
              </div>
              <div className="stat">
                <span className="stat__label">Low Stock:</span>
                <span className="stat__value stat__value--danger">{summary.inventory.low_stock}</span>
              </div>
            </div>
            <div className="metric-card__footer">
              {summary.inventory.low_stock > 0 ? (
                <span className="warning">⚠️ {summary.inventory.low_stock} items need attention</span>
              ) : (
                <span className="success">✓ All stock levels good</span>
              )}
            </div>
          </div>
        </div>

        {/* Purchase Orders Metric */}
        <div className="metric-card metric-card--info">
          <div className="metric-card__icon">🛒</div>
          <div className="metric-card__content">
            <h3 className="metric-card__title">Purchase Orders</h3>
            <div className="metric-card__value">{summary.purchase_orders.total}</div>
            <div className="metric-card__stats">
              <div className="stat">
                <span className="stat__label">Pending:</span>
                <span className="stat__value stat__value--warning">{summary.purchase_orders.pending}</span>
              </div>
              <div className="stat">
                <span className="stat__label">Approved:</span>
                <span className="stat__value stat__value--success">{summary.purchase_orders.approved}</span>
              </div>
            </div>
            <div className="metric-card__footer">
              <span className="label">Total Amount:</span>
              <span className="value">{formatCurrency(summary.purchase_orders.total_amount)}</span>
            </div>
          </div>
        </div>
      </div>

      {/* Charts Section */}
      <div className="charts-grid">
        <div className="chart-section">
          <PieChart
            data={projectsChartData}
            title="Projects by Status"
            donut={true}
            showPercentages={true}
          />
        </div>

        <div className="chart-section">
          <PieChart
            data={purchaseOrdersChartData}
            title="Purchase Orders by Status"
            donut={true}
            showPercentages={true}
          />
        </div>

        <div className="chart-section chart-section--wide">
          <BarChart
            data={[
              { label: 'Budget', value: summary.projects.total_budget },
              { label: 'Spent', value: summary.projects.total_cost },
              { label: 'Revenue', value: summary.time.revenue },
              { label: 'PO Total', value: summary.purchase_orders.total_amount },
            ]}
            title="Financial Overview"
            valuePrefix="$"
            height={250}
          />
        </div>
      </div>

      {/* Quick Links */}
      <div className="quick-links">
        <h2>Detailed Reports</h2>
        <div className="quick-links__grid">
          <a href="/wp-admin/admin.php?page=ict-platform-reports-projects" className="quick-link-card">
            <div className="quick-link-card__icon">📊</div>
            <div className="quick-link-card__content">
              <h3>Project Reports</h3>
              <p>View detailed project statistics, progress, and budget analysis</p>
            </div>
            <span className="quick-link-card__arrow">→</span>
          </a>

          <a href="/wp-admin/admin.php?page=ict-platform-reports-time" className="quick-link-card">
            <div className="quick-link-card__icon">⏱️</div>
            <div className="quick-link-card__content">
              <h3>Time Reports</h3>
              <p>Analyze time entries, billable hours, and technician performance</p>
            </div>
            <span className="quick-link-card__arrow">→</span>
          </a>

          <a href="/wp-admin/admin.php?page=ict-platform-reports-budget" className="quick-link-card">
            <div className="quick-link-card__icon">💰</div>
            <div className="quick-link-card__content">
              <h3>Budget Reports</h3>
              <p>Track budget utilization, costs, and financial performance</p>
            </div>
            <span className="quick-link-card__arrow">→</span>
          </a>

          <a href="/wp-admin/admin.php?page=ict-platform-reports-inventory" className="quick-link-card">
            <div className="quick-link-card__icon">📦</div>
            <div className="quick-link-card__content">
              <h3>Inventory Reports</h3>
              <p>Review stock levels, valuation, and inventory movements</p>
            </div>
            <span className="quick-link-card__arrow">→</span>
          </a>
        </div>
      </div>
    </div>
  );
};

export default ReportsDashboard;
