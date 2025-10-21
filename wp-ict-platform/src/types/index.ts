/**
 * TypeScript type definitions for ICT Platform
 *
 * @package ICT_Platform
 * @since   1.0.0
 */

// API Response types
export interface APIResponse<T> {
  success: boolean;
  data?: T;
  message?: string;
  error?: string;
}

export interface PaginatedResponse<T> {
  data: T[];
  total: number;
  page: number;
  per_page: number;
  total_pages: number;
}

// Project types
export interface Project {
  id: number;
  zoho_crm_id?: string;
  client_id?: number;
  project_name: string;
  project_number?: string;
  site_address?: string;
  status: ProjectStatus;
  priority: ProjectPriority;
  start_date?: string;
  end_date?: string;
  estimated_hours: number;
  actual_hours: number;
  budget_amount: number;
  actual_cost: number;
  progress_percentage: number;
  project_manager_id?: number;
  notes?: string;
  sync_status: SyncStatus;
  last_synced?: string;
  created_at: string;
  updated_at: string;
}

export type ProjectStatus = 'pending' | 'in-progress' | 'on-hold' | 'completed' | 'cancelled';
export type ProjectPriority = 'low' | 'medium' | 'high' | 'urgent';
export type SyncStatus = 'pending' | 'syncing' | 'synced' | 'error' | 'conflict';

export interface ProjectFormData {
  project_name: string;
  client_id?: number;
  site_address?: string;
  status: ProjectStatus;
  priority: ProjectPriority;
  start_date?: string;
  end_date?: string;
  estimated_hours?: number;
  budget_amount?: number;
  project_manager_id?: number;
  notes?: string;
}

// Time Entry types
export interface TimeEntry {
  id: number;
  project_id: number;
  technician_id: number;
  zoho_people_id?: string;
  task_type: string;
  clock_in: string;
  clock_out?: string;
  total_hours: number;
  billable_hours: number;
  hourly_rate: number;
  notes?: string;
  status: TimeEntryStatus;
  approved_by?: number;
  approved_at?: string;
  approval_notes?: string;
  gps_latitude?: number;
  gps_longitude?: number;
  gps_latitude_out?: number;
  gps_longitude_out?: number;
  sync_status: SyncStatus;
  last_synced_at?: string;
  created_at: string;
  updated_at: string;
}

export type TimeEntryStatus = 'in-progress' | 'submitted' | 'approved' | 'rejected';

export interface TimeEntryFormData {
  project_id: number;
  task_type?: string;
  notes?: string;
  billable_hours?: number;
  status?: TimeEntryStatus;
}

export interface TimeEntryFilters {
  status?: string;
  technician_id?: number;
  project_id?: number;
  date_from?: string;
  date_to?: string;
  search?: string;
}

// Inventory types
export interface InventoryItem {
  id: number;
  zoho_books_item_id?: string;
  sku: string;
  item_name: string;
  description?: string;
  category?: string;
  unit_of_measure: string;
  quantity_on_hand: number;
  quantity_allocated: number;
  quantity_available: number;
  reorder_level: number;
  reorder_quantity: number;
  unit_cost: number;
  unit_price: number;
  supplier_id?: number;
  location?: string;
  barcode?: string;
  is_active: boolean;
  sync_status: SyncStatus;
  last_synced?: string;
  created_at: string;
  updated_at: string;
}

// Purchase Order types
export interface PurchaseOrder {
  id: number;
  zoho_books_po_id?: string;
  po_number: string;
  project_id?: number;
  supplier_id: number;
  po_date: string;
  delivery_date?: string;
  status: PurchaseOrderStatus;
  subtotal: number;
  tax_amount: number;
  shipping_cost: number;
  total_amount: number;
  notes?: string;
  created_by?: number;
  approved_by?: number;
  approved_at?: string;
  received_date?: string;
  sync_status: SyncStatus;
  last_synced?: string;
  created_at: string;
  updated_at: string;
}

export type PurchaseOrderStatus = 'draft' | 'pending' | 'approved' | 'ordered' | 'received' | 'cancelled';

// Resource types
export interface ProjectResource {
  id: number;
  project_id: number;
  resource_type: ResourceType;
  resource_id: number;
  allocation_start: string;
  allocation_end: string;
  allocation_percentage: number;
  estimated_hours: number;
  actual_hours: number;
  status: ResourceStatus;
  notes?: string;
  created_at: string;
  updated_at: string;
}

export type ResourceType = 'technician' | 'equipment' | 'vehicle';
export type ResourceStatus = 'scheduled' | 'active' | 'completed' | 'cancelled';

export interface ResourceFormData {
  project_id: number;
  resource_type: ResourceType;
  resource_id: number;
  allocation_start: string;
  allocation_end: string;
  allocation_percentage?: number;
  estimated_hours?: number;
  notes?: string;
}

export interface ResourceFilters {
  project_id?: number;
  resource_type?: ResourceType;
  resource_id?: number;
  status?: ResourceStatus;
  date_from?: string;
  date_to?: string;
}

export interface TechnicianSkill {
  id: number;
  technician_id: number;
  skill_name: string;
  proficiency_level: number; // 1-5
  years_experience?: number;
  certifications?: string;
  last_updated: string;
}

export interface ResourceConflict {
  resource_type: ResourceType;
  resource_id: number;
  existing_allocation: ProjectResource;
  requested_allocation: {
    allocation_start: string;
    allocation_end: string;
  };
  overlap_hours: number;
}

export interface CalendarEvent {
  id: number;
  title: string;
  start: string;
  end: string;
  resourceId?: number;
  resourceType?: ResourceType;
  backgroundColor?: string;
  borderColor?: string;
  extendedProps?: {
    project_id?: number;
    status?: ResourceStatus;
    notes?: string;
  };
}

export interface ResourceAvailability {
  resource_type: ResourceType;
  resource_id: number;
  date: string;
  available_hours: number;
  allocated_hours: number;
  availability_percentage: number;
}

// Sync types
export interface SyncQueueItem {
  id: number;
  entity_type: string;
  entity_id: number;
  action: SyncAction;
  zoho_service: ZohoService;
  priority: number;
  status: QueueStatus;
  attempts: number;
  max_attempts: number;
  last_error?: string;
  payload?: string;
  scheduled_at: string;
  processed_at?: string;
  created_at: string;
  updated_at: string;
}

export type SyncAction = 'create' | 'update' | 'delete';
export type ZohoService = 'crm' | 'fsm' | 'books' | 'people' | 'desk';
export type QueueStatus = 'pending' | 'processing' | 'completed' | 'failed' | 'skipped';

export interface SyncLog {
  id: number;
  entity_type: string;
  entity_id?: number;
  direction: 'inbound' | 'outbound';
  zoho_service: ZohoService;
  action: string;
  status: 'success' | 'error';
  request_data?: string;
  response_data?: string;
  error_message?: string;
  duration_ms: number;
  synced_at: string;
}

// User types
export interface User {
  id: number;
  username: string;
  email: string;
  first_name: string;
  last_name: string;
  display_name: string;
  role: string;
  avatar_url?: string;
}

// Settings types
export interface AppSettings {
  dateFormat: string;
  timeFormat: string;
  currency: string;
  timeRounding: number;
  offlineMode: boolean;
  gpsTracking: boolean;
}

export interface UserCapabilities {
  manageProjects: boolean;
  manageTimeEntries: boolean;
  approveTime: boolean;
  manageInventory: boolean;
  managePurchaseOrders: boolean;
  viewReports: boolean;
  manageSync: boolean;
}

export interface ZohoStatus {
  [key: string]: boolean;
}

// Filter and Sort types
export interface ProjectFilters {
  status?: ProjectStatus[];
  priority?: ProjectPriority[];
  client_id?: number;
  project_manager_id?: number;
  search?: string;
  date_from?: string;
  date_to?: string;
}

export interface SortOptions {
  field: string;
  order: 'asc' | 'desc';
}

// Chart data types
export interface ChartDataPoint {
  label: string;
  value: number;
  color?: string;
}

export interface TimeSeriesData {
  date: string;
  value: number;
}

// Global state
export interface RootState {
  projects: ProjectsState;
  timeEntries: TimeEntriesState;
  resources: ResourcesState;
  inventory: InventoryState;
  purchaseOrders: PurchaseOrdersState;
  sync: SyncState;
  ui: UIState;
}

export interface ProjectsState {
  items: Project[];
  currentProject?: Project;
  loading: boolean;
  error?: string;
  filters: ProjectFilters;
  sort: SortOptions;
  pagination: {
    page: number;
    per_page: number;
    total: number;
  };
}

export interface TimeEntriesState {
  items: TimeEntry[];
  activeEntry?: TimeEntry;
  loading: boolean;
  error?: string;
  filters: TimeEntryFilters;
  pagination: {
    page: number;
    per_page: number;
    total: number;
    total_pages: number;
  };
}

export interface ResourcesState {
  items: ProjectResource[];
  loading: boolean;
  error?: string;
  filters: ResourceFilters;
  conflicts: ResourceConflict[];
  calendarEvents: CalendarEvent[];
  availability: ResourceAvailability[];
  technicianSkills: Record<number, TechnicianSkill[]>; // technician_id => skills
  pagination: {
    page: number;
    per_page: number;
    total: number;
    total_pages: number;
  };
}

export interface InventoryState {
  items: InventoryItem[];
  loading: boolean;
  error?: string;
  lowStockItems: InventoryItem[];
  categories: string[];
  locations: string[];
  filters: {
    search?: string;
    category?: string;
    location?: string;
    is_active?: boolean;
    low_stock?: boolean;
  };
  pagination: {
    page: number;
    per_page: number;
    total: number;
    total_pages: number;
  };
  stockHistory: StockHistoryItem[];
}

export interface StockHistoryItem {
  id: number;
  item_id: number;
  quantity_change: number;
  adjustment_type: string;
  reason: string;
  reference?: string;
  project_id?: number;
  user_id: number;
  created_at: string;
}

export interface PurchaseOrdersState {
  items: PurchaseOrder[];
  currentPO?: PurchaseOrder;
  loading: boolean;
  error?: string;
  filters: {
    status?: PurchaseOrderStatus | PurchaseOrderStatus[];
    project_id?: number;
    supplier_id?: number;
    date_from?: string;
    date_to?: string;
    search?: string;
  };
  pagination: {
    page: number;
    per_page: number;
    total: number;
    total_pages: number;
  };
}

export interface SyncState {
  queue: SyncQueueItem[];
  logs: SyncLog[];
  isRunning: boolean;
  lastSync?: string;
  pendingCount: number;
}

export interface UIState {
  sidebarOpen: boolean;
  modalOpen: boolean;
  modalContent?: string;
  toast?: ToastMessage;
}

export interface ToastMessage {
  type: 'success' | 'error' | 'warning' | 'info';
  message: string;
  duration?: number;
}

// Component Props types
export interface ModalProps {
  isOpen: boolean;
  onClose: () => void;
  title: string;
  children: React.ReactNode;
  size?: 'small' | 'medium' | 'large';
}

export interface ButtonProps {
  variant?: 'primary' | 'secondary' | 'danger' | 'success';
  size?: 'small' | 'medium' | 'large';
  disabled?: boolean;
  loading?: boolean;
  onClick?: () => void;
  children: React.ReactNode;
  type?: 'button' | 'submit' | 'reset';
}

export interface TableColumn<T> {
  key: keyof T | string;
  label: string;
  sortable?: boolean;
  render?: (item: T) => React.ReactNode;
}
