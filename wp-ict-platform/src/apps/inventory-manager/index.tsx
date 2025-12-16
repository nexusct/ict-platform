/**
 * Inventory Manager Standalone App
 *
 * @package ICT_Platform
 * @since   1.0.0
 */

import React from 'react';
import { createRoot } from 'react-dom/client';
import { Provider } from 'react-redux';
import { store } from '../../store';
import InventoryDashboard from '../../components/inventory/InventoryDashboard';
import StockAdjustment from '../../components/inventory/StockAdjustment';
import LowStockAlerts from '../../components/inventory/LowStockAlerts';

// Standalone Inventory Manager App
const appRoot = document.getElementById('ict-inventory-manager-app');
if (appRoot) {
  createRoot(appRoot).render(
    <React.StrictMode>
      <Provider store={store}>
        <div className="ict-standalone-app ict-inventory-manager-app">
          <InventoryDashboard />
          <LowStockAlerts maxItems={10} autoRefresh={true} />
          <StockAdjustment />
        </div>
      </Provider>
    </React.StrictMode>
  );
}
