/**
 * Common Components Index
 *
 * @package ICT_Platform
 * @since   1.0.0
 */

// Error handling
export { ErrorBoundary } from './ErrorBoundary';

// Notifications
export { ToastContainer, toast } from './Toast';
export { NotificationCenter, useNotifications } from './NotificationCenter';
export type { Notification } from './NotificationCenter';

// Dialogs
export { ConfirmDialog, useConfirm } from './ConfirmDialog';
export { ConflictResolution, ConflictResolutionModal } from './ConflictResolution';

// Icons
export { Icon, StatusIcon } from './Icon';
export type { IconName } from './Icon';

// Loading states
export {
  Skeleton,
  TableSkeleton,
  TableRowSkeleton,
  CardSkeleton,
  StatCardSkeleton,
  ListItemSkeleton,
  FormFieldSkeleton,
  FormSkeleton,
  ChartSkeleton,
  ProjectListSkeleton,
  DashboardSkeleton,
} from './Skeleton';

export {
  ProgressBar,
  StepIndicator,
  LoadingOverlay,
  Spinner,
  OperationProgress,
} from './ProgressIndicator';

// Empty states
export {
  EmptyState,
  NoProjectsEmpty,
  NoTimeEntriesEmpty,
  NoInventoryEmpty,
  NoResultsEmpty,
  ErrorEmpty,
  OfflineEmpty,
  NoPendingApprovalsEmpty,
  NoAlertsEmpty,
} from './EmptyState';

// Tooltips
export { Tooltip, HelpTooltip } from './Tooltip';

// Form components
export {
  FormField,
  CurrencyField,
  useFormValidation,
  validationRules,
} from './FormField';

// Navigation
export { Pagination, usePagination } from './Pagination';

// Network status
export { OfflineBanner } from './OfflineBanner';

// Session management
export { SessionTimeoutWarning } from './SessionTimeoutWarning';
