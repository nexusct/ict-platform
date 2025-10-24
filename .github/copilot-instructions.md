# GitHub Copilot Instructions for ICT Platform

## Project Overview

**ICT Platform** is a production-ready WordPress plugin providing comprehensive operations management for ICT/electrical contracting businesses. It includes complete Zoho suite integration (CRM, FSM, Books, People, Desk, WorkDrive) and QuoteWerks integration with bidirectional synchronization.

**Status**: Phases 1-7 Complete (100% core functionality) - Production Ready

## Repository Structure

```
ict-platform/
├── wp-ict-platform/              # WordPress Plugin (main codebase)
│   ├── ict-platform.php          # Plugin entry point
│   ├── includes/                 # PHP core classes
│   │   ├── class-ict-core.php
│   │   ├── class-ict-autoloader.php
│   │   ├── integrations/         # Zoho & QuoteWerks integrations
│   │   │   ├── zoho/            # 5 Zoho service adapters
│   │   │   └── class-ict-quotewerks-adapter.php
│   │   └── sync/                # Sync engine & orchestration
│   ├── admin/                    # WordPress admin functionality
│   ├── api/                      # REST API controllers
│   │   └── rest/                # REST endpoint handlers
│   ├── public/                   # Public-facing features
│   ├── src/                      # React/TypeScript frontend
│   │   ├── components/          # React components
│   │   ├── store/               # Redux Toolkit slices
│   │   ├── services/            # API service layer
│   │   ├── types/               # TypeScript definitions
│   │   ├── hooks/               # Custom React hooks
│   │   └── styles/              # SASS stylesheets
│   └── tests/                    # PHPUnit & Jest tests
├── ict-mobile-app/               # React Native mobile app (planned)
└── .github/                      # GitHub configuration
```

## Technology Stack

### Backend
- **PHP**: 8.1+ with PSR-4 autoloading
- **WordPress**: 6.4+ core framework
- **Database**: MySQL 5.7+ with 10 custom tables (prefix: `wp_ict_`)
- **Testing**: PHPUnit, WordPress Coding Standards (WPCS)
- **Background Jobs**: WordPress Action Scheduler

### Frontend
- **React**: 18.2 with TypeScript 5.2
- **State Management**: Redux Toolkit with RTK Query
- **Build Tools**: Webpack 5, Babel, SASS
- **UI Libraries**: FullCalendar, Chart.js, Framer Motion
- **Testing**: Jest, React Testing Library
- **Code Quality**: ESLint, Prettier, TypeScript strict mode

### Integrations
- **Zoho Services**: CRM, FSM, Books, People, Desk, WorkDrive (OAuth 2.0)
- **QuoteWerks**: Bidirectional sync via webhooks and REST API
- **Future**: Microsoft Teams messaging integration ready

## Critical Architecture Patterns

### 1. PSR-4 Autoloading

The plugin uses a custom autoloader (`class-ict-autoloader.php`) that maps class prefixes to directories:

```php
// Class naming convention
ICT_Admin_*           → admin/
ICT_Public_*          → public/
ICT_API_*             → api/
ICT_Model_*           → models/
ICT_PostType_*        → post-types/
ICT_Taxonomy_*        → taxonomies/
ICT_Integration_*     → integrations/
ICT_Zoho_*            → integrations/zoho/
ICT_QuoteWerks_*      → integrations/
ICT_Sync_*            → sync/
ICT_REST_*            → api/rest/

// File naming convention
class-ict-{name}.php
```

**Important**: Always follow this naming convention. New classes must match the pattern or update the autoloader.

### 2. Database Schema

10 custom tables with consistent structure:

| Table | Purpose | Key Fields |
|-------|---------|------------|
| `wp_ict_projects` | Project data synced with Zoho CRM | `zoho_crm_id`, `zoho_fsm_id`, `qw_document_id` |
| `wp_ict_time_entries` | Time tracking with GPS | `clock_in`, `clock_out`, `zoho_people_id` |
| `wp_ict_inventory_items` | Stock management | `zoho_books_id`, `qw_product_id` |
| `wp_ict_purchase_orders` | PO workflow | `zoho_books_id` |
| `wp_ict_project_resources` | Resource allocation | `technician_id`, `project_id` |
| `wp_ict_sync_queue` | Pending sync operations | `entity_type`, `action`, `status`, `attempts` |
| `wp_ict_sync_log` | Sync history & debugging | `direction`, `duration_ms`, `error_message` |
| `wp_ict_location_tracking` | GPS coordinates | `latitude`, `longitude`, `accuracy` |
| `wp_ict_expenses` | Expense submissions | `receipt_url`, `approval_status` |
| `wp_ict_tasks` | Task management | `project_id`, `assigned_to` |

**Table constants** are defined in `ict-platform.php` (e.g., `ICT_PROJECTS_TABLE`).

### 3. Sync Architecture - CRITICAL

**Bidirectional Synchronization Flow:**

```
QuoteWerks → WordPress → Zoho Services
     ↑           ↓          ↓
     └───────────┴──────────┘
         Webhook Updates
```

**Key Components:**

1. **Sync Queue System** (`wp_ict_sync_queue` table):
   - Priority-based queue (1-10, lower = higher priority)
   - Retry logic: max 3 attempts with exponential backoff
   - Status: pending → processing → completed/failed
   - Processes 20 items per cron run (prevent timeouts)

2. **Sync Engine** (`class-ict-sync-engine.php`):
   - Runs via WordPress cron every 15 minutes
   - Rate limiting: 60 requests/minute per Zoho service
   - Conflict resolution: Last-write-wins with manual override option

3. **Service Adapters**:
   - `ICT_Zoho_CRM_Adapter` - Deals ↔ Projects
   - `ICT_Zoho_FSM_Adapter` - Work Orders ↔ Tasks
   - `ICT_Zoho_Books_Adapter` - Items ↔ Inventory, POs
   - `ICT_Zoho_People_Adapter` - Timesheets ↔ Time Entries
   - `ICT_Zoho_Desk_Adapter` - Tickets ↔ Support
   - `ICT_QuoteWerks_Adapter` - Quotes ↔ Projects/Inventory

4. **Webhook Receiver** (`class-ict-webhook-receiver.php`):
   - HMAC-SHA256 signature verification
   - Real-time updates from QuoteWerks and Zoho
   - Automatic queue insertion for bidirectional sync

**Helper Methods:**

```php
// Add item to sync queue
ICT_Helper::queue_sync( array(
    'entity_type'  => 'project',        // project, time_entry, inventory_item, etc.
    'entity_id'    => $id,
    'action'       => 'update',         // create, update, delete
    'zoho_service' => 'crm',            // crm, fsm, books, people, desk
    'priority'     => 5,                // 1-10, lower is higher priority
    'payload'      => $data,
) );

// Log sync operation
ICT_Helper::log_sync( array(
    'entity_type'   => 'project',
    'entity_id'     => $id,
    'direction'     => 'outbound',      // outbound or inbound
    'zoho_service'  => 'crm',
    'action'        => 'update',
    'status'        => 'success',       // success or error
    'request_data'  => $request,
    'response_data' => $response,
    'error_message' => null,
    'duration_ms'   => $duration,
) );
```

### 4. Security - NON-NEGOTIABLE

1. **OAuth 2.0 Token Management**:
   - All Zoho credentials encrypted using OpenSSL AES-256-CBC
   - Encryption key: `NONCE_KEY` constant from `wp-config.php`
   - Auto-refresh tokens before expiration
   - Decrypt via `ICT_Admin_Settings::decrypt()`

2. **REST API Security**:
   - WordPress nonce verification for all POST/PUT/DELETE
   - JWT authentication for mobile app endpoints
   - Capability checks: `current_user_can('manage_ict_{feature}')`
   - Rate limiting on authentication endpoints

3. **Webhook Security**:
   - HMAC-SHA256 signature verification (QuoteWerks, Zoho)
   - Secret keys stored encrypted in wp_options
   - IP whitelist support (optional)

4. **Data Sanitization**:
   - Input: `sanitize_text_field()`, `absint()`, `sanitize_email()`
   - Output: `esc_html()`, `esc_attr()`, `esc_url()`
   - SQL: Always use `$wpdb->prepare()` for queries

**Never**:
- Hardcode credentials
- Skip nonce verification
- Trust user input
- Use direct SQL without preparation
- Store sensitive data unencrypted

### 5. React/TypeScript Architecture

**Path Aliases** (defined in `tsconfig.json` and `webpack.config.js`):
```typescript
@components  → src/components/
@hooks       → src/hooks/
@services    → src/services/
@store       → src/store/
@types       → src/types/
@utils       → src/utils/
```

**Type Definitions** (`src/types/index.ts`):
- 300+ lines of comprehensive TypeScript interfaces
- All API responses typed
- Redux state types
- Component prop interfaces

**Redux Toolkit Slices**:
```typescript
src/store/slices/
├── projectsSlice.ts           // Projects CRUD with RTK Query
├── timeEntriesSlice.ts        // Time tracking
├── inventorySlice.ts          // Inventory management
├── purchaseOrdersSlice.ts     // PO workflow
├── resourcesSlice.ts          // Resource allocation
└── uiSlice.ts                 // Global UI state
```

**Component Structure**:
```
src/components/
├── projects/                  // Project management
├── time/                     // Time tracking
├── resources/                // Resource management
├── inventory/                // Inventory & PO
├── reports/                  // Dashboards & analytics
└── shared/                   // Reusable components
```

**API Service Layer** (`src/services/api.ts`):
- Axios instance with interceptors
- JWT token auto-refresh
- Error handling with user-friendly messages
- Base URL from WordPress localized script

### 6. User Roles & Capabilities

Custom roles created on plugin activation:

```php
ict_project_manager:
  - manage_ict_projects
  - manage_ict_time_entries
  - manage_ict_resources
  - view_ict_reports
  - edit_others_ict_projects

ict_technician:
  - edit_ict_time_entries (own only)
  - view_ict_projects (assigned only)
  - submit_ict_expenses

ict_inventory_manager:
  - manage_ict_inventory
  - manage_ict_purchase_orders
  - view_ict_inventory_reports
```

**Capability checks** must be performed in:
- REST API endpoints: `permission_callback`
- Admin pages: `current_user_can()`
- Frontend components: Conditionally render based on user meta

## Development Workflows

### Build & Development

```bash
cd wp-ict-platform

# Install dependencies
npm install
composer install

# Development build with hot reload
npm run dev

# Production build (minified, optimized)
npm run build

# Type checking
npm run type-check
```

### Code Quality & Linting

```bash
# JavaScript/TypeScript
npm run lint              # Check for errors
npm run lint:fix          # Auto-fix issues
npm run format            # Prettier formatting

# PHP
composer phpcs            # Check WordPress Coding Standards
composer phpcbf           # Auto-fix PHP issues
```

### Testing

```bash
# JavaScript tests
npm test                  # Run Jest tests
npm run test:watch        # Watch mode
npm run test:coverage     # Coverage report

# PHP tests
composer test             # Run PHPUnit tests

# Type checking (critical before commits)
npm run type-check
```

### API Development

```bash
# Validate OpenAPI spec
npm run api:validate

# Generate TypeScript types from OpenAPI
npm run api:types
```

## Common Development Tasks

### Adding a New REST Endpoint

1. **Create Controller** (if complex logic needed):
   ```php
   // api/rest/class-ict-rest-{entity}-controller.php
   class ICT_REST_{Entity}_Controller extends WP_REST_Controller {
       public function register_routes() {
           register_rest_route('ict/v1', '/entity', array(
               'methods'             => 'GET',
               'callback'            => array($this, 'get_items'),
               'permission_callback' => array($this, 'get_items_permissions_check'),
           ));
       }
   }
   ```

2. **Register in API Class**:
   ```php
   // api/class-ict-api.php
   public function register_entity_routes() {
       $controller = new ICT_REST_Entity_Controller();
       $controller->register_routes();
   }
   ```

3. **Add TypeScript Types**:
   ```typescript
   // src/types/index.ts
   export interface Entity {
       id: number;
       name: string;
       // ... other fields
   }
   ```

4. **Create API Service Method**:
   ```typescript
   // src/services/api.ts
   export const getEntities = async (filters?: EntityFilters): Promise<Entity[]> => {
       const response = await apiClient.get('/entity', { params: filters });
       return response.data;
   };
   ```

### Adding a Zoho Service Integration

1. **Create Adapter Class**:
   ```php
   // includes/integrations/zoho/class-ict-zoho-{service}-adapter.php
   class ICT_Zoho_{Service}_Adapter {
       public function authenticate() { /* OAuth flow */ }
       public function create($data) { /* POST to Zoho */ }
       public function update($id, $data) { /* PUT to Zoho */ }
       public function delete($id) { /* DELETE from Zoho */ }
       public function test_connection() { /* Health check */ }
   }
   ```

2. **Register OAuth Settings**:
   ```php
   // admin/class-ict-admin-settings.php
   // Add fields for client_id, client_secret, redirect_uri
   ```

3. **Add to Integration Manager**:
   ```php
   // includes/integrations/class-ict-integration-manager.php
   public function init_zoho_adapters() {
       $this->zoho_{service} = new ICT_Zoho_{Service}_Adapter();
   }
   ```

### Creating a React Component

1. **Create Component File**:
   ```typescript
   // src/components/{module}/{ComponentName}.tsx
   import React from 'react';
   
   interface {ComponentName}Props {
       // Define props
   }
   
   export const {ComponentName}: React.FC<{ComponentName}Props> = ({ props }) => {
       return (
           <div className="ict-{component-name}">
               {/* Component JSX */}
           </div>
       );
   };
   ```

2. **Add Styles**:
   ```scss
   // src/styles/admin.scss
   .ict-{component-name} {
       // Component styles
   }
   ```

3. **Export from Index**:
   ```typescript
   // src/components/{module}/index.ts
   export * from './{ComponentName}';
   ```

4. **Write Tests**:
   ```typescript
   // src/components/{module}/__tests__/{ComponentName}.test.tsx
   import { render, screen } from '@testing-library/react';
   import { {ComponentName} } from '../{ComponentName}';
   
   describe('{ComponentName}', () => {
       it('renders correctly', () => {
           render(<{ComponentName} />);
           expect(screen.getByText('Expected Text')).toBeInTheDocument();
       });
   });
   ```

### Adding a Database Migration

1. **Update Activator**:
   ```php
   // includes/class-ict-activator.php
   public static function create_tables() {
       global $wpdb;
       
       $table_name = $wpdb->prefix . 'ict_new_table';
       $sql = "CREATE TABLE $table_name (
           id bigint(20) NOT NULL AUTO_INCREMENT,
           -- Add columns
           PRIMARY KEY (id)
       ) $charset_collate;";
       
       require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
       dbDelta($sql);
   }
   ```

2. **Define Table Constant**:
   ```php
   // ict-platform.php
   define('ICT_NEW_TABLE', $wpdb->prefix . 'ict_new_table');
   ```

3. **Increment DB Version**:
   ```php
   update_option('ict_db_version', '1.1.0');
   ```

### Creating Redux Slice with RTK Query

```typescript
// src/store/slices/entitySlice.ts
import { createSlice, createAsyncThunk } from '@reduxjs/toolkit';
import { getEntities } from '@services/api';

export const fetchEntities = createAsyncThunk(
    'entity/fetchAll',
    async (filters?: EntityFilters) => {
        return await getEntities(filters);
    }
);

const entitySlice = createSlice({
    name: 'entity',
    initialState: {
        items: [],
        loading: false,
        error: null,
    },
    reducers: {},
    extraReducers: (builder) => {
        builder
            .addCase(fetchEntities.pending, (state) => {
                state.loading = true;
            })
            .addCase(fetchEntities.fulfilled, (state, action) => {
                state.items = action.payload;
                state.loading = false;
            })
            .addCase(fetchEntities.rejected, (state, action) => {
                state.error = action.error.message;
                state.loading = false;
            });
    },
});

export default entitySlice.reducer;
```

## Project-Specific Conventions

### Naming Conventions

**PHP Classes**:
- Prefix all classes with `ICT_`
- Use underscores for namespace separation
- Example: `ICT_Zoho_CRM_Adapter`

**PHP Files**:
- Prefix with `class-ict-`
- Lowercase with hyphens
- Example: `class-ict-zoho-crm-adapter.php`

**REST API Endpoints**:
- Namespace: `ict/v1`
- Lowercase with hyphens
- Example: `/ict/v1/purchase-orders`

**Database Tables**:
- Prefix: `wp_ict_`
- Lowercase with underscores
- Example: `wp_ict_project_resources`

**TypeScript Files**:
- PascalCase for components
- camelCase for utilities/services
- Example: `ProjectDashboard.tsx`, `api.ts`

**CSS Classes**:
- Prefix: `ict-`
- BEM methodology where appropriate
- Example: `.ict-project-card__title`

**WordPress Hooks**:
- Action: `ict_{action_name}`
- Filter: `ict_{filter_name}`
- Example: `do_action('ict_after_project_sync', $project_id);`

### Code Style Preferences

**PHP**:
- Follow WordPress Coding Standards (WPCS) strictly
- Use tabs for indentation (WordPress standard)
- Always use Yoda conditions: `if ( 'value' === $variable )`
- Use `/** @var Type $variable */` for type hints

**TypeScript**:
- Use functional components with hooks (no class components)
- Prefer `const` over `let`
- Use explicit return types on functions
- Interfaces over type aliases for object shapes
- Enable strict mode in TypeScript

**React**:
- Props destructuring in function signature
- Use `React.FC<Props>` for component typing
- Custom hooks for reusable logic
- Memoization (`useMemo`, `useCallback`) for expensive computations

### Error Handling Patterns

**PHP**:
```php
// Always wrap external API calls
try {
    $response = $this->zoho_api->create_deal($data);
    
    ICT_Helper::log_sync(array(
        'status' => 'success',
        'response_data' => $response,
    ));
    
    return new WP_REST_Response($response, 200);
    
} catch (Exception $e) {
    ICT_Helper::log_sync(array(
        'status' => 'error',
        'error_message' => $e->getMessage(),
    ));
    
    return new WP_Error(
        'zoho_api_error',
        $e->getMessage(),
        array('status' => 500)
    );
}
```

**TypeScript**:
```typescript
// API service error handling
try {
    const response = await apiClient.post('/endpoint', data);
    return response.data;
} catch (error) {
    if (axios.isAxiosError(error)) {
        throw new Error(error.response?.data?.message || 'API request failed');
    }
    throw error;
}

// Component error boundaries
import { ErrorBoundary } from 'react-error-boundary';

<ErrorBoundary FallbackComponent={ErrorFallback}>
    <MyComponent />
</ErrorBoundary>
```

## Integration Points & Communication

### WordPress ↔ React Communication

**Data Flow**:
1. WordPress `wp_localize_script()` passes initial data to React
2. React makes REST API calls for CRUD operations
3. WordPress responds with JSON
4. React updates Redux store
5. Components re-render with new data

**Localized Data** (`admin/class-ict-admin.php`):
```php
wp_localize_script('ict-admin-bundle', 'ictPlatform', array(
    'apiUrl'    => rest_url('ict/v1'),
    'nonce'     => wp_create_nonce('wp_rest'),
    'currentUser' => wp_get_current_user(),
    'capabilities' => array(
        'canManageProjects' => current_user_can('manage_ict_projects'),
    ),
));
```

**React Access**:
```typescript
declare global {
    interface Window {
        ictPlatform: {
            apiUrl: string;
            nonce: string;
            currentUser: User;
            capabilities: Capabilities;
        };
    }
}

// Usage in services/api.ts
const apiClient = axios.create({
    baseURL: window.ictPlatform.apiUrl,
    headers: {
        'X-WP-Nonce': window.ictPlatform.nonce,
    },
});
```

### QuoteWerks ↔ WordPress Communication

**Inbound (QuoteWerks → WordPress)**:
- Webhook endpoint: `/wp-json/ict/v1/webhooks/quotewerks`
- Events: `quote.created`, `quote.updated`, `quote.converted`
- Signature verification with shared secret
- Automatic sync queue insertion

**Outbound (WordPress → QuoteWerks)**:
- REST API client in `ICT_QuoteWerks_Adapter`
- Updates: Inventory items, project status, time entries
- Authentication: API key in headers

### Zoho ↔ WordPress Communication

**OAuth 2.0 Flow**:
1. User initiates in Settings → Zoho → {Service}
2. Redirect to Zoho authorization URL
3. User grants permissions
4. Zoho redirects back with auth code
5. Exchange code for access/refresh tokens
6. Encrypt and store tokens in wp_options
7. Auto-refresh tokens 5 minutes before expiration

**API Calls**:
- Rate limit: 60 requests/minute per service
- Retry logic: 3 attempts with exponential backoff (1s, 2s, 4s)
- Timeout: 30 seconds per request
- Batch operations where supported (e.g., Books bulk items)

**Webhook Inbound**:
- Zoho sends notifications to `/wp-json/ict/v1/webhooks/zoho/{service}`
- HMAC-SHA256 signature verification
- Real-time updates trigger immediate sync queue processing

## Performance Considerations

### Caching Strategy

**Transient Cache**:
```php
// Cache expensive queries
$cache_key = 'ict_project_stats_' . $user_id;
$stats = get_transient($cache_key);

if (false === $stats) {
    $stats = $this->calculate_project_stats($user_id);
    set_transient($cache_key, $stats, 15 * MINUTE_IN_SECONDS);
}
```

**Invalidation**:
```php
// Clear cache on data changes
delete_transient('ict_project_stats_' . $user_id);
```

### Database Optimization

**Indexes**:
- All foreign keys indexed
- Common filter fields indexed (status, created_at, user_id)
- Composite indexes on frequently joined columns

**Query Optimization**:
- Limit results with pagination
- Use `WP_Query` arguments efficiently
- Avoid N+1 queries (use `JOIN` or `get_posts()` with meta query)

**Batch Processing**:
- Sync queue processes 20 items per cron run
- Use `wp_schedule_single_event()` for large operations
- Background processing for reports generation

### Asset Optimization

**Webpack Configuration**:
- Code splitting for admin, public, standalone apps
- Tree shaking to remove unused code
- Minification in production mode
- Source maps for debugging (dev only)

**Conditional Loading**:
```php
// Only load assets on relevant pages
if (is_admin() && $this->is_ict_admin_page()) {
    wp_enqueue_script('ict-admin-bundle');
    wp_enqueue_style('ict-admin-styles');
}
```

## Testing Guidelines

### PHP Testing (PHPUnit)

**Test Location**: `tests/` directory

**Example**:
```php
class Test_ICT_Helper extends WP_UnitTestCase {
    public function test_format_currency() {
        $result = ICT_Helper::format_currency(1234.56);
        $this->assertEquals('$1,234.56', $result);
    }
}
```

**Run Tests**:
```bash
composer test
```

### JavaScript Testing (Jest)

**Test Location**: `src/**/__tests__/` directories

**Example**:
```typescript
import { render, screen, fireEvent } from '@testing-library/react';
import { ProjectCard } from '../ProjectCard';

describe('ProjectCard', () => {
    it('renders project name', () => {
        const project = { id: 1, project_name: 'Test Project' };
        render(<ProjectCard project={project} />);
        expect(screen.getByText('Test Project')).toBeInTheDocument();
    });
    
    it('calls onEdit when edit button clicked', () => {
        const onEdit = jest.fn();
        const project = { id: 1, project_name: 'Test' };
        render(<ProjectCard project={project} onEdit={onEdit} />);
        
        fireEvent.click(screen.getByText('Edit'));
        expect(onEdit).toHaveBeenCalledWith(project);
    });
});
```

**Run Tests**:
```bash
npm test
npm run test:coverage  # Generate coverage report
```

### Manual Testing Checklist

Before committing changes that affect core functionality:

- [ ] Test sync workflow end-to-end (QuoteWerks → WP → Zoho)
- [ ] Verify webhook receivers with signature verification
- [ ] Check rate limiting doesn't block legitimate requests
- [ ] Test error handling with invalid data
- [ ] Verify capability checks prevent unauthorized access
- [ ] Test on clean WordPress install (activation/deactivation)
- [ ] Check mobile responsiveness
- [ ] Verify no console errors in browser
- [ ] Check no PHP errors/warnings in debug.log
- [ ] Test with WP_DEBUG and SCRIPT_DEBUG enabled

## Debugging & Troubleshooting

### Enable Debug Mode

**WordPress**:
```php
// wp-config.php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
define('WP_DEBUG_DISPLAY', false);
define('SCRIPT_DEBUG', true);  // Use non-minified assets
```

**Plugin-Specific**:
```php
// wp-config.php
define('ICT_DEBUG', true);  // Enables verbose logging
```

### Logging

**PHP Logs**:
```php
// Log to wp-content/debug.log
error_log('ICT Platform: ' . print_r($data, true));

// Use plugin helper
ICT_Helper::debug_log('Custom message', $data);
```

**JavaScript Console**:
```typescript
// Only log in development
if (process.env.NODE_ENV === 'development') {
    console.log('Debug:', data);
}
```

### Common Issues & Solutions

**Sync Queue Stuck**:
```sql
-- Check queue status
SELECT status, COUNT(*) FROM wp_ict_sync_queue GROUP BY status;

-- Reset stuck items
UPDATE wp_ict_sync_queue
SET status = 'pending'
WHERE status = 'processing'
AND updated_at < DATE_SUB(NOW(), INTERVAL 1 HOUR);
```

**OAuth Tokens Expired**:
- Navigate to Settings → Zoho → {Service}
- Click "Re-authorize"
- Grant permissions again
- Tokens will auto-refresh thereafter

**Webhook Not Receiving**:
- Check webhook URL is publicly accessible
- Verify signature verification isn't failing
- Check `wp_ict_webhook_log` table for errors
- Test with curl/Postman with correct signature

**Build Fails**:
```bash
# Clear node_modules and reinstall
rm -rf node_modules package-lock.json
npm install

# Clear webpack cache
rm -rf .cache
npm run build
```

## Security Best Practices - MUST FOLLOW

1. **Never commit secrets**:
   - Add `.env` files to `.gitignore`
   - Use `wp-config.php` constants for sensitive data
   - Encrypt tokens before storing in database

2. **Validate all inputs**:
   ```php
   $project_id = absint($_POST['project_id']);
   $email = sanitize_email($_POST['email']);
   $text = sanitize_text_field($_POST['name']);
   ```

3. **Escape all outputs**:
   ```php
   echo esc_html($user_input);
   echo '<a href="' . esc_url($url) . '">';
   echo '<input value="' . esc_attr($value) . '">';
   ```

4. **Use nonces for forms**:
   ```php
   // Generate
   wp_nonce_field('ict_save_project', 'ict_project_nonce');
   
   // Verify
   if (!wp_verify_nonce($_POST['ict_project_nonce'], 'ict_save_project')) {
       wp_die('Security check failed');
   }
   ```

5. **Check capabilities**:
   ```php
   if (!current_user_can('manage_ict_projects')) {
       wp_die('Insufficient permissions');
   }
   ```

6. **Prepare SQL queries**:
   ```php
   $wpdb->get_results($wpdb->prepare(
       "SELECT * FROM {$wpdb->prefix}ict_projects WHERE id = %d",
       $project_id
   ));
   ```

7. **Sanitize file uploads**:
   ```php
   $allowed_types = array('jpg', 'jpeg', 'png', 'pdf');
   $file_type = wp_check_filetype($filename, $allowed_types);
   ```

## Deployment Checklist

Before deploying to production:

- [ ] Run `npm run build` for production assets
- [ ] Run `npm run type-check` - must pass with 0 errors
- [ ] Run `npm run lint` - fix all errors
- [ ] Run `composer phpcs` - must pass WPCS
- [ ] Run `npm test` - all tests passing
- [ ] Update version in `ict-platform.php` and `package.json`
- [ ] Update `CHANGELOG.md`
- [ ] Test activation on clean WordPress install
- [ ] Verify database migrations run successfully
- [ ] Test all Zoho integrations authenticate
- [ ] Test webhook endpoints with real payloads
- [ ] Check sync queue processes correctly
- [ ] Verify error logging is working
- [ ] Test mobile app API endpoints
- [ ] Review security headers and SSL configuration
- [ ] Create plugin zip excluding dev files:
  ```bash
  zip -r ict-platform.zip wp-ict-platform/ \
    -x "*/node_modules/*" "*/src/*" "*/tests/*" "*/.git/*" "*.log"
  ```

## Health Monitoring

### API Health Check

```bash
curl -X GET "https://yoursite.com/wp-json/ict/v1/health" \
  -H "Authorization: Bearer YOUR_ADMIN_TOKEN"
```

**Response Indicators**:
- `status: "healthy"` - All systems operational
- `status: "degraded"` - Some integrations failing
- `status: "unhealthy"` - Critical systems down

**Checks**:
- Database connectivity
- Zoho service authentication
- QuoteWerks connectivity
- Sync queue health (< 50 pending items)
- Recent sync success rate (> 90%)

### Key Metrics to Monitor

- **Sync Queue**: Pending items < 50, failed < 10/hour
- **API Response Time**: < 1.5s average
- **Sync Success Rate**: > 90%
- **Error Rate**: < 5% of total requests
- **Database Size**: Monitor growth, cleanup old logs monthly

## Important File Locations

**Entry Points**:
- `ict-platform.php` - Main plugin file
- `includes/class-ict-core.php` - Core orchestrator
- `src/admin/index.tsx` - Admin React app entry
- `src/public/index.tsx` - Public React app entry

**Configuration**:
- `webpack.config.js` - Build configuration
- `tsconfig.json` - TypeScript configuration
- `phpcs.xml` - PHP CodeSniffer rules
- `.eslintrc.json` - ESLint rules
- `jest.config.js` - Jest test configuration

**Key Classes**:
- `includes/class-ict-autoloader.php` - PSR-4 autoloader
- `includes/class-ict-helper.php` - Utility functions
- `includes/sync/class-ict-sync-engine.php` - Sync orchestration
- `api/class-ict-api.php` - REST API registration
- `admin/class-ict-admin-settings.php` - Settings page

## Additional Resources

- [Main README](../README.md) - Project overview
- [CLAUDE.md](../CLAUDE.md) - Claude Code specific guidance
- [CONTRIBUTING.md](../wp-ict-platform/CONTRIBUTING.md) - Contribution guidelines
- [TROUBLESHOOTING_GUIDE.md](../TROUBLESHOOTING_GUIDE.md) - Common issues
- [DEPLOYMENT_CHECKLIST.md](../DEPLOYMENT_CHECKLIST.md) - Pre-launch checklist
- [LAUNCH_GUIDE.md](../LAUNCH_GUIDE.md) - Day-of-launch procedures

---

**Quick Command Reference**:

```bash
# Development
npm install && composer install
npm run dev

# Build
npm run build

# Quality
npm run lint && npm run type-check
composer phpcs

# Testing
npm test && composer test

# Deploy
npm run build && zip -r plugin.zip wp-ict-platform/ -x "*/node_modules/*" "*/src/*" "*/tests/*"
```

**Remember**: This is production code serving real businesses. Write defensive code, test thoroughly, and prioritize security above convenience.
