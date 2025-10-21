# ICT Platform - Quick Reference Guide

## 🚀 Common Operations

### Health Check Commands

**Check Overall System Health:**
```bash
curl -X GET "https://yoursite.com/wp-json/ict/v1/health" \
  -H "Authorization: Bearer YOUR_ADMIN_TOKEN" | jq
```

**Check Database Health:**
```bash
curl -X GET "https://yoursite.com/wp-json/ict/v1/health/database" \
  -H "Authorization: Bearer YOUR_ADMIN_TOKEN" | jq
```

**Check Zoho Integrations:**
```bash
curl -X GET "https://yoursite.com/wp-json/ict/v1/health/zoho" \
  -H "Authorization: Bearer YOUR_ADMIN_TOKEN" | jq
```

**Check QuoteWerks Integration:**
```bash
curl -X GET "https://yoursite.com/wp-json/ict/v1/health/quotewerks" \
  -H "Authorization: Bearer YOUR_ADMIN_TOKEN" | jq
```

**Check Sync Queue:**
```bash
curl -X GET "https://yoursite.com/wp-json/ict/v1/health/sync" \
  -H "Authorization: Bearer YOUR_ADMIN_TOKEN" | jq
```

### Test Sync Workflows

**Test QuoteWerks to Project Sync:**
```bash
curl -X POST "https://yoursite.com/wp-json/ict/v1/health/test-sync" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "workflow": "quote_to_project",
    "entity_id": 123
  }' | jq
```

**Test Full Workflow (QuoteWerks → WP → Zoho):**
```bash
curl -X POST "https://yoursite.com/wp-json/ict/v1/health/test-sync" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "workflow": "full_workflow",
    "entity_id": 123
  }' | jq
```

**Test Project to CRM Sync:**
```bash
curl -X POST "https://yoursite.com/wp-json/ict/v1/health/test-sync" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "workflow": "project_to_crm",
    "entity_id": 456
  }' | jq
```

### Database Queries

**Check Sync Queue Status:**
```sql
SELECT status, COUNT(*) as count
FROM wp_ict_sync_queue
GROUP BY status;
```

**View Recent Sync Operations:**
```sql
SELECT
  entity_type,
  direction,
  status,
  created_at,
  ROUND(duration_ms, 2) as duration_ms
FROM wp_ict_sync_log
ORDER BY created_at DESC
LIMIT 20;
```

**Check Failed Syncs:**
```sql
SELECT
  entity_type,
  error_message,
  created_at
FROM wp_ict_sync_log
WHERE status = 'error'
ORDER BY created_at DESC
LIMIT 10;
```

**Find Projects Not Synced to Zoho:**
```sql
SELECT id, project_name, created_at
FROM wp_ict_projects
WHERE zoho_crm_id IS NULL
ORDER BY created_at DESC;
```

**View Active Time Entries:**
```sql
SELECT
  te.id,
  u.display_name,
  te.clock_in,
  TIMESTAMPDIFF(HOUR, te.clock_in, NOW()) as hours_clocked
FROM wp_ict_time_entries te
JOIN wp_users u ON te.user_id = u.ID
WHERE te.clock_out IS NULL;
```

**Check Today's Activity:**
```sql
SELECT
  'Projects Created' as metric,
  COUNT(*) as count
FROM wp_ict_projects
WHERE DATE(created_at) = CURDATE()
UNION ALL
SELECT 'Time Entries', COUNT(*)
FROM wp_ict_time_entries
WHERE DATE(created_at) = CURDATE()
UNION ALL
SELECT 'Expenses Submitted', COUNT(*)
FROM wp_ict_expenses
WHERE DATE(created_at) = CURDATE();
```

### WordPress CLI Commands

**Process Sync Queue Manually:**
```bash
wp cron event run ict_process_sync_queue
```

**List Scheduled Cron Events:**
```bash
wp cron event list
```

**Check Plugin Status:**
```bash
wp plugin status ict-platform
```

**Verify Database Tables:**
```bash
wp db query "SHOW TABLES LIKE 'wp_ict_%';"
```

### Manual Sync Operations

**Force Sync a Specific Project:**
```sql
UPDATE wp_ict_projects
SET sync_status = 'pending'
WHERE id = 123;
```

**Add Item to Sync Queue:**
```sql
INSERT INTO wp_ict_sync_queue (
  entity_type, entity_id, action, zoho_service, priority, status
) VALUES (
  'project', 123, 'update', 'crm', 5, 'pending'
);
```

**Clear Failed Sync Items:**
```sql
DELETE FROM wp_ict_sync_queue
WHERE status = 'failed'
AND attempts >= 3;
```

### Monitoring Commands

**Watch Sync Queue in Real-Time:**
```bash
watch -n 5 'mysql -u user -p -e "SELECT status, COUNT(*) FROM wp_ict_sync_queue GROUP BY status;"'
```

**Monitor API Health:**
```bash
watch -n 10 'curl -s https://yoursite.com/wp-json/ict/v1/health | jq .status'
```

**Tail PHP Error Log:**
```bash
tail -f /var/log/php-errors.log
```

**Tail WordPress Debug Log:**
```bash
tail -f wp-content/debug.log
```

### Mobile App Testing

**Test Login:**
```bash
curl -X POST "https://yoursite.com/wp-json/ict/v1/auth/login" \
  -H "Content-Type: application/json" \
  -d '{
    "username": "technician1",
    "password": "password"
  }' | jq
```

**Test Refresh Token:**
```bash
curl -X POST "https://yoursite.com/wp-json/ict/v1/auth/refresh" \
  -H "Content-Type: application/json" \
  -d '{
    "refresh_token": "YOUR_REFRESH_TOKEN"
  }' | jq
```

**Test Location Tracking:**
```bash
curl -X POST "https://yoursite.com/wp-json/ict/v1/location/track" \
  -H "Authorization: Bearer YOUR_JWT_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "time_entry_id": 123,
    "latitude": 40.7128,
    "longitude": -74.0060,
    "accuracy": 10,
    "timestamp": "2024-01-15T10:30:00"
  }' | jq
```

## 🔧 Maintenance Tasks

### Daily

**Morning Health Check:**
```bash
# 1. Check overall health
curl -s https://yoursite.com/wp-json/ict/v1/health \
  -H "Authorization: Bearer TOKEN" | jq .status

# 2. Check sync queue
mysql -u user -p -e "
SELECT status, COUNT(*) FROM wp_ict_sync_queue GROUP BY status;
"

# 3. Review overnight errors
mysql -u user -p -e "
SELECT entity_type, error_message, COUNT(*) as count
FROM wp_ict_sync_log
WHERE status='error'
AND created_at >= DATE_SUB(NOW(), INTERVAL 12 HOUR)
GROUP BY entity_type, error_message;
"
```

### Weekly

**Database Optimization:**
```sql
OPTIMIZE TABLE wp_ict_sync_log;
OPTIMIZE TABLE wp_ict_location_tracking;
OPTIMIZE TABLE wp_ict_sync_queue;
```

**Check Token Expiration:**
```sql
SELECT
  option_name,
  DATE_ADD(FROM_UNIXTIME(
    CAST(JSON_UNQUOTE(JSON_EXTRACT(option_value, '$.expires_at')) AS UNSIGNED)
  ), INTERVAL 0 SECOND) as expires_at
FROM wp_options
WHERE option_name LIKE '%zoho%token%';
```

### Monthly

**Clean Old Logs (automated but can run manually):**
```sql
DELETE FROM wp_ict_sync_log
WHERE created_at < DATE_SUB(NOW(), INTERVAL 30 DAY);

DELETE FROM wp_ict_location_tracking
WHERE recorded_at < DATE_SUB(NOW(), INTERVAL 90 DAY);
```

**Database Size Check:**
```sql
SELECT
  table_name,
  ROUND(((data_length + index_length) / 1024 / 1024), 2) as size_mb,
  table_rows
FROM information_schema.tables
WHERE table_schema = 'your_database_name'
AND table_name LIKE 'wp_ict_%'
ORDER BY size_mb DESC;
```

## 🚨 Emergency Procedures

### Disable All Sync Operations

**Method 1: wp-config.php**
```php
define('ICT_DISABLE_SYNC', true);
```

**Method 2: Database**
```sql
UPDATE wp_options
SET option_value = '0'
WHERE option_name = 'ict_zoho_sync_enabled';
```

### Clear Stuck Sync Queue

```sql
-- Reset processing items back to pending
UPDATE wp_ict_sync_queue
SET status = 'pending'
WHERE status = 'processing'
AND updated_at < DATE_SUB(NOW(), INTERVAL 1 HOUR);

-- Delete all failed items
DELETE FROM wp_ict_sync_queue
WHERE status = 'failed';
```

### Restore from Backup

```bash
# Stop WordPress (if using Apache)
sudo service apache2 stop

# Restore database
mysql -u username -p database_name < backup.sql

# Restore files
tar -xzf backup.tar.gz -C /path/to/wordpress

# Start WordPress
sudo service apache2 start
```

### Reset Zoho OAuth

```sql
-- Clear all Zoho tokens
DELETE FROM wp_options
WHERE option_name LIKE '%zoho%token%';

-- Clear Zoho credentials (will need to re-enter)
DELETE FROM wp_options
WHERE option_name LIKE '%zoho%client%';
```

Then re-authorize in Settings → Zoho → each service.

## 📊 Performance Benchmarks

**Typical Values:**
- API Response Time: 300-1500ms
- Sync Queue Processing: 15-20 items per run
- QuoteWerks → WordPress: 5-10 seconds
- WordPress → Zoho CRM: 10-15 seconds
- WordPress → Zoho FSM: 10-15 seconds
- GPS Location Upload: 200-500ms
- Mobile App Login: 500-1000ms

**Warning Thresholds:**
- Sync Queue > 50 pending items
- Failed syncs > 10 in last hour
- API response > 3 seconds
- Sync success rate < 90%

**Critical Thresholds:**
- Sync Queue > 100 pending items
- Failed syncs > 25 in last hour
- API response > 5 seconds
- Sync success rate < 80%

## 🔑 Important File Locations

**Plugin Files:**
- Main plugin: `wp-content/plugins/wp-ict-platform/ict-platform.php`
- QuoteWerks adapter: `includes/integrations/class-ict-quotewerks-adapter.php`
- Sync orchestrator: `includes/sync/class-ict-sync-orchestrator.php`
- Health controller: `api/rest/class-ict-rest-health-controller.php`

**Configuration:**
- WordPress config: `wp-config.php`
- Plugin settings: WordPress Admin → ICT Platform → Settings

**Logs:**
- PHP errors: `/var/log/php-errors.log`
- WordPress debug: `wp-content/debug.log`
- Sync log: Database table `wp_ict_sync_log`
- Webhook log: Database table `wp_ict_webhook_log`

**Mobile App:**
- Config: `ict-mobile-app/.env`
- Location service: `ict-mobile-app/src/services/locationService.ts`

## 📚 Quick Links

- [Deployment Checklist](DEPLOYMENT_CHECKLIST.md)
- [Launch Guide](LAUNCH_GUIDE.md)
- [Troubleshooting Guide](TROUBLESHOOTING_GUIDE.md)
- [Launch Ready Summary](LAUNCH_READY_SUMMARY.md)
- [Main README](README.md)

---

**Keep this guide handy for daily operations!**

🤖 Generated with [Claude Code](https://claude.com/claude-code)
