# ICT Platform - Launch Guide

## Executive Summary

The ICT Platform is ready for production deployment. This guide covers the complete launch process, from final pre-launch checks through post-launch monitoring.

**Platform Components:**
- WordPress Plugin (Backend & Admin)
- React Native Mobile App (iOS & Android)
- QuoteWerks Integration
- Zoho Suite Integration (CRM, FSM, Books, People, Desk, WorkDrive)

**Launch Timeline:**
- T-24 hours: Final testing & validation
- T-2 hours: Final backup & preparation
- T-0: Go-live
- T+24 hours: Monitoring & validation

---

## Pre-Launch Summary

### Completed Components

#### WordPress Plugin (100% Complete)

**Phase 1-3: Foundation & Integrations**
- ✅ Core plugin architecture
- ✅ Database schema (10 custom tables)
- ✅ REST API framework
- ✅ Zoho OAuth integration (all 5 services)
- ✅ QuoteWerks adapter with webhook support

**Phase 4: Time Tracking**
- ✅ Clock in/out system
- ✅ GPS location capture
- ✅ Approval workflows
- ✅ Sync with Zoho People

**Phase 5: Resource Management**
- ✅ Technician allocation
- ✅ Skills matrix
- ✅ Calendar integration
- ✅ Capacity planning

**Phase 6: Inventory & Procurement**
- ✅ Stock management
- ✅ Purchase order workflow
- ✅ Approval process
- ✅ Sync with Zoho Books

**Phase 7: Reports & Analytics**
- ✅ Dashboard with metrics
- ✅ Custom charts (no external dependencies)
- ✅ Project, time, budget, and inventory reports
- ✅ Export functionality

#### Mobile App (100% Complete)

**Core Features:**
- ✅ JWT authentication with refresh tokens
- ✅ Time tracking with live timer
- ✅ Background GPS tracking (5-minute intervals)
- ✅ Smart task reminders (0.5 mile / 30 min threshold)
- ✅ Project & task management
- ✅ Expense submission with receipts
- ✅ File uploads to Zoho WorkDrive
- ✅ Schedule calendar view
- ✅ Push notifications

**Screens:**
- ✅ 18 screens fully implemented
- ✅ Redux state management
- ✅ Offline capability (time tracking)

#### Integration Layer (100% Complete)

**QuoteWerks:**
- ✅ Bidirectional sync adapter
- ✅ Webhook handler (6 event types)
- ✅ Quote to project conversion
- ✅ Line items sync to inventory
- ✅ Customer sync

**Zoho Services:**
- ✅ CRM: Deals ↔ Projects
- ✅ FSM: Work Orders ↔ Projects
- ✅ Books: Inventory & POs
- ✅ People: Time tracking
- ✅ Desk: Support tickets
- ✅ WorkDrive: File storage

**Sync Orchestration:**
- ✅ Sync queue with priority
- ✅ Error handling & retry logic
- ✅ Health monitoring
- ✅ Rate limiting
- ✅ Bidirectional sync

---

## Launch Day Timeline

### T-24 Hours: Final Validation

**Database Backup:**
```bash
# Full database backup
mysqldump -u username -p database_name > ict_platform_pre_launch_$(date +%Y%m%d).sql

# Verify backup
mysql -u username -p -e "USE database_name; SHOW TABLES LIKE 'wp_ict_%';"
```

**File Backup:**
```bash
# Backup plugin files
tar -czf wp-ict-platform-backup-$(date +%Y%m%d).tar.gz wp-content/plugins/wp-ict-platform/

# Backup uploads
tar -czf uploads-backup-$(date +%Y%m%d).tar.gz wp-content/uploads/
```

**Health Check:**
```bash
# Run comprehensive health check
curl -X GET "https://yoursite.com/wp-json/ict/v1/health" \
  -H "Authorization: Bearer YOUR_ADMIN_TOKEN" | jq
```

Expected output:
```json
{
  "status": "healthy",
  "timestamp": "2024-01-15 10:00:00",
  "version": "1.0.0",
  "checks": {
    "database": {
      "healthy": true,
      "tables": {
        "projects": {"exists": true, "count": 0},
        "time_entries": {"exists": true, "count": 0},
        ...
      }
    },
    "zoho": {
      "healthy_count": 5,
      "total_count": 5,
      "services": {
        "crm": {"healthy": true, "message": "Connected"},
        "fsm": {"healthy": true, "message": "Connected"},
        ...
      }
    },
    "quotewerks": {
      "healthy": true,
      "configured": true,
      "message": "Connected successfully"
    },
    "sync": {
      "status": "healthy",
      "queue": {
        "pending": 0,
        "processing": 0,
        "failed": 0
      }
    }
  }
}
```

**Test All Integrations:**

1. **QuoteWerks Test:**
```bash
# Test quote sync
curl -X POST "https://yoursite.com/wp-json/ict/v1/health/test-sync" \
  -H "Authorization: Bearer TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"workflow": "quote_to_project", "entity_id": 1}'
```

2. **Zoho CRM Test:**
```bash
# Test project to CRM sync
curl -X POST "https://yoursite.com/wp-json/ict/v1/health/test-sync" \
  -H "Authorization: Bearer TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"workflow": "project_to_crm", "entity_id": 1}'
```

3. **Full Workflow Test:**
```bash
# Test end-to-end workflow
curl -X POST "https://yoursite.com/wp-json/ict/v1/health/test-sync" \
  -H "Authorization: Bearer TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"workflow": "full_workflow", "entity_id": 1}'
```

**Mobile App Test:**
```bash
# Test mobile login
curl -X POST "https://yoursite.com/wp-json/ict/v1/auth/login" \
  -H "Content-Type: application/json" \
  -d '{"username": "technician1", "password": "test_password"}'

# Should return JWT token
```

**Performance Baseline:**
```bash
# Measure API response times
for endpoint in health projects time-entries inventory; do
  echo "Testing $endpoint..."
  time curl -s "https://yoursite.com/wp-json/ict/v1/$endpoint" \
    -H "Authorization: Bearer TOKEN" > /dev/null
done
```

Target response times:
- Health endpoint: < 1 second
- List endpoints: < 2 seconds
- Create/Update: < 3 seconds

### T-2 Hours: Final Preparation

**1. Clear All Test Data:**
```sql
-- Clear sync queue
TRUNCATE TABLE wp_ict_sync_queue;

-- Clear sync logs
TRUNCATE TABLE wp_ict_sync_log;

-- Clear webhook logs
TRUNCATE TABLE wp_ict_webhook_log;

-- Do NOT clear: projects, time_entries, inventory (if you have production data)
```

**2. Verify Cron Jobs:**
```bash
# Check scheduled events
wp cron event list

# Expected events:
# - ict_process_sync_queue (every 15 minutes)
# - ict_cleanup_sync_logs (daily)
# - ict_sync_from_zoho (hourly)
```

**3. Enable Production Mode:**
```php
// wp-config.php
define('WP_DEBUG', false);
define('WP_DEBUG_LOG', false);
define('WP_DEBUG_DISPLAY', false);
define('SCRIPT_DEBUG', false);
```

**4. Verify Security:**
```bash
# Check SSL certificate
curl -vI https://yoursite.com 2>&1 | grep -i "SSL certificate verify ok"

# Verify HTTPS redirect
curl -I http://yoursite.com | grep -i "location"

# Check file permissions
find wp-content/plugins/wp-ict-platform -type f -exec chmod 644 {} \;
find wp-content/plugins/wp-ict-platform -type d -exec chmod 755 {} \;
```

**5. Start Monitoring:**
```bash
# Monitor error log
tail -f /var/log/php-errors.log &

# Monitor database
watch -n 5 'mysql -u user -p -e "SELECT status, COUNT(*) FROM wp_ict_sync_queue GROUP BY status"'

# Monitor API
watch -n 10 'curl -s https://yoursite.com/wp-json/ict/v1/health | jq .status'
```

### T-0: Launch

**1. Enable QuoteWerks Webhook:**

In QuoteWerks webhook configuration:
- [ ] Enable webhook
- [ ] Verify URL: `https://yoursite.com/wp-json/ict/v1/webhooks/quotewerks`
- [ ] Verify events: quote.created, quote.updated, quote.approved, quote.converted, order.created
- [ ] Save configuration

**2. Verify Zoho Sync is Active:**
```sql
SELECT * FROM wp_options WHERE option_name='ict_zoho_sync_enabled';
-- Should be '1'
```

**3. Test First Real Transaction:**

**Create a test quote in QuoteWerks:**
- Quote number: LAUNCH-TEST-001
- Customer: Test Customer
- Line items: 2-3 items
- Submit/Save quote

**Verify Sync Flow:**
```bash
# Wait 30 seconds, then check webhook log
mysql -u user -p -e "
SELECT event_type, received_at
FROM wp_ict_webhook_log
WHERE source='quotewerks'
ORDER BY received_at DESC LIMIT 1;
"

# Check if project was created
mysql -u user -p -e "
SELECT id, project_name, quotewerks_id, sync_status
FROM wp_ict_projects
WHERE quotewerks_id='LAUNCH-TEST-001';
"

# Check sync queue
mysql -u user -p -e "
SELECT entity_type, action, status
FROM wp_ict_sync_queue
ORDER BY created_at DESC LIMIT 5;
"

# Check sync log
mysql -u user -p -e "
SELECT entity_type, direction, status, created_at
FROM wp_ict_sync_log
ORDER BY created_at DESC LIMIT 5;
"
```

**Expected Flow:**
1. QuoteWerks → Webhook received (< 5 seconds)
2. Project created in WordPress (< 10 seconds)
3. Sync queued for Zoho CRM & FSM (< 15 seconds)
4. Deal created in Zoho CRM (< 30 seconds)
5. Work Order created in Zoho FSM (< 45 seconds)

**4. Mobile App Launch:**

**Test Mobile Login:**
- Open mobile app
- Enter credentials
- Verify login successful
- Check dashboard loads

**Test Time Tracking:**
- Clock in
- Verify GPS location captured
- Wait 5 minutes
- Check background tracking
- Clock out
- Verify entry syncs to WordPress

**Test Expense Submission:**
- Create expense
- Upload receipt photo
- Submit
- Verify appears in WordPress
- Check sync to Zoho Books

### T+1 Hour: Initial Monitoring

**Check Sync Health:**
```bash
curl -X GET "https://yoursite.com/wp-json/ict/v1/health/sync" \
  -H "Authorization: Bearer TOKEN" | jq
```

**Review Metrics:**
```sql
-- Sync success rate (last hour)
SELECT
  status,
  COUNT(*) as count,
  ROUND(COUNT(*) * 100.0 / SUM(COUNT(*)) OVER(), 2) as percentage
FROM wp_ict_sync_log
WHERE created_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)
GROUP BY status;

-- Average sync duration
SELECT
  AVG(duration_ms) as avg_duration_ms,
  MIN(duration_ms) as min_duration_ms,
  MAX(duration_ms) as max_duration_ms
FROM wp_ict_sync_log
WHERE created_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)
AND status='success';

-- Queue health
SELECT
  status,
  COUNT(*) as count,
  AVG(attempts) as avg_attempts
FROM wp_ict_sync_queue
GROUP BY status;
```

**Check for Errors:**
```sql
-- Recent errors
SELECT
  entity_type,
  action,
  error_message,
  created_at
FROM wp_ict_sync_log
WHERE status='error'
AND created_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)
ORDER BY created_at DESC;
```

**Performance Check:**
```bash
# API response times
ab -n 100 -c 10 -H "Authorization: Bearer TOKEN" \
  https://yoursite.com/wp-json/ict/v1/health
```

Target metrics:
- Requests per second: > 20
- Time per request: < 500ms
- Failed requests: 0

### T+4 Hours: Extended Monitoring

**User Activity Check:**
```sql
-- Active time entries
SELECT COUNT(*) as active_entries
FROM wp_ict_time_entries
WHERE clock_out IS NULL;

-- Projects synced today
SELECT COUNT(*) as synced_today
FROM wp_ict_projects
WHERE DATE(last_synced) = CURDATE();

-- Sync operations today
SELECT
  direction,
  zoho_service,
  status,
  COUNT(*) as count
FROM wp_ict_sync_log
WHERE DATE(created_at) = CURDATE()
GROUP BY direction, zoho_service, status;
```

**Data Consistency Check:**
```sql
-- Projects with Zoho IDs
SELECT
  COUNT(*) as total_projects,
  SUM(CASE WHEN zoho_crm_id IS NOT NULL THEN 1 ELSE 0 END) as synced_to_crm,
  SUM(CASE WHEN zoho_fsm_id IS NOT NULL THEN 1 ELSE 0 END) as synced_to_fsm
FROM wp_ict_projects;

-- Inventory sync status
SELECT
  COUNT(*) as total_items,
  SUM(CASE WHEN zoho_books_id IS NOT NULL THEN 1 ELSE 0 END) as synced_to_books
FROM wp_ict_inventory_items;
```

### T+24 Hours: Full Day Review

**Generate Health Report:**
```bash
# Get complete health status
curl -X GET "https://yoursite.com/wp-json/ict/v1/health" \
  -H "Authorization: Bearer TOKEN" | jq > health_report_day1.json

# Review report
cat health_report_day1.json
```

**24-Hour Metrics:**
```sql
-- Total sync operations
SELECT
  DATE_FORMAT(created_at, '%Y-%m-%d %H:00:00') as hour,
  status,
  COUNT(*) as count
FROM wp_ict_sync_log
WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
GROUP BY hour, status
ORDER BY hour;

-- Success rate by service
SELECT
  zoho_service,
  status,
  COUNT(*) as count,
  ROUND(AVG(duration_ms), 2) as avg_duration_ms
FROM wp_ict_sync_log
WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
GROUP BY zoho_service, status;

-- Error summary
SELECT
  error_message,
  COUNT(*) as occurrences
FROM wp_ict_sync_log
WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
AND status='error'
GROUP BY error_message
ORDER BY occurrences DESC;
```

**User Adoption Metrics:**
```sql
-- Mobile app usage
SELECT
  u.display_name,
  COUNT(DISTINCT te.id) as time_entries,
  COUNT(DISTINCT e.id) as expenses_submitted,
  COUNT(DISTINCT lt.id) as location_points
FROM wp_users u
LEFT JOIN wp_ict_time_entries te ON u.ID = te.user_id
  AND te.created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
LEFT JOIN wp_ict_expenses e ON u.ID = e.user_id
  AND e.created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
LEFT JOIN wp_ict_location_tracking lt ON u.ID = lt.user_id
  AND lt.recorded_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
WHERE u.ID IN (SELECT user_id FROM wp_usermeta WHERE meta_key='wp_capabilities' AND meta_value LIKE '%ict_technician%')
GROUP BY u.ID
ORDER BY time_entries DESC;
```

---

## Post-Launch Procedures

### Daily Tasks (First Week)

**Morning Check (9:00 AM):**
```bash
# Run daily health check
curl -X GET "https://yoursite.com/wp-json/ict/v1/health" \
  -H "Authorization: Bearer TOKEN" | jq .status

# Check overnight sync operations
mysql -u user -p -e "
SELECT
  status,
  COUNT(*) as count
FROM wp_ict_sync_log
WHERE created_at >= DATE_SUB(NOW(), INTERVAL 12 HOUR)
GROUP BY status;
"

# Review errors
mysql -u user -p -e "
SELECT
  entity_type,
  error_message,
  COUNT(*) as count
FROM wp_ict_sync_log
WHERE status='error'
AND created_at >= DATE_SUB(NOW(), INTERVAL 12 HOUR)
GROUP BY entity_type, error_message;
"
```

**Mid-Day Check (1:00 PM):**
```bash
# Check sync queue buildup
mysql -u user -p -e "
SELECT status, COUNT(*) FROM wp_ict_sync_queue GROUP BY status;
"

# If pending > 50, investigate
# If failed > 10, investigate

# Check API response times
time curl -s "https://yoursite.com/wp-json/ict/v1/projects" \
  -H "Authorization: Bearer TOKEN" > /dev/null
```

**End of Day Review (5:00 PM):**
```bash
# Generate daily report
mysql -u user -p << EOF > daily_report_$(date +%Y%m%d).txt
-- Sync Summary
SELECT 'Sync Summary' as report;
SELECT
  status,
  COUNT(*) as operations,
  ROUND(AVG(duration_ms), 2) as avg_ms
FROM wp_ict_sync_log
WHERE DATE(created_at) = CURDATE()
GROUP BY status;

-- Top Errors
SELECT 'Top Errors' as report;
SELECT
  error_message,
  COUNT(*) as count
FROM wp_ict_sync_log
WHERE DATE(created_at) = CURDATE()
AND status='error'
GROUP BY error_message
ORDER BY count DESC
LIMIT 10;

-- Activity Summary
SELECT 'Activity Summary' as report;
SELECT
  'Projects Created' as metric,
  COUNT(*) as count
FROM wp_ict_projects
WHERE DATE(created_at) = CURDATE()
UNION ALL
SELECT
  'Time Entries' as metric,
  COUNT(*) as count
FROM wp_ict_time_entries
WHERE DATE(created_at) = CURDATE()
UNION ALL
SELECT
  'Expenses Submitted' as metric,
  COUNT(*) as count
FROM wp_ict_expenses
WHERE DATE(created_at) = CURDATE();
EOF

# Review report
cat daily_report_$(date +%Y%m%d).txt
```

### Weekly Tasks

**Monday Morning:**
- Review previous week's metrics
- Check for recurring errors
- Verify all integrations still connected
- Review user feedback
- Check database size and optimize if needed

```sql
-- Database size check
SELECT
  table_name,
  ROUND(((data_length + index_length) / 1024 / 1024), 2) as size_mb
FROM information_schema.tables
WHERE table_schema = 'your_database_name'
AND table_name LIKE 'wp_ict_%'
ORDER BY size_mb DESC;

-- Optimize if needed
OPTIMIZE TABLE wp_ict_sync_log;
OPTIMIZE TABLE wp_ict_location_tracking;
```

### Monthly Tasks

**First of Month:**
- Full system health audit
- Review OAuth token expiration dates
- Update documentation if needed
- Security review
- Performance optimization review
- User training needs assessment

---

## Success Criteria

**Launch is considered successful if:**

✅ All health checks return "healthy" status
✅ Sync success rate > 95%
✅ API response times < 2 seconds
✅ No critical errors in first 24 hours
✅ QuoteWerks quotes sync within 1 minute
✅ Zoho sync completes within 5 minutes
✅ Mobile app login works for all users
✅ GPS tracking functioning correctly
✅ No data loss or corruption
✅ Database performance stable

**Key Performance Indicators:**

- **Sync Success Rate:** > 95%
- **Average Sync Time:** < 30 seconds
- **API Response Time:** < 2 seconds
- **Mobile App Adoption:** > 80% of technicians using within first week
- **Error Rate:** < 5% of total operations
- **User Satisfaction:** > 4/5 in initial feedback

---

## Rollback Procedure

**If launch fails (within first 4 hours):**

1. **Immediate Actions:**
```php
// Add to wp-config.php
define('ICT_DISABLE_SYNC', true);
```

2. **Disable Webhooks:**
- QuoteWerks: Disable webhook
- Zoho: Pause webhooks (if configured)

3. **Restore Database:**
```bash
mysql -u user -p database_name < ict_platform_pre_launch_YYYYMMDD.sql
```

4. **Notify Stakeholders:**
- Send email to project team
- Update status page
- Provide ETA for resolution

5. **Root Cause Analysis:**
- Review error logs
- Identify failure point
- Document issue
- Plan fix

6. **Reschedule Launch:**
- Fix issues
- Re-test thoroughly
- Set new launch date
- Communicate timeline

---

## Contact Information

**Technical Team:**
- Lead Developer: [Name, Email, Phone]
- System Administrator: [Name, Email, Phone]
- Database Administrator: [Name, Email, Phone]

**Vendor Support:**
- QuoteWerks Support: [Contact Info]
- Zoho Support: [Contact Info]
- Hosting Provider: [Contact Info]

**Escalation Path:**
1. Check Troubleshooting Guide
2. Contact Lead Developer
3. Contact Vendor Support (if integration issue)
4. Implement Rollback (if critical)

---

**Launch Authorization:**

Approved by: _____________________
Date: _____________________
Time: _____________________

**Go/No-Go Decision:**

☐ GO - Proceed with launch
☐ NO-GO - Delay launch, reason: _____________________

---

🤖 Generated with [Claude Code](https://claude.com/claude-code)
