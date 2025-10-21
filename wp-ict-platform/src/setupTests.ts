/**
 * Test setup file
 *
 * @package ICT_Platform
 * @since   1.0.0
 */

import '@testing-library/jest-dom';

// Mock WordPress localized script
(global as any).ictPlatform = {
  apiUrl: 'http://localhost/wp-json/ict/v1',
  nonce: 'test-nonce',
  currentUser: 1,
  userCan: {
    manageProjects: true,
    manageTimeEntries: true,
    approveTime: true,
    manageInventory: true,
    managePurchaseOrders: true,
    viewReports: true,
    manageSync: true,
  },
  settings: {
    dateFormat: 'Y-m-d',
    timeFormat: 'H:i:s',
    currency: 'USD',
    timeRounding: 15,
    offlineMode: true,
    gpsTracking: true,
  },
  zohoStatus: {
    crm: true,
    fsm: true,
    books: true,
    people: true,
    desk: true,
  },
  i18n: {
    projects: 'Projects',
    timeTracking: 'Time Tracking',
    inventory: 'Inventory',
    reports: 'Reports',
    settings: 'Settings',
    sync: 'Sync',
  },
};
