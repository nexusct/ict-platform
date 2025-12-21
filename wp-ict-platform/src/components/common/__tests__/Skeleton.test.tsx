/**
 * Skeleton Component Tests
 *
 * @package ICT_Platform
 * @since   1.0.0
 */

import { render, screen } from '@testing-library/react';
import {
  Skeleton,
  TableSkeleton,
  CardSkeleton,
  StatCardSkeleton,
  FormSkeleton,
  ChartSkeleton,
} from '../Skeleton';

describe('Skeleton', () => {
  it('renders with default props', () => {
    const { container } = render(<Skeleton />);

    const skeleton = container.querySelector('.ict-skeleton');
    expect(skeleton).toBeInTheDocument();
    expect(skeleton).toHaveClass('ict-skeleton--text');
    expect(skeleton).toHaveClass('ict-skeleton--pulse');
  });

  it('applies custom width and height', () => {
    const { container } = render(<Skeleton width={200} height={50} />);

    const skeleton = container.querySelector('.ict-skeleton');
    expect(skeleton).toHaveStyle({ width: '200px', height: '50px' });
  });

  it('accepts string values for width and height', () => {
    const { container } = render(<Skeleton width="50%" height="2rem" />);

    const skeleton = container.querySelector('.ict-skeleton');
    expect(skeleton).toHaveStyle({ width: '50%', height: '2rem' });
  });

  it('applies variant classes correctly', () => {
    const variants = ['text', 'circular', 'rectangular', 'rounded'] as const;

    variants.forEach((variant) => {
      const { container } = render(<Skeleton variant={variant} />);
      const skeleton = container.querySelector('.ict-skeleton');
      expect(skeleton).toHaveClass(`ict-skeleton--${variant}`);
    });
  });

  it('applies animation classes correctly', () => {
    const animations = ['pulse', 'wave', 'none'] as const;

    animations.forEach((animation) => {
      const { container } = render(<Skeleton animation={animation} />);
      const skeleton = container.querySelector('.ict-skeleton');
      expect(skeleton).toHaveClass(`ict-skeleton--${animation}`);
    });
  });

  it('is hidden from accessibility tree', () => {
    const { container } = render(<Skeleton />);

    const skeleton = container.querySelector('.ict-skeleton');
    expect(skeleton).toHaveAttribute('aria-hidden', 'true');
    expect(skeleton).toHaveAttribute('role', 'presentation');
  });

  it('applies custom className', () => {
    const { container } = render(<Skeleton className="custom-class" />);

    const skeleton = container.querySelector('.ict-skeleton');
    expect(skeleton).toHaveClass('custom-class');
  });
});

describe('TableSkeleton', () => {
  it('renders correct number of rows and columns', () => {
    render(<TableSkeleton rows={3} columns={4} />);

    const table = screen.getByRole('status');
    expect(table).toBeInTheDocument();

    // Check for screen reader text
    expect(screen.getByText('Loading...')).toBeInTheDocument();
  });

  it('has proper accessibility attributes', () => {
    render(<TableSkeleton />);

    const container = screen.getByRole('status');
    expect(container).toHaveAttribute('aria-label', 'Loading table data');
  });
});

describe('CardSkeleton', () => {
  it('renders skeleton card structure', () => {
    render(<CardSkeleton />);

    const card = screen.getByRole('status');
    expect(card).toBeInTheDocument();
    expect(card).toHaveClass('ict-card-skeleton');
  });

  it('has proper accessibility label', () => {
    render(<CardSkeleton />);

    expect(screen.getByRole('status')).toHaveAttribute('aria-label', 'Loading');
  });
});

describe('StatCardSkeleton', () => {
  it('renders stat card skeleton', () => {
    render(<StatCardSkeleton />);

    const statCard = screen.getByRole('status');
    expect(statCard).toBeInTheDocument();
    expect(statCard).toHaveClass('stat-card');
  });
});

describe('FormSkeleton', () => {
  it('renders correct number of fields', () => {
    const { container } = render(<FormSkeleton fields={5} />);

    const fields = container.querySelectorAll('.ict-form-field-skeleton');
    expect(fields).toHaveLength(5);
  });

  it('defaults to 4 fields', () => {
    const { container } = render(<FormSkeleton />);

    const fields = container.querySelectorAll('.ict-form-field-skeleton');
    expect(fields).toHaveLength(4);
  });
});

describe('ChartSkeleton', () => {
  it('renders with custom height', () => {
    const { container } = render(<ChartSkeleton height={400} />);

    const chart = container.querySelector('.ict-chart-skeleton');
    expect(chart).toHaveStyle({ height: '400px' });
  });

  it('defaults to 300px height', () => {
    const { container } = render(<ChartSkeleton />);

    const chart = container.querySelector('.ict-chart-skeleton');
    expect(chart).toHaveStyle({ height: '300px' });
  });

  it('has proper accessibility label', () => {
    render(<ChartSkeleton />);

    expect(screen.getByRole('status')).toHaveAttribute('aria-label', 'Loading chart');
  });
});
