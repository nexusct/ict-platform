# ICT Platform - Troubleshooting Guide

## Table of Contents

1. [QuoteWerks Integration Issues](#quotewerks-integration-issues)
2. [Zoho Integration Issues](#zoho-integration-issues)
3. [Sync Problems](#sync-problems)
4. [Mobile App Issues](#mobile-app-issues)
5. [Performance Issues](#performance-issues)
6. [Database Problems](#database-problems)
7. [Authentication Issues](#authentication-issues)
8. [Common Error Messages](#common-error-messages)

---

## QuoteWerks Integration Issues

### Problem: QuoteWerks webhook not triggering

**Symptoms:**
- Quotes created in QuoteWerks don't appear in WordPress
- No entries in sync log for QuoteWerks events

**Diagnosis:**
```bash
# Check webhook log table
mysql -u username -p -e "SELECT * FROM wp_ict_webhook_log WHERE source='quotewerks' ORDER BY received_at DESC LIMIT 10;"
```

**Solutions:**

1. **Verify Webhook Configuration in QuoteWerks:**
   - URL should be: `https://yoursite.com/wp-json/ict/v1/webhooks/quotewerks`
   - Secret key matches what's in WordPress settings
   - Webhook is enabled

2. **Check SSL Certificate:**
   ```bash
   # Test SSL
   curl -v https://yoursite.com/wp-json/ict/v1/webhooks/quotewerks
   ```
   - Must have valid SSL certificate
   - QuoteWerks requires HTTPS

3. **Test Webhook Manually:**
   ```bash
   curl -X POST "https://yoursite.com/wp-json/ict/v1/webhooks/quotewerks" \
     -H "Content-Type: application/json" \
     -H "X-QuoteWerks-Event: quote.created" \
     -H "X-QuoteWerks-Signature: your_hmac_signature" \
     -d '{"quote": {"doc_number": "TEST123"}}'
   ```

4. **Check PHP Error Log:**
   ```bash
   tail -f /var/log/php-errors.log
   ```

5. **Verify Firewall/Security:**
   - Ensure server allows incoming webhooks
   - Check ModSecurity rules
   - Verify IP whitelisting (if configured)

### Problem: Quote data not syncing correctly

**Symptoms:**
- Quote appears in WordPress but data is incomplete
- Line items missing
- Customer information not synced

**Solutions:**

1. **Check QuoteWerks API Response:**
   - Enable debug mode in plugin settings
   - Check sync log for full API response
   ```sql
   SELECT request_data, response_data FROM wp_ict_sync_log
   WHERE entity_type='quote'
   ORDER BY created_at DESC LIMIT 1;
   ```

2. **Verify Field Mappings:**
   - Check `class-ict-quotewerks-adapter.php` line 250-300
   - Ensure all required fields are mapped
   - Verify QuoteWerks field names match

3. **Check for Missing Data:**
   ```php
   // Add to wp-config.php temporarily
   define('WP_DEBUG', true);
   define('WP_DEBUG_LOG', true);
   ```

### Problem: QuoteWerks authentication failing

**Symptoms:**
- "Authentication failed" in sync log
- 401 Unauthorized errors

**Solutions:**

1. **Verify API Credentials:**
   - Settings → QuoteWerks → Test Connection
   - Ensure API key is valid and not expired
   - Check username/password if using basic auth

2. **Test Connection Directly:**
   ```bash
   curl -X GET "https://quotewerks-api.example.com/api/quotes" \
     -H "Authorization: Bearer YOUR_API_KEY"
   ```

3. **Check API Endpoint:**
   - Verify API URL is correct
   - Ensure no trailing slashes
   - Check if API version changed

---

## Zoho Integration Issues

### Problem: Zoho OAuth authentication failing

**Symptoms:**
- "Invalid client" error
- "Redirect URI mismatch"
- Tokens not refreshing

**Solutions:**

1. **Verify OAuth Configuration:**
   - Client ID and Secret match Zoho console
   - Redirect URI exactly matches: `https://yoursite.com/wp-admin/`
   - No trailing slashes or extra paths

2. **Re-authorize:**
   - Settings → Zoho CRM → Disconnect
   - Delete stored tokens
   - Re-authorize from scratch

3. **Check Token Expiry:**
   ```sql
   SELECT * FROM wp_options WHERE option_name LIKE '%zoho%token%';
   ```
   - Access tokens expire after 1 hour
   - Refresh tokens should auto-refresh
   - Check `class-ict-zoho-api-client.php` refresh logic

4. **Verify Scopes:**
   - Ensure all required scopes are enabled in Zoho console
   - CRM: `ZohoCRM.modules.ALL,ZohoCRM.settings.ALL`
   - FSM: `Zoho.FSM.modules.ALL`
   - Books: `ZohoBooks.fullaccess.all`

### Problem: Zoho API rate limiting

**Symptoms:**
- "Rate limit exceeded" errors
- Sync operations failing intermittently
- 429 HTTP status codes

**Solutions:**

1. **Check Current Rate Limit:**
   ```sql
   SELECT COUNT(*) as requests
   FROM wp_ict_sync_log
   WHERE zoho_service='crm'
   AND created_at >= DATE_SUB(NOW(), INTERVAL 1 MINUTE);
   ```
   - Zoho allows 60 requests/minute per service

2. **Implement Backoff Strategy:**
   - Sync engine already has rate limiting
   - Increase sync interval if needed
   ```php
   // In wp-config.php
   define('ICT_SYNC_INTERVAL', 20); // minutes
   ```

3. **Prioritize Sync Queue:**
   ```sql
   UPDATE wp_ict_sync_queue
   SET priority=1
   WHERE entity_type='project' AND action='create';
   ```

4. **Distribute Load:**
   - Sync different services at different times
   - Use scheduled cron jobs instead of realtime

### Problem: Zoho Books organization ID error

**Symptoms:**
- "Organization ID not configured"
- Books sync failing

**Solutions:**

1. **Get Organization ID:**
   ```bash
   curl "https://www.zohoapis.com/books/v3/organizations" \
     -H "Authorization: Zoho-oauthtoken YOUR_TOKEN"
   ```

2. **Configure in Settings:**
   - Settings → Zoho Books → Organization ID
   - Enter the ID from API response

3. **Verify in Database:**
   ```sql
   SELECT option_value FROM wp_options
   WHERE option_name='ict_zoho_books_organization_id';
   ```

---

## Sync Problems

### Problem: Sync queue building up

**Symptoms:**
- Pending queue items > 50
- Syncs not processing
- System slow

**Diagnosis:**
```sql
SELECT status, COUNT(*) as count
FROM wp_ict_sync_queue
GROUP BY status;
```

**Solutions:**

1. **Check Cron Status:**
   ```bash
   wp cron event list --path=/path/to/wordpress
   ```
   - Verify `ict_process_sync_queue` is scheduled
   - Check last run time

2. **Manual Sync Processing:**
   ```bash
   wp cron event run ict_process_sync_queue --path=/path/to/wordpress
   ```

3. **Clear Failed Items:**
   ```sql
   DELETE FROM wp_ict_sync_queue
   WHERE status='failed'
   AND attempts >= 3
   AND created_at < DATE_SUB(NOW(), INTERVAL 24 HOUR);
   ```

4. **Increase Processing Limit:**
   ```php
   // In class-ict-sync-engine.php
   // Change from 20 to 50
   public function process_queue( $limit = 50 ) {
   ```

5. **Check for Blocking Issues:**
   - Review error logs
   - Check database locks
   - Verify API connections

### Problem: Data not syncing bidirectionally

**Symptoms:**
- Updates in Zoho don't reflect in WordPress
- Changes in WordPress don't sync to Zoho

**Solutions:**

1. **Check Sync Direction:**
   ```sql
   SELECT direction, COUNT(*)
   FROM wp_ict_sync_log
   GROUP BY direction;
   ```
   - Should see both 'inbound' and 'outbound'

2. **Verify Webhooks (Inbound):**
   - Zoho webhooks must be configured
   - Check Zoho console for webhook settings

3. **Check Sync Timestamps:**
   ```sql
   SELECT * FROM wp_ict_projects
   WHERE last_synced IS NULL
   OR last_synced < DATE_SUB(NOW(), INTERVAL 1 HOUR);
   ```

4. **Force Re-sync:**
   ```sql
   UPDATE wp_ict_projects
   SET sync_status='pending'
   WHERE id=123;
   ```

### Problem: Duplicate records being created

**Symptoms:**
- Same quote creating multiple projects
- Duplicate Zoho records

**Solutions:**

1. **Check Zoho ID Mapping:**
   ```sql
   SELECT id, quotewerks_id, zoho_crm_id
   FROM wp_ict_projects
   WHERE quotewerks_id='QUOTE123';
   ```
   - Should only return one record

2. **Verify Unique Constraints:**
   ```sql
   SHOW INDEX FROM wp_ict_projects;
   ```
   - Should have unique index on `quotewerks_id`

3. **Add Missing Index:**
   ```sql
   ALTER TABLE wp_ict_projects
   ADD UNIQUE INDEX idx_quotewerks_id (quotewerks_id);
   ```

4. **Clean Up Duplicates:**
   ```sql
   -- Find duplicates
   SELECT quotewerks_id, COUNT(*) as count
   FROM wp_ict_projects
   GROUP BY quotewerks_id
   HAVING count > 1;

   -- Manual cleanup required - keep newest record
   ```

---

## Mobile App Issues

### Problem: Mobile app login failing

**Symptoms:**
- "Invalid credentials" error
- JWT token not returned

**Solutions:**

1. **Test Login Endpoint:**
   ```bash
   curl -X POST "https://yoursite.com/wp-json/ict/v1/auth/login" \
     -H "Content-Type: application/json" \
     -d '{"username": "testuser", "password": "password"}'
   ```

2. **Verify User Credentials:**
   - Ensure user exists in WordPress
   - User has correct role (ict_technician or higher)
   - Password is correct

3. **Check JWT Secret:**
   ```sql
   SELECT option_value FROM wp_options
   WHERE option_name='ict_jwt_secret_key';
   ```
   - If empty, regenerate in settings

4. **Check CORS Headers:**
   ```apache
   Header set Access-Control-Allow-Origin "*"
   Header set Access-Control-Allow-Methods "GET, POST, PUT, DELETE, OPTIONS"
   ```

### Problem: GPS tracking not working

**Symptoms:**
- Location not being saved
- No location data in database

**Solutions:**

1. **Check Location Table:**
   ```sql
   SELECT * FROM wp_ict_location_tracking
   ORDER BY recorded_at DESC LIMIT 10;
   ```

2. **Verify Permissions (Mobile):**
   - iOS: Location "Always" permission granted
   - Android: Background location permission granted

3. **Test Location Endpoint:**
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
     }'
   ```

4. **Check Background Service:**
   - Verify react-native-background-geolocation is running
   - Check mobile app logs for errors

### Problem: File uploads failing

**Symptoms:**
- "Upload failed" error
- Files not appearing in WorkDrive

**Solutions:**

1. **Check Upload Size Limit:**
   ```php
   // In php.ini
   upload_max_filesize = 32M
   post_max_size = 32M
   ```

2. **Verify WorkDrive OAuth:**
   - Settings → Zoho WorkDrive → Test Connection
   - Re-authorize if expired

3. **Test Upload Endpoint:**
   ```bash
   curl -X POST "https://yoursite.com/wp-json/ict/v1/files/upload" \
     -H "Authorization: Bearer YOUR_JWT_TOKEN" \
     -F "file=@test.jpg" \
     -F "project_id=123"
   ```

4. **Check File Permissions:**
   ```bash
   ls -la wp-content/uploads/
   chmod 755 wp-content/uploads/
   ```

---

## Performance Issues

### Problem: Slow API responses

**Symptoms:**
- API calls taking > 3 seconds
- Timeouts

**Solutions:**

1. **Enable Query Monitoring:**
   ```php
   define('SAVEQUERIES', true);
   ```

2. **Check Slow Queries:**
   ```sql
   SHOW PROCESSLIST;
   ```

3. **Add Missing Indexes:**
   ```sql
   -- Projects table
   ALTER TABLE wp_ict_projects ADD INDEX idx_status (status);
   ALTER TABLE wp_ict_projects ADD INDEX idx_zoho_crm_id (zoho_crm_id);

   -- Sync queue
   ALTER TABLE wp_ict_sync_queue ADD INDEX idx_status_priority (status, priority);
   ```

4. **Enable Object Caching:**
   ```bash
   # Install Redis
   sudo apt-get install redis-server php-redis

   # Install WordPress Redis plugin
   wp plugin install redis-cache --activate
   ```

5. **Optimize Database:**
   ```sql
   OPTIMIZE TABLE wp_ict_projects;
   OPTIMIZE TABLE wp_ict_sync_queue;
   OPTIMIZE TABLE wp_ict_sync_log;
   ```

### Problem: High memory usage

**Solutions:**

1. **Increase PHP Memory:**
   ```php
   // wp-config.php
   define('WP_MEMORY_LIMIT', '512M');
   define('WP_MAX_MEMORY_LIMIT', '768M');
   ```

2. **Optimize Sync Processing:**
   - Process queue in smaller batches
   - Add delays between API calls

3. **Clean Up Logs:**
   ```sql
   DELETE FROM wp_ict_sync_log
   WHERE created_at < DATE_SUB(NOW(), INTERVAL 30 DAY);
   ```

---

## Database Problems

### Problem: Missing tables

**Solutions:**

1. **Verify Tables:**
   ```sql
   SHOW TABLES LIKE 'wp_ict_%';
   ```

2. **Recreate Tables:**
   - Deactivate plugin
   - Reactivate plugin (runs activation hook)
   - Or manually run: `ICT_Activator::activate()`

3. **Manual Table Creation:**
   - Check `class-ict-activator.php`
   - Copy CREATE TABLE statements
   - Run manually in MySQL

### Problem: Database connection errors

**Solutions:**

1. **Check Credentials:**
   ```php
   // wp-config.php
   define('DB_NAME', 'database_name');
   define('DB_USER', 'username');
   define('DB_PASSWORD', 'password');
   define('DB_HOST', 'localhost');
   ```

2. **Test Connection:**
   ```bash
   mysql -h localhost -u username -p database_name
   ```

3. **Check Max Connections:**
   ```sql
   SHOW VARIABLES LIKE 'max_connections';
   SET GLOBAL max_connections = 200;
   ```

---

## Authentication Issues

### Problem: JWT tokens expiring too quickly

**Solutions:**

1. **Increase Token Lifetime:**
   ```php
   // In class-ict-rest-auth-controller.php
   $expires_at = $issued_at + ( 24 * HOUR_IN_SECONDS ); // 24 hours
   ```

2. **Implement Refresh Token:**
   - Mobile app should use refresh token endpoint
   - `/auth/refresh` endpoint already implemented

3. **Check Token in Database:**
   ```sql
   SELECT * FROM wp_usermeta
   WHERE meta_key='ict_refresh_token'
   AND user_id=123;
   ```

---

## Common Error Messages

### "Failed to create record in Zoho CRM"

**Cause:** API request to Zoho failed

**Fix:**
1. Check Zoho API status
2. Verify OAuth token is valid
3. Check required fields are present
4. Review Zoho API error in sync log

### "Entity not found in Zoho"

**Cause:** Trying to update record that doesn't exist

**Fix:**
1. Check `zoho_crm_id` field in database
2. Verify record wasn't deleted in Zoho
3. Re-create record instead of update

### "QuoteWerks API credentials not configured"

**Cause:** Missing API key or URL

**Fix:**
1. Settings → QuoteWerks
2. Enter API URL and Key
3. Save and test connection

### "Sync queue processing failed"

**Cause:** Error in sync engine

**Fix:**
1. Check PHP error log
2. Verify database connection
3. Check API credentials
4. Review specific error in sync log

### "Invalid webhook signature"

**Cause:** Webhook secret mismatch

**Fix:**
1. Regenerate secret in Settings
2. Update secret in QuoteWerks
3. Test webhook

---

## Emergency Procedures

### Complete System Failure

1. **Disable Sync:**
   ```php
   // wp-config.php
   define('ICT_DISABLE_SYNC', true);
   ```

2. **Check Error Logs:**
   ```bash
   tail -n 100 /var/log/php-errors.log
   tail -n 100 wp-content/debug.log
   ```

3. **Database Backup:**
   ```bash
   mysqldump -u username -p database_name > backup_$(date +%Y%m%d_%H%M%S).sql
   ```

4. **Contact Support:**
   - Gather error logs
   - Note time of failure
   - Document steps to reproduce

---

## Getting Help

**Before Contacting Support:**

1. Check this troubleshooting guide
2. Review error logs
3. Test with health check endpoint
4. Document the issue (screenshots, error messages)
5. Note WordPress, PHP, and plugin versions

**Information to Provide:**

- WordPress version
- PHP version
- Plugin version
- Error messages (exact text)
- Steps to reproduce
- When issue started
- Recent changes made

**Health Check Report:**
```bash
curl -X GET "https://yoursite.com/wp-json/ict/v1/health" \
  -H "Authorization: Bearer YOUR_TOKEN" > health_report.json
```

Send `health_report.json` along with support request.
