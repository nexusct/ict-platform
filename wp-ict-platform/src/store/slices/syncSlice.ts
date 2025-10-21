/**
 * Sync Redux Slice (Placeholder)
 *
 * @package ICT_Platform
 * @since   1.0.0
 */

import { createSlice } from '@reduxjs/toolkit';
import type { SyncState } from '../../types';

const initialState: SyncState = {
  queue: [],
  logs: [],
  isRunning: false,
  lastSync: undefined,
  pendingCount: 0,
};

const syncSlice = createSlice({
  name: 'sync',
  initialState,
  reducers: {},
});

export default syncSlice.reducer;
