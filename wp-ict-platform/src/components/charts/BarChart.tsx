/**
 * Bar Chart Component
 *
 * Simple, lightweight bar chart component for displaying data.
 * Uses native SVG for rendering without external dependencies.
 *
 * @package ICT_Platform
 * @since   1.0.0
 */

import React from 'react';

export interface BarChartDataPoint {
  label: string;
  value: number;
  color?: string;
}

interface BarChartProps {
  data: BarChartDataPoint[];
  title?: string;
  height?: number;
  valuePrefix?: string;
  valueSuffix?: string;
  showValues?: boolean;
  horizontal?: boolean;
}

const BarChart: React.FC<BarChartProps> = ({
  data,
  title,
  height = 300,
  valuePrefix = '',
  valueSuffix = '',
  showValues = true,
  horizontal = false,
}) => {
  const maxValue = Math.max(...data.map(d => d.value), 0);
  const colors = ['#0073aa', '#46b450', '#ffb900', '#dc3232', '#00a0d2', '#9333ea'];

  const formatValue = (value: number): string => {
    return `${valuePrefix}${value.toLocaleString()}${valueSuffix}`;
  };

  if (horizontal) {
    return (
      <div className="chart chart--bar chart--horizontal">
        {title && <h3 className="chart__title">{title}</h3>}
        <div className="chart__content" style={{ height: `${height}px` }}>
          {data.map((item, index) => {
            const percentage = maxValue > 0 ? (item.value / maxValue) * 100 : 0;
            const color = item.color || colors[index % colors.length];

            return (
              <div key={index} className="bar-item">
                <div className="bar-item__label">{item.label}</div>
                <div className="bar-item__bar-container">
                  <div
                    className="bar-item__bar"
                    style={{
                      width: `${percentage}%`,
                      backgroundColor: color,
                    }}
                  >
                    {showValues && (
                      <span className="bar-item__value">{formatValue(item.value)}</span>
                    )}
                  </div>
                </div>
              </div>
            );
          })}
        </div>
      </div>
    );
  }

  // Vertical bar chart
  return (
    <div className="chart chart--bar chart--vertical">
      {title && <h3 className="chart__title">{title}</h3>}
      <div className="chart__content" style={{ height: `${height}px` }}>
        <div className="bars-container">
          {data.map((item, index) => {
            const percentage = maxValue > 0 ? (item.value / maxValue) * 100 : 0;
            const color = item.color || colors[index % colors.length];

            return (
              <div key={index} className="bar-item">
                <div className="bar-item__bar-wrapper">
                  {showValues && (
                    <div className="bar-item__value">{formatValue(item.value)}</div>
                  )}
                  <div
                    className="bar-item__bar"
                    style={{
                      height: `${percentage}%`,
                      backgroundColor: color,
                    }}
                  ></div>
                </div>
                <div className="bar-item__label">{item.label}</div>
              </div>
            );
          })}
        </div>
      </div>
    </div>
  );
};

export default BarChart;
