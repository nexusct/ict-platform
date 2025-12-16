/**
 * Redux Store Configuration
 *
 * @package ICT_Platform_Mobile
 * @since   1.0.0
 */

import { configureStore } from '@reduxjs/toolkit';
import authReducer from './slices/authSlice';
import projectsReducer from './slices/projectsSlice';
import timeEntriesReducer from './slices/timeEntriesSlice';
import inventoryReducer from './slices/inventorySlice';
import offlineReducer from './slices/offlineSlice';
import notificationsReducer from './slices/notificationsSlice';

export const store = configureStore({
  reducer: {
    auth: authReducer,
    projects: projectsReducer,
    timeEntries: timeEntriesReducer,
    inventory: inventoryReducer,
    offline: offlineReducer,
    notifications: notificationsReducer,
  },
  middleware: (getDefaultMiddleware) =>
    getDefaultMiddleware({
      serializableCheck: {
        // Ignore these action types
        ignoredActions: ['persist/PERSIST', 'persist/REHYDRATE'],
      },
    }),
});

export type RootState = ReturnType<typeof store.getState>;
export type AppDispatch = typeof store.dispatch;
