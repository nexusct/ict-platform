/**
 * Projects Redux Slice
 *
 * @package ICT_Platform
 * @since   1.0.0
 */

import { createSlice, createAsyncThunk, PayloadAction } from '@reduxjs/toolkit';
import { projectAPI } from '../../services/api';
import type { Project, ProjectFormData, ProjectFilters, SortOptions, ProjectsState } from '../../types';

const initialState: ProjectsState = {
  items: [],
  currentProject: undefined,
  loading: false,
  error: undefined,
  filters: {},
  sort: {
    field: 'created_at',
    order: 'desc',
  },
  pagination: {
    page: 1,
    per_page: 20,
    total: 0,
  },
};

// Async thunks
export const fetchProjects = createAsyncThunk(
  'projects/fetchAll',
  async (params?: { page?: number; per_page?: number }, { getState }) => {
    const state = getState() as { projects: ProjectsState };
    const { filters, sort, pagination } = state.projects;

    const response = await projectAPI.getAll({
      page: params?.page || pagination.page,
      per_page: params?.per_page || pagination.per_page,
      status: filters.status?.join(','),
      search: filters.search,
    });

    return response;
  }
);

export const fetchProjectById = createAsyncThunk(
  'projects/fetchById',
  async (id: number) => {
    const response = await projectAPI.getById(id);
    return response.data!;
  }
);

export const createProject = createAsyncThunk(
  'projects/create',
  async (data: ProjectFormData) => {
    const response = await projectAPI.create(data);
    return response.data!;
  }
);

export const updateProject = createAsyncThunk(
  'projects/update',
  async ({ id, data }: { id: number; data: Partial<ProjectFormData> }) => {
    const response = await projectAPI.update(id, data);
    return response.data!;
  }
);

export const deleteProject = createAsyncThunk(
  'projects/delete',
  async (id: number) => {
    await projectAPI.delete(id);
    return id;
  }
);

export const syncProjectToZoho = createAsyncThunk(
  'projects/syncToZoho',
  async (id: number) => {
    await projectAPI.syncToZoho(id);
    return id;
  }
);

// Slice
const projectsSlice = createSlice({
  name: 'projects',
  initialState,
  reducers: {
    setFilters: (state, action: PayloadAction<Partial<ProjectFilters>>) => {
      state.filters = { ...state.filters, ...action.payload };
      state.pagination.page = 1; // Reset to first page when filters change
    },
    clearFilters: (state) => {
      state.filters = {};
      state.pagination.page = 1;
    },
    setSort: (state, action: PayloadAction<SortOptions>) => {
      state.sort = action.payload;
    },
    setPage: (state, action: PayloadAction<number>) => {
      state.pagination.page = action.payload;
    },
    setCurrentProject: (state, action: PayloadAction<Project | undefined>) => {
      state.currentProject = action.payload;
    },
    clearError: (state) => {
      state.error = undefined;
    },
  },
  extraReducers: (builder) => {
    // Fetch projects
    builder.addCase(fetchProjects.pending, (state) => {
      state.loading = true;
      state.error = undefined;
    });
    builder.addCase(fetchProjects.fulfilled, (state, action) => {
      state.loading = false;
      state.items = action.payload.data;
      state.pagination.total = action.payload.total;
      state.pagination.page = action.payload.page;
      state.pagination.per_page = action.payload.per_page;
    });
    builder.addCase(fetchProjects.rejected, (state, action) => {
      state.loading = false;
      state.error = action.error.message;
    });

    // Fetch project by ID
    builder.addCase(fetchProjectById.pending, (state) => {
      state.loading = true;
      state.error = undefined;
    });
    builder.addCase(fetchProjectById.fulfilled, (state, action) => {
      state.loading = false;
      state.currentProject = action.payload;
    });
    builder.addCase(fetchProjectById.rejected, (state, action) => {
      state.loading = false;
      state.error = action.error.message;
    });

    // Create project
    builder.addCase(createProject.pending, (state) => {
      state.loading = true;
      state.error = undefined;
    });
    builder.addCase(createProject.fulfilled, (state, action) => {
      state.loading = false;
      state.items.unshift(action.payload);
      state.pagination.total += 1;
    });
    builder.addCase(createProject.rejected, (state, action) => {
      state.loading = false;
      state.error = action.error.message;
    });

    // Update project
    builder.addCase(updateProject.fulfilled, (state, action) => {
      const index = state.items.findIndex((p) => p.id === action.payload.id);
      if (index !== -1) {
        state.items[index] = action.payload;
      }
      if (state.currentProject?.id === action.payload.id) {
        state.currentProject = action.payload;
      }
    });

    // Delete project
    builder.addCase(deleteProject.fulfilled, (state, action) => {
      state.items = state.items.filter((p) => p.id !== action.payload);
      state.pagination.total -= 1;
      if (state.currentProject?.id === action.payload) {
        state.currentProject = undefined;
      }
    });

    // Sync to Zoho
    builder.addCase(syncProjectToZoho.fulfilled, (state, action) => {
      const index = state.items.findIndex((p) => p.id === action.payload);
      if (index !== -1) {
        state.items[index].sync_status = 'syncing';
      }
    });
  },
});

export const { setFilters, clearFilters, setSort, setPage, setCurrentProject, clearError } =
  projectsSlice.actions;

export default projectsSlice.reducer;
