/**
 * API Service Layer
 *
 * Enhanced with retry logic, timeout handling, and improved error messages
 *
 * @package ICT_Platform
 * @since   1.0.0
 */

import axios, { AxiosInstance, AxiosError, AxiosRequestConfig } from 'axios';
import type {
  APIResponse,
  PaginatedResponse,
  Project,
  ProjectFormData,
  TimeEntry,
  InventoryItem,
  PurchaseOrder,
  ProjectResource,
  ResourceFormData,
  ResourceType,
  ResourceConflict,
  CalendarEvent,
  ResourceAvailability,
  TechnicianSkill,
  SyncQueueItem,
  SyncLog,
} from '../types';
import { errorLogger } from '../utils/errorLogger';

// Get WordPress API settings from localized script
declare const ictPlatform: {
  apiUrl: string;
  nonce: string;
  currentUser: number;
  userCan: Record<string, boolean>;
  settings: Record<string, unknown>;
  zohoStatus: Record<string, boolean>;
  i18n: Record<string, string>;
};

// Retry configuration
interface RetryConfig {
  maxRetries: number;
  baseDelay: number;
  maxDelay: number;
}

const DEFAULT_RETRY_CONFIG: RetryConfig = {
  maxRetries: 3,
  baseDelay: 1000,
  maxDelay: 10000,
};

const DEFAULT_TIMEOUT = 30000; // 30 seconds

// Extended request config with retry tracking
interface ExtendedRequestConfig extends AxiosRequestConfig {
  _retryCount?: number;
  _skipRetry?: boolean;
}

/**
 * Calculate exponential backoff delay with jitter
 */
function calculateBackoff(retryCount: number, config: RetryConfig): number {
  const delay = Math.min(config.baseDelay * Math.pow(2, retryCount), config.maxDelay);
  const jitter = delay * 0.1 * Math.random();
  return delay + jitter;
}

/**
 * Sleep helper
 */
function sleep(ms: number): Promise<void> {
  return new Promise((resolve) => setTimeout(resolve, ms));
}

/**
 * Check if error is retryable
 */
function isRetryableError(error: AxiosError): boolean {
  // Network errors (no response)
  if (!error.response) return true;

  // Server errors (5xx)
  const status = error.response.status;
  if (status >= 500 && status < 600) return true;

  // Rate limiting
  if (status === 429) return true;

  return false;
}

/**
 * Parse error into user-friendly message
 */
export function parseApiError(error: unknown): {
  message: string;
  details?: string;
  statusCode?: number;
  isNetworkError: boolean;
} {
  const result = {
    message: 'An unexpected error occurred',
    details: undefined as string | undefined,
    statusCode: undefined as number | undefined,
    isNetworkError: false,
  };

  if (error instanceof AxiosError) {
    if (!error.response) {
      result.isNetworkError = true;
      if (error.code === 'ECONNABORTED' || error.message.includes('timeout')) {
        result.message = 'Request timed out. Please try again.';
      } else {
        result.message = 'Unable to connect. Please check your internet connection.';
      }
      return result;
    }

    const { status, data } = error.response;
    result.statusCode = status;

    switch (status) {
      case 400:
        result.message = (data as { message?: string })?.message || 'Invalid request';
        break;
      case 401:
        result.message = 'Your session has expired. Please refresh the page.';
        break;
      case 403:
        result.message = 'You do not have permission for this action.';
        break;
      case 404:
        result.message = 'The requested item was not found.';
        break;
      case 409:
        result.message = 'A conflict occurred. Please refresh and try again.';
        break;
      case 422: {
        result.message = (data as { message?: string })?.message || 'Validation failed';
        const errors = (data as { errors?: Record<string, string[]> })?.errors;
        if (errors) {
          const firstField = Object.keys(errors)[0];
          result.details = errors[firstField]?.[0];
        }
        break;
      }
      case 429:
        result.message = 'Too many requests. Please wait a moment.';
        break;
      case 500:
      case 502:
      case 503:
        result.message = 'Server error. Please try again later.';
        break;
      default:
        result.message = (data as { message?: string })?.message || 'An error occurred';
    }
  } else if (error instanceof Error) {
    result.message = error.message;
  }

  return result;
}

class APIService {
  private client: AxiosInstance;
  private retryConfig: RetryConfig;

  constructor(retryConfig: RetryConfig = DEFAULT_RETRY_CONFIG) {
    this.retryConfig = retryConfig;

    this.client = axios.create({
      baseURL: typeof ictPlatform !== 'undefined' ? ictPlatform.apiUrl : '/wp-json/ict-platform/v1',
      headers: {
        'Content-Type': 'application/json',
        'X-WP-Nonce': typeof ictPlatform !== 'undefined' ? ictPlatform.nonce : '',
      },
      timeout: DEFAULT_TIMEOUT,
    });

    this.setupInterceptors();
  }

  private setupInterceptors(): void {
    // Request interceptor - add timestamp for debugging
    this.client.interceptors.request.use((config) => {
      (config as ExtendedRequestConfig)._retryCount = 0;
      return config;
    });

    // Response interceptor - retry logic and error handling
    this.client.interceptors.response.use(
      (response) => response,
      async (error: AxiosError) => {
        const config = error.config as ExtendedRequestConfig;

        if (!config) {
          return Promise.reject(error);
        }

        // Handle 401 - unauthorized
        if (error.response?.status === 401) {
          errorLogger.logApiError('Session expired', {
            endpoint: config.url,
            status: 401,
          });
          // Could dispatch event to trigger re-auth
          window.dispatchEvent(new CustomEvent('ict:session-expired'));
        }

        // Check if we should retry
        config._retryCount = config._retryCount || 0;
        const shouldRetry =
          !config._skipRetry &&
          config._retryCount < this.retryConfig.maxRetries &&
          isRetryableError(error);

        if (shouldRetry) {
          config._retryCount += 1;
          const delay = calculateBackoff(config._retryCount, this.retryConfig);

          // Log retry attempt
          errorLogger.logApiError(`Retrying request (attempt ${config._retryCount})`, {
            endpoint: config.url,
            status: error.response?.status,
          });

          await sleep(delay);
          return this.client(config);
        }

        // Log final error
        errorLogger.logApiError(error.message, {
          endpoint: config.url,
          status: error.response?.status,
          response: error.response?.data,
        });

        return Promise.reject(error);
      }
    );
  }

  // Generic request methods with proper typing
  async get<T>(endpoint: string, params?: Record<string, unknown>): Promise<T> {
    const response = await this.client.get<T>(endpoint, { params });
    return response.data;
  }

  async post<T>(endpoint: string, data?: unknown): Promise<T> {
    const response = await this.client.post<T>(endpoint, data);
    return response.data;
  }

  async put<T>(endpoint: string, data?: unknown): Promise<T> {
    const response = await this.client.put<T>(endpoint, data);
    return response.data;
  }

  async delete<T>(endpoint: string): Promise<T> {
    const response = await this.client.delete<T>(endpoint);
    return response.data;
  }

  // Request with custom timeout
  async requestWithTimeout<T>(
    method: 'get' | 'post' | 'put' | 'delete',
    endpoint: string,
    options?: { data?: unknown; params?: Record<string, unknown>; timeout?: number }
  ): Promise<T> {
    const config: AxiosRequestConfig = {
      method,
      url: endpoint,
      timeout: options?.timeout || DEFAULT_TIMEOUT,
    };

    if (options?.data) config.data = options.data;
    if (options?.params) config.params = options.params;

    const response = await this.client.request<T>(config);
    return response.data;
  }

  // Skip retry for specific requests
  async postNoRetry<T>(endpoint: string, data?: unknown): Promise<T> {
    const response = await this.client.post<T>(endpoint, data, {
      _skipRetry: true,
    } as ExtendedRequestConfig);
    return response.data;
  }
}

// Create singleton instance
const api = new APIService();

// Project API
export const projectAPI = {
  getAll: (params?: {
    page?: number;
    per_page?: number;
    status?: string;
    search?: string;
  }) => api.get<PaginatedResponse<Project>>('/projects', params),

  getById: (id: number) => api.get<APIResponse<Project>>(`/projects/${id}`),

  create: (data: ProjectFormData) => api.post<APIResponse<Project>>('/projects', data),

  update: (id: number, data: Partial<ProjectFormData>) =>
    api.put<APIResponse<Project>>(`/projects/${id}`, data),

  delete: (id: number) => api.delete<APIResponse<void>>(`/projects/${id}`),

  syncToZoho: (id: number) =>
    api.post<APIResponse<void>>(`/projects/${id}/sync`, { service: 'crm' }),
};

// Time Entry API
export const timeEntryAPI = {
  getAll: (params?: {
    page?: number;
    per_page?: number;
    status?: string;
    project_id?: number;
    technician_id?: number;
    date_from?: string;
    date_to?: string;
    search?: string;
  }) => api.get<PaginatedResponse<TimeEntry>>('/time-entries', params),

  getOne: (id: number) => api.get<APIResponse<TimeEntry>>(`/time-entries/${id}`),

  create: (data: {
    project_id: number;
    task_type?: string;
    notes?: string;
    gps_latitude?: number;
    gps_longitude?: number;
  }) => api.post<APIResponse<TimeEntry>>('/time-entries', data),

  update: (id: number, data: {
    task_type?: string;
    notes?: string;
    billable_hours?: number;
    status?: string;
  }) => api.put<APIResponse<TimeEntry>>(`/time-entries/${id}`, data),

  delete: (id: number) => api.delete<APIResponse<void>>(`/time-entries/${id}`),

  clockIn: (data: {
    project_id: number;
    task_type?: string;
    notes?: string;
    gps_latitude?: number;
    gps_longitude?: number;
  }) => api.post<APIResponse<TimeEntry>>('/time/clock-in', data),

  clockOut: (data?: {
    entry_id?: number;
    notes?: string;
    gps_latitude?: number;
    gps_longitude?: number;
  }) => api.post<APIResponse<TimeEntry>>('/time/clock-out', data),

  getActive: () => api.get<APIResponse<TimeEntry | null>>('/time/active'),

  approve: (id: number, notes?: string) =>
    api.post<APIResponse<TimeEntry>>(`/time-entries/${id}/approve`, { notes }),

  reject: (id: number, reason: string) =>
    api.post<APIResponse<TimeEntry>>(`/time-entries/${id}/reject`, { reason }),

  syncToZoho: (id: number) =>
    api.post<APIResponse<void>>(`/time-entries/${id}/sync`),
};

// Inventory API
export const inventoryAPI = {
  // Get all inventory items with filtering
  getAll: (params?: {
    page?: number;
    per_page?: number;
    search?: string;
    category?: string;
    location?: string;
    is_active?: boolean;
    low_stock?: boolean;
  }) => api.get<PaginatedResponse<InventoryItem>>('/inventory', params),

  // Get single inventory item
  getById: (id: number) => api.get<APIResponse<InventoryItem>>(`/inventory/${id}`),

  // Create inventory item
  create: (data: {
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
  }) => api.post<APIResponse<InventoryItem>>('/inventory', data),

  // Update inventory item
  update: (id: number, data: Partial<InventoryItem>) =>
    api.put<APIResponse<InventoryItem>>(`/inventory/${id}`, data),

  // Delete inventory item
  delete: (id: number) => api.delete<APIResponse<void>>(`/inventory/${id}`),

  // Adjust stock
  adjustStock: (
    id: number,
    adjustment: number,
    adjustment_type: 'received' | 'consumed' | 'damaged' | 'lost' | 'returned' | 'transfer' | 'correction' | 'initial',
    reason: string,
    project_id?: number,
    reference?: string
  ) =>
    api.post<APIResponse<InventoryItem>>(`/inventory/${id}/adjust`, {
      adjustment,
      adjustment_type,
      reason,
      project_id,
      reference,
    }),

  // Get stock history
  getStockHistory: (id: number, per_page?: number) =>
    api.get<APIResponse<any[]>>(`/inventory/${id}/history`, { per_page }),

  // Get low stock items
  getLowStock: () => api.get<APIResponse<InventoryItem[]>>('/inventory/low-stock'),

  // Transfer stock
  transferStock: (params: {
    item_id: number;
    from_location: string;
    to_location: string;
    quantity: number;
    notes?: string;
  }) => api.post<APIResponse<void>>('/inventory/transfer', params),

  // Get categories
  getCategories: () => api.get<APIResponse<string[]>>('/inventory/categories'),

  // Get locations
  getLocations: () => api.get<APIResponse<string[]>>('/inventory/locations'),

  // Bulk update
  bulkUpdate: (items: Array<{ id: number; [key: string]: any }>) =>
    api.put<APIResponse<{ updated: number }>>('/inventory/bulk', { items }),
};

// Purchase Order API
export const purchaseOrderAPI = {
  // Get all purchase orders with filtering
  getAll: (params?: {
    page?: number;
    per_page?: number;
    status?: string | string[];
    project_id?: number;
    supplier_id?: number;
    date_from?: string;
    date_to?: string;
    search?: string;
  }) => api.get<PaginatedResponse<PurchaseOrder>>('/purchase-orders', params),

  // Get single purchase order with line items
  getById: (id: number) => api.get<APIResponse<PurchaseOrder>>(`/purchase-orders/${id}`),

  // Create new purchase order
  create: (data: {
    po_number?: string;
    project_id?: number;
    supplier_id: number;
    po_date: string;
    delivery_date?: string;
    status?: string;
    notes?: string;
    line_items: Array<{
      inventory_id: number;
      description: string;
      quantity: number;
      unit_price: number;
      tax_rate?: number;
    }>;
  }) => api.post<APIResponse<PurchaseOrder>>('/purchase-orders', data),

  // Update purchase order
  update: (id: number, data: {
    project_id?: number;
    supplier_id?: number;
    po_date?: string;
    delivery_date?: string;
    status?: string;
    notes?: string;
    line_items?: Array<{
      id?: number;
      inventory_id: number;
      description: string;
      quantity: number;
      unit_price: number;
      tax_rate?: number;
    }>;
  }) => api.put<APIResponse<PurchaseOrder>>(`/purchase-orders/${id}`, data),

  // Delete purchase order (draft only)
  delete: (id: number) => api.delete<APIResponse<void>>(`/purchase-orders/${id}`),

  // Approve purchase order
  approve: (id: number) => api.post<APIResponse<PurchaseOrder>>(`/purchase-orders/${id}/approve`),

  // Mark purchase order as ordered
  markAsOrdered: (id: number) =>
    api.post<APIResponse<PurchaseOrder>>(`/purchase-orders/${id}/mark-ordered`),

  // Receive purchase order and update inventory
  receive: (id: number, received_items: Array<{
    line_item_id: number;
    inventory_id: number;
    quantity_received: number;
    notes?: string;
  }>) =>
    api.post<APIResponse<PurchaseOrder>>(`/purchase-orders/${id}/receive`, { received_items }),

  // Cancel purchase order
  cancel: (id: number, reason?: string) =>
    api.post<APIResponse<PurchaseOrder>>(`/purchase-orders/${id}/cancel`, { reason }),

  // Generate unique PO number
  generateNumber: () => api.get<APIResponse<string>>('/purchase-orders/generate-number'),
};

// Resource API
export const resourceAPI = {
  // Get all resource allocations with filtering
  getAll: (params?: {
    page?: number;
    per_page?: number;
    project_id?: number;
    resource_type?: ResourceType;
    resource_id?: number;
    status?: string;
    date_from?: string;
    date_to?: string;
  }) => api.get<PaginatedResponse<ProjectResource>>('/resources', params),

  // Get single resource allocation
  getOne: (id: number) => api.get<APIResponse<ProjectResource>>(`/resources/${id}`),

  // Create new resource allocation
  create: (data: ResourceFormData) =>
    api.post<APIResponse<ProjectResource>>('/resources', data),

  // Update resource allocation
  update: (id: number, data: Partial<ResourceFormData>) =>
    api.put<APIResponse<ProjectResource>>(`/resources/${id}`, data),

  // Delete resource allocation
  delete: (id: number) => api.delete<APIResponse<void>>(`/resources/${id}`),

  // Check for resource conflicts
  checkConflicts: (params: {
    resource_type: ResourceType;
    resource_id: number;
    allocation_start: string;
    allocation_end: string;
    exclude_id?: number;
  }) => api.get<APIResponse<ResourceConflict[]>>('/resources/conflicts', params),

  // Get resource availability for date range
  getAvailability: (params: {
    resource_type?: ResourceType;
    resource_id?: number;
    date_from: string;
    date_to: string;
  }) => api.get<APIResponse<ResourceAvailability[]>>('/resources/availability', params),

  // Get calendar events for FullCalendar
  getCalendarEvents: (params: {
    start: string;
    end: string;
    resource_type?: ResourceType;
    resource_id?: number;
  }) => api.get<APIResponse<CalendarEvent[]>>('/resources/calendar', params),

  // Get technician skills
  getTechnicianSkills: (technicianId: number) =>
    api.get<APIResponse<TechnicianSkill[]>>(`/resources/technicians/${technicianId}/skills`),

  // Update technician skills
  updateTechnicianSkills: (technicianId: number, skills: Partial<TechnicianSkill>[]) =>
    api.put<APIResponse<TechnicianSkill[]>>(
      `/resources/technicians/${technicianId}/skills`,
      { skills }
    ),

  // Batch create allocations
  batchCreate: (allocations: ResourceFormData[]) =>
    api.post<APIResponse<ProjectResource[]>>('/resources/batch', { allocations }),

  // Batch update allocations
  batchUpdate: (updates: { id: number; data: Partial<ResourceFormData> }[]) =>
    api.put<APIResponse<ProjectResource[]>>('/resources/batch', { updates }),

  // Batch delete allocations
  batchDelete: (ids: number[]) =>
    api.post<APIResponse<void>>('/resources/batch/delete', { ids }),
};

// Sync API
export const syncAPI = {
  getStatus: () =>
    api.get<
      APIResponse<{
        queue: SyncQueueItem[];
        pending_count: number;
        is_running: boolean;
        last_sync: string;
      }>
    >('/sync/status'),

  trigger: (service?: string) => api.post<APIResponse<void>>('/sync/trigger', { service }),

  getLogs: (params?: { service?: string; limit?: number }) =>
    api.get<PaginatedResponse<SyncLog>>('/sync/logs', params),

  clearQueue: () => api.post<APIResponse<void>>('/sync/clear-queue'),

  retryFailed: () => api.post<APIResponse<void>>('/sync/retry-failed'),
};

// Reports API
export const reportsAPI = {
  // Get project report
  getProjectReport: (params?: {
    date_from?: string;
    date_to?: string;
    status?: string;
    priority?: string;
  }) => api.get<APIResponse<any>>('/reports/projects', params),

  // Get time report
  getTimeReport: (params?: {
    date_from?: string;
    date_to?: string;
    technician_id?: number;
    project_id?: number;
    group_by?: 'day' | 'week' | 'month' | 'technician' | 'project';
  }) => api.get<APIResponse<any>>('/reports/time', params),

  // Get budget report
  getBudgetReport: (params?: {
    project_id?: number;
    date_from?: string;
    date_to?: string;
  }) => api.get<APIResponse<any>>('/reports/budget', params),

  // Get inventory report
  getInventoryReport: (params?: {
    category?: string;
    location?: string;
    date_from?: string;
    date_to?: string;
  }) => api.get<APIResponse<any>>('/reports/inventory', params),

  // Get dashboard summary
  getDashboardSummary: () => api.get<APIResponse<any>>('/reports/dashboard'),

  // Export report
  exportReport: (
    report_type: 'projects' | 'time' | 'budget' | 'inventory',
    format?: 'csv' | 'excel' | 'pdf',
    filters?: Record<string, any>
  ) =>
    api.post<APIResponse<{ export_url: string; format: string; message: string }>>(
      '/reports/export',
      {
        report_type,
        format: format || 'csv',
        ...filters,
      }
    ),
};

export default api;
