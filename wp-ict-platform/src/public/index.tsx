/**
 * Public App Entry Point
 *
 * @package ICT_Platform
 * @since   1.0.0
 */

import React from 'react';
import { createRoot } from 'react-dom/client';
import { Provider } from 'react-redux';
import { store } from '../store';
import { TimeTracker } from '../components/time/TimeTracker';
import { TimeClock } from '../components/time/TimeClock';

// Public Time Clock (for technicians)
const publicTimeClockRoot = document.getElementById('ict-public-time-clock');
if (publicTimeClockRoot) {
  createRoot(publicTimeClockRoot).render(
    <React.StrictMode>
      <Provider store={store}>
        <div className="ict-public-app">
          <TimeTracker />
          <TimeClock />
        </div>
      </Provider>
    </React.StrictMode>
  );
}

// Client Portal root
const clientPortalRoot = document.getElementById('ict-client-portal-root');
if (clientPortalRoot) {
  createRoot(clientPortalRoot).render(
    <React.StrictMode>
      <Provider store={store}>
        <div className="ict-public-app">
          <h1>Client Portal</h1>
          <p>Welcome to the ICT Platform client portal.</p>
        </div>
      </Provider>
    </React.StrictMode>
  );
}
