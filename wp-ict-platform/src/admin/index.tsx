/**
 * Admin App Entry Point
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
import '../styles/admin.scss';

// Dashboard root
const dashboardRoot = document.getElementById('ict-dashboard-root');
if (dashboardRoot) {
  createRoot(dashboardRoot).render(
    <React.StrictMode>
      <Provider store={store}>
        <div className="ict-platform-app">
          <h1>ICT Platform Dashboard</h1>
          <p>Welcome to the ICT Platform management system.</p>
        </div>
      </Provider>
    </React.StrictMode>
  );
}

// Projects root
const projectsRoot = document.getElementById('ict-projects-root');
if (projectsRoot) {
  createRoot(projectsRoot).render(
    <React.StrictMode>
      <Provider store={store}>
        <ProjectDashboard />
      </Provider>
    </React.StrictMode>
  );
}

// Time Tracking root
const timeTrackingRoot = document.getElementById('ict-time-tracking-root');
if (timeTrackingRoot) {
  createRoot(timeTrackingRoot).render(
    <React.StrictMode>
      <Provider store={store}>
        <TimesheetList />
      </Provider>
    </React.StrictMode>
  );
}

// Time Clock root (separate mobile-friendly page)
const timeClockRoot = document.getElementById('ict-time-clock-root');
if (timeClockRoot) {
  createRoot(timeClockRoot).render(
    <React.StrictMode>
      <Provider store={store}>
        <div className="ict-platform-app ict-platform-app--time-clock">
          <TimeTracker />
          <TimeClock />
        </div>
      </Provider>
    </React.StrictMode>
  );
}

// Timesheet Approval root (for managers)
const timesheetApprovalRoot = document.getElementById('ict-timesheet-approval-root');
if (timesheetApprovalRoot) {
  createRoot(timesheetApprovalRoot).render(
    <React.StrictMode>
      <Provider store={store}>
        <TimesheetApproval />
      </Provider>
    </React.StrictMode>
  );
}

// Resources root - Main resource management page
const resourcesRoot = document.getElementById('ict-resources-root');
if (resourcesRoot) {
  createRoot(resourcesRoot).render(
    <React.StrictMode>
      <Provider store={store}>
        <div className="ict-platform-app">
          <ResourceCalendar editable={true} />
        </div>
      </Provider>
    </React.StrictMode>
  );
}

// Resource Calendar root (separate page for calendar view)
const resourceCalendarRoot = document.getElementById('ict-resource-calendar-root');
if (resourceCalendarRoot) {
  createRoot(resourceCalendarRoot).render(
    <React.StrictMode>
      <Provider store={store}>
        <ResourceCalendar editable={true} />
      </Provider>
    </React.StrictMode>
  );
}

// Availability Matrix root
const availabilityMatrixRoot = document.getElementById('ict-availability-matrix-root');
if (availabilityMatrixRoot) {
  createRoot(availabilityMatrixRoot).render(
    <React.StrictMode>
      <Provider store={store}>
        <AvailabilityMatrix />
      </Provider>
    </React.StrictMode>
  );
}

// Resource Allocation root (for creating/editing allocations)
const resourceAllocationRoot = document.getElementById('ict-resource-allocation-root');
if (resourceAllocationRoot) {
  createRoot(resourceAllocationRoot).render(
    <React.StrictMode>
      <Provider store={store}>
        <ResourceAllocation />
      </Provider>
    </React.StrictMode>
  );
}

// Skill Matrix root
const skillMatrixRoot = document.getElementById('ict-skill-matrix-root');
if (skillMatrixRoot) {
  createRoot(skillMatrixRoot).render(
    <React.StrictMode>
      <Provider store={store}>
        <SkillMatrix editable={true} />
      </Provider>
    </React.StrictMode>
  );
}

// Inventory root - Main inventory dashboard
const inventoryRoot = document.getElementById('ict-inventory-root');
if (inventoryRoot) {
  createRoot(inventoryRoot).render(
    <React.StrictMode>
      <Provider store={store}>
        <InventoryDashboard />
      </Provider>
    </React.StrictMode>
  );
}

// Inventory Dashboard root (separate page)
const inventoryDashboardRoot = document.getElementById('ict-inventory-dashboard-root');
if (inventoryDashboardRoot) {
  createRoot(inventoryDashboardRoot).render(
    <React.StrictMode>
      <Provider store={store}>
        <InventoryDashboard />
      </Provider>
    </React.StrictMode>
  );
}

// Stock Adjustment root
const stockAdjustmentRoot = document.getElementById('ict-stock-adjustment-root');
if (stockAdjustmentRoot) {
  createRoot(stockAdjustmentRoot).render(
    <React.StrictMode>
      <Provider store={store}>
        <StockAdjustment />
      </Provider>
    </React.StrictMode>
  );
}

// Purchase Order Form root
const purchaseOrderFormRoot = document.getElementById('ict-purchase-order-form-root');
if (purchaseOrderFormRoot) {
  createRoot(purchaseOrderFormRoot).render(
    <React.StrictMode>
      <Provider store={store}>
        <PurchaseOrderForm />
      </Provider>
    </React.StrictMode>
  );
}

// Low Stock Alerts root (widget)
const lowStockAlertsRoot = document.getElementById('ict-low-stock-alerts-root');
if (lowStockAlertsRoot) {
  createRoot(lowStockAlertsRoot).render(
    <React.StrictMode>
      <Provider store={store}>
        <LowStockAlerts maxItems={10} autoRefresh={true} />
      </Provider>
    </React.StrictMode>
  );
}

// Low Stock Alerts Widget (compact version for dashboard)
const lowStockAlertsWidgetRoot = document.getElementById('ict-low-stock-alerts-widget-root');
if (lowStockAlertsWidgetRoot) {
  createRoot(lowStockAlertsWidgetRoot).render(
    <React.StrictMode>
      <Provider store={store}>
        <LowStockAlerts maxItems={5} compact={true} showHeader={true} />
      </Provider>
    </React.StrictMode>
  );
}

// Reports root - Main reports dashboard
const reportsRoot = document.getElementById('ict-reports-root');
if (reportsRoot) {
  createRoot(reportsRoot).render(
    <React.StrictMode>
      <Provider store={store}>
        <ReportsDashboard />
      </Provider>
    </React.StrictMode>
  );
}

// Reports Dashboard root (separate page)
const reportsDashboardRoot = document.getElementById('ict-reports-dashboard-root');
if (reportsDashboardRoot) {
  createRoot(reportsDashboardRoot).render(
    <React.StrictMode>
      <Provider store={store}>
        <ReportsDashboard />
      </Provider>
    </React.StrictMode>
  );
}

// Sync root
const syncRoot = document.getElementById('ict-sync-root');
if (syncRoot) {
  createRoot(syncRoot).render(
    <React.StrictMode>
      <Provider store={store}>
        <div className="ict-platform-app">
          <h1>Zoho Sync</h1>
          <p>Sync management module coming soon...</p>
        </div>
      </Provider>
    </React.StrictMode>
  );
}

// Settings root
const settingsRoot = document.getElementById('ict-settings-root');
if (settingsRoot) {
  createRoot(settingsRoot).render(
    <React.StrictMode>
      <Provider store={store}>
        <div className="ict-platform-app">
          <h1>Settings</h1>
          <p>Settings module coming soon...</p>
        </div>
      </Provider>
    </React.StrictMode>
  );
}
