# ICT Platform - Master Installation Guide
## Complete Setup & Deployment Documentation

**Version:** 1.0.0
**Last Updated:** 2025-10-13
**Support:** https://github.com/yourusername/ict-platform/issues

---

## 📋 Table of Contents

1. [System Requirements](#system-requirements)
2. [Pre-Installation Checklist](#pre-installation-checklist)
3. [Installation Methods](#installation-methods)
4. [Step-by-Step Installation](#step-by-step-installation)
5. [Zoho Integration Setup](#zoho-integration-setup)
6. [Initial Configuration](#initial-configuration)
7. [Database Optimization](#database-optimization)
8. [Security Hardening](#security-hardening)
9. [Performance Tuning](#performance-tuning)
10. [Troubleshooting](#troubleshooting)
11. [Backup & Recovery](#backup-recovery)
12. [Upgrade Path](#upgrade-path)

---

## 🖥️ System Requirements

### Minimum Requirements
| Component | Requirement |
|-----------|------------|
| **WordPress** | 6.4+ |
| **PHP** | 8.1+ |
| **MySQL** | 5.7+ or MariaDB 10.3+ |
| **Memory** | 256MB PHP memory limit |
| **Disk Space** | 500MB available |
| **Web Server** | Apache 2.4+ or Nginx 1.18+ |

### Recommended Requirements
| Component | Requirement |
|-----------|------------|
| **WordPress** | 6.4+ (latest) |
| **PHP** | 8.2+ |
| **MySQL** | 8.0+ or MariaDB 10.6+ |
| **Memory** | 512MB+ PHP memory limit |
| **Disk Space** | 2GB+ available |
| **Web Server** | Nginx 1.20+ with HTTP/2 |
| **SSL** | Valid SSL certificate |
| **Caching** | Redis or Memcached |

### PHP Extensions Required
```bash
- php-curl
- php-gd
- php-mbstring
- php-xml
- php-zip
- php-json
- php-mysqli
- php-openssl
- php-intl
```

### Optional But Recommended
```bash
- php-redis (for caching)
- php-imagick (for image processing)
- php-opcache (for performance)
```

---

## ✅ Pre-Installation Checklist

### Before You Begin

- [ ] WordPress installation is complete and accessible
- [ ] You have admin access to WordPress dashboard
- [ ] PHP version meets requirements (8.1+)
- [ ] MySQL/MariaDB database is accessible
- [ ] Server has adequate disk space (2GB+)
- [ ] Node.js 18+ and npm are installed (for development)
- [ ] Composer 2.0+ is installed (for development)
- [ ] SSL certificate is installed (recommended)
- [ ] Backup of current WordPress site exists
- [ ] Zoho accounts are created for integration
- [ ] You have FTP/SFTP access to server
- [ ] You have SSH access (recommended)

### Required Zoho Accounts
1. **Zoho CRM** - For project/deal management
2. **Zoho People** - For timesheet integration
3. **Zoho Books** - For inventory and invoicing
4. **Zoho FSM** - For field service management
5. **Zoho Desk** - For support ticket integration

---

## 📦 Installation Methods

### Method 1: Production Install (Recommended)

**Best for:** Live sites, production environments

```bash
# 1. Download release package
wget https://github.com/yourusername/ict-platform/releases/latest/download/ict-platform.zip

# 2. Extract to WordPress plugins directory
cd /path/to/wordpress/wp-content/plugins/
unzip ict-platform.zip

# 3. Set permissions
chmod -R 755 ict-platform
chown -R www-data:www-data ict-platform
```

### Method 2: Development Install

**Best for:** Development, customization

```bash
# 1. Clone repository
cd /path/to/wordpress/wp-content/plugins/
git clone https://github.com/yourusername/ict-platform.git
cd ict-platform

# 2. Install dependencies
npm install
composer install

# 3. Build assets
npm run build

# 4. Set permissions
chmod -R 755 .
```

### Method 3: WordPress Admin Upload

**Best for:** Shared hosting, no server access

1. Download `ict-platform.zip` from releases
2. Go to WordPress Admin → Plugins → Add New
3. Click "Upload Plugin"
4. Choose the ZIP file
5. Click "Install Now"
6. Click "Activate Plugin"

---

## 🚀 Step-by-Step Installation

### Step 1: Install Plugin Files

**Using FTP/SFTP:**
1. Connect to your server via FTP
2. Navigate to `/wp-content/plugins/`
3. Upload the `ict-platform` folder
4. Ensure all files are uploaded completely

**Using SSH:**
```bash
cd /var/www/html/wp-content/plugins/
wget https://github.com/yourusername/ict-platform/releases/latest/download/ict-platform.zip
unzip ict-platform.zip
rm ict-platform.zip
```

### Step 2: Set Correct Permissions

```bash
# Navigate to plugin directory
cd /var/www/html/wp-content/plugins/ict-platform

# Set directory permissions
find . -type d -exec chmod 755 {} \;

# Set file permissions
find . -type f -exec chmod 644 {} \;

# Set owner (replace www-data with your web server user)
chown -R www-data:www-data .
```

### Step 3: Activate Plugin

**Via WordPress Admin:**
1. Log into WordPress Admin
2. Go to **Plugins → Installed Plugins**
3. Find "ICT Platform"
4. Click "Activate"

**Via WP-CLI:**
```bash
wp plugin activate ict-platform
```

### Step 4: Verify Installation

Upon activation, the plugin will:
1. ✅ Create 7 custom database tables
2. ✅ Create 3 custom user roles
3. ✅ Set default options
4. ✅ Schedule cron jobs
5. ✅ Create upload directories

**Check for Success:**
- Go to **ICT Platform** menu in WordPress admin
- You should see the main dashboard
- Check **ICT Platform → Settings** to verify options

### Step 5: Run Database Check

```bash
# Via WP-CLI
wp ict-platform verify-database

# Via WordPress Admin
# Go to ICT Platform → Settings → System → Verify Database
```

---

## 🔗 Zoho Integration Setup

### Overview

ICT Platform integrates with 5 Zoho services. Each requires OAuth 2.0 setup.

### Step 1: Create Zoho Developer Account

1. Go to [https://api-console.zoho.com/](https://api-console.zoho.com/)
2. Sign in with your Zoho account
3. Accept terms and conditions

### Step 2: Register OAuth Application

**For Each Zoho Service (CRM, People, Books, FSM, Desk):**

1. Click "Add Client"
2. Select "Server-based Applications"
3. Fill in details:
   - **Client Name:** `ICT Platform - [Service Name]`
   - **Homepage URL:** `https://yoursite.com`
   - **Authorized Redirect URI:**
     ```
     https://yoursite.com/wp-admin/admin.php?page=ict-settings&tab=zoho&service=[service]
     ```
     Replace `[service]` with: `crm`, `people`, `books`, `fsm`, or `desk`

4. Click "Create"
5. **Save the Client ID and Client Secret** (you'll need these)

### Step 3: Configure in ICT Platform

1. Go to **ICT Platform → Settings → Zoho Integration**
2. For each service:
   - Paste **Client ID**
   - Paste **Client Secret**
   - Click "Save Settings"
3. Click "Connect to Zoho" for each service
4. Authorize the application in Zoho
5. You'll be redirected back - connection is now active

### Step 4: Configure Webhooks (Optional but Recommended)

**For Real-Time Sync:**

In each Zoho service, configure webhooks:

**Webhook URL Format:**
```
https://yoursite.com/wp-json/ict/v1/webhooks/[service]
```

**Example for CRM:**
```
https://yoursite.com/wp-json/ict/v1/webhooks/crm
```

**Webhook Secret:**
- Generate a random secret key (32+ characters)
- Save in **ICT Platform → Settings → Zoho Integration → Webhook Secret**
- Use the same secret in Zoho webhook configuration

### Step 5: Test Integration

1. Go to **ICT Platform → Sync**
2. Click "Test Connection" for each service
3. Verify green checkmarks
4. Click "Run Initial Sync"
5. Monitor sync progress in real-time

---

## ⚙️ Initial Configuration

### Step 1: Configure User Roles

**Create User Accounts:**

1. Go to **Users → Add New**
2. Assign appropriate role:
   - **ICT Project Manager** - Full project access
   - **ICT Technician** - Field worker access
   - **ICT Inventory Manager** - Inventory management

**Default Capabilities:**

| Role | Capabilities |
|------|-------------|
| **ICT Project Manager** | Manage projects, approve time, view reports, manage resources |
| **ICT Technician** | Clock in/out, view assigned projects, submit timesheets |
| **ICT Inventory Manager** | Manage inventory, create POs, adjust stock |

### Step 2: Configure Settings

**General Settings:**
- Go to **ICT Platform → Settings → General**
- Set:
  - Date format
  - Time format
  - Currency
  - Timezone
  - Company details

**Time Tracking Settings:**
- Go to **ICT Platform → Settings → Time Tracking**
- Configure:
  - Rounding interval (15, 30, or 60 minutes)
  - GPS tracking (enable/disable)
  - Auto clock-out time
  - Overtime rules

**Project Settings:**
- Go to **ICT Platform → Settings → Projects**
- Set:
  - Default project status
  - Auto-numbering format
  - Budget alert thresholds
  - Progress calculation method

### Step 3: Import Initial Data (Optional)

**Import Projects:**
```bash
wp ict-platform import projects --file=/path/to/projects.csv
```

**Import Inventory:**
```bash
wp ict-platform import inventory --file=/path/to/inventory.csv
```

**CSV Format Examples in:** `/docs/import-templates/`

### Step 4: Configure Notifications

1. Go to **ICT Platform → Settings → Notifications**
2. Enable/disable notification types:
   - Email notifications
   - In-app notifications
   - SMS notifications (requires integration)
3. Set notification recipients per event type

---

## 🗄️ Database Optimization

### Recommended MySQL Configuration

Add to `my.cnf` or `my.ini`:

```ini
[mysqld]
# InnoDB Settings
innodb_buffer_pool_size = 512M
innodb_log_file_size = 128M
innodb_flush_log_at_trx_commit = 2
innodb_flush_method = O_DIRECT

# Query Cache (MySQL 5.7 only)
query_cache_type = 1
query_cache_size = 64M
query_cache_limit = 2M

# Connection Settings
max_connections = 200
connect_timeout = 10
wait_timeout = 300

# Performance
tmp_table_size = 64M
max_heap_table_size = 64M
join_buffer_size = 2M
sort_buffer_size = 2M
```

### Index Optimization

```sql
-- Run after installation
OPTIMIZE TABLE wp_ict_projects;
OPTIMIZE TABLE wp_ict_time_entries;
OPTIMIZE TABLE wp_ict_inventory_items;
OPTIMIZE TABLE wp_ict_purchase_orders;
OPTIMIZE TABLE wp_ict_project_resources;
OPTIMIZE TABLE wp_ict_sync_queue;
OPTIMIZE TABLE wp_ict_sync_log;

-- Analyze tables
ANALYZE TABLE wp_ict_projects;
ANALYZE TABLE wp_ict_time_entries;
-- (repeat for all tables)
```

### Automated Database Maintenance

Add to cron:
```bash
# Daily at 2 AM
0 2 * * * wp db optimize --allow-root
```

---

## 🔒 Security Hardening

### 1. File Permissions

```bash
# Set secure permissions
find /var/www/html/wp-content/plugins/ict-platform -type d -exec chmod 755 {} \;
find /var/www/html/wp-content/plugins/ict-platform -type f -exec chmod 644 {} \;

# Protect sensitive files
chmod 600 /var/www/html/wp-content/plugins/ict-platform/config/*.php
```

### 2. Disable File Editing

Add to `wp-config.php`:
```php
define('DISALLOW_FILE_EDIT', true);
define('DISALLOW_FILE_MODS', true); // Prevents plugin updates via admin
```

### 3. Limit Login Attempts

Install plugin: **Limit Login Attempts Reloaded**
```bash
wp plugin install limit-login-attempts-reloaded --activate
```

### 4. Two-Factor Authentication

Install plugin: **Two Factor Authentication**
```bash
wp plugin install two-factor --activate
```

### 5. SSL/TLS Configuration

Ensure all API calls use HTTPS:

Add to `wp-config.php`:
```php
define('FORCE_SSL_ADMIN', true);
if (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') {
    $_SERVER['HTTPS'] = 'on';
}
```

### 6. Security Headers

Add to `.htaccess` (Apache):
```apache
<IfModule mod_headers.c>
    Header set X-Content-Type-Options "nosniff"
    Header set X-Frame-Options "SAMEORIGIN"
    Header set X-XSS-Protection "1; mode=block"
    Header set Referrer-Policy "no-referrer-when-downgrade"
    Header set Content-Security-Policy "default-src 'self' https:; script-src 'self' 'unsafe-inline' 'unsafe-eval' https:; style-src 'self' 'unsafe-inline' https:;"
</IfModule>
```

For Nginx, add to server block:
```nginx
add_header X-Content-Type-Options "nosniff" always;
add_header X-Frame-Options "SAMEORIGIN" always;
add_header X-XSS-Protection "1; mode=block" always;
add_header Referrer-Policy "no-referrer-when-downgrade" always;
```

### 7. Secure Zoho Tokens

Tokens are automatically encrypted with AES-256-CBC using WordPress salts.

**Regenerate WordPress Salts:**
```bash
# Get new salts
curl https://api.wordpress.org/secret-key/1.1/salt/

# Replace in wp-config.php
```

### 8. API Rate Limiting

The plugin includes built-in rate limiting for Zoho (60 req/min per service).

For WordPress REST API, add rate limiting:
```php
// Add to functions.php or custom plugin
add_filter('rest_authentication_errors', function($result) {
    $ip = $_SERVER['REMOTE_ADDR'];
    $transient_key = 'rest_api_rate_limit_' . md5($ip);
    $requests = get_transient($transient_key) ?: 0;

    if ($requests > 100) { // 100 requests per minute
        return new WP_Error(
            'rest_rate_limit_exceeded',
            'Rate limit exceeded. Please try again later.',
            array('status' => 429)
        );
    }

    set_transient($transient_key, $requests + 1, MINUTE_IN_SECONDS);
    return $result;
});
```

### 9. Database User Permissions

Create dedicated database user:
```sql
CREATE USER 'ict_platform'@'localhost' IDENTIFIED BY 'strong_password_here';
GRANT SELECT, INSERT, UPDATE, DELETE ON wordpress_db.wp_ict_* TO 'ict_platform'@'localhost';
FLUSH PRIVILEGES;
```

### 10. Backup Encryption

Encrypt database backups:
```bash
# Backup with encryption
wp db export - | gpg --encrypt --recipient your@email.com > backup-$(date +%Y%m%d).sql.gpg
```

---

## ⚡ Performance Tuning

### 1. Enable Object Caching

**Install Redis:**
```bash
# Ubuntu/Debian
sudo apt-get install redis-server php-redis

# CentOS/RHEL
sudo yum install redis php-redis

# Restart services
sudo systemctl restart redis
sudo systemctl restart php-fpm
```

**Configure WordPress:**
```bash
wp plugin install redis-cache --activate
wp redis enable
```

### 2. Enable OPcache

Add to `php.ini`:
```ini
[opcache]
opcache.enable=1
opcache.memory_consumption=256
opcache.interned_strings_buffer=16
opcache.max_accelerated_files=10000
opcache.revalidate_freq=2
opcache.fast_shutdown=1
```

### 3. CDN Configuration

**Recommended CDN:** Cloudflare (free tier available)

1. Sign up at [cloudflare.com](https://www.cloudflare.com)
2. Add your site
3. Update DNS to Cloudflare nameservers
4. Enable caching rules for static assets:
   - CSS, JS, images, fonts
   - Cache TTL: 1 month

### 4. Image Optimization

Install and configure:
```bash
wp plugin install wp-smushit --activate
```

Or use command line:
```bash
# Optimize all images
wp smush optimize all
```

### 5. Lazy Loading

Already built-in for images. For videos:
```bash
wp plugin install lazy-loading-feature-plugin --activate
```

### 6. Database Query Optimization

**Enable Query Monitor** (development only):
```bash
wp plugin install query-monitor --activate
```

Identify slow queries and add indexes as needed.

### 7. Minify Assets

Assets are already minified in production build.

To verify:
```bash
ls -lh wp-content/plugins/ict-platform/assets/
# Files should have .min.js and .min.css extensions
```

### 8. Cron Optimization

Move WordPress cron to system cron for better reliability:

**Disable WP Cron:**
Add to `wp-config.php`:
```php
define('DISABLE_WP_CRON', true);
```

**Add to system cron:**
```bash
crontab -e

# Add this line
*/15 * * * * wget -q -O - https://yoursite.com/wp-cron.php?doing_wp_cron >/dev/null 2>&1
```

### 9. Optimize Sync Queue Processing

Configure in **ICT Platform → Settings → Sync**:
- **Batch size:** 20 items (default is optimal)
- **Processing interval:** 15 minutes
- **Max retry attempts:** 3
- Enable "Process queue in background"

### 10. Load Balancing (For High Traffic)

For multiple servers, use:
- **Database:** MySQL replication or Galera cluster
- **Files:** Shared storage (NFS, S3)
- **Sessions:** Redis-based session storage
- **Load balancer:** HAProxy or Nginx

Example HAProxy config:
```haproxy
frontend http_front
   bind *:80
   default_backend web_back

backend web_back
   balance roundrobin
   server web1 192.168.1.10:80 check
   server web2 192.168.1.11:80 check
```

---

## 🔧 Troubleshooting

### Common Issues & Solutions

#### Issue 1: Plugin Activation Fails

**Symptoms:** White screen or error on activation

**Solutions:**
```bash
# Check PHP error log
tail -f /var/log/php/error.log

# Increase PHP memory limit
# Add to wp-config.php:
define('WP_MEMORY_LIMIT', '512M');

# Check database connection
wp db check

# Verify all files uploaded
find /path/to/ict-platform -type f | wc -l
# Should be 100+ files
```

#### Issue 2: Database Tables Not Created

**Symptoms:** Error messages about missing tables

**Solutions:**
```bash
# Deactivate and reactivate plugin
wp plugin deactivate ict-platform
wp plugin activate ict-platform

# Or manually create tables
wp ict-platform create-tables

# Verify tables exist
wp db query "SHOW TABLES LIKE 'wp_ict_%';"
```

#### Issue 3: Zoho Connection Fails

**Symptoms:** "Failed to connect" error

**Solutions:**
1. Verify Client ID and Client Secret are correct
2. Check redirect URI matches exactly
3. Ensure SSL is enabled (required by Zoho)
4. Clear browser cache and cookies
5. Try different browser
6. Check server can reach Zoho APIs:
   ```bash
   curl -I https://accounts.zoho.com
   ```

#### Issue 4: Assets Not Loading

**Symptoms:** Broken layout, missing styles

**Solutions:**
```bash
# Rebuild assets
cd /path/to/ict-platform
npm run build

# Clear WordPress cache
wp cache flush

# Clear browser cache

# Check file permissions
ls -l assets/css/
ls -l assets/js/
# Should be readable by web server
```

#### Issue 5: Sync Queue Not Processing

**Symptoms:** Items stuck in queue

**Solutions:**
```bash
# Check cron is running
wp cron event list

# Manually trigger cron
wp cron event run ict_process_sync_queue

# Check cron schedule
wp cron schedule list

# View sync logs
wp db query "SELECT * FROM wp_ict_sync_log ORDER BY id DESC LIMIT 10;"
```

#### Issue 6: Performance Issues

**Symptoms:** Slow page loads

**Solutions:**
1. Enable object caching (Redis/Memcached)
2. Optimize database:
   ```bash
   wp db optimize
   ```
3. Check slow queries:
   ```bash
   wp db query "SHOW FULL PROCESSLIST;"
   ```
4. Increase PHP memory:
   ```ini
   memory_limit = 512M
   ```
5. Clear sync log (if very large):
   ```sql
   DELETE FROM wp_ict_sync_log WHERE created_at < DATE_SUB(NOW(), INTERVAL 30 DAY);
   ```

#### Issue 7: 404 Errors on REST API Endpoints

**Symptoms:** API calls return 404

**Solutions:**
```bash
# Flush rewrite rules
wp rewrite flush

# Verify REST API works
wp rest-api check

# Test endpoint
curl -I https://yoursite.com/wp-json/ict/v1/projects
```

#### Issue 8: Upload Directory Not Writable

**Symptoms:** Can't upload files

**Solutions:**
```bash
# Create upload directory
mkdir -p wp-content/uploads/ict-platform

# Set permissions
chmod 755 wp-content/uploads/ict-platform
chown www-data:www-data wp-content/uploads/ict-platform
```

---

## 💾 Backup & Recovery

### Automated Backups

**Using UpdraftPlus (Recommended):**
```bash
wp plugin install updraftplus --activate
```

Configure:
1. **Backup Schedule:** Daily
2. **Backup Location:** Remote (Dropbox, Google Drive, S3)
3. **Include:** Database + Files
4. **Retention:** Keep 30 backups

**Using WP-CLI:**
```bash
# Create backup script
cat > /usr/local/bin/backup-ict-platform.sh << 'EOF'
#!/bin/bash
BACKUP_DIR="/backups/ict-platform"
DATE=$(date +%Y%m%d-%H%M%S)

# Create backup directory
mkdir -p $BACKUP_DIR

# Backup database
wp db export $BACKUP_DIR/database-$DATE.sql

# Backup files
tar -czf $BACKUP_DIR/files-$DATE.tar.gz /var/www/html/wp-content/plugins/ict-platform

# Delete old backups (keep 30 days)
find $BACKUP_DIR -type f -mtime +30 -delete

echo "Backup completed: $DATE"
EOF

chmod +x /usr/local/bin/backup-ict-platform.sh

# Add to cron (daily at 2 AM)
crontab -e
0 2 * * * /usr/local/bin/backup-ict-platform.sh
```

### Manual Backup

**Database:**
```bash
wp db export ict-platform-backup-$(date +%Y%m%d).sql
```

**Files:**
```bash
tar -czf ict-platform-files-$(date +%Y%m%d).tar.gz wp-content/plugins/ict-platform
```

### Recovery Procedure

**Database Recovery:**
```bash
# Import database
wp db import ict-platform-backup-20250101.sql

# Verify
wp db check
```

**File Recovery:**
```bash
# Extract files
cd wp-content/plugins/
tar -xzf /backups/ict-platform-files-20250101.tar.gz

# Set permissions
chmod -R 755 ict-platform
chown -R www-data:www-data ict-platform
```

**Full Site Recovery:**
```bash
# 1. Restore WordPress core and wp-content
# 2. Import database
wp db import backup.sql

# 3. Update URLs if domain changed
wp search-replace 'oldsite.com' 'newsite.com'

# 4. Flush cache and rewrite rules
wp cache flush
wp rewrite flush

# 5. Verify installation
wp ict-platform verify
```

---

## 🔄 Upgrade Path

### From Version 1.0.0 to 1.1.0

**Pre-Upgrade Checklist:**
- [ ] Create full backup
- [ ] Test in staging environment
- [ ] Review changelog for breaking changes
- [ ] Notify users of maintenance window

**Upgrade Steps:**

1. **Backup Current Installation:**
   ```bash
   wp db export pre-upgrade-backup.sql
   tar -czf pre-upgrade-files.tar.gz wp-content/plugins/ict-platform
   ```

2. **Download New Version:**
   ```bash
   cd /tmp
   wget https://github.com/yourusername/ict-platform/releases/download/v1.1.0/ict-platform.zip
   ```

3. **Deactivate Plugin:**
   ```bash
   wp plugin deactivate ict-platform
   ```

4. **Replace Files:**
   ```bash
   cd /var/www/html/wp-content/plugins/
   rm -rf ict-platform
   unzip /tmp/ict-platform.zip
   ```

5. **Activate Plugin:**
   ```bash
   wp plugin activate ict-platform
   ```

6. **Run Database Migrations:**
   ```bash
   wp ict-platform migrate
   ```

7. **Clear Caches:**
   ```bash
   wp cache flush
   wp rewrite flush
   ```

8. **Verify Installation:**
   ```bash
   wp ict-platform verify
   ```

**Rollback Procedure (If Needed):**
```bash
# Deactivate new version
wp plugin deactivate ict-platform

# Restore files
cd /var/www/html/wp-content/plugins/
rm -rf ict-platform
tar -xzf /backups/pre-upgrade-files.tar.gz

# Restore database
wp db import /backups/pre-upgrade-backup.sql

# Reactivate
wp plugin activate ict-platform
```

---

## 📞 Support & Resources

### Documentation
- **User Guide:** `/docs/USER_GUIDE.md`
- **API Documentation:** `/docs/API_REFERENCE.md`
- **Developer Guide:** `/docs/CLAUDE.md`

### Support Channels
- **GitHub Issues:** https://github.com/yourusername/ict-platform/issues
- **Community Forum:** https://community.yoursite.com
- **Email Support:** support@yoursite.com

### Useful Commands

```bash
# Verify installation
wp ict-platform verify

# Check system status
wp ict-platform status

# Clear sync queue
wp ict-platform clear-queue

# Run sync manually
wp ict-platform sync --service=crm

# View sync logs
wp ict-platform logs --limit=50

# Export data
wp ict-platform export --type=projects --format=csv

# Import data
wp ict-platform import --type=projects --file=data.csv

# Reset plugin (DANGER - deletes all data)
wp ict-platform reset --yes-i-am-sure
```

---

## 🎉 Installation Complete!

**Next Steps:**
1. [Configure Zoho Integration](#zoho-integration-setup)
2. [Set Up User Roles](#configure-user-roles)
3. [Import Initial Data](#import-initial-data-optional)
4. [Read User Guide](USER_GUIDE.md)
5. [Configure Backups](#automated-backups)

**Need Help?**
- Check [Troubleshooting](#troubleshooting)
- Review [Common Issues](#common-issues--solutions)
- Contact Support

---

**Built with ❤️ for ICT/Electrical Contracting Businesses**

