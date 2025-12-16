# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

This is a WordPress plugin called **ICT Platform** that provides a complete operations management system for ICT/electrical contracting businesses. It integrates with Zoho's suite of services (CRM, FSM, Books, People, Desk) for project management, time tracking, resource allocation, inventory management, and procurement with bidirectional synchronization.

## Technology Stack

- **Backend**: PHP 8.1+, WordPress 6.4+
- **Frontend**: React 18+, TypeScript, Redux Toolkit
- **Build**: Webpack 5, Babel
- **Database**: MySQL 5.7+ with 7 custom tables
- **Testing**: PHPUnit, Jest, React Testing Library
- **APIs**: WordPress REST API + Custom endpoints
- **Background Jobs**: WordPress Action Scheduler
- **Standards**: WordPress Coding Standards, ESLint, Prettier

## Key Architecture Concepts

### 1. Plugin Structure

The plugin follows WordPress best practices with separation of concerns:

- **Main Entry**: `ict-platform.php` - Plugin bootstrap and constants
- **Core System**: `includes/` - Core classes (Loader, Activator, Core, etc.)
- **Admin Area**: `admin/` - Backend admin functionality
- **Public Area**: `public/` - Frontend user-facing features
- **API Layer**: `api/` - REST endpoints and webhooks
- **React Apps**: `src/` - TypeScript React applications
- **Compiled Assets**: `assets/` - Built CSS/JS bundles

### 2. Autoloading System

The plugin uses a custom PSR-4-style autoloader (`class-ict-autoloader.php`) that maps class prefixes to directories:

- `ICT_Admin_*` → `admin/`
- `ICT_Public_*` → `public/`
- `ICT_API_*` → `api/`
- `ICT_Model_*` → `models/`
- `ICT_PostType_*` → `post-types/`
- `ICT_Taxonomy_*` → `taxonomies/`
- `ICT_Integration_*` → `integrations/`
- `ICT_Zoho_*` → `integrations/zoho/`
- `ICT_Sync_*` → `sync/`

Class files follow naming convention: `class-ict-{name}.php`

### 3. Database Schema

Seven custom tables (prefix: `wp_ict_`):

1. **ict_projects** - Project data synced with Zoho CRM deals
2. **ict_time_entries** - Time tracking synced with Zoho People
3. **ict_inventory_items** - Inventory synced with Zoho Books
4. **ict_purchase_orders** - PO workflow synced with Zoho Books
5. **ict_project_resources** - Resource allocation
6. **ict_sync_queue** - Pending sync operations
7. **ict_sync_log** - Sync history and debugging

Table constants defined in main plugin file (e.g., `ICT_PROJECTS_TABLE`).

### 4. Sync Architecture

Bidirectional sync handled by:

- **Queue System**: `ICT_Sync_Engine` processes `ict_sync_queue` table
- **Helper Methods**: `ICT_Helper::queue_sync()` adds items to queue
- **Cron Jobs**: WordPress cron runs sync every 15 minutes
- **Service Adapters**: Each Zoho service has dedicated adapter class
- **Logging**: All sync operations logged to `ict_sync_log` table

Rate limiting: 60 requests/minute per Zoho service.

### 5. User Roles & Capabilities

Custom roles created on activation:

- **ict_project_manager** - Full project and time management access
- **ict_technician** - Can clock in/out, view assigned projects
- **ict_inventory_manager** - Inventory and PO management

Capabilities follow pattern: `manage_ict_{feature}`, `edit_ict_{feature}`, etc.

### 6. React Architecture

- **Entry Points**: 5 webpack bundles configured:
  - `admin` - Admin dashboard (`src/admin/index.tsx`)
  - `public` - Public-facing components (`src/public/index.tsx`)
  - `time-tracker` - Standalone time tracking app (`src/apps/time-tracker/index.tsx`)
  - `project-dashboard` - Project management app (`src/apps/project-dashboard/index.tsx`)
  - `inventory-manager` - Inventory management app (`src/apps/inventory-manager/index.tsx`)
- **State Management**: Redux Toolkit with 8 slices:
  - `projectsSlice` - Project CRUD and filtering
  - `timeEntriesSlice` - Time entry management
  - `inventorySlice` - Inventory items and stock levels
  - `purchaseOrdersSlice` - PO workflow management
  - `resourcesSlice` - Resource allocation
  - `reportsSlice` - Report generation
  - `syncSlice` - Sync status and queue
  - `uiSlice` - UI state (modals, loading, notifications)
- **Typed Hooks**: `src/store/hooks.ts` exports `useAppDispatch` and `useAppSelector`
- **API Layer**: Axios services in `src/services/`
- **Component Structure**: Shared components in `src/components/`
- **Path Aliases**: `@components`, `@hooks`, `@services`, `@utils`, `@types`

## Development Commands

### Build & Development

```bash
# Development build with watch
npm run dev

# Production build
npm run build

# Install dependencies
npm install
composer install
```

### Code Quality

```bash
# Lint JavaScript/TypeScript
npm run lint
npm run lint:fix

# Format code
npm run format

# Check PHP standards
composer phpcs

# Fix PHP standards
composer phpcbf
```

### Testing

```bash
# Run JavaScript tests
npm test
npm run test:watch
npm run test:coverage

# Run PHP tests
composer test

# Type checking
npm run type-check
```

## Common Development Tasks

### Adding a New Zoho Service Adapter

1. Create `includes/integrations/zoho/class-ict-zoho-{service}-adapter.php`
2. Implement methods: `authenticate()`, `create()`, `update()`, `delete()`, `test_connection()`
3. Add OAuth settings in `class-ict-admin-settings.php`
4. Register in integration manager

### Adding a New REST Endpoint

1. Add route in `api/class-ict-api.php` in appropriate `register_*_routes()` method
2. Create handler method following naming convention
3. Add permission callback
4. Create controller class in `api/rest/` for complex logic

### Creating a Custom Post Type

1. Create class in `includes/post-types/class-ict-posttype-{name}.php`
2. Extend pattern from `class-ict-posttype-project.php`
3. Register in `class-ict-core.php` `define_admin_hooks()` method

### Adding a Database Migration

1. Update `class-ict-activator.php` `create_tables()` method
2. Add new table SQL
3. Define table constant in `ict-platform.php`
4. Increment DB version option

### Creating a React Component

1. Add component file in appropriate `src/` subdirectory
2. Use TypeScript interfaces for props
3. Export from nearest `index.ts`
4. Add tests in `__tests__/` directory

## Important Patterns

### Enqueueing Assets

Assets only load on relevant admin pages (checked via `is_ict_admin_page()`) or public pages with shortcodes/templates.

### Sync Queue Pattern

```php
// Add item to sync queue
ICT_Helper::queue_sync( array(
    'entity_type'  => 'project',
    'entity_id'    => $id,
    'action'       => 'update', // create, update, delete
    'zoho_service' => 'crm',    // crm, fsm, books, people, desk
    'priority'     => 5,         // 1-10, lower is higher priority
    'payload'      => $data,
) );
```

### Logging Sync Operations

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

### Helper Utilities

- `ICT_Helper::format_currency()` - Format money values
- `ICT_Helper::calculate_hours()` - Time calculations
- `ICT_Helper::round_time()` - Round to nearest interval
- `ICT_Helper::is_overtime()` - Check overtime status
- `ICT_Helper::generate_project_number()` - Auto-generate project numbers
- `ICT_Helper::sanitize_coordinates()` - GPS validation

## Security Notes

- All Zoho credentials encrypted using OpenSSL AES-256-CBC
- Decryption via `ICT_Admin_Settings::decrypt()`
- REST API uses WordPress nonce verification
- All database inputs sanitized, outputs escaped
- Capabilities checked for all privileged operations

## Testing Approach

- **PHP**: Unit tests for helpers and utility functions
- **React**: Component tests with React Testing Library
- **Integration**: Test Zoho sync flows end-to-end
- **Coverage Target**: 70%+ for all metrics

## Deployment Process

1. Run `npm run build` for production assets
2. Ensure all tests pass
3. Update version in `ict-platform.php` and `package.json`
4. Create zip excluding `node_modules/`, `src/`, `tests/`, `.git/`
5. Test activation in clean WordPress environment

## Known Considerations

- Sync queue processes maximum 20 items per cron run (prevent timeout)
- Failed sync items retry up to 3 times before marking as failed
- Sync logs older than 30 days automatically cleaned up
- OAuth tokens auto-refresh when expired
- Rate limiting enforced per Zoho service (60 req/min)
- PWA features enabled for offline time tracking
- GPS tracking optional, can be disabled in settings

## File Locations Reference

- **Main plugin file**: `ict-platform.php`
- **Activation logic**: `includes/class-ict-activator.php`
- **Core bootstrap**: `includes/class-ict-core.php`
- **Admin menu**: `admin/class-ict-admin-menu.php`
- **Settings**: `admin/class-ict-admin-settings.php`
- **REST API**: `api/class-ict-api.php`
- **Sync engine**: `includes/sync/class-ict-sync-engine.php`
- **Integration manager**: `includes/integrations/class-ict-integration-manager.php`
- **Helper utilities**: `includes/class-ict-helper.php`
- **Webpack config**: `webpack.config.js`
- **TypeScript config**: `tsconfig.json`

## Implemented Features

### Zoho Integration Adapters (5 complete)
- `class-ict-zoho-crm-adapter.php` - CRM deals and contacts sync
- `class-ict-zoho-fsm-adapter.php` - Field service management
- `class-ict-zoho-books-adapter.php` - Accounting and invoicing
- `class-ict-zoho-people-adapter.php` - HR and time tracking
- `class-ict-zoho-desk-adapter.php` - Support tickets

### Additional Integrations
- `class-ict-quotewerks-adapter.php` - Quote management
- `class-ict-microsoft-teams-adapter.php` - Teams notifications

### Enterprise Feature Modules (45+ implemented in `includes/features/`)
- **Project Management**: Project templates, milestones, recurring tasks
- **Time & Attendance**: Extended time tracking, GPS tracking, biometric auth
- **Financial**: Budget tracker, profitability analysis, multi-currency, invoice generator
- **Inventory**: Inventory alerts, equipment manager, supplier manager
- **Communication**: Messaging, push notifications, announcements, email templates
- **Reporting**: KPI tracker, report builder, advanced reporting, data export
- **Security**: Two-factor auth, audit log, API rate limiter, advanced role manager
- **Customer**: Client portal, satisfaction surveys, SLA tracking, warranty tracker
- **Operations**: Resource scheduler, PO approval workflow, calendar sync
- **System**: System health monitoring, webhook manager, import wizard, global search, notification center, custom field builder, offline manager, document manager, activity feed, dashboard widgets, certification tracker, field media capture

## Next Development Phases

Phases 1-7 are substantially complete. Remaining work:

1. **Testing**: Add Jest unit tests for React components
2. **PHP Tests**: Add PHPUnit tests for feature modules
3. **Documentation**: API documentation and user guides
4. **Performance**: Optimize bundle sizes and lazy loading
5. **Mobile**: PWA enhancements for field technicians
