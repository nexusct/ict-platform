/**
 * Purchase Orders Redux Slice
 *
 * Manages purchase order state including:
 * - PO CRUD operations
 * - Approval workflow
 * - Order receiving
 * - Line items management
 * - Status tracking
 *
 * @package ICT_Platform
 * @since   1.0.0
 */

import { createSlice, createAsyncThunk, PayloadAction } from '@reduxjs/toolkit';
import {
  PurchaseOrdersState,
  PurchaseOrder,
  PurchaseOrderStatus,
} from '../../types';
import { purchaseOrderAPI } from '../../services/api';

// Initial state
const initialState: PurchaseOrdersState = {
  items: [],
  currentPO: undefined,
  loading: false,
  error: undefined,
  filters: {},
  pagination: {
    page: 1,
    per_page: 20,
    total: 0,
    total_pages: 0,
  },
};

// Async thunks

/**
 * Fetch purchase orders with filtering
 */
export const fetchPurchaseOrders = createAsyncThunk(
  'purchaseOrders/fetchPurchaseOrders',
  async (params?: {
    page?: number;
    per_page?: number;
    status?: PurchaseOrderStatus | PurchaseOrderStatus[];
    project_id?: number;
    supplier_id?: number;
    date_from?: string;
    date_to?: string;
    search?: string;
  }) => {
    const response = await purchaseOrderAPI.getAll(params);
    return response;
  }
);

/**
 * Fetch single purchase order by ID with line items
 */
export const fetchPurchaseOrderById = createAsyncThunk(
  'purchaseOrders/fetchPurchaseOrderById',
  async (id: number) => {
    const response = await purchaseOrderAPI.getById(id);
    return response.data!;
  }
);

/**
 * Create new purchase order
 */
export const createPurchaseOrder = createAsyncThunk(
  'purchaseOrders/createPurchaseOrder',
  async (data: {
    po_number?: string;
    project_id?: number;
    supplier_id: number;
    po_date: string;
    delivery_date?: string;
    status?: PurchaseOrderStatus;
    notes?: string;
    line_items: Array<{
      inventory_id: number;
      description: string;
      quantity: number;
      unit_price: number;
      tax_rate?: number;
    }>;
  }) => {
    const response = await purchaseOrderAPI.create(data);
    return response.data!;
  }
);

/**
 * Update existing purchase order
 */
export const updatePurchaseOrder = createAsyncThunk(
  'purchaseOrders/updatePurchaseOrder',
  async ({ id, data }: {
    id: number;
    data: {
      project_id?: number;
      supplier_id?: number;
      po_date?: string;
      delivery_date?: string;
      status?: PurchaseOrderStatus;
      notes?: string;
      line_items?: Array<{
        id?: number;
        inventory_id: number;
        description: string;
        quantity: number;
        unit_price: number;
        tax_rate?: number;
      }>;
    };
  }) => {
    const response = await purchaseOrderAPI.update(id, data);
    return response.data!;
  }
);

/**
 * Delete purchase order (draft only)
 */
export const deletePurchaseOrder = createAsyncThunk(
  'purchaseOrders/deletePurchaseOrder',
  async (id: number) => {
    await purchaseOrderAPI.delete(id);
    return id;
  }
);

/**
 * Approve purchase order
 */
export const approvePurchaseOrder = createAsyncThunk(
  'purchaseOrders/approvePurchaseOrder',
  async (id: number) => {
    const response = await purchaseOrderAPI.approve(id);
    return response.data!;
  }
);

/**
 * Mark purchase order as ordered
 */
export const markPurchaseOrderAsOrdered = createAsyncThunk(
  'purchaseOrders/markAsOrdered',
  async (id: number) => {
    const response = await purchaseOrderAPI.markAsOrdered(id);
    return response.data!;
  }
);

/**
 * Receive purchase order and update inventory
 */
export const receivePurchaseOrder = createAsyncThunk(
  'purchaseOrders/receivePurchaseOrder',
  async (params: {
    id: number;
    received_items: Array<{
      line_item_id: number;
      inventory_id: number;
      quantity_received: number;
      notes?: string;
    }>;
  }) => {
    const response = await purchaseOrderAPI.receive(params.id, params.received_items);
    return response.data!;
  }
);

/**
 * Cancel purchase order
 */
export const cancelPurchaseOrder = createAsyncThunk(
  'purchaseOrders/cancelPurchaseOrder',
  async (params: { id: number; reason?: string }) => {
    const response = await purchaseOrderAPI.cancel(params.id, params.reason);
    return response.data!;
  }
);

/**
 * Generate unique PO number
 */
export const generatePONumber = createAsyncThunk(
  'purchaseOrders/generatePONumber',
  async () => {
    const response = await purchaseOrderAPI.generateNumber();
    return response.data!;
  }
);

// Slice definition
const purchaseOrdersSlice = createSlice({
  name: 'purchaseOrders',
  initialState,
  reducers: {
    // Set filters
    setFilters: (state, action: PayloadAction<Partial<PurchaseOrdersState['filters']>>) => {
      state.filters = { ...state.filters, ...action.payload };
      state.pagination.page = 1; // Reset to first page when filters change
    },

    // Clear filters
    clearFilters: (state) => {
      state.filters = {};
      state.pagination.page = 1;
    },

    // Set pagination
    setPagination: (state, action: PayloadAction<{ page?: number; per_page?: number }>) => {
      if (action.payload.page !== undefined) {
        state.pagination.page = action.payload.page;
      }
      if (action.payload.per_page !== undefined) {
        state.pagination.per_page = action.payload.per_page;
      }
    },

    // Set current PO
    setCurrentPO: (state, action: PayloadAction<PurchaseOrder | undefined>) => {
      state.currentPO = action.payload;
    },

    // Clear error
    clearError: (state) => {
      state.error = undefined;
    },
  },
  extraReducers: (builder) => {
    // Fetch purchase orders
    builder
      .addCase(fetchPurchaseOrders.pending, (state) => {
        state.loading = true;
        state.error = undefined;
      })
      .addCase(fetchPurchaseOrders.fulfilled, (state, action) => {
        state.loading = false;
        state.items = action.payload.data;
        state.pagination = {
          page: action.payload.page,
          per_page: action.payload.per_page,
          total: action.payload.total,
          total_pages: action.payload.total_pages,
        };
      })
      .addCase(fetchPurchaseOrders.rejected, (state, action) => {
        state.loading = false;
        state.error = action.error.message || 'Failed to fetch purchase orders';
      });

    // Fetch purchase order by ID
    builder
      .addCase(fetchPurchaseOrderById.pending, (state) => {
        state.loading = true;
        state.error = undefined;
      })
      .addCase(fetchPurchaseOrderById.fulfilled, (state, action: PayloadAction<PurchaseOrder>) => {
        state.loading = false;
        state.currentPO = action.payload;

        // Update in items list if present
        const index = state.items.findIndex(po => po.id === action.payload.id);
        if (index >= 0) {
          state.items[index] = action.payload;
        }
      })
      .addCase(fetchPurchaseOrderById.rejected, (state, action) => {
        state.loading = false;
        state.error = action.error.message || 'Failed to fetch purchase order';
      });

    // Create purchase order
    builder
      .addCase(createPurchaseOrder.pending, (state) => {
        state.loading = true;
        state.error = undefined;
      })
      .addCase(createPurchaseOrder.fulfilled, (state, action: PayloadAction<PurchaseOrder>) => {
        state.loading = false;
        state.items.unshift(action.payload);
        state.currentPO = action.payload;
        state.pagination.total += 1;
      })
      .addCase(createPurchaseOrder.rejected, (state, action) => {
        state.loading = false;
        state.error = action.error.message || 'Failed to create purchase order';
      });

    // Update purchase order
    builder
      .addCase(updatePurchaseOrder.pending, (state) => {
        state.loading = true;
        state.error = undefined;
      })
      .addCase(updatePurchaseOrder.fulfilled, (state, action: PayloadAction<PurchaseOrder>) => {
        state.loading = false;
        const index = state.items.findIndex(po => po.id === action.payload.id);
        if (index >= 0) {
          state.items[index] = action.payload;
        }
        if (state.currentPO?.id === action.payload.id) {
          state.currentPO = action.payload;
        }
      })
      .addCase(updatePurchaseOrder.rejected, (state, action) => {
        state.loading = false;
        state.error = action.error.message || 'Failed to update purchase order';
      });

    // Delete purchase order
    builder
      .addCase(deletePurchaseOrder.pending, (state) => {
        state.loading = true;
        state.error = undefined;
      })
      .addCase(deletePurchaseOrder.fulfilled, (state, action: PayloadAction<number>) => {
        state.loading = false;
        state.items = state.items.filter(po => po.id !== action.payload);
        if (state.currentPO?.id === action.payload) {
          state.currentPO = undefined;
        }
        state.pagination.total -= 1;
      })
      .addCase(deletePurchaseOrder.rejected, (state, action) => {
        state.loading = false;
        state.error = action.error.message || 'Failed to delete purchase order';
      });

    // Approve purchase order
    builder
      .addCase(approvePurchaseOrder.pending, (state) => {
        state.loading = true;
        state.error = undefined;
      })
      .addCase(approvePurchaseOrder.fulfilled, (state, action: PayloadAction<PurchaseOrder>) => {
        state.loading = false;
        const index = state.items.findIndex(po => po.id === action.payload.id);
        if (index >= 0) {
          state.items[index] = action.payload;
        }
        if (state.currentPO?.id === action.payload.id) {
          state.currentPO = action.payload;
        }
      })
      .addCase(approvePurchaseOrder.rejected, (state, action) => {
        state.loading = false;
        state.error = action.error.message || 'Failed to approve purchase order';
      });

    // Mark as ordered
    builder
      .addCase(markPurchaseOrderAsOrdered.pending, (state) => {
        state.loading = true;
        state.error = undefined;
      })
      .addCase(markPurchaseOrderAsOrdered.fulfilled, (state, action: PayloadAction<PurchaseOrder>) => {
        state.loading = false;
        const index = state.items.findIndex(po => po.id === action.payload.id);
        if (index >= 0) {
          state.items[index] = action.payload;
        }
        if (state.currentPO?.id === action.payload.id) {
          state.currentPO = action.payload;
        }
      })
      .addCase(markPurchaseOrderAsOrdered.rejected, (state, action) => {
        state.loading = false;
        state.error = action.error.message || 'Failed to mark purchase order as ordered';
      });

    // Receive purchase order
    builder
      .addCase(receivePurchaseOrder.pending, (state) => {
        state.loading = true;
        state.error = undefined;
      })
      .addCase(receivePurchaseOrder.fulfilled, (state, action: PayloadAction<PurchaseOrder>) => {
        state.loading = false;
        const index = state.items.findIndex(po => po.id === action.payload.id);
        if (index >= 0) {
          state.items[index] = action.payload;
        }
        if (state.currentPO?.id === action.payload.id) {
          state.currentPO = action.payload;
        }
      })
      .addCase(receivePurchaseOrder.rejected, (state, action) => {
        state.loading = false;
        state.error = action.error.message || 'Failed to receive purchase order';
      });

    // Cancel purchase order
    builder
      .addCase(cancelPurchaseOrder.pending, (state) => {
        state.loading = true;
        state.error = undefined;
      })
      .addCase(cancelPurchaseOrder.fulfilled, (state, action: PayloadAction<PurchaseOrder>) => {
        state.loading = false;
        const index = state.items.findIndex(po => po.id === action.payload.id);
        if (index >= 0) {
          state.items[index] = action.payload;
        }
        if (state.currentPO?.id === action.payload.id) {
          state.currentPO = action.payload;
        }
      })
      .addCase(cancelPurchaseOrder.rejected, (state, action) => {
        state.loading = false;
        state.error = action.error.message || 'Failed to cancel purchase order';
      });

    // Generate PO number
    builder
      .addCase(generatePONumber.pending, (state) => {
        state.loading = true;
        state.error = undefined;
      })
      .addCase(generatePONumber.fulfilled, (state) => {
        state.loading = false;
      })
      .addCase(generatePONumber.rejected, (state, action) => {
        state.loading = false;
        state.error = action.error.message || 'Failed to generate PO number';
      });
  },
});

// Export actions
export const {
  setFilters,
  clearFilters,
  setPagination,
  setCurrentPO,
  clearError,
} = purchaseOrdersSlice.actions;

// Selectors
export const selectPurchaseOrders = (state: { purchaseOrders: PurchaseOrdersState }) =>
  state.purchaseOrders.items;

export const selectCurrentPO = (state: { purchaseOrders: PurchaseOrdersState }) =>
  state.purchaseOrders.currentPO;

export const selectPOLoading = (state: { purchaseOrders: PurchaseOrdersState }) =>
  state.purchaseOrders.loading;

export const selectPOError = (state: { purchaseOrders: PurchaseOrdersState }) =>
  state.purchaseOrders.error;

export const selectPOFilters = (state: { purchaseOrders: PurchaseOrdersState }) =>
  state.purchaseOrders.filters;

export const selectPOPagination = (state: { purchaseOrders: PurchaseOrdersState }) =>
  state.purchaseOrders.pagination;

// Helper selector to get PO by ID
export const selectPurchaseOrderById = (id: number) =>
  (state: { purchaseOrders: PurchaseOrdersState }) =>
    state.purchaseOrders.items.find(po => po.id === id);

// Helper selector to get POs by status
export const selectPurchaseOrdersByStatus = (status: PurchaseOrderStatus) =>
  (state: { purchaseOrders: PurchaseOrdersState }) =>
    state.purchaseOrders.items.filter(po => po.status === status);

// Helper selector to calculate total PO amount for a project
export const selectProjectPOTotal = (projectId: number) =>
  (state: { purchaseOrders: PurchaseOrdersState }) =>
    state.purchaseOrders.items
      .filter(po => po.project_id === projectId && po.status !== 'cancelled')
      .reduce((sum, po) => sum + po.total_amount, 0);

// Export reducer
export default purchaseOrdersSlice.reducer;
