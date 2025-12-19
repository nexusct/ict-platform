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

// ============================================
// NEW FEATURE TYPES (v2.1.0)
// ============================================

// Document types
export interface Document {
  id: number;
  project_id?: number;
  entity_type: string;
  entity_id?: number;
  document_name: string;
  original_filename: string;
  file_path: string;
  file_type?: string;
  file_size: number;
  mime_type?: string;
  category: DocumentCategory;
  description?: string;
  version: number;
  is_public: boolean;
  uploaded_by: number;
  tags?: string;
  created_at: string;
  updated_at: string;
  url?: string;
}

export type DocumentCategory =
  | 'general' | 'contract' | 'permit' | 'invoice'
  | 'photo' | 'drawing' | 'report' | 'safety'
  | 'warranty' | 'manual';

// Equipment types
export interface Equipment {
  id: number;
  equipment_name: string;
  equipment_number?: string;
  equipment_type: EquipmentType;
  manufacturer?: string;
  model?: string;
  serial_number?: string;
  purchase_date?: string;
  purchase_price: number;
  current_value: number;
  status: EquipmentStatus;
  condition_rating: number;
  location?: string;
  assigned_to?: number;
  assigned_project?: number;
  last_maintenance?: string;
  next_maintenance?: string;
  maintenance_interval_days: number;
  qr_code?: string;
  notes?: string;
  specifications?: object;
  created_at: string;
  updated_at: string;
}

export type EquipmentType =
  | 'power-tool' | 'hand-tool' | 'testing' | 'safety'
  | 'lifting' | 'cable' | 'diagnostic' | 'measurement'
  | 'communication' | 'other';

export type EquipmentStatus = 'available' | 'in-use' | 'maintenance' | 'retired';

// Expense types
export interface Expense {
  id: number;
  project_id?: number;
  technician_id: number;
  expense_date: string;
  category: ExpenseCategory;
  vendor?: string;
  description?: string;
  amount: number;
  currency: string;
  receipt_path?: string;
  receipt_url?: string;
  status: ExpenseStatus;
  approved_by?: number;
  approved_at?: string;
  reimbursed_at?: string;
  payment_method?: string;
  is_billable: boolean;
  notes?: string;
  technician_name?: string;
  created_at: string;
  updated_at: string;
}

export type ExpenseCategory =
  | 'fuel' | 'materials' | 'tools' | 'meals' | 'travel'
  | 'accommodation' | 'parking' | 'communication'
  | 'training' | 'safety' | 'office' | 'other';

export type ExpenseStatus = 'pending' | 'approved' | 'rejected' | 'reimbursed';

// Signature types
export interface Signature {
  id: number;
  entity_type: string;
  entity_id: number;
  signer_name: string;
  signer_email?: string;
  signer_role?: string;
  signature_data: string;
  signature_image?: string;
  signature_url?: string;
  ip_address?: string;
  location_latitude?: number;
  location_longitude?: number;
  signed_at: string;
  created_at: string;
}

// Voice Note types
export interface VoiceNote {
  id: number;
  project_id?: number;
  entity_type: string;
  entity_id?: number;
  recorded_by: number;
  recorded_by_name?: string;
  audio_path: string;
  audio_url?: string;
  duration_seconds: number;
  file_size: number;
  transcription?: string;
  transcription_status: TranscriptionStatus;
  location_latitude?: number;
  location_longitude?: number;
  tags?: string;
  created_at: string;
}

export type TranscriptionStatus = 'pending' | 'processing' | 'completed' | 'failed';

// Fleet/Vehicle types
export interface Vehicle {
  id: number;
  vehicle_name: string;
  vehicle_number?: string;
  vehicle_type: VehicleType;
  make?: string;
  model?: string;
  year?: number;
  vin?: string;
  license_plate?: string;
  color?: string;
  status: VehicleStatus;
  current_mileage: number;
  fuel_type?: string;
  fuel_capacity: number;
  assigned_driver?: number;
  driver_name?: string;
  assigned_project?: number;
  insurance_expiry?: string;
  registration_expiry?: string;
  last_service_date?: string;
  next_service_date?: string;
  service_interval_miles: number;
  gps_device_id?: string;
  qr_code?: string;
  notes?: string;
  last_location?: VehicleLocation;
  created_at: string;
  updated_at: string;
}

export type VehicleType =
  | 'van' | 'truck' | 'pickup' | 'suv' | 'sedan'
  | 'box-truck' | 'trailer' | 'specialty';

export type VehicleStatus = 'available' | 'in-use' | 'maintenance' | 'retired';

export interface VehicleLocation {
  latitude: number;
  longitude: number;
  speed: number;
  heading?: number;
  recorded_at: string;
  address?: string;
}

// Notification types
export interface Notification {
  id: number;
  user_id: number;
  type: NotificationType;
  title: string;
  message: string;
  data?: object;
  priority: 'low' | 'normal' | 'high' | 'urgent';
  is_read: boolean;
  read_at?: string;
  action_url?: string;
  entity_type?: string;
  entity_id?: number;
  push_sent: boolean;
  created_at: string;
}

export type NotificationType =
  | 'general' | 'announcement' | 'time_approval' | 'expense_submitted'
  | 'expense_approved' | 'expense_rejected' | 'expense_reimbursed'
  | 'project_update' | 'low_stock' | 'sync_error' | 'client_approval'
  | 'support_request';

export interface NotificationPreferences {
  email_notifications: boolean;
  push_notifications: boolean;
  time_approval: boolean;
  expense_updates: boolean;
  project_updates: boolean;
  inventory_alerts: boolean;
  sync_errors: boolean;
  daily_digest: boolean;
  quiet_hours_start?: string;
  quiet_hours_end?: string;
}

// QR Code types
export interface QrCode {
  id: number;
  code: string;
  entity_type: string;
  entity_id: number;
  label?: string;
  description?: string;
  qr_image_path?: string;
  qr_image_url?: string;
  scan_url?: string;
  is_active: boolean;
  scan_count: number;
  last_scanned_at?: string;
  last_scanned_by?: number;
  created_by: number;
  created_at: string;
  updated_at: string;
}

// Activity Log types
export interface ActivityLogEntry {
  id: number;
  user_id: number;
  user_name?: string;
  user_avatar?: string;
  action: string;
  entity_type: string;
  entity_id?: number;
  entity_name?: string;
  description?: string;
  old_values?: object;
  new_values?: object;
  changes?: ActivityChange[];
  ip_address?: string;
  created_at: string;
}

export interface ActivityChange {
  field: string;
  old: unknown;
  new: unknown;
  old_value?: unknown;
  new_value?: unknown;
}

// Icon types
export interface IconProps {
  name: string;
  size?: number;
  color?: string;
  className?: string;
  'aria-hidden'?: boolean | string;
}

// Weather types
export interface WeatherCurrent {
  temperature: number;
  feels_like: number;
  humidity: number;
  precipitation: number;
  rain: number;
  snow: number;
  cloud_cover: number;
  wind_speed: number;
  wind_gusts: number;
  weather_code: number;
  weather_description: string;
}

export interface WeatherForecastDay {
  date: string;
  temp_high: number;
  temp_low: number;
  feels_like_high: number;
  feels_like_low: number;
  precipitation: number;
  rain: number;
  snow: number;
  precip_probability: number;
  wind_speed: number;
  wind_gusts: number;
  weather_code: number;
  weather_description: string;
}

export interface WeatherAlert {
  date: string;
  alerts: string[];
  severity: 'low' | 'medium' | 'high';
  work_safe: boolean;
  conditions: WeatherForecastDay;
}

export interface WorkSafetyAssessment {
  safe_to_work: boolean;
  warnings: string[];
  recommendations: string[];
  risk_level: 'low' | 'medium' | 'high';
}

// Client Portal types
export interface ClientProject {
  id: number;
  project_name: string;
  project_number?: string;
  status: ProjectStatus;
  status_label: string;
  priority: ProjectPriority;
  priority_label: string;
  start_date?: string;
  end_date?: string;
  progress_percentage: number;
  site_address?: string;
}

export interface ClientDashboard {
  project_stats: { status: string; count: number }[];
  active_projects: ClientProject[];
  recent_documents: Document[];
  welcome_message: string;
}

// ============================================
// IMPROVEMENT TYPES (v2.1.0)
// ============================================

// Gantt Chart types
export interface GanttTask {
  id: string;
  name: string;
  start: string;
  end: string;
  progress: number;
  dependencies?: string[];
  assignee?: number;
  color?: string;
  type: 'task' | 'milestone' | 'project';
  parent?: string;
}

// Advanced Time Tracking types
export interface TimeBreak {
  id: number;
  time_entry_id: number;
  start: string;
  end?: string;
  duration_minutes: number;
  type: 'lunch' | 'rest' | 'other';
}

export interface MultipleRate {
  id: number;
  name: string;
  rate: number;
  multiplier: number;
  applies_to: 'overtime' | 'weekend' | 'holiday' | 'night';
}

// Bulk Operations types
export interface BulkOperation {
  entity_type: string;
  entity_ids: number[];
  action: 'update' | 'delete' | 'approve' | 'sync';
  data?: object;
}

export interface BulkOperationResult {
  success_count: number;
  error_count: number;
  errors: { id: number; error: string }[];
}

// Search types
export interface GlobalSearchResult {
  type: string;
  id: number;
  title: string;
  subtitle?: string;
  url: string;
  highlight?: string;
}

export interface SavedFilter {
  id: number;
  name: string;
  entity_type: string;
  filters: object;
  is_default: boolean;
  created_at: string;
}

// Theme types
export interface ThemeSettings {
  mode: 'light' | 'dark' | 'system';
  primaryColor?: string;
  accentColor?: string;
  fontSize: 'small' | 'medium' | 'large';
  compactMode: boolean;
}

// Offline types
export interface OfflineQueueItem {
  id: string;
  action: string;
  endpoint: string;
  method: 'GET' | 'POST' | 'PUT' | 'DELETE';
  data?: object;
  timestamp: number;
  retries: number;
  status: 'pending' | 'syncing' | 'failed';
}

export interface OfflineState {
  isOnline: boolean;
  lastSyncAt?: string;
  queuedActions: number;
  syncInProgress: boolean;
}
