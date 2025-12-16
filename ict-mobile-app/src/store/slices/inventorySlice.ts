/**
 * Inventory Redux Slice
 *
 * @package ICT_Platform_Mobile
 * @since   1.0.0
 */

import { createSlice, createAsyncThunk, PayloadAction } from '@reduxjs/toolkit';
import { apiService } from '../../services/api';
import type { InventoryItem } from '../../types';

interface InventoryState {
  items: InventoryItem[];
  selectedItem: InventoryItem | null;
  isLoading: boolean;
  error: string | null;
  filters: {
    category: string;
    search: string;
    lowStock: boolean;
  };
  categories: string[];
}

const initialState: InventoryState = {
  items: [],
  selectedItem: null,
  isLoading: false,
  error: null,
  filters: {
    category: 'all',
    search: '',
    lowStock: false,
  },
  categories: [],
};

export const fetchInventory = createAsyncThunk(
  'inventory/fetchInventory',
  async (params: { category?: string; search?: string; low_stock?: boolean } = {}, { rejectWithValue }) => {
    try {
      const response = await apiService.get('/inventory', { params });
      return response.data.data;
    } catch (error: any) {
      return rejectWithValue(error.response?.data?.message || 'Failed to fetch inventory');
    }
  }
);

export const fetchItemByBarcode = createAsyncThunk(
  'inventory/fetchItemByBarcode',
  async (barcode: string, { rejectWithValue }) => {
    try {
      const response = await apiService.get('/inventory/barcode', { params: { barcode } });
      return response.data.data;
    } catch (error: any) {
      return rejectWithValue(error.response?.data?.message || 'Item not found');
    }
  }
);

export const updateItemQuantity = createAsyncThunk(
  'inventory/updateItemQuantity',
  async ({ id, quantity, reason }: { id: number; quantity: number; reason?: string }, { rejectWithValue }) => {
    try {
      const response = await apiService.put(`/inventory/${id}/quantity`, { quantity, reason });
      return response.data.data;
    } catch (error: any) {
      return rejectWithValue(error.response?.data?.message || 'Failed to update quantity');
    }
  }
);

export const fetchCategories = createAsyncThunk(
  'inventory/fetchCategories',
  async (_, { rejectWithValue }) => {
    try {
      const response = await apiService.get('/inventory/categories');
      return response.data.data;
    } catch (error: any) {
      return rejectWithValue(error.response?.data?.message || 'Failed to fetch categories');
    }
  }
);

const inventorySlice = createSlice({
  name: 'inventory',
  initialState,
  reducers: {
    setFilters: (state, action: PayloadAction<Partial<InventoryState['filters']>>) => {
      state.filters = { ...state.filters, ...action.payload };
    },
    setSelectedItem: (state, action: PayloadAction<InventoryItem | null>) => {
      state.selectedItem = action.payload;
    },
    clearError: (state) => {
      state.error = null;
    },
  },
  extraReducers: (builder) => {
    builder
      // Fetch Inventory
      .addCase(fetchInventory.pending, (state) => {
        state.isLoading = true;
        state.error = null;
      })
      .addCase(fetchInventory.fulfilled, (state, action) => {
        state.isLoading = false;
        state.items = action.payload;
      })
      .addCase(fetchInventory.rejected, (state, action) => {
        state.isLoading = false;
        state.error = action.payload as string;
      })
      // Fetch By Barcode
      .addCase(fetchItemByBarcode.pending, (state) => {
        state.isLoading = true;
        state.error = null;
      })
      .addCase(fetchItemByBarcode.fulfilled, (state, action) => {
        state.isLoading = false;
        state.selectedItem = action.payload;
      })
      .addCase(fetchItemByBarcode.rejected, (state, action) => {
        state.isLoading = false;
        state.error = action.payload as string;
      })
      // Update Quantity
      .addCase(updateItemQuantity.fulfilled, (state, action) => {
        const index = state.items.findIndex((item) => item.id === action.payload.id);
        if (index !== -1) {
          state.items[index] = action.payload;
        }
        if (state.selectedItem?.id === action.payload.id) {
          state.selectedItem = action.payload;
        }
      })
      // Fetch Categories
      .addCase(fetchCategories.fulfilled, (state, action) => {
        state.categories = action.payload;
      });
  },
});

export const { setFilters, setSelectedItem, clearError } = inventorySlice.actions;
export default inventorySlice.reducer;
