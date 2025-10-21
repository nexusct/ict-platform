/**
 * Line Chart Component
 *
 * Simple line chart component for displaying time series data.
 * Uses native SVG for rendering without external dependencies.
 *
 * @package ICT_Platform
 * @since   1.0.0
 */

import React from 'react';

export interface LineChartDataPoint {
  label: string;
  value: number;
}

interface LineChartProps {
  data: LineChartDataPoint[];
  title?: string;
  height?: number;
  color?: string;
  valuePrefix?: string;
  valueSuffix?: string;
  showDots?: boolean;
  showGrid?: boolean;
  fillArea?: boolean;
}

const LineChart: React.FC<LineChartProps> = ({
  data,
  title,
  height = 300,
  color = '#0073aa',
  valuePrefix = '',
  valueSuffix = '',
  showDots = true,
  showGrid = true,
  fillArea = true,
}) => {
  const padding = { top: 20, right: 20, bottom: 40, left: 50 };
  const chartWidth = 600;
  const chartHeight = height;
  const graphWidth = chartWidth - padding.left - padding.right;
  const graphHeight = chartHeight - padding.top - padding.bottom;

  if (data.length === 0) {
    return (
      <div className="chart chart--line">
        {title && <h3 className="chart__title">{title}</h3>}
        <div className="chart__empty">No data available</div>
      </div>
    );
  }

  const maxValue = Math.max(...data.map(d => d.value), 0);
  const minValue = Math.min(...data.map(d => d.value), 0);
  const valueRange = maxValue - minValue || 1;

  // Calculate points
  const points = data.map((point, index) => {
    const x = padding.left + (index / (data.length - 1 || 1)) * graphWidth;
    const y = padding.top + graphHeight - ((point.value - minValue) / valueRange) * graphHeight;
    return { x, y, value: point.value, label: point.label };
  });

  // Create line path
  const linePath = points
    .map((point, index) => {
      const command = index === 0 ? 'M' : 'L';
      return `${command} ${point.x} ${point.y}`;
    })
    .join(' ');

  // Create area path
  const areaPath = fillArea
    ? `${linePath} L ${points[points.length - 1].x} ${padding.top + graphHeight} L ${padding.left} ${padding.top + graphHeight} Z`
    : '';

  // Grid lines
  const gridLines = [];
  if (showGrid) {
    for (let i = 0; i <= 5; i++) {
      const y = padding.top + (graphHeight / 5) * i;
      const value = maxValue - (valueRange / 5) * i;
      gridLines.push({
        y,
        value: `${valuePrefix}${value.toFixed(0)}${valueSuffix}`,
      });
    }
  }

  return (
    <div className="chart chart--line">
      {title && <h3 className="chart__title">{title}</h3>}
      <div className="chart__content">
        <svg
          width={chartWidth}
          height={chartHeight}
          viewBox={`0 0 ${chartWidth} ${chartHeight}`}
          className="line-chart-svg"
        >
          {/* Grid lines */}
          {showGrid &&
            gridLines.map((line, index) => (
              <g key={index}>
                <line
                  x1={padding.left}
                  y1={line.y}
                  x2={chartWidth - padding.right}
                  y2={line.y}
                  stroke="#e5e7eb"
                  strokeWidth="1"
                />
                <text
                  x={padding.left - 10}
                  y={line.y}
                  textAnchor="end"
                  alignmentBaseline="middle"
                  fontSize="12"
                  fill="#6b7280"
                >
                  {line.value}
                </text>
              </g>
            ))}

          {/* Area fill */}
          {fillArea && (
            <path d={areaPath} fill={color} fillOpacity="0.2" />
          )}

          {/* Line */}
          <path
            d={linePath}
            fill="none"
            stroke={color}
            strokeWidth="2"
            strokeLinecap="round"
            strokeLinejoin="round"
          />

          {/* Data points */}
          {showDots &&
            points.map((point, index) => (
              <g key={index}>
                <circle
                  cx={point.x}
                  cy={point.y}
                  r="4"
                  fill="white"
                  stroke={color}
                  strokeWidth="2"
                  className="line-chart-dot"
                />
                <title>{`${point.label}: ${valuePrefix}${point.value}${valueSuffix}`}</title>
              </g>
            ))}

          {/* X-axis labels */}
          {data.map((point, index) => {
            const x = padding.left + (index / (data.length - 1 || 1)) * graphWidth;
            const shouldShow = data.length <= 12 || index % Math.ceil(data.length / 12) === 0;

            return shouldShow ? (
              <text
                key={index}
                x={x}
                y={chartHeight - padding.bottom + 20}
                textAnchor="middle"
                fontSize="11"
                fill="#6b7280"
              >
                {point.label}
              </text>
            ) : null;
          })}
        </svg>
      </div>
    </div>
  );
};

export default LineChart;
