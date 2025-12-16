/**
 * Project Dashboard Standalone App
 *
 * @package ICT_Platform
 * @since   1.0.0
 */

import React from 'react';
import { createRoot } from 'react-dom/client';
import { Provider } from 'react-redux';
import { store } from '../../store';
import { ProjectDashboard } from '../../components/projects/ProjectDashboard';
import ResourceCalendar from '../../components/resources/ResourceCalendar';

// Standalone Project Dashboard App
const appRoot = document.getElementById('ict-project-dashboard-app');
if (appRoot) {
  createRoot(appRoot).render(
    <React.StrictMode>
      <Provider store={store}>
        <div className="ict-standalone-app ict-project-dashboard-app">
          <ProjectDashboard />
          <ResourceCalendar editable={true} />
        </div>
      </Provider>
    </React.StrictMode>
  );
}
