/**
 * Skeleton Loading Component
 *
 * Provides skeleton placeholders for better perceived performance during loading
 *
 * @package ICT_Platform
 * @since   1.0.0
 */

import React from 'react';

interface SkeletonProps {
  width?: string | number;
  height?: string | number;
  variant?: 'text' | 'circular' | 'rectangular' | 'rounded';
  animation?: 'pulse' | 'wave' | 'none';
  className?: string;
}

export const Skeleton: React.FC<SkeletonProps> = ({
  width = '100%',
  height = 20,
  variant = 'text',
  animation = 'pulse',
  className = '',
}) => {
  const style: React.CSSProperties = {
    width: typeof width === 'number' ? `${width}px` : width,
    height: typeof height === 'number' ? `${height}px` : height,
  };

  return (
    <div
      className={`ict-skeleton ict-skeleton--${variant} ict-skeleton--${animation} ${className}`}
      style={style}
      aria-hidden="true"
      role="presentation"
    />
  );
};

// Pre-built skeleton patterns for common UI elements
export const TableRowSkeleton: React.FC<{ columns?: number }> = ({ columns = 5 }) => (
  <tr className="ict-skeleton-row">
    {Array.from({ length: columns }).map((_, i) => (
      <td key={i}>
        <Skeleton height={16} />
      </td>
    ))}
  </tr>
);

export const TableSkeleton: React.FC<{ rows?: number; columns?: number }> = ({
  rows = 5,
  columns = 5,
}) => (
  <div className="ict-table-skeleton" role="status" aria-label="Loading table data">
    <table className="widefat">
      <thead>
        <tr>
          {Array.from({ length: columns }).map((_, i) => (
            <th key={i}>
              <Skeleton height={16} width="80%" />
            </th>
          ))}
        </tr>
      </thead>
      <tbody>
        {Array.from({ length: rows }).map((_, i) => (
          <TableRowSkeleton key={i} columns={columns} />
        ))}
      </tbody>
    </table>
    <span className="screen-reader-text">Loading...</span>
  </div>
);

export const CardSkeleton: React.FC = () => (
  <div className="ict-card-skeleton" role="status" aria-label="Loading">
    <div className="ict-card-skeleton__header">
      <Skeleton variant="circular" width={40} height={40} />
      <div className="ict-card-skeleton__title">
        <Skeleton height={16} width="60%" />
        <Skeleton height={12} width="40%" />
      </div>
    </div>
    <div className="ict-card-skeleton__body">
      <Skeleton height={14} />
      <Skeleton height={14} />
      <Skeleton height={14} width="80%" />
    </div>
    <span className="screen-reader-text">Loading...</span>
  </div>
);

export const StatCardSkeleton: React.FC = () => (
  <div className="stat-card ict-skeleton-stat-card" role="status" aria-label="Loading statistic">
    <Skeleton variant="circular" width={48} height={48} />
    <div className="stat-content">
      <Skeleton height={28} width={60} />
      <Skeleton height={14} width={80} />
    </div>
    <span className="screen-reader-text">Loading...</span>
  </div>
);

export const ListItemSkeleton: React.FC = () => (
  <div className="ict-list-item-skeleton" role="status" aria-label="Loading item">
    <Skeleton variant="circular" width={32} height={32} />
    <div className="ict-list-item-skeleton__content">
      <Skeleton height={16} width="70%" />
      <Skeleton height={12} width="50%" />
    </div>
    <span className="screen-reader-text">Loading...</span>
  </div>
);

export const FormFieldSkeleton: React.FC<{ hasLabel?: boolean }> = ({ hasLabel = true }) => (
  <div className="ict-form-field-skeleton" role="status" aria-label="Loading form field">
    {hasLabel && <Skeleton height={14} width={100} className="ict-form-field-skeleton__label" />}
    <Skeleton height={36} variant="rounded" />
    <span className="screen-reader-text">Loading...</span>
  </div>
);

export const FormSkeleton: React.FC<{ fields?: number }> = ({ fields = 4 }) => (
  <div className="ict-form-skeleton" role="status" aria-label="Loading form">
    {Array.from({ length: fields }).map((_, i) => (
      <FormFieldSkeleton key={i} />
    ))}
    <div className="ict-form-skeleton__actions">
      <Skeleton height={36} width={100} variant="rounded" />
      <Skeleton height={36} width={80} variant="rounded" />
    </div>
    <span className="screen-reader-text">Loading...</span>
  </div>
);

export const ChartSkeleton: React.FC<{ height?: number }> = ({ height = 300 }) => (
  <div
    className="ict-chart-skeleton"
    role="status"
    aria-label="Loading chart"
    style={{ height }}
  >
    <div className="ict-chart-skeleton__bars">
      {Array.from({ length: 7 }).map((_, i) => (
        <div
          key={i}
          className="ict-chart-skeleton__bar"
          style={{ height: `${30 + Math.random() * 60}%` }}
        />
      ))}
    </div>
    <span className="screen-reader-text">Loading chart...</span>
  </div>
);

export const ProjectListSkeleton: React.FC = () => (
  <div className="ict-project-list" role="status" aria-label="Loading projects">
    <div className="list-filters">
      <Skeleton height={36} width={300} variant="rounded" />
      <Skeleton height={36} width={150} variant="rounded" />
    </div>
    <TableSkeleton rows={5} columns={7} />
    <span className="screen-reader-text">Loading projects...</span>
  </div>
);

export const DashboardSkeleton: React.FC = () => (
  <div className="ict-dashboard-skeleton" role="status" aria-label="Loading dashboard">
    <div className="dashboard-header">
      <Skeleton height={32} width={200} />
      <Skeleton height={36} width={120} variant="rounded" />
    </div>
    <div className="project-stats-grid">
      {Array.from({ length: 4 }).map((_, i) => (
        <StatCardSkeleton key={i} />
      ))}
    </div>
    <div className="ict-dashboard-skeleton__content">
      <ChartSkeleton />
    </div>
    <span className="screen-reader-text">Loading dashboard...</span>
  </div>
);

export default Skeleton;
