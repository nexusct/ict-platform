/**
 * Redux Store Configuration
 *
 * @package ICT_Platform
 * @since   1.0.0
 */

import { configureStore } from '@reduxjs/toolkit';
import projectsReducer from './slices/projectsSlice';
import timeEntriesReducer from './slices/timeEntriesSlice';
import resourcesReducer from './slices/resourcesSlice';
import inventoryReducer from './slices/inventorySlice';
import purchaseOrdersReducer from './slices/purchaseOrdersSlice';
import reportsReducer from './slices/reportsSlice';
import syncReducer from './slices/syncSlice';
import uiReducer from './slices/uiSlice';

export const store = configureStore({
  reducer: {
    projects: projectsReducer,
    timeEntries: timeEntriesReducer,
    resources: resourcesReducer,
    inventory: inventoryReducer,
    purchaseOrders: purchaseOrdersReducer,
    reports: reportsReducer,
    sync: syncReducer,
    ui: uiReducer,
  },
  middleware: (getDefaultMiddleware) =>
    getDefaultMiddleware({
      serializableCheck: {
        // Ignore these action types
        ignoredActions: ['ui/showToast'],
      },
    }),
});

export type RootState = ReturnType<typeof store.getState>;
export type AppDispatch = typeof store.dispatch;
