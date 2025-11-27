/**
 * Enhanced Dashboard Component
 *
 * Advanced reporting dashboard with charts, KPIs, and customizable widgets.
 *
 * @package ICT_Platform
 * @since   2.1.0
 */

import React, { useState, useEffect, useMemo } from 'react';

interface DashboardWidget {
  id: string;
  type: 'kpi' | 'chart' | 'list' | 'calendar' | 'map';
  title: string;
  size: 'small' | 'medium' | 'large' | 'full';
  position: { row: number; col: number };
  config?: Record<string, any>;
}

interface KPIData {
  label: string;
  value: number | string;
  change?: number;
  changeType?: 'increase' | 'decrease';
  icon?: string;
  color?: string;
}

interface ChartData {
  labels: string[];
  datasets: {
    label: string;
    data: number[];
    color?: string;
  }[];
}

interface EnhancedDashboardProps {
  widgets?: DashboardWidget[];
  apiEndpoint?: string;
  dateRange?: { start: Date; end: Date };
  onWidgetAdd?: (widget: DashboardWidget) => void;
  onWidgetRemove?: (widgetId: string) => void;
  onWidgetReorder?: (widgets: DashboardWidget[]) => void;
}

const defaultWidgets: DashboardWidget[] = [
  { id: 'kpi-revenue', type: 'kpi', title: 'Revenue', size: 'small', position: { row: 0, col: 0 } },
  { id: 'kpi-projects', type: 'kpi', title: 'Active Projects', size: 'small', position: { row: 0, col: 1 } },
  { id: 'kpi-hours', type: 'kpi', title: 'Hours Logged', size: 'small', position: { row: 0, col: 2 } },
  { id: 'kpi-efficiency', type: 'kpi', title: 'Team Efficiency', size: 'small', position: { row: 0, col: 3 } },
  { id: 'chart-revenue', type: 'chart', title: 'Revenue Trend', size: 'large', position: { row: 1, col: 0 } },
  { id: 'chart-hours', type: 'chart', title: 'Hours by Project', size: 'medium', position: { row: 1, col: 2 } },
  { id: 'list-tasks', type: 'list', title: 'Upcoming Tasks', size: 'medium', position: { row: 2, col: 0 } },
  { id: 'list-activity', type: 'list', title: 'Recent Activity', size: 'medium', position: { row: 2, col: 1 } },
];

const EnhancedDashboard: React.FC<EnhancedDashboardProps> = ({
  widgets = defaultWidgets,
  apiEndpoint = '/wp-json/ict/v1/reports/dashboard',
  dateRange,
  onWidgetAdd,
  onWidgetRemove,
  onWidgetReorder,
}) => {
  const [dashboardData, setDashboardData] = useState<Record<string, any>>({});
  const [isLoading, setIsLoading] = useState(true);
  const [selectedPeriod, setSelectedPeriod] = useState<'week' | 'month' | 'quarter' | 'year'>('month');
  const [isEditMode, setIsEditMode] = useState(false);

  // Fetch dashboard data
  useEffect(() => {
    const fetchData = async () => {
      setIsLoading(true);
      try {
        const params = new URLSearchParams({ period: selectedPeriod });
        if (dateRange) {
          params.append('start', dateRange.start.toISOString());
          params.append('end', dateRange.end.toISOString());
        }

        const response = await fetch(`${apiEndpoint}?${params.toString()}`, {
          headers: {
            'X-WP-Nonce': (window as any).ictSettings?.nonce || '',
          },
        });

        const data = await response.json();
        setDashboardData(data.data || generateMockData());
      } catch (error) {
        console.error('Failed to fetch dashboard data:', error);
        setDashboardData(generateMockData());
      } finally {
        setIsLoading(false);
      }
    };

    fetchData();
  }, [apiEndpoint, selectedPeriod, dateRange]);

  // Generate mock data for demo
  const generateMockData = (): Record<string, any> => ({
    kpis: {
      revenue: { value: 124500, change: 12.5, changeType: 'increase' },
      projects: { value: 18, change: 3, changeType: 'increase' },
      hours: { value: 1240, change: -5, changeType: 'decrease' },
      efficiency: { value: 94, change: 2, changeType: 'increase' },
    },
    charts: {
      revenue: {
        labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun'],
        datasets: [
          { label: 'Revenue', data: [65000, 72000, 85000, 91000, 102000, 124500], color: '#3b82f6' },
          { label: 'Target', data: [70000, 75000, 80000, 85000, 90000, 100000], color: '#e5e7eb' },
        ],
      },
      hours: {
        labels: ['Project A', 'Project B', 'Project C', 'Project D', 'Other'],
        datasets: [
          { label: 'Hours', data: [320, 280, 240, 200, 200], color: '#8b5cf6' },
        ],
      },
    },
    lists: {
      tasks: [
        { id: 1, title: 'Complete electrical inspection', project: 'Office Building', due: '2024-01-15', priority: 'high' },
        { id: 2, title: 'Install network cabling', project: 'Retail Store', due: '2024-01-16', priority: 'normal' },
        { id: 3, title: 'Review purchase orders', project: 'Admin', due: '2024-01-17', priority: 'low' },
      ],
      activity: [
        { id: 1, action: 'Time entry created', user: 'John D.', time: '5m ago' },
        { id: 2, action: 'Project updated', user: 'Sarah M.', time: '15m ago' },
        { id: 3, action: 'Invoice sent', user: 'Mike R.', time: '1h ago' },
      ],
    },
  });

  // Render KPI widget
  const renderKPIWidget = (widget: DashboardWidget) => {
    const kpiKey = widget.id.replace('kpi-', '');
    const kpiData = dashboardData.kpis?.[kpiKey] || { value: 0 };

    const icons: Record<string, string> = {
      revenue: '$',
      projects: '#',
      hours: '⏱',
      efficiency: '%',
    };

    const formatValue = (key: string, value: number): string => {
      if (key === 'revenue') return `$${value.toLocaleString()}`;
      if (key === 'efficiency') return `${value}%`;
      return value.toLocaleString();
    };

    return (
      <div className="ict-dashboard-kpi">
        <div className="ict-kpi-header">
          <span className="ict-kpi-icon">{icons[kpiKey]}</span>
          <span className="ict-kpi-title">{widget.title}</span>
        </div>
        <div className="ict-kpi-value">{formatValue(kpiKey, kpiData.value)}</div>
        {kpiData.change !== undefined && (
          <div className={`ict-kpi-change ${kpiData.changeType}`}>
            <svg width="12" height="12" viewBox="0 0 24 24" fill="currentColor">
              {kpiData.changeType === 'increase' ? (
                <path d="M7 14l5-5 5 5H7z" />
              ) : (
                <path d="M7 10l5 5 5-5H7z" />
              )}
            </svg>
            {Math.abs(kpiData.change)}%
            <span className="ict-kpi-period">vs last {selectedPeriod}</span>
          </div>
        )}
      </div>
    );
  };

  // Render Chart widget
  const renderChartWidget = (widget: DashboardWidget) => {
    const chartKey = widget.id.replace('chart-', '');
    const chartData = dashboardData.charts?.[chartKey];

    if (!chartData) return <div className="ict-widget-empty">No data available</div>;

    const maxValue = Math.max(...chartData.datasets.flatMap((d: any) => d.data));

    return (
      <div className="ict-dashboard-chart">
        <div className="ict-chart-legend">
          {chartData.datasets.map((dataset: any, i: number) => (
            <span key={i} className="ict-chart-legend-item">
              <span className="ict-chart-legend-color" style={{ background: dataset.color }} />
              {dataset.label}
            </span>
          ))}
        </div>
        <div className="ict-chart-container">
          {chartKey === 'revenue' ? (
            // Line chart
            <svg viewBox="0 0 400 200" className="ict-line-chart">
              {chartData.datasets.map((dataset: any, di: number) => {
                const points = dataset.data.map((value: number, i: number) => {
                  const x = (i / (dataset.data.length - 1)) * 380 + 10;
                  const y = 190 - (value / maxValue) * 170;
                  return `${x},${y}`;
                });

                return (
                  <g key={di}>
                    <polyline
                      fill="none"
                      stroke={dataset.color}
                      strokeWidth="2"
                      points={points.join(' ')}
                    />
                    {dataset.data.map((value: number, i: number) => {
                      const x = (i / (dataset.data.length - 1)) * 380 + 10;
                      const y = 190 - (value / maxValue) * 170;
                      return (
                        <circle
                          key={i}
                          cx={x}
                          cy={y}
                          r="4"
                          fill={dataset.color}
                        />
                      );
                    })}
                  </g>
                );
              })}
              {chartData.labels.map((label: string, i: number) => (
                <text
                  key={i}
                  x={(i / (chartData.labels.length - 1)) * 380 + 10}
                  y="198"
                  fontSize="10"
                  textAnchor="middle"
                  fill="var(--ict-text-muted, #6b7280)"
                >
                  {label}
                </text>
              ))}
            </svg>
          ) : (
            // Bar chart
            <div className="ict-bar-chart">
              {chartData.datasets[0].data.map((value: number, i: number) => (
                <div key={i} className="ict-bar-item">
                  <div className="ict-bar-wrapper">
                    <div
                      className="ict-bar"
                      style={{
                        height: `${(value / maxValue) * 100}%`,
                        background: chartData.datasets[0].color,
                      }}
                    />
                  </div>
                  <span className="ict-bar-label">{chartData.labels[i]}</span>
                  <span className="ict-bar-value">{value}</span>
                </div>
              ))}
            </div>
          )}
        </div>
      </div>
    );
  };

  // Render List widget
  const renderListWidget = (widget: DashboardWidget) => {
    const listKey = widget.id.replace('list-', '');
    const listData = dashboardData.lists?.[listKey] || [];

    const priorityColors: Record<string, string> = {
      high: '#ef4444',
      normal: '#3b82f6',
      low: '#6b7280',
    };

    return (
      <div className="ict-dashboard-list">
        {listData.length === 0 ? (
          <div className="ict-widget-empty">No items</div>
        ) : listKey === 'tasks' ? (
          listData.map((item: any) => (
            <div key={item.id} className="ict-list-item task">
              <span
                className="ict-list-priority"
                style={{ background: priorityColors[item.priority] }}
              />
              <div className="ict-list-content">
                <span className="ict-list-title">{item.title}</span>
                <span className="ict-list-meta">
                  {item.project} • Due {new Date(item.due).toLocaleDateString()}
                </span>
              </div>
            </div>
          ))
        ) : (
          listData.map((item: any) => (
            <div key={item.id} className="ict-list-item activity">
              <div className="ict-list-content">
                <span className="ict-list-title">{item.action}</span>
                <span className="ict-list-meta">
                  {item.user} • {item.time}
                </span>
              </div>
            </div>
          ))
        )}
      </div>
    );
  };

  // Render widget content
  const renderWidgetContent = (widget: DashboardWidget) => {
    if (isLoading) {
      return (
        <div className="ict-widget-loading">
          <div className="ict-widget-spinner" />
        </div>
      );
    }

    switch (widget.type) {
      case 'kpi':
        return renderKPIWidget(widget);
      case 'chart':
        return renderChartWidget(widget);
      case 'list':
        return renderListWidget(widget);
      default:
        return <div className="ict-widget-empty">Widget type not supported</div>;
    }
  };

  // Get widget size class
  const getWidgetSizeClass = (size: DashboardWidget['size']): string => {
    const sizes = {
      small: 'ict-widget-small',
      medium: 'ict-widget-medium',
      large: 'ict-widget-large',
      full: 'ict-widget-full',
    };
    return sizes[size];
  };

  return (
    <div className="ict-enhanced-dashboard">
      {/* Header */}
      <div className="ict-dashboard-header">
        <h2>Dashboard</h2>
        <div className="ict-dashboard-controls">
          <select
            value={selectedPeriod}
            onChange={(e) => setSelectedPeriod(e.target.value as any)}
            className="ict-dashboard-period-select"
          >
            <option value="week">This Week</option>
            <option value="month">This Month</option>
            <option value="quarter">This Quarter</option>
            <option value="year">This Year</option>
          </select>

          <button
            className={`ict-dashboard-edit-btn ${isEditMode ? 'active' : ''}`}
            onClick={() => setIsEditMode(!isEditMode)}
          >
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
              <path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7" />
              <path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z" />
            </svg>
            {isEditMode ? 'Done' : 'Customize'}
          </button>

          <button className="ict-dashboard-refresh-btn" onClick={() => window.location.reload()}>
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
              <path d="M23 4v6h-6M1 20v-6h6" />
              <path d="M3.51 9a9 9 0 0114.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0020.49 15" />
            </svg>
          </button>
        </div>
      </div>

      {/* Widget Grid */}
      <div className="ict-dashboard-grid">
        {widgets.map((widget) => (
          <div
            key={widget.id}
            className={`ict-dashboard-widget ${getWidgetSizeClass(widget.size)} ${isEditMode ? 'edit-mode' : ''}`}
          >
            <div className="ict-widget-header">
              <h3>{widget.title}</h3>
              {isEditMode && (
                <div className="ict-widget-actions">
                  <button
                    className="ict-widget-action"
                    onClick={() => onWidgetRemove?.(widget.id)}
                    title="Remove widget"
                  >
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                      <path d="M18 6L6 18M6 6l12 12" />
                    </svg>
                  </button>
                </div>
              )}
            </div>
            <div className="ict-widget-content">
              {renderWidgetContent(widget)}
            </div>
          </div>
        ))}

        {isEditMode && (
          <div className="ict-dashboard-widget ict-widget-add" onClick={() => onWidgetAdd?.({
            id: `widget-${Date.now()}`,
            type: 'kpi',
            title: 'New Widget',
            size: 'small',
            position: { row: 0, col: 0 },
          })}>
            <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
              <circle cx="12" cy="12" r="10" />
              <path d="M12 8v8M8 12h8" />
            </svg>
            <span>Add Widget</span>
          </div>
        )}
      </div>

      <style>{`
        .ict-enhanced-dashboard {
          padding: 24px;
        }

        .ict-dashboard-header {
          display: flex;
          justify-content: space-between;
          align-items: center;
          margin-bottom: 24px;
        }

        .ict-dashboard-header h2 {
          margin: 0;
          font-size: 24px;
          font-weight: 600;
          color: var(--ict-text-color, #1f2937);
        }

        .ict-dashboard-controls {
          display: flex;
          gap: 12px;
        }

        .ict-dashboard-period-select {
          padding: 8px 16px;
          font-size: 14px;
          border: 1px solid var(--ict-border-color, #e5e7eb);
          border-radius: 8px;
          background: var(--ict-bg-color, #fff);
          color: var(--ict-text-color, #1f2937);
          cursor: pointer;
        }

        .ict-dashboard-edit-btn,
        .ict-dashboard-refresh-btn {
          display: flex;
          align-items: center;
          gap: 8px;
          padding: 8px 16px;
          font-size: 14px;
          font-weight: 500;
          border: 1px solid var(--ict-border-color, #e5e7eb);
          border-radius: 8px;
          background: var(--ict-bg-color, #fff);
          color: var(--ict-text-color, #1f2937);
          cursor: pointer;
          transition: all 0.2s;
        }

        .ict-dashboard-edit-btn:hover,
        .ict-dashboard-refresh-btn:hover {
          background: var(--ict-bg-hover, #f3f4f6);
        }

        .ict-dashboard-edit-btn.active {
          background: var(--ict-primary, #3b82f6);
          color: #fff;
          border-color: var(--ict-primary, #3b82f6);
        }

        .ict-dashboard-refresh-btn {
          padding: 8px;
        }

        .ict-dashboard-grid {
          display: grid;
          grid-template-columns: repeat(4, 1fr);
          gap: 20px;
        }

        .ict-dashboard-widget {
          background: var(--ict-bg-color, #fff);
          border: 1px solid var(--ict-border-color, #e5e7eb);
          border-radius: 12px;
          overflow: hidden;
          transition: all 0.2s;
        }

        .ict-dashboard-widget.edit-mode {
          border-style: dashed;
          cursor: move;
        }

        .ict-dashboard-widget:hover {
          box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
        }

        .ict-widget-small {
          grid-column: span 1;
        }

        .ict-widget-medium {
          grid-column: span 2;
        }

        .ict-widget-large {
          grid-column: span 2;
        }

        .ict-widget-full {
          grid-column: span 4;
        }

        .ict-widget-header {
          display: flex;
          justify-content: space-between;
          align-items: center;
          padding: 16px 20px;
          border-bottom: 1px solid var(--ict-border-light, #f3f4f6);
        }

        .ict-widget-header h3 {
          margin: 0;
          font-size: 14px;
          font-weight: 600;
          color: var(--ict-text-secondary, #4b5563);
        }

        .ict-widget-actions {
          display: flex;
          gap: 8px;
        }

        .ict-widget-action {
          padding: 4px;
          background: none;
          border: none;
          color: var(--ict-text-muted, #9ca3af);
          cursor: pointer;
          border-radius: 4px;
          transition: all 0.2s;
        }

        .ict-widget-action:hover {
          background: var(--ict-bg-hover, #f3f4f6);
          color: #ef4444;
        }

        .ict-widget-content {
          padding: 20px;
          min-height: 120px;
        }

        .ict-widget-loading {
          display: flex;
          align-items: center;
          justify-content: center;
          height: 100%;
        }

        .ict-widget-spinner {
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

        .ict-widget-empty {
          display: flex;
          align-items: center;
          justify-content: center;
          height: 100%;
          color: var(--ict-text-muted, #9ca3af);
          font-size: 14px;
        }

        /* KPI Widget */
        .ict-dashboard-kpi {
          text-align: center;
        }

        .ict-kpi-header {
          display: flex;
          align-items: center;
          justify-content: center;
          gap: 8px;
          margin-bottom: 12px;
        }

        .ict-kpi-icon {
          font-size: 16px;
          color: var(--ict-text-muted, #9ca3af);
        }

        .ict-kpi-title {
          font-size: 12px;
          color: var(--ict-text-muted, #6b7280);
          text-transform: uppercase;
          letter-spacing: 0.5px;
        }

        .ict-kpi-value {
          font-size: 28px;
          font-weight: 700;
          color: var(--ict-text-color, #1f2937);
          margin-bottom: 8px;
        }

        .ict-kpi-change {
          display: flex;
          align-items: center;
          justify-content: center;
          gap: 4px;
          font-size: 13px;
          font-weight: 500;
        }

        .ict-kpi-change.increase {
          color: #10b981;
        }

        .ict-kpi-change.decrease {
          color: #ef4444;
        }

        .ict-kpi-period {
          color: var(--ict-text-muted, #9ca3af);
          font-weight: 400;
          margin-left: 4px;
        }

        /* Chart Widget */
        .ict-dashboard-chart {
          height: 100%;
          display: flex;
          flex-direction: column;
        }

        .ict-chart-legend {
          display: flex;
          gap: 16px;
          margin-bottom: 16px;
        }

        .ict-chart-legend-item {
          display: flex;
          align-items: center;
          gap: 6px;
          font-size: 12px;
          color: var(--ict-text-secondary, #4b5563);
        }

        .ict-chart-legend-color {
          width: 12px;
          height: 12px;
          border-radius: 2px;
        }

        .ict-chart-container {
          flex: 1;
        }

        .ict-line-chart {
          width: 100%;
          height: 200px;
        }

        .ict-bar-chart {
          display: flex;
          gap: 16px;
          height: 200px;
          align-items: flex-end;
        }

        .ict-bar-item {
          flex: 1;
          display: flex;
          flex-direction: column;
          align-items: center;
          gap: 8px;
        }

        .ict-bar-wrapper {
          width: 100%;
          height: 150px;
          display: flex;
          align-items: flex-end;
        }

        .ict-bar {
          width: 100%;
          border-radius: 4px 4px 0 0;
          transition: height 0.5s ease-out;
        }

        .ict-bar-label {
          font-size: 10px;
          color: var(--ict-text-muted, #6b7280);
          white-space: nowrap;
          overflow: hidden;
          text-overflow: ellipsis;
          max-width: 60px;
        }

        .ict-bar-value {
          font-size: 12px;
          font-weight: 600;
          color: var(--ict-text-color, #1f2937);
        }

        /* List Widget */
        .ict-dashboard-list {
          display: flex;
          flex-direction: column;
          gap: 12px;
        }

        .ict-list-item {
          display: flex;
          align-items: flex-start;
          gap: 12px;
          padding: 12px;
          background: var(--ict-bg-secondary, #f9fafb);
          border-radius: 8px;
        }

        .ict-list-priority {
          width: 4px;
          height: 100%;
          min-height: 32px;
          border-radius: 2px;
        }

        .ict-list-content {
          flex: 1;
        }

        .ict-list-title {
          display: block;
          font-size: 14px;
          font-weight: 500;
          color: var(--ict-text-color, #1f2937);
          margin-bottom: 4px;
        }

        .ict-list-meta {
          font-size: 12px;
          color: var(--ict-text-muted, #6b7280);
        }

        /* Add Widget Button */
        .ict-widget-add {
          display: flex;
          flex-direction: column;
          align-items: center;
          justify-content: center;
          gap: 12px;
          min-height: 160px;
          border: 2px dashed var(--ict-border-color, #e5e7eb);
          background: transparent;
          cursor: pointer;
          color: var(--ict-text-muted, #9ca3af);
          transition: all 0.2s;
        }

        .ict-widget-add:hover {
          border-color: var(--ict-primary, #3b82f6);
          color: var(--ict-primary, #3b82f6);
          background: rgba(59, 130, 246, 0.05);
        }

        @media (max-width: 1024px) {
          .ict-dashboard-grid {
            grid-template-columns: repeat(2, 1fr);
          }

          .ict-widget-large,
          .ict-widget-full {
            grid-column: span 2;
          }
        }

        @media (max-width: 640px) {
          .ict-dashboard-grid {
            grid-template-columns: 1fr;
          }

          .ict-widget-small,
          .ict-widget-medium,
          .ict-widget-large,
          .ict-widget-full {
            grid-column: span 1;
          }

          .ict-dashboard-header {
            flex-direction: column;
            gap: 16px;
            align-items: flex-start;
          }
        }
      `}</style>
    </div>
  );
};

export default EnhancedDashboard;
