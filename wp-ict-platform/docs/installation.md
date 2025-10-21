# ICT Platform - Installation Guide

## Requirements

- **WordPress**: 6.4 or higher
- **PHP**: 8.1 or higher
- **MySQL**: 5.7 or higher (or MariaDB 10.3+)
- **Node.js**: 18+ (for development)
- **Composer**: 2.0+ (for development)

## Installation Steps

### 1. Upload Plugin

Upload the `wp-ict-platform` folder to your `/wp-content/plugins/` directory, or install the plugin through the WordPress plugins screen directly.

### 2. Activate Plugin

Activate the plugin through the 'Plugins' screen in WordPress.

### 3. Initial Configuration

1. Navigate to **ICT Platform > Settings**
2. Configure general settings (currency, date/time formats)
3. Set up Zoho OAuth credentials for each service

### 4. Zoho Integration Setup

#### A. Create Zoho OAuth Apps

For each Zoho service (CRM, FSM, Books, People, Desk):

1. Go to [Zoho API Console](https://api-console.zoho.com/)
2. Click "Add Client"
3. Select "Server-based Applications"
4. Fill in:
   - Client Name: `ICT Platform - [Service Name]`
   - Homepage URL: Your WordPress site URL
   - Authorized Redirect URIs: `https://yoursite.com/wp-admin/admin.php?page=ict-settings&tab=zoho&service=[service]`
5. Copy the Client ID and Client Secret

#### B. Configure in WordPress

1. Go to **ICT Platform > Settings > Zoho Integration**
2. For each service:
   - Enter Client ID
   - Enter Client Secret
   - Click "Connect to Zoho"
   - Authorize the application in Zoho
3. Test each connection

### 5. User Roles Setup

The plugin creates three custom roles:

- **ICT Project Manager**: Can manage projects, time entries, and purchase orders
- **ICT Technician**: Can clock in/out and view assigned projects
- **ICT Inventory Manager**: Can manage inventory and purchase orders

Assign these roles to appropriate users in **Users > All Users**.

### 6. Initial Sync

1. Go to **ICT Platform > Sync**
2. For each connected Zoho service:
   - Click "Run Initial Sync"
   - Monitor progress
   - Review any errors

### 7. Configure Cron Jobs

The plugin uses WordPress cron for scheduled syncs. For better reliability, configure server cron:

```bash
*/15 * * * * wget -q -O - https://yoursite.com/wp-cron.php?doing_wp_cron >/dev/null 2>&1
```

## Development Setup

### Build Frontend Assets

```bash
# Install dependencies
npm install

# Development build with watch
npm run dev

# Production build
npm run build
```

### Install PHP Dependencies

```bash
composer install
```

### Run Tests

```bash
# PHP tests
composer test

# JavaScript tests
npm test

# Code standards
composer phpcs
npm run lint
```

## Troubleshooting

### Activation Errors

If you encounter errors during activation:

1. Check PHP version: `php -v`
2. Ensure database permissions are correct
3. Check error logs: `wp-content/debug.log`

### Sync Issues

If sync is not working:

1. Verify Zoho credentials are correct
2. Check sync logs: **ICT Platform > Sync > Logs**
3. Ensure WordPress cron is running
4. Check rate limits haven't been exceeded

### Permission Issues

If users can't access features:

1. Verify user roles are assigned correctly
2. Check capabilities in **Settings > General > Capabilities**
3. Try resetting roles: Deactivate and reactivate plugin

## Support

For issues and bug reports:
https://github.com/yourusername/ict-platform/issues

## Next Steps

- [User Guide](./user-guide.md)
- [API Documentation](./api-reference.md)
- [Zoho Integration Guide](./zoho-integration.md)
