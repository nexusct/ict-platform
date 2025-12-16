/**
 * Time Tracker Standalone App
 *
 * @package ICT_Platform
 * @since   1.0.0
 */

import React from 'react';
import { createRoot } from 'react-dom/client';
import { Provider } from 'react-redux';
import { store } from '../../store';
import { TimeTracker } from '../../components/time/TimeTracker';
import { TimeClock } from '../../components/time/TimeClock';
import { TimesheetList } from '../../components/time/TimesheetList';

// Standalone Time Tracker App
const appRoot = document.getElementById('ict-time-tracker-app');
if (appRoot) {
  createRoot(appRoot).render(
    <React.StrictMode>
      <Provider store={store}>
        <div className="ict-standalone-app ict-time-tracker-app">
          <TimeTracker />
          <TimeClock />
          <TimesheetList />
        </div>
      </Provider>
    </React.StrictMode>
  );
}
