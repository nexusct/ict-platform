# ICT Platform - Development Guide

## Project Structure

```
wp-ict-platform/
├── ict-platform.php          # Main plugin file
├── includes/                  # Core plugin classes
│   ├── class-ict-core.php
│   ├── class-ict-loader.php
│   ├── class-ict-activator.php
│   ├── class-ict-autoloader.php
│   ├── post-types/           # Custom post types
│   ├── taxonomies/           # Custom taxonomies
│   ├── models/               # Data models
│   ├── integrations/         # Zoho integrations
│   └── sync/                 # Sync engine
├── admin/                    # Admin functionality
│   ├── class-ict-admin.php
│   ├── class-ict-admin-menu.php
│   └── class-ict-admin-settings.php
├── public/                   # Public-facing functionality
│   └── class-ict-public.php
├── api/                      # REST API endpoints
│   ├── class-ict-api.php
│   ├── rest/                 # REST controllers
│   └── webhooks/             # Webhook handlers
├── src/                      # React/TypeScript source
│   ├── admin/                # Admin React app
│   ├── public/               # Public React app
│   ├── apps/                 # Standalone apps
│   ├── components/           # Shared components
│   ├── hooks/                # Custom hooks
│   ├── services/             # API services
│   └── utils/                # Utilities
├── assets/                   # Compiled assets
│   ├── css/
│   ├── js/
│   └── images/
├── tests/                    # Tests
│   ├── unit/
│   └── integration/
└── docs/                     # Documentation
```

## Development Workflow

### 1. Setup Development Environment

```bash
# Clone repository
git clone https://github.com/yourusername/ict-platform.git
cd ict-platform

# Install dependencies
npm install
composer install

# Start development build
npm run dev
```

### 2. Coding Standards

#### PHP Standards

Follow WordPress Coding Standards:

```bash
# Check code
composer phpcs

# Fix code
composer phpcbf
```

#### JavaScript/TypeScript Standards

```bash
# Check code
npm run lint

# Fix code
npm run lint:fix

# Format code
npm run format
```

### 3. Adding New Features

#### Creating a New REST Endpoint

1. Add route in `api/class-ict-api.php`:

```php
register_rest_route(
    $this->namespace,
    '/my-endpoint',
    array(
        'methods'             => WP_REST_Server::READABLE,
        'callback'            => array( $this, 'my_endpoint_handler' ),
        'permission_callback' => array( $this, 'check_permission' ),
    )
);
```

2. Add handler method:

```php
public function my_endpoint_handler( $request ) {
    return new WP_REST_Response( array( 'data' => 'value' ), 200 );
}
```

#### Creating a React Component

1. Create component file in `src/components/`:

```typescript
// src/components/MyComponent.tsx
import React from 'react';

interface MyComponentProps {
  title: string;
}

export const MyComponent: React.FC<MyComponentProps> = ({ title }) => {
  return <div className="my-component">{title}</div>;
};
```

2. Export from index:

```typescript
// src/components/index.ts
export { MyComponent } from './MyComponent';
```

#### Adding a Custom Post Type

1. Create class in `includes/post-types/`:

```php
class ICT_PostType_MyType {
    public function register() {
        register_post_type( 'ict_mytype', $args );
    }
}
```

2. Register in `class-ict-core.php`:

```php
$my_post_type = new ICT_PostType_MyType();
$this->loader->add_action( 'init', $my_post_type, 'register' );
```

### 4. Database Operations

#### Adding a New Table

1. Update `class-ict-activator.php`:

```php
$table_sql = "CREATE TABLE {$wpdb->prefix}ict_mytable (
    id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    // ... columns
    PRIMARY KEY  (id)
) $charset_collate;";

dbDelta( $table_sql );
```

2. Add table constant in `ict-platform.php`:

```php
define( 'ICT_MYTABLE_TABLE', $wpdb->prefix . 'ict_mytable' );
```

### 5. Zoho Integration

#### Creating a Service Adapter

1. Create adapter class in `includes/integrations/zoho/`:

```php
class ICT_Zoho_Service_Adapter {
    public function authenticate() {
        // OAuth flow
    }

    public function create( $entity_type, $data ) {
        // API call to create
    }

    public function update( $entity_type, $id, $data ) {
        // API call to update
    }
}
```

2. Queue sync operations:

```php
ICT_Helper::queue_sync( array(
    'entity_type'  => 'project',
    'entity_id'    => $project_id,
    'action'       => 'update',
    'zoho_service' => 'crm',
    'payload'      => $data,
) );
```

### 6. Testing

#### PHP Unit Tests

```php
// tests/unit/test-helper.php
class Test_ICT_Helper extends WP_UnitTestCase {
    public function test_format_currency() {
        $result = ICT_Helper::format_currency( 100.50 );
        $this->assertEquals( '$100.50', $result );
    }
}
```

Run with:
```bash
composer test
```

#### JavaScript Tests

```typescript
// src/components/__tests__/MyComponent.test.tsx
import { render, screen } from '@testing-library/react';
import { MyComponent } from '../MyComponent';

test('renders title', () => {
  render(<MyComponent title="Test" />);
  expect(screen.getByText('Test')).toBeInTheDocument();
});
```

Run with:
```bash
npm test
```

### 7. Build and Deploy

```bash
# Production build
npm run build

# Create plugin package
zip -r ict-platform.zip . -x "node_modules/*" "src/*" "tests/*" ".git/*"
```

## Best Practices

1. **Security**: Always sanitize input and escape output
2. **Performance**: Use transients for caching
3. **Hooks**: Use appropriate hooks for extensibility
4. **Documentation**: Document all functions with PHPDoc
5. **Version Control**: Commit logical units of work
6. **Testing**: Write tests for new features

## Common Tasks

### Clear Sync Queue

```sql
TRUNCATE TABLE wp_ict_sync_queue;
```

### Reset Sync Status

```php
update_option( 'ict_zoho_crm_sync_status', 'idle' );
```

### Force Full Sync

```php
do_action( 'ict_force_full_sync', 'crm' );
```

## Resources

- [WordPress Plugin Handbook](https://developer.wordpress.org/plugins/)
- [React Documentation](https://react.dev/)
- [TypeScript Documentation](https://www.typescriptlang.org/docs/)
- [Zoho API Documentation](https://www.zoho.com/developer/)
