# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

**ICT Platform** is a WordPress plugin (v2.0.0) providing a complete operations management system for ICT/electrical contracting businesses. It integrates with Zoho's suite of services (CRM, FSM, Books, People, Desk) for project management, time tracking, resource allocation, inventory management, and procurement with bidirectional synchronization.

The repository contains two projects:
- **`wp-ict-platform/`** - The main WordPress plugin
- **`ict-mobile-app/`** - A React Native (Expo) mobile companion app for field technicians

## Technology Stack

- **Backend**: PHP 8.1+ (strict types), WordPress 6.4+
- **Frontend**: React 18, TypeScript 5.2, Redux Toolkit 1.9
- **Mobile**: React Native / Expo with EAS Build
- **Build**: Webpack 5, Babel, ts-loader
- **Database**: MySQL 5.7+ with 20 custom tables
- **Testing**: PHPUnit 10, Jest 29, React Testing Library 14
- **APIs**: WordPress REST API (namespace `ict/v1`) + Custom endpoints
- **Background Jobs**: WordPress cron (Action Scheduler)
- **PWA**: Workbox 7 with service worker caching
- **Standards**: WordPress Coding Standards (PHPCS 3), ESLint 8, Prettier 3

## Repository Structure

```
ict-platform/                          # Repository root
├── CLAUDE.md                          # This file
├── main.yml                           # GitHub Actions CI/CD pipeline
├── wp-ict-platform/                   # Main WordPress plugin (all dev work here)
│   ├── ict-platform.php               # Plugin bootstrap, constants, autoloaders
│   ├── package.json                   # npm dependencies & scripts
│   ├── composer.json                  # PHP dependencies & scripts
│   ├── webpack.config.js              # 5 entry points, PWA, code splitting
│   ├── tsconfig.json                  # Strict TypeScript config
│   ├── jest.config.js                 # Jest with ts-jest, 70% coverage threshold
│   ├── .eslintrc.json                 # ESLint + TypeScript + React + Prettier
│   ├── .prettierrc.json               # Single quotes, 100 char width, semicolons
│   ├── phpcs.xml                      # WordPress coding standards
│   ├── manifest.json                  # PWA manifest
│   ├── admin/                         # Admin panel PHP classes
│   ├── api/                           # Legacy REST controllers & webhooks
│   ├── assets/                        # Compiled CSS/JS bundles (build output)
│   ├── docs/                          # OpenAPI spec, install & dev guides
│   ├── includes/                      # Core PHP classes (legacy architecture)
│   ├── public/                        # Public-facing PHP class
│   └── src/                           # React/TS source + PSR-4 PHP namespaced classes
└── ict-mobile-app/                    # React Native companion app
    ├── App.tsx                        # App entry point
    ├── app.json                       # Expo config
    ├── eas.json                       # EAS Build config
    └── src/                           # Screens, navigation, store, services
```

## Dual Architecture (Legacy + PSR-4)

The plugin runs two architectures simultaneously for gradual migration:

### Legacy Architecture (v1.x)
- **Bootstrap**: `ICT_Core` class in `includes/class-ict-core.php`
- **Autoloader**: `ICT_Autoloader` in `includes/class-ict-autoloader.php`
- **Convention**: Classes prefixed with `ICT_`, files named `class-ict-{name}.php`
- **Location**: `includes/`, `admin/`, `api/`, `public/`
- **Class prefix mapping**:
  - `ICT_Admin_*` → `admin/`
  - `ICT_Public_*` → `public/`
  - `ICT_API_*` → `api/`
  - `ICT_REST_*` → `api/rest/`
  - `ICT_Webhook_*` → `api/webhooks/`
  - `ICT_Model_*` → `models/`
  - `ICT_PostType_*` → `includes/post-types/`
  - `ICT_Taxonomy_*` → `includes/taxonomies/`
  - `ICT_Integration_*` → `includes/integrations/`
  - `ICT_Zoho_*` → `includes/integrations/zoho/`
  - `ICT_Sync_*` → `includes/sync/`
  - `ICT_Database_*` → `database/`

### PSR-4 Namespaced Architecture (v2.0)
- **Bootstrap**: `ICT_Platform\Core\Application` in `src/Core/Application.php`
- **Autoloader**: Composer PSR-4 (`ICT_Platform\` → `src/`)
- **DI Container**: `ICT_Platform\Container\Container` (PSR-11 compliant)
- **API Router**: `ICT_Platform\Api\Router` with 18 controllers in `src/Api/Controllers/`
- **Utilities**: `src/Util/Helper.php`, `src/Util/Cache.php`, `src/Util/SyncLogger.php`
- **Access**: `ict_platform()` global function returns the Application instance

Both systems are loaded in `ict-platform.php:run_ict_platform()`.

## Database Schema (20 Tables)

All tables use prefix `wp_ict_`. Constants defined in `ict-platform.php`.

### Core Tables (v1.0)
| Constant | Table | Purpose |
|----------|-------|---------|
| `ICT_PROJECTS_TABLE` | `ict_projects` | Projects synced with Zoho CRM deals |
| `ICT_TIME_ENTRIES_TABLE` | `ict_time_entries` | Time tracking synced with Zoho People |
| `ICT_INVENTORY_ITEMS_TABLE` | `ict_inventory_items` | Inventory synced with Zoho Books |
| `ICT_PURCHASE_ORDERS_TABLE` | `ict_purchase_orders` | PO workflow synced with Zoho Books |
| `ICT_PROJECT_RESOURCES_TABLE` | `ict_project_resources` | Resource allocation |
| `ICT_SYNC_QUEUE_TABLE` | `ict_sync_queue` | Pending sync operations |
| `ICT_SYNC_LOG_TABLE` | `ict_sync_log` | Sync history and debugging |

### Extended Tables (v2.1)
| Constant | Table | Purpose |
|----------|-------|---------|
| `ICT_DOCUMENTS_TABLE` | `ict_documents` | Document management |
| `ICT_EQUIPMENT_TABLE` | `ict_equipment` | Equipment tracking |
| `ICT_EXPENSES_TABLE` | `ict_expenses` | Expense records |
| `ICT_SIGNATURES_TABLE` | `ict_signatures` | Digital signatures |
| `ICT_VOICE_NOTES_TABLE` | `ict_voice_notes` | Voice notes |
| `ICT_ACTIVITY_LOG_TABLE` | `ict_activity_log` | Activity tracking |
| `ICT_FLEET_TABLE` | `ict_fleet` | Fleet management |
| `ICT_FLEET_LOCATIONS_TABLE` | `ict_fleet_locations` | Fleet GPS locations |
| `ICT_NOTIFICATIONS_TABLE` | `ict_notifications` | Notification storage |
| `ICT_QR_CODES_TABLE` | `ict_qr_codes` | QR code data |

## Sync Architecture

Bidirectional sync with all 5 Zoho services:

- **Queue System**: `ICT_Sync_Engine` + `ICT_Sync_Queue_Processor` + `ICT_Sync_Orchestrator` (in `includes/sync/`)
- **Helper Methods**: `ICT_Helper::queue_sync()` adds items to `ict_sync_queue`
- **Cron Jobs**: WordPress cron runs sync every 15 minutes
- **Rate Limiting**: `ICT_Zoho_Rate_Limiter` enforces 60 req/min per service
- **Token Management**: `ICT_Zoho_Token_Manager` handles OAuth auto-refresh
- **API Client**: `ICT_Zoho_API_Client` is the base HTTP client for all adapters
- **Logging**: All operations logged to `ict_sync_log` table
- **Constraints**: Max 20 items/cron run, 3 retries before failure, 30-day log retention

### Sync Queue Pattern

```php
ICT_Helper::queue_sync( array(
    'entity_type'  => 'project',
    'entity_id'    => $id,
    'action'       => 'update', // create, update, delete
    'zoho_service' => 'crm',    // crm, fsm, books, people, desk
    'priority'     => 5,         // 1-10, lower is higher priority
    'payload'      => $data,
) );
```

### Sync Logging Pattern

```php
ICT_Helper::log_sync( array(
    'entity_type'   => 'project',
    'entity_id'     => $id,
    'direction'     => 'outbound', // outbound or inbound
    'zoho_service'  => 'crm',
    'action'        => 'update',
    'status'        => 'success',  // success or error
    'request_data'  => $request,
    'response_data' => $response,
    'error_message' => null,
    'duration_ms'   => $duration,
) );
```

## React/TypeScript Frontend

### Entry Points (5 Webpack Bundles)

| Bundle | Entry | Purpose |
|--------|-------|---------|
| `admin` | `src/admin/index.tsx` | Admin dashboard |
| `public` | `src/public/index.tsx` | Public-facing components |
| `time-tracker` | `src/apps/time-tracker/index.tsx` | Standalone time tracking |
| `project-dashboard` | `src/apps/project-dashboard/index.tsx` | Project management |
| `inventory-manager` | `src/apps/inventory-manager/index.tsx` | Inventory management |

Output: `assets/js/dist/[name].bundle.js` with vendor/common chunk splitting.

### Redux Store (`src/store/`)

8 slices in `src/store/slices/`:

- `projectsSlice` - Project CRUD and filtering
- `timeEntriesSlice` - Time entry management
- `inventorySlice` - Inventory items and stock levels
- `purchaseOrdersSlice` - PO workflow management
- `resourcesSlice` - Resource allocation
- `reportsSlice` - Report generation
- `syncSlice` - Sync status and queue
- `uiSlice` - UI state (modals, loading, notifications)

Typed hooks in `src/store/hooks.ts`: `useAppDispatch`, `useAppSelector`.

### Component Structure (`src/components/`)

```
components/
├── charts/           # BarChart, LineChart, PieChart (Chart.js)
├── common/           # 14 shared components + index.ts + __tests__/
├── improvements/     # 10 enhancement components (Gantt, DarkMode, etc.)
├── inventory/        # InventoryDashboard, LowStockAlerts, PurchaseOrderForm, StockAdjustment
├── projects/         # ProjectDashboard, ProjectForm, ProjectList, ProjectStats
├── reports/          # ReportsDashboard
├── resources/        # AvailabilityMatrix, ResourceAllocation, ResourceCalendar, SkillMatrix
└── time/             # TimeClock, TimeTracker, TimesheetApproval, TimesheetList
```

### Custom Hooks (`src/hooks/`)

- `useAppDispatch` - Typed Redux dispatch
- `useAutoSave` - Auto-save functionality
- `useDebounce` - Input debouncing
- `useOnlineStatus` - Online/offline detection
- `useSessionTimeout` - Session timeout warnings
- `useUnsavedChanges` - Unsaved changes detection

### Path Aliases (Webpack + TypeScript)

- `@` → `src/`
- `@components` → `src/components/`
- `@hooks` → `src/hooks/`
- `@services` → `src/services/`
- `@utils` → `src/utils/`
- `@types` → `src/types/`

Also mapped in `jest.config.js` for testing.

## REST API

### Legacy Controllers (`api/rest/`)

13 REST controllers following `ICT_REST_{Name}_Controller` pattern:
- Auth, Projects, Time Entries, Inventory, Purchase Orders
- Resources, Reports, Schedule, Health, Location
- Expenses, Files/Tasks

### PSR-4 Controllers (`src/Api/Controllers/`)

18 controllers extending `AbstractController`, registered via `Router`:
- Project, TimeEntry, Inventory, PurchaseOrder, Resource, Report, Sync
- Document, Equipment, Expense, Signature, VoiceNote
- Fleet, Notification, QrCode, Activity, Weather, ClientPortal

### Webhooks (`api/webhooks/`)

- `ICT_Webhook_Receiver` - Generic inbound webhook handler
- `ICT_Quotewerks_Webhook` - QuoteWerks-specific webhooks

### API Namespace

All endpoints registered under `ict/v1` (e.g., `/wp-json/ict/v1/projects`).

OpenAPI spec: `docs/openapi.yaml`.

## Admin System (`admin/`)

- `ICT_Admin` - Main admin class, asset enqueueing
- `ICT_Admin_Menu` - WordPress admin menu registration
- `ICT_Admin_Settings` - Settings pages, OAuth config, credential encryption/decryption
- `ICT_Setup_Wizard` - Initial plugin setup wizard (with CSS/JS in `admin/css/`, `admin/js/`)
- `ICT_AI_Setup_Assistant` - AI-powered setup assistance

Assets only load on relevant admin pages (checked via `is_ict_admin_page()`).

## Integrations

### Zoho Service Adapters (`includes/integrations/zoho/`)

| Adapter | Zoho Service | Syncs With |
|---------|-------------|------------|
| `ICT_Zoho_CRM_Adapter` | CRM | Deals ↔ Projects |
| `ICT_Zoho_FSM_Adapter` | Field Service | Work orders |
| `ICT_Zoho_Books_Adapter` | Books | Accounting, invoicing |
| `ICT_Zoho_People_Adapter` | People | HR, timesheets |
| `ICT_Zoho_Desk_Adapter` | Desk | Support tickets |

Supporting classes: `ICT_Zoho_API_Client`, `ICT_Zoho_Token_Manager`, `ICT_Zoho_Rate_Limiter`.

### Third-Party Integrations

- `ICT_Quotewerks_Adapter` - QuoteWerks quote management
- `ICT_Microsoft_Teams_Adapter` - Microsoft Teams notifications
- `ICT_Twilio_SMS_Adapter` (`includes/notifications/`) - SMS via Twilio

### Notification System (`includes/notifications/`)

- `ICT_Notification_Manager` - Orchestrator for all notification channels
- `ICT_Email_Notification` - Email delivery
- `ICT_Push_Notification` - Push notifications (PWA)
- `ICT_Twilio_SMS_Adapter` - SMS notifications

## Enterprise Feature Modules (`includes/features/`)

40 feature modules organized by domain:

### Project Management
- `class-ict-project-templates.php` - Project templates
- `class-ict-project-milestones.php` - Milestone tracking
- `class-ict-recurring-tasks.php` - Recurring task automation
- `class-ict-quote-builder.php` - Quote creation

### Time & Attendance
- `class-ict-time-tracking-extended.php` - Advanced time tracking
- `class-ict-gps-tracking.php` - GPS location tracking

### Financial
- `class-ict-budget-tracker.php` - Budget management
- `class-ict-profitability.php` - Profitability analysis
- `class-ict-multi-currency.php` - Multi-currency support
- `class-ict-invoice-generator.php` - Invoice generation

### Inventory & Procurement
- `class-ict-inventory-alerts.php` - Low stock alerts
- `class-ict-equipment-manager.php` - Equipment lifecycle
- `class-ict-supplier-manager.php` - Supplier management
- `class-ict-po-approval.php` - PO approval workflow

### Communication
- `class-ict-messaging.php` - Internal messaging
- `class-ict-push-notifications.php` - Push notifications
- `class-ict-announcements.php` - Company announcements
- `class-ict-email-templates.php` - Email template management

### Reporting & Analytics
- `class-ict-kpi-tracker.php` - KPI dashboard
- `class-ict-report-builder.php` - Custom report builder
- `class-ict-data-export.php` - Data export (CSV, PDF)

### Security
- `class-ict-two-factor-auth.php` - 2FA authentication
- `class-ict-audit-log.php` - Audit trail
- `class-ict-api-rate-limiter.php` - API rate limiting
- `class-ict-webhook-manager.php` - Webhook management

### Customer Operations
- `class-ict-client-portal.php` - Client-facing portal
- `class-ict-satisfaction-surveys.php` - Customer surveys
- `class-ict-sla-tracking.php` - SLA compliance tracking
- `class-ict-warranty-tracker.php` - Warranty management

### Operations
- `class-ict-resource-scheduler.php` - Resource scheduling
- `class-ict-calendar-sync.php` - Calendar synchronization

### System Infrastructure
- `class-ict-system-health.php` - System health monitoring
- `class-ict-import-wizard.php` - Data import wizard
- `class-ict-global-search.php` - Global search
- `class-ict-notification-center.php` - Notification center
- `class-ict-dashboard-widgets.php` - Dashboard widgets
- `class-ict-document-manager.php` - Document management
- `class-ict-field-media.php` - Field media capture
- `class-ict-activity-feed.php` - Activity feed
- `class-ict-certification-tracker.php` - Certification tracking

### Additional Core Modules (in `includes/` root)
- `class-ict-biometric-auth.php` - Biometric authentication
- `class-ict-custom-field-builder.php` - Custom field creation
- `class-ict-data-validator.php` - Data validation
- `class-ict-advanced-reporting.php` - Advanced reporting engine
- `class-ict-advanced-role-manager.php` - Role & capability management
- `class-ict-offline-manager.php` - Offline functionality

## Workflow Engine (`includes/workflow/`)

- `ICT_Nexus_Workflow` - Workflow definition and configuration
- `ICT_Nexus_Workflow_Engine` - Workflow execution engine

## User Roles & Capabilities

Custom roles created on activation:

- **ict_project_manager** - Full project and time management access
- **ict_technician** - Can clock in/out, view assigned projects
- **ict_inventory_manager** - Inventory and PO management

Capabilities follow pattern: `manage_ict_{feature}`, `edit_ict_{feature}`, etc.

## Mobile App (`ict-mobile-app/`)

React Native (Expo) companion app for field technicians.

### Structure
- **Navigation**: `src/navigation/` - RootNavigator, AuthNavigator, MainNavigator, ProjectsNavigator, InventoryNavigator, MoreNavigator
- **Screens**: `src/screens/` - Dashboard, TimeTracking, auth screens, project screens, inventory screens (with barcode scanner), settings/profile
- **State**: Redux Toolkit with slices for auth, projects, timeEntries, inventory, notifications, offline
- **Context**: Auth, Offline, Theme contexts in `src/context/`
- **API**: `src/services/api.ts` - API client connecting to WordPress REST endpoints
- **Build**: EAS Build with Fastlane for iOS deployment

## Development Commands

All commands run from `wp-ict-platform/` directory:

### Build & Development
```bash
npm install              # Install JS dependencies
composer install         # Install PHP dependencies
npm run dev              # Development build with watch
npm run build            # Production build (includes Workbox SW)
npm run build:dev        # Development build (no watch)
```

### Code Quality
```bash
npm run lint             # ESLint check
npm run lint:fix         # ESLint auto-fix
npm run format           # Prettier format
npm run type-check       # TypeScript strict checking
composer phpcs           # PHP CodeSniffer (WordPress standard)
composer phpcbf          # PHP CodeSniffer auto-fix
```

### Testing
```bash
npm test                 # Run Jest tests
npm run test:watch       # Jest watch mode
npm run test:coverage    # Jest with coverage report (70% threshold)
composer test            # Run PHPUnit tests
```

## Code Style Conventions

### PHP
- WordPress Coding Standards via PHPCS
- `declare(strict_types=1)` in PSR-4 classes
- File naming: `class-ict-{name}.php` (legacy), PSR-4 standard (new)
- Excluded PHPCS rules: `WordPress.Files.FileName`, `WordPress.NamingConventions.ValidVariableName`, `WordPress.NamingConventions.ValidFunctionName`

### TypeScript/React
- Strict mode enabled (`noUnusedLocals`, `noUnusedParameters`, `noImplicitReturns`)
- Single quotes, semicolons, 100-char line width, 2-space indentation
- Trailing commas (ES5 style)
- `react-in-jsx-scope` rule disabled (React 18 JSX transform)
- `@typescript-eslint/no-explicit-any` set to warn
- Unused vars with `_` prefix are allowed

## Common Development Tasks

### Adding a New Zoho Service Adapter

1. Create `includes/integrations/zoho/class-ict-zoho-{service}-adapter.php`
2. Implement methods: `authenticate()`, `create()`, `update()`, `delete()`, `test_connection()`
3. Add OAuth settings in `admin/class-ict-admin-settings.php`
4. Register in `includes/integrations/class-ict-integration-manager.php`

### Adding a REST Endpoint (Legacy)

1. Add route in `api/class-ict-api.php` in appropriate `register_*_routes()` method
2. Create handler method following naming convention
3. Add permission callback
4. Create controller class in `api/rest/` for complex logic

### Adding a REST Endpoint (PSR-4)

1. Create controller in `src/Api/Controllers/` extending `AbstractController`
2. Register in `src/Api/Router.php` controllers array
3. Container will auto-resolve dependencies

### Adding a Database Migration

1. Update `includes/class-ict-activator.php` `create_tables()` method
2. Add new table SQL using `$wpdb->prefix`
3. Define table constant in `ict-platform.php`
4. Increment DB version option

### Creating a React Component

1. Add component file in appropriate `src/components/` subdirectory
2. Use TypeScript interfaces for props
3. Export from nearest `index.ts`
4. Add tests in `__tests__/` directory alongside the component

### Adding a Feature Module

1. Create `includes/features/class-ict-{feature-name}.php`
2. Follow singleton or static initialization pattern
3. Register hooks in `ICT_Core::define_feature_hooks()`

## Helper Utilities

- `ICT_Helper::format_currency()` - Format money values
- `ICT_Helper::calculate_hours()` - Time calculations
- `ICT_Helper::round_time()` - Round to nearest interval
- `ICT_Helper::is_overtime()` - Check overtime status
- `ICT_Helper::generate_project_number()` - Auto-generate project numbers
- `ICT_Helper::sanitize_coordinates()` - GPS validation
- `ICT_Helper::queue_sync()` - Add items to sync queue
- `ICT_Helper::log_sync()` - Log sync operations

## Security Notes

- All Zoho credentials encrypted using OpenSSL AES-256-CBC
- Decryption via `ICT_Admin_Settings::decrypt()`
- REST API uses WordPress nonce verification
- All database inputs sanitized, outputs escaped
- Capabilities checked for all privileged operations
- Two-factor authentication support
- Comprehensive audit logging
- API rate limiting (60 req/min per Zoho service)
- Session timeout detection on frontend

## Testing Status

### Existing Tests
- **Jest**: 4 test files in `src/`:
  - `src/components/common/__tests__/ConfirmDialog.test.tsx`
  - `src/components/common/__tests__/ErrorBoundary.test.tsx`
  - `src/components/common/__tests__/Skeleton.test.tsx`
  - `src/hooks/__tests__/useDebounce.test.ts`
- **PHPUnit**: Configured but no test files yet

### Coverage Configuration
- Jest: 70% threshold for branches, functions, lines, and statements
- CSS/SCSS mocked via `identity-obj-proxy`

## Deployment Process

1. Run `npm run build` for production assets (generates service worker)
2. Ensure all tests pass (`npm test`, `composer test`)
3. Update version in `ict-platform.php` and `package.json`
4. Create zip excluding: `node_modules/`, `src/` (TypeScript source), `tests/`, `.git/`
5. Test activation in clean WordPress 6.4+ environment

## CI/CD

GitHub Actions pipeline (`main.yml`) runs on push/PR to `main`/`develop`:
- PHP 8.2 setup with WordPress extensions
- Composer install + PHP-CS-Fixer lint + PHPUnit
- Node.js 18 setup with npm ci
- ESLint + Prettier check + Jest tests + Production build

**Note**: The CI config references `./backend` and `./frontend` working directories which need to be updated to match the actual `./wp-ict-platform` directory structure.

## Key File Locations

| Purpose | Path (relative to `wp-ict-platform/`) |
|---------|---------------------------------------|
| Main plugin file | `ict-platform.php` |
| Legacy bootstrap | `includes/class-ict-core.php` |
| PSR-4 Application | `src/Core/Application.php` |
| DI Container | `src/Container/Container.php` |
| Legacy autoloader | `includes/class-ict-autoloader.php` |
| Plugin activation | `includes/class-ict-activator.php` |
| Admin menu | `admin/class-ict-admin-menu.php` |
| Admin settings | `admin/class-ict-admin-settings.php` |
| Legacy REST API | `api/class-ict-api.php` |
| PSR-4 API Router | `src/Api/Router.php` |
| Sync engine | `includes/sync/class-ict-sync-engine.php` |
| Integration manager | `includes/integrations/class-ict-integration-manager.php` |
| Helper utilities | `includes/class-ict-helper.php` |
| Redux store | `src/store/index.ts` |
| TypeScript types | `src/types/index.ts` |
| API service | `src/services/api.ts` |
| Webpack config | `webpack.config.js` |
| TypeScript config | `tsconfig.json` |
| OpenAPI spec | `docs/openapi.yaml` |
| PWA manifest | `manifest.json` |

## Known Considerations

- Sync queue processes maximum 20 items per cron run (prevent timeout)
- Failed sync items retry up to 3 times before marking as failed
- Sync logs older than 30 days automatically cleaned up
- OAuth tokens auto-refresh when expired
- Rate limiting enforced per Zoho service (60 req/min)
- PWA features enabled for offline time tracking
- GPS tracking optional, can be disabled in settings
- Assets only enqueued on relevant pages to minimize footprint
- Workbox service worker generated only in production builds

## Next Development Phases

Phases 1-7 are substantially complete. Remaining work:

1. **Testing**: Add Jest unit tests for remaining React components
2. **PHP Tests**: Add PHPUnit tests for feature modules
3. **CI/CD Fix**: Update `main.yml` working directory paths
4. **Performance**: Optimize bundle sizes and implement lazy loading
5. **Mobile**: Complete React Native app and PWA enhancements
