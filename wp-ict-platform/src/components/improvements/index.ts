/**
 * Improvement Components Index
 *
 * Exports all reliability and GUI improvement components.
 *
 * @package ICT_Platform
 * @since   2.1.0
 */

// Offline Support
export { default as OfflineIndicator, OfflineProvider, useOffline } from './OfflineManager';

// Theme
export { default as DarkModeToggle, ThemeProvider, useTheme } from './DarkModeToggle';

// Bulk Operations
export { default as BulkActions } from './BulkActions';

// Global Search
export { default as GlobalSearch } from './GlobalSearch';

// Project Management
export { default as GanttChart } from './GanttChart';

// Inventory
export { default as BarcodeScanner } from './BarcodeScanner';

// Resource Management
export { default as ResourceScheduler } from './ResourceScheduler';

// Activity & Monitoring
export { default as ActivityFeed } from './ActivityFeed';

// Time Tracking
export { default as AdvancedTimeTracking } from './AdvancedTimeTracking';

// Dashboard & Reporting
export { default as EnhancedDashboard } from './EnhancedDashboard';
