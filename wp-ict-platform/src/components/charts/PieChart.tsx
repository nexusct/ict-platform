/**
 * Pie/Donut Chart Component
 *
 * Simple pie or donut chart component for displaying proportional data.
 * Uses native SVG for rendering without external dependencies.
 *
 * @package ICT_Platform
 * @since   1.0.0
 */

import React from 'react';

export interface PieChartDataPoint {
  label: string;
  value: number;
  color?: string;
}

interface PieChartProps {
  data: PieChartDataPoint[];
  title?: string;
  size?: number;
  donut?: boolean;
  donutWidth?: number;
  showLegend?: boolean;
  showPercentages?: boolean;
  valuePrefix?: string;
  valueSuffix?: string;
}

const PieChart: React.FC<PieChartProps> = ({
  data,
  title,
  size = 300,
  donut = false,
  donutWidth = 60,
  showLegend = true,
  showPercentages = true,
  valuePrefix = '',
  valueSuffix = '',
}) => {
  const colors = ['#0073aa', '#46b450', '#ffb900', '#dc3232', '#00a0d2', '#9333ea', '#f56565', '#4299e1'];
  const center = size / 2;
  const radius = donut ? (size / 2) - (donutWidth / 2) : size / 2 - 10;

  const total = data.reduce((sum, item) => sum + item.value, 0);

  if (total === 0) {
    return (
      <div className="chart chart--pie">
        {title && <h3 className="chart__title">{title}</h3>}
        <div className="chart__empty">No data available</div>
      </div>
    );
  }

  let currentAngle = -90; // Start from top

  const slices = data.map((item, index) => {
    const percentage = (item.value / total) * 100;
    const angle = (item.value / total) * 360;
    const startAngle = currentAngle;
    const endAngle = currentAngle + angle;

    // Calculate path
    const startRad = (startAngle * Math.PI) / 180;
    const endRad = (endAngle * Math.PI) / 180;

    const x1 = center + radius * Math.cos(startRad);
    const y1 = center + radius * Math.sin(startRad);
    const x2 = center + radius * Math.cos(endRad);
    const y2 = center + radius * Math.sin(endRad);

    const largeArc = angle > 180 ? 1 : 0;

    let path;
    if (donut) {
      const innerRadius = radius - donutWidth;
      const ix1 = center + innerRadius * Math.cos(startRad);
      const iy1 = center + innerRadius * Math.sin(startRad);
      const ix2 = center + innerRadius * Math.cos(endRad);
      const iy2 = center + innerRadius * Math.sin(endRad);

      path = `
        M ${x1} ${y1}
        A ${radius} ${radius} 0 ${largeArc} 1 ${x2} ${y2}
        L ${ix2} ${iy2}
        A ${innerRadius} ${innerRadius} 0 ${largeArc} 0 ${ix1} ${iy1}
        Z
      `;
    } else {
      path = `
        M ${center} ${center}
        L ${x1} ${y1}
        A ${radius} ${radius} 0 ${largeArc} 1 ${x2} ${y2}
        Z
      `;
    }

    currentAngle = endAngle;

    return {
      path,
      color: item.color || colors[index % colors.length],
      label: item.label,
      value: item.value,
      percentage: percentage.toFixed(1),
    };
  });

  return (
    <div className="chart chart--pie">
      {title && <h3 className="chart__title">{title}</h3>}
      <div className="chart__content chart__content--pie">
        <div className="pie-chart-wrapper">
          <svg width={size} height={size} viewBox={`0 0 ${size} ${size}`} className="pie-chart-svg">
            {slices.map((slice, index) => (
              <g key={index} className="pie-slice">
                <path
                  d={slice.path}
                  fill={slice.color}
                  stroke="white"
                  strokeWidth="2"
                  className="pie-slice__path"
                />
                <title>
                  {slice.label}: {valuePrefix}{slice.value.toLocaleString()}{valueSuffix} ({slice.percentage}%)
                </title>
              </g>
            ))}

            {donut && (
              <g className="pie-center-text">
                <text
                  x={center}
                  y={center - 10}
                  textAnchor="middle"
                  fontSize="24"
                  fontWeight="700"
                  fill="#1f2937"
                >
                  {total.toLocaleString()}
                </text>
                <text
                  x={center}
                  y={center + 15}
                  textAnchor="middle"
                  fontSize="12"
                  fill="#6b7280"
                >
                  Total
                </text>
              </g>
            )}
          </svg>

          {showLegend && (
            <div className="pie-legend">
              {slices.map((slice, index) => (
                <div key={index} className="pie-legend__item">
                  <div
                    className="pie-legend__color"
                    style={{ backgroundColor: slice.color }}
                  ></div>
                  <div className="pie-legend__label">{slice.label}</div>
                  <div className="pie-legend__value">
                    {valuePrefix}{slice.value.toLocaleString()}{valueSuffix}
                    {showPercentages && <span className="pie-legend__percentage"> ({slice.percentage}%)</span>}
                  </div>
                </div>
              ))}
            </div>
          )}
        </div>
      </div>
    </div>
  );
};

export default PieChart;
