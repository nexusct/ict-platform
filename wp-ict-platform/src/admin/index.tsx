/**
 * Admin App Entry Point
 *
 * Enhanced with ErrorBoundary, ToastContainer, and OfflineBanner for reliability
 *
 * @package ICT_Platform
 * @since   1.0.0
 */

import React from 'react';
import { createRoot } from 'react-dom/client';
import { Provider } from 'react-redux';
import { store } from '../store';
import { ProjectDashboard } from '../components/projects/ProjectDashboard';
import { TimeTracker } from '../components/time/TimeTracker';
import { TimeClock } from '../components/time/TimeClock';
import { TimesheetList } from '../components/time/TimesheetList';
import { TimesheetApproval } from '../components/time/TimesheetApproval';
import ResourceCalendar from '../components/resources/ResourceCalendar';
import AvailabilityMatrix from '../components/resources/AvailabilityMatrix';
import ResourceAllocation from '../components/resources/ResourceAllocation';
import SkillMatrix from '../components/resources/SkillMatrix';
import InventoryDashboard from '../components/inventory/InventoryDashboard';
import StockAdjustment from '../components/inventory/StockAdjustment';
import PurchaseOrderForm from '../components/inventory/PurchaseOrderForm';
import LowStockAlerts from '../components/inventory/LowStockAlerts';
import ReportsDashboard from '../components/reports/ReportsDashboard';
import { ErrorBoundary, ToastContainer, OfflineBanner, SessionTimeoutWarning } from '../components/common';
import '../styles/admin.scss';

/**
 * AppWrapper provides common functionality for all app roots:
 * - Error boundary for crash protection
 * - Redux store provider
 * - Offline status banner
 * - Toast notifications
 * - Session timeout warning
 */
interface AppWrapperProps {
  children: React.ReactNode;
  showSessionWarning?: boolean;
}

const AppWrapper: React.FC<AppWrapperProps> = ({ children, showSessionWarning = true }) => (
  <ErrorBoundary showDetails={process.env.NODE_ENV !== 'production'}>
    <Provider store={store}>
      <OfflineBanner position="top" />
      {children}
      <ToastContainer position="top-right" />
      {showSessionWarning && <SessionTimeoutWarning />}
    </Provider>
  </ErrorBoundary>
);

/**
 * Helper function to render a React root with common wrapper
 */
function renderApp(elementId: string, Component: React.ReactNode, options?: { showSessionWarning?: boolean }) {
  const root = document.getElementById(elementId);
  if (root) {
    createRoot(root).render(
      <React.StrictMode>
        <AppWrapper showSessionWarning={options?.showSessionWarning}>
          {Component}
        </AppWrapper>
      </React.StrictMode>
    );
  }
}

// Dashboard root
renderApp('ict-dashboard-root', (
  <div className="ict-platform-app">
    <h1>ICT Platform Dashboard</h1>
    <p>Welcome to the ICT Platform management system.</p>
  </div>
));

// Projects root
renderApp('ict-projects-root', <ProjectDashboard />);

// Time Tracking root
renderApp('ict-time-tracking-root', <TimesheetList />);

// Time Clock root (separate mobile-friendly page)
renderApp('ict-time-clock-root', (
  <div className="ict-platform-app ict-platform-app--time-clock">
    <TimeTracker />
    <TimeClock />
  </div>
));

// Timesheet Approval root (for managers)
renderApp('ict-timesheet-approval-root', <TimesheetApproval />);

// Resources root - Main resource management page
renderApp('ict-resources-root', (
  <div className="ict-platform-app">
    <ResourceCalendar editable={true} />
  </div>
));

// Resource Calendar root (separate page for calendar view)
renderApp('ict-resource-calendar-root', <ResourceCalendar editable={true} />);

// Availability Matrix root
renderApp('ict-availability-matrix-root', <AvailabilityMatrix />);

// Resource Allocation root (for creating/editing allocations)
renderApp('ict-resource-allocation-root', <ResourceAllocation />);

// Skill Matrix root
renderApp('ict-skill-matrix-root', <SkillMatrix editable={true} />);

// Inventory root - Main inventory dashboard
renderApp('ict-inventory-root', <InventoryDashboard />);

// Inventory Dashboard root (separate page)
renderApp('ict-inventory-dashboard-root', <InventoryDashboard />);

// Stock Adjustment root
renderApp('ict-stock-adjustment-root', <StockAdjustment />);

// Purchase Order Form root
renderApp('ict-purchase-order-form-root', <PurchaseOrderForm />);

// Low Stock Alerts root (widget)
renderApp('ict-low-stock-alerts-root', <LowStockAlerts maxItems={10} autoRefresh={true} />);

// Low Stock Alerts Widget (compact version for dashboard)
renderApp('ict-low-stock-alerts-widget-root', <LowStockAlerts maxItems={5} compact={true} showHeader={true} />);

// Reports root - Main reports dashboard
renderApp('ict-reports-root', <ReportsDashboard />);

// Reports Dashboard root (separate page)
renderApp('ict-reports-dashboard-root', <ReportsDashboard />);

// Sync root
renderApp('ict-sync-root', (
  <div className="ict-platform-app">
    <h1>Zoho Sync</h1>
    <p>Sync management module coming soon...</p>
  </div>
));

// Settings root
renderApp('ict-settings-root', (
  <div className="ict-platform-app">
    <h1>Settings</h1>
    <p>Settings module coming soon...</p>
  </div>
));
