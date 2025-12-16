/**
 * TypeScript Type Definitions
 *
 * @package ICT_Platform_Mobile
 * @since   1.0.0
 */

// User & Auth
export interface User {
  id: number;
  email: string;
  name: string;
  role: 'admin' | 'project_manager' | 'technician' | 'inventory_manager';
  avatar?: string;
  phone?: string;
}

export interface AuthState {
  user: User | null;
  token: string | null;
  isAuthenticated: boolean;
  isLoading: boolean;
}

// Projects
export interface Project {
  id: number;
  project_number: string;
  name: string;
  client_name: string;
  status: 'planning' | 'active' | 'on_hold' | 'completed' | 'cancelled';
  priority: 'low' | 'normal' | 'high' | 'urgent';
  start_date: string;
  end_date: string;
  budget: number;
  address?: string;
  latitude?: number;
  longitude?: number;
  description?: string;
  zoho_id?: string;
}

// Time Entries
export interface TimeEntry {
  id: number;
  project_id: number;
  user_id: number;
  start_time: string;
  end_time?: string;
  breaks: TimeBreak[];
  notes?: string;
  hourly_rate: number;
  status: 'active' | 'paused' | 'completed';
  latitude?: number;
  longitude?: number;
  synced: boolean;
}

export interface TimeBreak {
  id: string;
  start_time: string;
  end_time?: string;
  type: 'short' | 'lunch' | 'other';
}

// Inventory
export interface InventoryItem {
  id: number;
  sku: string;
  name: string;
  description?: string;
  category: string;
  quantity: number;
  unit: string;
  unit_cost: number;
  reorder_point: number;
  location?: string;
  barcode?: string;
  image_url?: string;
}

// Equipment
export interface Equipment {
  id: number;
  name: string;
  serial_number: string;
  category: string;
  status: 'available' | 'in_use' | 'maintenance' | 'retired';
  assigned_to?: number;
  assigned_project?: number;
  last_maintenance?: string;
  next_maintenance?: string;
  qr_code?: string;
}

// Fleet
export interface Vehicle {
  id: number;
  name: string;
  plate_number: string;
  vin?: string;
  make: string;
  model: string;
  year: number;
  status: 'available' | 'in_use' | 'maintenance' | 'out_of_service';
  current_driver?: number;
  mileage: number;
  fuel_type: string;
  last_service?: string;
  next_service?: string;
}

export interface VehicleLocation {
  vehicle_id: number;
  latitude: number;
  longitude: number;
  speed?: number;
  heading?: number;
  timestamp: string;
}

// Documents
export interface Document {
  id: number;
  name: string;
  type: 'permit' | 'contract' | 'invoice' | 'report' | 'photo' | 'other';
  project_id?: number;
  file_url: string;
  file_size: number;
  mime_type: string;
  uploaded_by: number;
  created_at: string;
}

// Expenses
export interface Expense {
  id: number;
  project_id?: number;
  user_id: number;
  category: string;
  amount: number;
  description: string;
  receipt_url?: string;
  status: 'pending' | 'approved' | 'rejected' | 'reimbursed';
  date: string;
  synced: boolean;
}

// Notifications
export interface AppNotification {
  id: number;
  type: string;
  title: string;
  message: string;
  data?: Record<string, any>;
  read: boolean;
  created_at: string;
}

// Offline Queue
export interface OfflineQueueItem {
  id: string;
  action: string;
  endpoint: string;
  method: 'GET' | 'POST' | 'PUT' | 'DELETE';
  data?: any;
  timestamp: number;
  retries: number;
  status: 'pending' | 'syncing' | 'failed';
}

// API Response
export interface ApiResponse<T> {
  success: boolean;
  data: T;
  message?: string;
  meta?: {
    total: number;
    page: number;
    per_page: number;
  };
}

// Navigation Types
export type RootStackParamList = {
  Auth: undefined;
  Main: undefined;
};

export type AuthStackParamList = {
  Login: undefined;
  ForgotPassword: undefined;
};

export type MainTabParamList = {
  Dashboard: undefined;
  Projects: undefined;
  TimeTracking: undefined;
  Inventory: undefined;
  More: undefined;
};

export type ProjectStackParamList = {
  ProjectList: undefined;
  ProjectDetail: { projectId: number };
  ProjectDocuments: { projectId: number };
  ProjectTimeEntries: { projectId: number };
};

export type InventoryStackParamList = {
  InventoryList: undefined;
  InventoryDetail: { itemId: number };
  BarcodeScanner: undefined;
};

export type MoreStackParamList = {
  MoreMenu: undefined;
  Equipment: undefined;
  EquipmentDetail: { equipmentId: number };
  Fleet: undefined;
  VehicleDetail: { vehicleId: number };
  Expenses: undefined;
  ExpenseDetail: { expenseId: number };
  Settings: undefined;
  Profile: undefined;
};
