# QuoteWerks Integration - Implementation Complete

## Overview

The QuoteWerks integration is now fully implemented with a complete admin interface for managing connection settings, webhooks, sync options, and field mappings.

## Components Implemented

### 1. Admin Menu Class
**File**: `wp-ict-platform/admin/class-ict-admin-quotewerks.php` (~1000 lines)

#### Features:
- Complete settings page with 4 sections:
  - Connection Settings (API URL, API Key, Username, Password)
  - Webhook Configuration (URL, Secret, Enable/Disable)
  - Sync Options (Auto-sync, Line items, Tasks, Default status)
  - Field Mapping (Custom QuoteWerks → WordPress field mappings)

#### Security:
- AES-256-CBC encryption for API credentials
- Encryption key derived from WordPress salt
- Credentials stored with `encrypted:` prefix
- Automatic encryption on save, decryption on use

#### AJAX Handlers:
1. **Test Connection** (`ict_test_quotewerks_connection`)
   - Tests QuoteWerks API connection
   - Updates connection status indicator
   - Caches result for 1 hour

2. **Sync Quotes** (`ict_sync_quotes_manual`)
   - Manually triggers sync of last 10 quotes
   - Shows success message with count
   - Reloads page to show updated stats

3. **Get Quote Preview** (`ict_get_quote_preview`)
   - Fetches quote data for preview
   - Used for validation and testing

4. **Regenerate Webhook Secret** (`ict_regenerate_webhook_secret`)
   - Generates new 64-character hex secret
   - Updates UI with new secret
   - Requires confirmation

#### Dashboard Features:
- **Connection Status Indicator**
  - Green pulsing indicator when connected
  - Red indicator when connection fails
  - Yellow indicator when not configured
  - Real-time test connection button

- **Sync Statistics Grid**
  - Total syncs (last 30 days)
  - Successful syncs count
  - Failed syncs count
  - Success rate percentage
  - Last sync time

- **Recent Activity Table**
  - Last 10 sync operations
  - Shows time, quote ID, action, status, duration
  - Color-coded status badges

### 2. Admin JavaScript
**File**: `wp-ict-platform/admin/js/quotewerks-admin.js` (~350 lines)

#### Functionality:
- Test connection with loading states
- Manual quote sync with spinner animation
- Webhook secret regeneration with confirmation
- Field mapping add/remove rows
- Copy to clipboard (webhook URL and secret)
- Auto-dismissing admin notices
- Smooth scroll to notices

#### User Experience:
- Button disabled states during AJAX
- Visual feedback for all actions
- Animated transitions
- Responsive error handling
- Page reload after successful sync

### 3. Admin CSS
**File**: `wp-ict-platform/admin/css/quotewerks-admin.css` (~350 lines)

#### Styling:
- Professional header with status indicator
- Pulsing animation for connected status
- Grid layout for sync stats
- Responsive design (mobile-friendly)
- Hover effects on stat boxes
- Status badge colors (green for success, red for error)
- Field mapping table styling
- Loading states with spin animation

#### Responsive Breakpoints:
- Desktop: Full grid layout
- Tablet (< 782px): Vertical layout
- Mobile (< 600px): Single column

### 4. Core Integration
**File**: `wp-ict-platform/includes/class-ict-core.php` (Modified)

#### Changes:
- Added `require_once` for QuoteWerks admin class (line 80)
- Instantiated `ICT_Admin_QuoteWerks` in `define_admin_hooks()` (line 133)
- Class automatically registers its hooks via constructor

## Admin Menu Structure

The QuoteWerks settings page appears in the WordPress admin menu:

```
ICT Platform (Main Menu)
├── Dashboard
├── Projects
├── Time Tracking
├── Resources
├── Inventory
├── Reports
├── QuoteWerks ← NEW SUBMENU PAGE
│   ├── Connection Settings
│   ├── Webhook Configuration
│   ├── Sync Options
│   ├── Field Mapping
│   ├── Sync Statistics
│   └── Recent Activity
└── Settings
```

**Menu Location**: `admin.php?page=ict-quotewerks`
**Capability Required**: `manage_options`

## Settings Registered

All settings are registered in the `ict_quotewerks_settings` option group:

| Setting Name | Type | Sanitization | Default |
|-------------|------|--------------|---------|
| `ict_quotewerks_api_url` | string | `esc_url_raw` | '' |
| `ict_quotewerks_api_key` | string | Encrypted | '' |
| `ict_quotewerks_username` | string | `sanitize_text_field` | '' |
| `ict_quotewerks_password` | string | Encrypted | '' |
| `ict_quotewerks_webhook_secret` | string | `sanitize_text_field` | Auto-generated |
| `ict_quotewerks_webhook_enabled` | boolean | `rest_sanitize_boolean` | false |
| `ict_quotewerks_auto_sync` | boolean | `rest_sanitize_boolean` | true |
| `ict_quotewerks_sync_line_items` | boolean | `rest_sanitize_boolean` | true |
| `ict_quotewerks_create_tasks` | boolean | `rest_sanitize_boolean` | true |
| `ict_quotewerks_default_project_status` | string | `sanitize_text_field` | 'planning' |
| `ict_quotewerks_field_mappings` | array | Custom sanitizer | See defaults below |

### Default Field Mappings

```php
[
    'DocNumber'     => 'quotewerks_id',
    'DocTotal'      => 'budget_amount',
    'DocDate'       => 'start_date',
    'CustomerName'  => 'client_name',
    'Description'   => 'description',
]
```

## QuoteWerks Fields Available

- DocNumber - Quote Number
- DocTotal - Total Amount
- DocDate - Quote Date
- CustomerName - Customer Name
- CustomerID - Customer ID
- Description - Description
- Status - Status
- SalesPerson - Sales Person
- Terms - Payment Terms
- ShipToAddress - Shipping Address
- BillToAddress - Billing Address
- PurchaseOrder - PO Number
- Notes - Notes

## WordPress Fields Available

- quotewerks_id - QuoteWerks ID
- project_name - Project Name
- budget_amount - Budget Amount
- start_date - Start Date
- end_date - End Date
- client_name - Client Name
- description - Description
- status - Status
- priority - Priority
- notes - Notes
- po_number - PO Number
- location - Location

## Webhook Configuration

### Webhook Endpoint
```
POST /wp-json/ict/v1/webhooks/quotewerks
```

### Supported Events
1. `quote.created` - When a new quote is created
2. `quote.updated` - When a quote is modified
3. `quote.approved` - When a quote is approved
4. `quote.converted` - When a quote is converted to an order
5. `order.created` - When an order is created
6. `customer.updated` - When customer information is updated

### Security
- HMAC-SHA256 signature verification
- Secret key stored in WordPress options
- Signature sent in `X-QuoteWerks-Signature` header
- Webhook can be enabled/disabled via settings

## Integration with Existing Components

### QuoteWerks Adapter
The admin menu works with the existing `ICT_QuoteWerks_Adapter` class:
- `test_connection()` - Tests API connectivity
- `sync_recent_quotes($limit)` - Syncs recent quotes
- `get_quote($quote_id)` - Fetches single quote
- `sync_quote_to_project($quote_id)` - Converts quote to project

### Sync Log Table
Queries the `ict_sync_log` table for statistics and recent activity:
```sql
SELECT COUNT(*) as total_syncs,
       SUM(CASE WHEN status = 'success' THEN 1 ELSE 0 END) as successful,
       SUM(CASE WHEN status = 'error' THEN 1 ELSE 0 END) as failed,
       MAX(created_at) as last_sync
FROM wp_ict_sync_log
WHERE entity_type = 'quote'
AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
```

## Usage Instructions

### Initial Setup

1. **Navigate to QuoteWerks Settings**
   - Go to WordPress Admin → ICT Platform → QuoteWerks

2. **Configure Connection**
   - Enter QuoteWerks API URL (e.g., `https://api.quotewerks.com`)
   - Enter API Key (will be encrypted automatically)
   - Optional: Enter username/password for basic auth
   - Click "Test Connection" to verify

3. **Configure Webhook**
   - Copy the webhook URL to QuoteWerks settings
   - Copy the webhook secret to QuoteWerks
   - Enable "Process incoming webhooks"
   - Configure webhook events in QuoteWerks

4. **Configure Sync Options**
   - Enable/disable automatic sync
   - Enable/disable line item sync
   - Enable/disable task creation
   - Set default project status for new syncs

5. **Map Fields**
   - Review default field mappings
   - Add custom mappings as needed
   - Remove unwanted mappings

6. **Save Settings**
   - Click "Save Changes" at bottom of page

### Testing

1. **Test Connection**
   - Click "Test Connection" button in header
   - Verify green "Connected" status appears
   - Check for success message

2. **Manual Sync**
   - Click "Sync Quotes Now" button
   - Wait for sync to complete
   - Review sync statistics
   - Check recent activity table

3. **Monitor Activity**
   - View sync statistics in dashboard
   - Review recent activity table
   - Click "View Sync Log" for detailed history

## Error Handling

### Connection Errors
- Invalid API URL → "Connection failed" message
- Invalid API key → Authentication error
- Network timeout → Timeout error message

### Sync Errors
- Quote not found → Logged in sync log
- Invalid data → Validation error, logged
- Database error → Error message, logged

### Webhook Errors
- Invalid signature → 401 Unauthorized
- Missing data → 400 Bad Request
- Processing error → Logged, 500 error

## Localization

All strings are translatable using the `ict-platform` text domain:

```php
__( 'Connection Settings', 'ict-platform' )
esc_html__( 'QuoteWerks Integration', 'ict-platform' )
```

Languages can be added by creating `.po` and `.mo` files in:
```
wp-ict-platform/languages/ict-platform-{locale}.po
wp-ict-platform/languages/ict-platform-{locale}.mo
```

## Performance Considerations

### Caching
- Connection test results cached for 1 hour
- Reduces API calls during testing

### AJAX Optimization
- Only one sync operation at a time
- Page reload after sync to prevent stale data
- Disabled button states prevent double-clicks

### Database Queries
- Sync stats query limited to 30 days
- Recent activity limited to 10 records
- Indexes on `entity_type` and `created_at` recommended

## Security Best Practices

### Credential Storage
✅ API keys encrypted with AES-256-CBC
✅ Encryption key derived from WordPress salt
✅ Passwords never displayed in plain text
✅ Input fields show `••••••••••••` for existing credentials

### AJAX Security
✅ Nonce verification on all AJAX requests
✅ Capability check (`manage_options`) on all handlers
✅ Input sanitization on all user input
✅ Output escaping on all output

### Webhook Security
✅ HMAC-SHA256 signature verification
✅ Secret key regeneration available
✅ Webhooks can be disabled
✅ Invalid signatures rejected with 401

## Future Enhancements

### Potential Additions
1. **Bulk Sync**
   - Sync all quotes at once
   - Progress bar for long syncs
   - Background processing

2. **Advanced Mapping**
   - Custom field transformations
   - Conditional mappings
   - Data type conversions

3. **Sync Filters**
   - Only sync quotes matching criteria
   - Date range filters
   - Status filters

4. **Notifications**
   - Email alerts on sync errors
   - Slack/Discord webhooks
   - Admin dashboard widget

5. **Logging Enhancements**
   - Export sync logs to CSV
   - Filter logs by date/status
   - Detailed error messages

## Testing Checklist

- [x] Settings page renders correctly
- [x] Connection test works
- [x] Manual sync triggers
- [x] Webhook secret generates
- [x] Field mappings save
- [x] Stats display correctly
- [x] Recent activity shows
- [x] Copy to clipboard works
- [x] Responsive design functions
- [x] AJAX handlers secured
- [x] Credentials encrypted
- [x] Localization ready

## Files Modified/Created

### Created
1. `wp-ict-platform/admin/class-ict-admin-quotewerks.php` (1010 lines)
2. `wp-ict-platform/admin/js/quotewerks-admin.js` (339 lines)
3. `wp-ict-platform/admin/css/quotewerks-admin.css` (347 lines)

### Modified
1. `wp-ict-platform/includes/class-ict-core.php` (Added QuoteWerks instantiation)

### Total Lines Added
**1,696 lines** of production-ready code

## Dependencies

### PHP Extensions Required
- OpenSSL (for encryption)
- cURL (for API requests)

### WordPress Requirements
- WordPress 6.4+
- PHP 8.1+
- MySQL 5.7+

### JavaScript Dependencies
- jQuery (enqueued by WordPress)

## Conclusion

The QuoteWerks integration is now fully functional with a complete admin interface. Administrators can:

1. ✅ Configure QuoteWerks API connection
2. ✅ Test connectivity in real-time
3. ✅ Set up webhooks for real-time sync
4. ✅ Configure sync options and behavior
5. ✅ Map custom fields between systems
6. ✅ Monitor sync statistics
7. ✅ View recent activity
8. ✅ Manually trigger syncs
9. ✅ Regenerate webhook secrets

All credentials are encrypted, all AJAX is secured, and the UI is responsive and user-friendly.

**Status**: ✅ **PRODUCTION READY**
