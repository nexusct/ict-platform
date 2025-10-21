/**
 * Reports Redux Slice
 *
 * Manages reports state including:
 * - Project reports
 * - Time reports
 * - Budget reports
 * - Inventory reports
 * - Dashboard summary
 * - Report exports
 *
 * @package ICT_Platform
 * @since   1.0.0
 */

import { createSlice, createAsyncThunk, PayloadAction } from '@reduxjs/toolkit';
import { reportsAPI } from '../../services/api';

// Report Data Types
export interface ProjectReportData {
  statistics: {
    total_projects: number;
    active_projects: number;
    completed_projects: number;
    on_hold_projects: number;
    cancelled_projects: number;
    avg_progress: number;
    total_budget: number;
    total_cost: number;
    total_estimated_hours: number;
    total_actual_hours: number;
  };
  by_status: Array<{ status: string; count: number }>;
  by_priority: Array<{ priority: string; count: number }>;
  timeline: Array<{ month: string; count: number }>;
  top_projects: Array<{
    id: number;
    project_name: string;
    budget_amount: number;
    actual_cost: number;
    progress_percentage: number;
    status: string;
  }>;
  generated_at: string;
}

export interface TimeReportData {
  statistics: {
    total_entries: number;
    total_hours: number;
    billable_hours: number;
    total_revenue: number;
    avg_hours_per_entry: number;
  };
  grouped_data: Array<{
    group_key: string;
    label: string;
    entry_count: number;
    total_hours: number;
    billable_hours: number;
    revenue: number;
  }>;
  top_technicians: Array<{
    technician_id: number;
    entry_count: number;
    total_hours: number;
    billable_hours: number;
    revenue: number;
  }>;
  by_status: Array<{
    status: string;
    count: number;
    total_hours: number;
  }>;
  group_by: string;
  generated_at: string;
}

export interface BudgetReportData {
  overview: {
    total_budget: number;
    total_cost: number;
    remaining_budget: number;
    avg_budget_utilization: number;
  };
  labor_costs: {
    labor_cost: number;
    total_hours: number;
  };
  material_costs: {
    material_cost: number;
    po_count: number;
  };
  projects: Array<{
    id: number;
    project_name: string;
    budget_amount: number;
    actual_cost: number;
    remaining: number;
    utilization_percent: number;
    status: string;
  }>;
  generated_at: string;
}

export interface InventoryReportData {
  overview: {
    total_items: number;
    total_quantity: number;
    total_value: number;
    total_allocated: number;
    total_available: number;
    low_stock_count: number;
  };
  by_category: Array<{
    category: string;
    item_count: number;
    total_quantity: number;
    total_value: number;
  }>;
  by_location: Array<{
    location: string;
    item_count: number;
    total_quantity: number;
    total_value: number;
  }>;
  movements: Array<{
    adjustment_type: string;
    count: number;
    total_quantity: number;
  }>;
  top_items: Array<{
    id: number;
    item_name: string;
    sku: string;
    quantity_on_hand: number;
    unit_cost: number;
    total_value: number;
  }>;
  generated_at: string;
}

export interface DashboardSummary {
  projects: {
    total: number;
    active: number;
    completed: number;
    total_budget: number;
    total_cost: number;
  };
  time: {
    total_entries: number;
    total_hours: number;
    revenue: number;
  };
  inventory: {
    total_items: number;
    total_value: number;
    low_stock: number;
  };
  purchase_orders: {
    total: number;
    pending: number;
    approved: number;
    total_amount: number;
  };
  generated_at: string;
}

export interface ReportsState {
  projectReport: ProjectReportData | null;
  timeReport: TimeReportData | null;
  budgetReport: BudgetReportData | null;
  inventoryReport: InventoryReportData | null;
  dashboardSummary: DashboardSummary | null;
  loading: boolean;
  error?: string;
  lastUpdated?: string;
}

// Initial state
const initialState: ReportsState = {
  projectReport: null,
  timeReport: null,
  budgetReport: null,
  inventoryReport: null,
  dashboardSummary: null,
  loading: false,
  error: undefined,
  lastUpdated: undefined,
};

// Async thunks

/**
 * Fetch project report
 */
export const fetchProjectReport = createAsyncThunk(
  'reports/fetchProjectReport',
  async (params?: {
    date_from?: string;
    date_to?: string;
    status?: string;
    priority?: string;
  }) => {
    const response = await reportsAPI.getProjectReport(params);
    return response.data!;
  }
);

/**
 * Fetch time report
 */
export const fetchTimeReport = createAsyncThunk(
  'reports/fetchTimeReport',
  async (params?: {
    date_from?: string;
    date_to?: string;
    technician_id?: number;
    project_id?: number;
    group_by?: 'day' | 'week' | 'month' | 'technician' | 'project';
  }) => {
    const response = await reportsAPI.getTimeReport(params);
    return response.data!;
  }
);

/**
 * Fetch budget report
 */
export const fetchBudgetReport = createAsyncThunk(
  'reports/fetchBudgetReport',
  async (params?: {
    project_id?: number;
    date_from?: string;
    date_to?: string;
  }) => {
    const response = await reportsAPI.getBudgetReport(params);
    return response.data!;
  }
);

/**
 * Fetch inventory report
 */
export const fetchInventoryReport = createAsyncThunk(
  'reports/fetchInventoryReport',
  async (params?: {
    category?: string;
    location?: string;
    date_from?: string;
    date_to?: string;
  }) => {
    const response = await reportsAPI.getInventoryReport(params);
    return response.data!;
  }
);

/**
 * Fetch dashboard summary
 */
export const fetchDashboardSummary = createAsyncThunk(
  'reports/fetchDashboardSummary',
  async () => {
    const response = await reportsAPI.getDashboardSummary();
    return response.data!;
  }
);

/**
 * Export report
 */
export const exportReport = createAsyncThunk(
  'reports/exportReport',
  async (params: {
    report_type: 'projects' | 'time' | 'budget' | 'inventory';
    format?: 'csv' | 'excel' | 'pdf';
    filters?: Record<string, any>;
  }) => {
    const response = await reportsAPI.exportReport(
      params.report_type,
      params.format,
      params.filters
    );
    return response.data!;
  }
);

// Slice definition
const reportsSlice = createSlice({
  name: 'reports',
  initialState,
  reducers: {
    // Clear specific report
    clearProjectReport: (state) => {
      state.projectReport = null;
    },
    clearTimeReport: (state) => {
      state.timeReport = null;
    },
    clearBudgetReport: (state) => {
      state.budgetReport = null;
    },
    clearInventoryReport: (state) => {
      state.inventoryReport = null;
    },

    // Clear all reports
    clearAllReports: (state) => {
      state.projectReport = null;
      state.timeReport = null;
      state.budgetReport = null;
      state.inventoryReport = null;
      state.dashboardSummary = null;
    },

    // Clear error
    clearError: (state) => {
      state.error = undefined;
    },
  },
  extraReducers: (builder) => {
    // Fetch project report
    builder
      .addCase(fetchProjectReport.pending, (state) => {
        state.loading = true;
        state.error = undefined;
      })
      .addCase(fetchProjectReport.fulfilled, (state, action: PayloadAction<ProjectReportData>) => {
        state.loading = false;
        state.projectReport = action.payload;
        state.lastUpdated = new Date().toISOString();
      })
      .addCase(fetchProjectReport.rejected, (state, action) => {
        state.loading = false;
        state.error = action.error.message || 'Failed to fetch project report';
      });

    // Fetch time report
    builder
      .addCase(fetchTimeReport.pending, (state) => {
        state.loading = true;
        state.error = undefined;
      })
      .addCase(fetchTimeReport.fulfilled, (state, action: PayloadAction<TimeReportData>) => {
        state.loading = false;
        state.timeReport = action.payload;
        state.lastUpdated = new Date().toISOString();
      })
      .addCase(fetchTimeReport.rejected, (state, action) => {
        state.loading = false;
        state.error = action.error.message || 'Failed to fetch time report';
      });

    // Fetch budget report
    builder
      .addCase(fetchBudgetReport.pending, (state) => {
        state.loading = true;
        state.error = undefined;
      })
      .addCase(fetchBudgetReport.fulfilled, (state, action: PayloadAction<BudgetReportData>) => {
        state.loading = false;
        state.budgetReport = action.payload;
        state.lastUpdated = new Date().toISOString();
      })
      .addCase(fetchBudgetReport.rejected, (state, action) => {
        state.loading = false;
        state.error = action.error.message || 'Failed to fetch budget report';
      });

    // Fetch inventory report
    builder
      .addCase(fetchInventoryReport.pending, (state) => {
        state.loading = true;
        state.error = undefined;
      })
      .addCase(fetchInventoryReport.fulfilled, (state, action: PayloadAction<InventoryReportData>) => {
        state.loading = false;
        state.inventoryReport = action.payload;
        state.lastUpdated = new Date().toISOString();
      })
      .addCase(fetchInventoryReport.rejected, (state, action) => {
        state.loading = false;
        state.error = action.error.message || 'Failed to fetch inventory report';
      });

    // Fetch dashboard summary
    builder
      .addCase(fetchDashboardSummary.pending, (state) => {
        state.loading = true;
        state.error = undefined;
      })
      .addCase(fetchDashboardSummary.fulfilled, (state, action: PayloadAction<DashboardSummary>) => {
        state.loading = false;
        state.dashboardSummary = action.payload;
        state.lastUpdated = new Date().toISOString();
      })
      .addCase(fetchDashboardSummary.rejected, (state, action) => {
        state.loading = false;
        state.error = action.error.message || 'Failed to fetch dashboard summary';
      });

    // Export report
    builder
      .addCase(exportReport.pending, (state) => {
        state.loading = true;
        state.error = undefined;
      })
      .addCase(exportReport.fulfilled, (state) => {
        state.loading = false;
      })
      .addCase(exportReport.rejected, (state, action) => {
        state.loading = false;
        state.error = action.error.message || 'Failed to export report';
      });
  },
});

// Export actions
export const {
  clearProjectReport,
  clearTimeReport,
  clearBudgetReport,
  clearInventoryReport,
  clearAllReports,
  clearError,
} = reportsSlice.actions;

// Selectors
export const selectProjectReport = (state: { reports: ReportsState }) =>
  state.reports.projectReport;

export const selectTimeReport = (state: { reports: ReportsState }) =>
  state.reports.timeReport;

export const selectBudgetReport = (state: { reports: ReportsState }) =>
  state.reports.budgetReport;

export const selectInventoryReport = (state: { reports: ReportsState }) =>
  state.reports.inventoryReport;

export const selectDashboardSummary = (state: { reports: ReportsState }) =>
  state.reports.dashboardSummary;

export const selectReportsLoading = (state: { reports: ReportsState }) =>
  state.reports.loading;

export const selectReportsError = (state: { reports: ReportsState }) =>
  state.reports.error;

export const selectLastUpdated = (state: { reports: ReportsState }) =>
  state.reports.lastUpdated;

// Export reducer
export default reportsSlice.reducer;
