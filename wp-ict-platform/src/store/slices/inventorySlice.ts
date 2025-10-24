/**
 * Inventory Redux Slice
 *
 * Manages inventory state including:
 * - Inventory CRUD operations
 * - Stock adjustments
 * - Low stock alerts
 * - Categories and locations
 * - Stock history
 *
 * @package ICT_Platform
 * @since   1.0.0
 */

import { createSlice, createAsyncThunk, PayloadAction } from '@reduxjs/toolkit';
import {
  InventoryState,
  InventoryItem,
  PaginatedResponse,
} from '../../types';
import { inventoryAPI } from '../../services/api';

// Initial state
const initialState: InventoryState = {
  items: [],
  loading: false,
  error: undefined,
  lowStockItems: [],
  categories: [],
  locations: [],
  filters: {},
  pagination: {
    page: 1,
    per_page: 20,
    total: 0,
    total_pages: 0,
  },
  stockHistory: [],
};

// Async thunks

/**
 * Fetch inventory items with filtering
 */
export const fetchInventory = createAsyncThunk<
  PaginatedResponse<InventoryItem>,
  {
    page?: number;
    per_page?: number;
    search?: string;
    category?: string;
    location?: string;
    is_active?: boolean;
    low_stock?: boolean;
  } | undefined
>(
  'inventory/fetchInventory',
  async (params) => {
    const response = await inventoryAPI.getAll(params);
    return response;
  }
);

/**
 * Fetch single inventory item
 */
export const fetchInventoryItem = createAsyncThunk(
  'inventory/fetchInventoryItem',
  async (id: number) => {
    const response = await inventoryAPI.getById(id);
    return response.data!;
  }
);

/**
 * Create inventory item
 */
export const createInventoryItem = createAsyncThunk(
  'inventory/createInventoryItem',
  async (data: {
    sku: string;
    item_name: string;
    description?: string;
    category?: string;
    unit_of_measure?: string;
    quantity_on_hand?: number;
    reorder_level?: number;
    reorder_quantity?: number;
    unit_cost?: number;
    unit_price?: number;
    supplier_id?: number;
    location?: string;
    barcode?: string;
    is_active?: boolean;
  }) => {
    const response = await inventoryAPI.create(data);
    return response.data!;
  }
);

/**
 * Update inventory item
 */
export const updateInventoryItem = createAsyncThunk(
  'inventory/updateInventoryItem',
  async ({ id, data }: { id: number; data: Partial<InventoryItem> }) => {
    const response = await inventoryAPI.update(id, data);
    return response.data!;
  }
);

/**
 * Delete inventory item
 */
export const deleteInventoryItem = createAsyncThunk(
  'inventory/deleteInventoryItem',
  async (id: number) => {
    await inventoryAPI.delete(id);
    return id;
  }
);

/**
 * Adjust stock
 */
export const adjustStock = createAsyncThunk(
  'inventory/adjustStock',
  async (params: {
    id: number;
    adjustment: number;
    adjustment_type: 'received' | 'consumed' | 'damaged' | 'lost' | 'returned' | 'transfer' | 'correction' | 'initial';
    reason: string;
    project_id?: number;
    reference?: string;
  }) => {
    const response = await inventoryAPI.adjustStock(
      params.id,
      params.adjustment,
      params.adjustment_type,
      params.reason,
      params.project_id,
      params.reference
    );
    return response.data!;
  }
);

/**
 * Get stock history
 */
export const fetchStockHistory = createAsyncThunk(
  'inventory/fetchStockHistory',
  async (params: { id: number; per_page?: number }) => {
    const response = await inventoryAPI.getStockHistory(params.id, params.per_page);
    return response.data!;
  }
);

/**
 * Fetch low stock items
 */
export const fetchLowStockItems = createAsyncThunk(
  'inventory/fetchLowStockItems',
  async () => {
    const response = await inventoryAPI.getLowStock();
    return response.data!;
  }
);

/**
 * Fetch categories
 */
export const fetchCategories = createAsyncThunk(
  'inventory/fetchCategories',
  async () => {
    const response = await inventoryAPI.getCategories();
    return response.data!;
  }
);

/**
 * Fetch locations
 */
export const fetchLocations = createAsyncThunk(
  'inventory/fetchLocations',
  async () => {
    const response = await inventoryAPI.getLocations();
    return response.data!;
  }
);

/**
 * Bulk update inventory items
 */
export const bulkUpdateInventory = createAsyncThunk(
  'inventory/bulkUpdate',
  async (items: Array<{ id: number; [key: string]: any }>) => {
    const response = await inventoryAPI.bulkUpdate(items);
    return response;
  }
);

// Slice definition
const inventorySlice = createSlice({
  name: 'inventory',
  initialState,
  reducers: {
    // Set filters
    setFilters: (state, action: PayloadAction<Partial<InventoryState['filters']>>) => {
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

    // Clear error
    clearError: (state) => {
      state.error = undefined;
    },

    // Clear stock history
    clearStockHistory: (state) => {
      state.stockHistory = [];
    },
  },
  extraReducers: (builder) => {
    // Fetch inventory
    builder
      .addCase(fetchInventory.pending, (state) => {
        state.loading = true;
        state.error = undefined;
      })
      .addCase(fetchInventory.fulfilled, (state, action: PayloadAction<PaginatedResponse<InventoryItem>>) => {
        state.loading = false;
        state.items = action.payload.data;
        state.pagination = {
          page: action.payload.page,
          per_page: action.payload.per_page,
          total: action.payload.total,
          total_pages: action.payload.total_pages,
        };
      })
      .addCase(fetchInventory.rejected, (state, action) => {
        state.loading = false;
        state.error = action.error.message || 'Failed to fetch inventory';
      });

    // Fetch inventory item
    builder
      .addCase(fetchInventoryItem.pending, (state) => {
        state.loading = true;
        state.error = undefined;
      })
      .addCase(fetchInventoryItem.fulfilled, (state, action: PayloadAction<InventoryItem>) => {
        state.loading = false;
        const index = state.items.findIndex(item => item.id === action.payload.id);
        if (index >= 0) {
          state.items[index] = action.payload;
        } else {
          state.items.push(action.payload);
        }
      })
      .addCase(fetchInventoryItem.rejected, (state, action) => {
        state.loading = false;
        state.error = action.error.message || 'Failed to fetch inventory item';
      });

    // Create inventory item
    builder
      .addCase(createInventoryItem.pending, (state) => {
        state.loading = true;
        state.error = undefined;
      })
      .addCase(createInventoryItem.fulfilled, (state, action: PayloadAction<InventoryItem>) => {
        state.loading = false;
        state.items.unshift(action.payload);
        state.pagination.total += 1;
      })
      .addCase(createInventoryItem.rejected, (state, action) => {
        state.loading = false;
        state.error = action.error.message || 'Failed to create inventory item';
      });

    // Update inventory item
    builder
      .addCase(updateInventoryItem.pending, (state) => {
        state.loading = true;
        state.error = undefined;
      })
      .addCase(updateInventoryItem.fulfilled, (state, action: PayloadAction<InventoryItem>) => {
        state.loading = false;
        const index = state.items.findIndex(item => item.id === action.payload.id);
        if (index >= 0) {
          state.items[index] = action.payload;
        }
      })
      .addCase(updateInventoryItem.rejected, (state, action) => {
        state.loading = false;
        state.error = action.error.message || 'Failed to update inventory item';
      });

    // Delete inventory item
    builder
      .addCase(deleteInventoryItem.pending, (state) => {
        state.loading = true;
        state.error = undefined;
      })
      .addCase(deleteInventoryItem.fulfilled, (state, action: PayloadAction<number>) => {
        state.loading = false;
        state.items = state.items.filter(item => item.id !== action.payload);
        state.pagination.total -= 1;
      })
      .addCase(deleteInventoryItem.rejected, (state, action) => {
        state.loading = false;
        state.error = action.error.message || 'Failed to delete inventory item';
      });

    // Adjust stock
    builder
      .addCase(adjustStock.pending, (state) => {
        state.loading = true;
        state.error = undefined;
      })
      .addCase(adjustStock.fulfilled, (state, action: PayloadAction<InventoryItem>) => {
        state.loading = false;
        const index = state.items.findIndex(item => item.id === action.payload.id);
        if (index >= 0) {
          state.items[index] = action.payload;
        }
      })
      .addCase(adjustStock.rejected, (state, action) => {
        state.loading = false;
        state.error = action.error.message || 'Failed to adjust stock';
      });

    // Fetch stock history
    builder
      .addCase(fetchStockHistory.pending, (state) => {
        state.loading = true;
        state.error = undefined;
      })
      .addCase(fetchStockHistory.fulfilled, (state, action) => {
        state.loading = false;
        state.stockHistory = action.payload;
      })
      .addCase(fetchStockHistory.rejected, (state, action) => {
        state.loading = false;
        state.error = action.error.message || 'Failed to fetch stock history';
      });

    // Fetch low stock items
    builder
      .addCase(fetchLowStockItems.pending, (state) => {
        state.loading = true;
        state.error = undefined;
      })
      .addCase(fetchLowStockItems.fulfilled, (state, action: PayloadAction<InventoryItem[]>) => {
        state.loading = false;
        state.lowStockItems = action.payload;
      })
      .addCase(fetchLowStockItems.rejected, (state, action) => {
        state.loading = false;
        state.error = action.error.message || 'Failed to fetch low stock items';
      });

    // Fetch categories
    builder
      .addCase(fetchCategories.pending, (state) => {
        state.loading = true;
      })
      .addCase(fetchCategories.fulfilled, (state, action: PayloadAction<string[]>) => {
        state.loading = false;
        state.categories = action.payload;
      })
      .addCase(fetchCategories.rejected, (state, action) => {
        state.loading = false;
        state.error = action.error.message || 'Failed to fetch categories';
      });

    // Fetch locations
    builder
      .addCase(fetchLocations.pending, (state) => {
        state.loading = true;
      })
      .addCase(fetchLocations.fulfilled, (state, action: PayloadAction<string[]>) => {
        state.loading = false;
        state.locations = action.payload;
      })
      .addCase(fetchLocations.rejected, (state, action) => {
        state.loading = false;
        state.error = action.error.message || 'Failed to fetch locations';
      });

    // Bulk update
    builder
      .addCase(bulkUpdateInventory.pending, (state) => {
        state.loading = true;
        state.error = undefined;
      })
      .addCase(bulkUpdateInventory.fulfilled, (state) => {
        state.loading = false;
      })
      .addCase(bulkUpdateInventory.rejected, (state, action) => {
        state.loading = false;
        state.error = action.error.message || 'Failed to bulk update inventory';
      });
  },
});

// Export actions
export const {
  setFilters,
  clearFilters,
  setPagination,
  clearError,
  clearStockHistory,
} = inventorySlice.actions;

// Selectors
export const selectInventory = (state: { inventory: InventoryState }) => state.inventory.items;
export const selectInventoryLoading = (state: { inventory: InventoryState }) => state.inventory.loading;
export const selectInventoryError = (state: { inventory: InventoryState }) => state.inventory.error;
export const selectLowStockItems = (state: { inventory: InventoryState }) => state.inventory.lowStockItems;
export const selectInventoryCategories = (state: { inventory: InventoryState }) => state.inventory.categories;
export const selectInventoryLocations = (state: { inventory: InventoryState }) => state.inventory.locations;
export const selectInventoryFilters = (state: { inventory: InventoryState }) => state.inventory.filters;
export const selectInventoryPagination = (state: { inventory: InventoryState }) => state.inventory.pagination;
export const selectStockHistory = (state: { inventory: InventoryState }) => state.inventory.stockHistory;

// Helper selector to get item by ID
export const selectInventoryItemById = (itemId: number) => (state: { inventory: InventoryState }) =>
  state.inventory.items.find(item => item.id === itemId);

// Helper selector to check if any items are low stock
export const selectHasLowStockItems = (state: { inventory: InventoryState }) => state.inventory.lowStockItems.length > 0;

// Export reducer
export default inventorySlice.reducer;
