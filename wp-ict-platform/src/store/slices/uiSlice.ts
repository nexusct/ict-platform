/**
 * UI Redux Slice
 *
 * @package ICT_Platform
 * @since   1.0.0
 */

import { createSlice, PayloadAction } from '@reduxjs/toolkit';
import type { UIState, ToastMessage } from '../../types';

const initialState: UIState = {
  sidebarOpen: true,
  modalOpen: false,
  modalContent: undefined,
  toast: undefined,
};

const uiSlice = createSlice({
  name: 'ui',
  initialState,
  reducers: {
    toggleSidebar: (state) => {
      state.sidebarOpen = !state.sidebarOpen;
    },
    setSidebarOpen: (state, action: PayloadAction<boolean>) => {
      state.sidebarOpen = action.payload;
    },
    openModal: (state, action: PayloadAction<string>) => {
      state.modalOpen = true;
      state.modalContent = action.payload;
    },
    closeModal: (state) => {
      state.modalOpen = false;
      state.modalContent = undefined;
    },
    showToast: (state, action: PayloadAction<ToastMessage>) => {
      state.toast = action.payload;
    },
    hideToast: (state) => {
      state.toast = undefined;
    },
  },
});

export const { toggleSidebar, setSidebarOpen, openModal, closeModal, showToast, hideToast } =
  uiSlice.actions;

export default uiSlice.reducer;
