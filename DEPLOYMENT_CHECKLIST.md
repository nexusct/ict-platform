# ICT Platform - Deployment Checklist

## Pre-Launch Validation

### 1. Environment Verification

- [ ] PHP version 8.1+ installed and active
- [ ] WordPress 6.4+ installed
- [ ] MySQL 5.7+ running
- [ ] Node.js 18+ available for builds
- [ ] SSL certificate installed (required for OAuth)
- [ ] Cron jobs enabled on server
- [ ] `allow_url_fopen` enabled in PHP (for API calls)
- [ ] `max_execution_time` set to at least 300 seconds
- [ ] `memory_limit` set to at least 256M
- [ ] `upload_max_filesize` set to at least 32M

### 2. WordPress Plugin Installation

```bash
# Navigate to WordPress plugins directory
cd wp-content/plugins/

# Upload plugin
# Copy wp-ict-platform folder to plugins directory

# Install PHP dependencies
cd wp-ict-platform
composer install --no-dev --optimize-autoloader

# Install Node dependencies and build
npm install
npm run build

# Set correct permissions
chmod -R 755 .
chmod -R 644 *.php
```

- [ ] Plugin files uploaded
- [ ] Composer dependencies installed
- [ ] Node dependencies installed
- [ ] Production build completed (`npm run build`)
- [ ] File permissions set correctly
- [ ] Plugin activated in WordPress admin

### 3. Database Setup

- [ ] Navigate to ICT Platform → Settings
- [ ] Verify all 7 custom tables created:
  - `wp_ict_projects`
  - `wp_ict_time_entries`
  - `wp_ict_inventory_items`
  - `wp_ict_purchase_orders`
  - `wp_ict_project_resources`
  - `wp_ict_sync_queue`
  - `wp_ict_sync_log`
  - `wp_ict_location_tracking` (for mobile GPS)
  - `wp_ict_expenses` (for mobile expenses)
  - `wp_ict_tasks` (for task management)

**Verification Command:**
```sql
SHOW TABLES LIKE 'wp_ict_%';
```

### 4. QuoteWerks Integration Setup

- [ ] QuoteWerks API URL configured
- [ ] QuoteWerks API Key/Token configured
- [ ] QuoteWerks username/password (if applicable)
- [ ] Webhook secret key generated and saved
- [ ] Test connection: Settings → QuoteWerks → Test Connection

**Configure Webhook in QuoteWerks:**
- Webhook URL: `https://yoursite.com/wp-json/ict/v1/webhooks/quotewerks`
- Secret Key: (copy from Settings)
- Events to enable:
  - `quote.created`
  - `quote.updated`
  - `quote.approved`
  - `quote.converted`
  - `order.created`
  - `customer.updated`

### 5. Zoho CRM Integration Setup

**Create OAuth App:**
1. Go to https://api-console.zoho.com/
2. Create "Server-based Application"
3. Set redirect URI: `https://yoursite.com/wp-admin/`
4. Copy Client ID and Client Secret

**Configure in Plugin:**
- [ ] Zoho CRM Client ID entered
- [ ] Zoho CRM Client Secret entered
- [ ] Scopes configured: `ZohoCRM.modules.ALL,ZohoCRM.settings.ALL`
- [ ] Authorization completed (OAuth flow)
- [ ] Refresh token stored
- [ ] Test connection successful

**Field Mappings:**
- WordPress Project → Zoho CRM Deal
- Project Status → Deal Stage
- Budget Amount → Deal Amount
- End Date → Closing Date

### 6. Zoho FSM Integration Setup

**Configure OAuth:**
- [ ] Zoho FSM Client ID entered
- [ ] Zoho FSM Client Secret entered
- [ ] Scopes: `Zoho.FSM.modules.ALL`
- [ ] Authorization completed
- [ ] Test connection successful

**Field Mappings:**
- WordPress Project → Zoho FSM Work Order
- Project Task → FSM Task
- Technician → Field Agent

### 7. Zoho Books Integration Setup

**Configure OAuth:**
- [ ] Zoho Books Client ID entered
- [ ] Zoho Books Client Secret entered
- [ ] Organization ID configured
- [ ] Scopes: `ZohoBooks.fullaccess.all`
- [ ] Authorization completed
- [ ] Test connection successful

**Field Mappings:**
- Inventory Item → Zoho Books Item
- Purchase Order → Zoho Books PO
- Supplier → Vendor

### 8. Zoho People Integration Setup

**Configure OAuth:**
- [ ] Zoho People Client ID entered
- [ ] Zoho People Client Secret entered
- [ ] Scopes: `ZohoPeople.attendance.ALL`
- [ ] Authorization completed
- [ ] Test connection successful

**Sync Configuration:**
- Time Entry → Attendance Record
- Employee → WordPress User

### 9. Zoho Desk Integration Setup

**Configure OAuth:**
- [ ] Zoho Desk Client ID entered
- [ ] Zoho Desk Client Secret entered
- [ ] Org ID configured
- [ ] Scopes: `Desk.tickets.ALL`
- [ ] Authorization completed
- [ ] Test connection successful

### 10. Zoho WorkDrive Setup (for mobile file uploads)

**Configure OAuth:**
- [ ] WorkDrive Client ID entered
- [ ] WorkDrive Client Secret entered
- [ ] Scopes: `WorkDrive.files.ALL`
- [ ] Default folder configured for uploads
- [ ] Test upload successful

### 11. Health Check Verification

**API Health Endpoint:**
```bash
# Test overall health
curl -X GET "https://yoursite.com/wp-json/ict/v1/health" \
  -H "Authorization: Bearer YOUR_ADMIN_TOKEN"
```

- [ ] Database health: All tables present
- [ ] QuoteWerks connection: OK
- [ ] Zoho CRM connection: OK
- [ ] Zoho FSM connection: OK
- [ ] Zoho Books connection: OK
- [ ] Zoho People connection: OK
- [ ] Zoho Desk connection: OK
- [ ] Sync queue: No critical errors

### 12. Sync Testing

**Test Workflows:**

```bash
# Test QuoteWerks to Project sync
curl -X POST "https://yoursite.com/wp-json/ict/v1/health/test-sync" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"workflow": "quote_to_project", "entity_id": 123}'

# Test full workflow
curl -X POST "https://yoursite.com/wp-json/ict/v1/health/test-sync" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"workflow": "full_workflow", "entity_id": 123}'
```

- [ ] QuoteWerks → WordPress sync working
- [ ] WordPress → Zoho CRM sync working
- [ ] WordPress → Zoho FSM sync working
- [ ] Inventory → Zoho Books sync working
- [ ] Full workflow test passed

### 13. WordPress Cron Configuration

**Verify Cron:**
```bash
# Check if WP Cron is working
wp cron event list
```

**Scheduled Events Should Include:**
- `ict_process_sync_queue` - Every 15 minutes
- `ict_cleanup_sync_logs` - Daily
- `ict_sync_from_zoho` - Hourly (inbound sync)

**Optional: Use System Cron (recommended for production)**

Edit crontab:
```bash
crontab -e
```

Add:
```
*/15 * * * * curl -s https://yoursite.com/wp-cron.php?doing_wp_cron > /dev/null 2>&1
```

Disable WP Cron in `wp-config.php`:
```php
define('DISABLE_WP_CRON', true);
```

- [ ] WP Cron events scheduled
- [ ] System cron configured (optional but recommended)

### 14. User Roles & Permissions

**Verify Custom Roles Created:**
- [ ] `ict_project_manager` - Full access to projects, time, inventory
- [ ] `ict_technician` - Can clock in/out, view assigned projects
- [ ] `ict_inventory_manager` - Manage inventory and POs

**Assign Roles:**
- [ ] Project managers assigned
- [ ] Technicians assigned
- [ ] Inventory managers assigned

**Test Permissions:**
- [ ] Project manager can create projects
- [ ] Technician can clock in/out
- [ ] Technician cannot approve time entries
- [ ] Inventory manager can create POs

### 15. Mobile App Configuration

**WordPress Settings:**
- [ ] JWT authentication enabled
- [ ] Mobile API endpoints active
- [ ] Location tracking table created
- [ ] Expenses table created

**Mobile App `.env` File:**
```env
API_BASE_URL=https://yoursite.com/wp-json/ict/v1
WP_BASE_URL=https://yoursite.com/wp-json/wp/v2
```

**Test Mobile Endpoints:**
```bash
# Test login
curl -X POST "https://yoursite.com/wp-json/ict/v1/auth/login" \
  -H "Content-Type: application/json" \
  -d '{"username": "testuser", "password": "password"}'

# Should return JWT token
```

- [ ] Mobile login working
- [ ] Location tracking endpoint working
- [ ] File upload endpoint working
- [ ] Expenses endpoint working

### 16. Performance Optimization

**WordPress Optimization:**
- [ ] Object caching enabled (Redis/Memcached recommended)
- [ ] Opcode cache enabled (OPcache)
- [ ] Database queries optimized
- [ ] Asset minification enabled

**Database Indexes:**
```sql
-- Verify indexes exist on sync tables
SHOW INDEX FROM wp_ict_sync_queue;
SHOW INDEX FROM wp_ict_sync_log;
SHOW INDEX FROM wp_ict_projects;
```

- [ ] Database indexes present
- [ ] Query execution time < 1 second

### 17. Security Hardening

- [ ] SSL/TLS certificate valid
- [ ] All API credentials encrypted
- [ ] Webhook signatures verified
- [ ] File upload validation enabled
- [ ] SQL injection protection verified
- [ ] XSS protection enabled
- [ ] CSRF tokens validated
- [ ] Rate limiting configured

**Recommended Security Headers:**
```apache
Header set X-Content-Type-Options "nosniff"
Header set X-Frame-Options "SAMEORIGIN"
Header set X-XSS-Protection "1; mode=block"
```

### 18. Monitoring & Logging

**Enable Logging:**
- [ ] WordPress debug log enabled (initially)
- [ ] Sync logs monitored
- [ ] Error tracking configured
- [ ] Uptime monitoring configured

**Monitor These Metrics:**
- Sync queue length (should stay < 50)
- Failed sync count (should be < 5 per hour)
- API response times (should be < 2 seconds)
- Database connection pool usage

### 19. Backup Configuration

- [ ] Daily database backups scheduled
- [ ] Plugin files backed up
- [ ] Media files backed up
- [ ] Backup restoration tested
- [ ] Offsite backup configured

**Backup Important Data:**
- Database tables (all `wp_ict_*` tables)
- Uploaded files in `wp-content/uploads/`
- Plugin settings/options
- OAuth tokens (encrypted)

### 20. Documentation & Training

- [ ] User documentation provided
- [ ] Admin training completed
- [ ] Technician training completed
- [ ] API documentation accessible
- [ ] Troubleshooting guide reviewed
- [ ] Emergency contacts documented

## Launch Day Checklist

### T-1 Hour
- [ ] Full backup completed
- [ ] Health check: All systems green
- [ ] Sync queue cleared
- [ ] Monitor dashboard ready

### T-0 (Launch)
- [ ] Enable QuoteWerks webhook
- [ ] Enable Zoho sync (if not already)
- [ ] Monitor sync queue for 30 minutes
- [ ] Test creating quote in QuoteWerks
- [ ] Verify quote syncs to WordPress
- [ ] Verify project syncs to Zoho CRM/FSM
- [ ] Test mobile app login
- [ ] Test time tracking

### T+1 Hour
- [ ] Check sync logs for errors
- [ ] Verify no failed sync items
- [ ] Monitor API response times
- [ ] Check database performance

### T+24 Hours
- [ ] Review all sync operations
- [ ] Check error logs
- [ ] Verify data consistency across platforms
- [ ] User feedback collected
- [ ] Performance metrics reviewed

## Post-Launch Monitoring

**Daily Tasks (First Week):**
- Check sync queue status
- Review error logs
- Monitor API health endpoint
- Verify data sync accuracy
- Address user issues

**Weekly Tasks:**
- Review sync performance metrics
- Clean up old sync logs (automated)
- Check database size
- Review backup logs
- Update documentation as needed

**Monthly Tasks:**
- Review OAuth token expiration
- Update dependencies (if needed)
- Performance optimization review
- Security audit
- User feedback analysis

## Rollback Plan

**If Critical Issues Occur:**

1. **Disable Integrations:**
   ```php
   // Add to wp-config.php
   define('ICT_DISABLE_SYNC', true);
   ```

2. **Disable Webhooks:**
   - Go to QuoteWerks webhook settings
   - Temporarily disable webhook

3. **Restore Database:**
   ```bash
   mysql -u username -p database_name < backup.sql
   ```

4. **Deactivate Plugin:**
   - WordPress Admin → Plugins → Deactivate ICT Platform

5. **Restore Previous Version:**
   - Replace plugin files with previous version
   - Reactivate

6. **Notify Users:**
   - Send notification of temporary issue
   - Provide ETA for resolution

## Support Contacts

**Technical Support:**
- Developer: [contact]
- System Admin: [contact]
- QuoteWerks Support: [contact]
- Zoho Support: [contact]

**Escalation Path:**
1. Check troubleshooting guide
2. Review error logs
3. Contact developer
4. Contact Zoho/QuoteWerks support
5. Implement rollback if necessary

---

**Launch Sign-off:**

- [ ] All checklist items completed
- [ ] All integrations tested and verified
- [ ] Team trained and ready
- [ ] Monitoring in place
- [ ] Backup/rollback plan ready

**Authorized by:** ___________________
**Date:** ___________________
**Time:** ___________________
