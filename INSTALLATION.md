# Installation Guide - Advanced Link Tracker

## Quick Installation

### Step 1: Upload Plugin Files

Copy the entire `yourls-advanced-tracker` directory to your YOURLS plugins folder:

```
yourls/
└── user/
    └── plugins/
        └── advanced-tracker/     ← Rename folder to this
            ├── plugin.php
            ├── admin-page.php
            ├── style.css
            ├── README.md
            └── INSTALLATION.md
```

**Important**: The folder MUST be named `advanced-tracker` (not `yourls-advanced-tracker`)

### Step 2: Activate the Plugin

1. Log in to your YOURLS admin panel
2. Navigate to **Manage Plugins** (usually at `http://your-domain.com/admin/plugins.php`)
3. Find **Advanced Link Tracker** in the list
4. Click the **Activate** button
5. You'll be redirected back to the plugins page - activation creates the database table automatically

### Step 3: Access the Dashboard

1. Look for **Advanced Tracker** in your YOURLS admin menu
2. Click it to open the analytics dashboard
3. Start creating short links - data will be tracked automatically!

## Detailed Installation Methods

### Method 1: Manual Upload (Recommended for most users)

1. Download the plugin files
2. Create a folder named `advanced-tracker` in `user/plugins/`
3. Upload all plugin files to this folder
4. Activate via admin panel

### Method 2: Git Clone (For developers)

```bash
cd /path/to/yourls/user/plugins/
git clone https://github.com/yourusername/yourls-advanced-tracker.git advanced-tracker
```

Then activate via admin panel.

### Method 3: FTP/SFTP Upload

1. Connect to your server via FTP/SFTP
2. Navigate to `yourls/user/plugins/`
3. Upload the `advanced-tracker` folder
4. Set permissions if needed (usually 755 for folders, 644 for files)
5. Activate via admin panel

## Verification

After installation, verify everything is working:

### 1. Check Database Table

Run this SQL query in phpMyAdmin or MySQL:

```sql
SHOW TABLES LIKE '%advanced_tracking%';
```

You should see a table named something like `yourls_url_advanced_tracking`

### 2. Test Data Collection

1. Create a short URL in YOURLS
2. Visit the short URL (it will redirect)
3. Go to **Advanced Tracker** dashboard
4. You should see the click recorded with all details

### 3. Check Dashboard Access

- Dashboard should load without errors
- Charts should render (requires internet for Chart.js CDN)
- Statistics cards should display numbers
- Data table should be visible (empty if no clicks yet)

## Troubleshooting Installation

### Plugin Not Appearing in List

**Possible causes:**
- Folder name is incorrect (must be `advanced-tracker`)
- Files not uploaded to correct location
- PHP syntax errors in plugin.php

**Solution:**
```bash
# Check folder structure
ls -la /path/to/yourls/user/plugins/advanced-tracker/

# Should show:
# plugin.php
# admin-page.php
# style.css
# README.md
```

### Activation Fails

**Possible causes:**
- Database permissions issue
- MySQL version too old
- Character encoding problems

**Solution:**
1. Check YOURLS database credentials in config.php
2. Verify MySQL user has CREATE TABLE permission
3. Check PHP error logs for details

### Database Table Not Created

**Manual table creation:**

Run this SQL (replace `yourls_` with your table prefix):

```sql
CREATE TABLE IF NOT EXISTS `yourls_url_advanced_tracking` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `keyword` varchar(200) NOT NULL,
    `timestamp` datetime NOT NULL,
    `ip_address` varchar(45) NOT NULL,
    `country` varchar(100) DEFAULT NULL,
    `country_code` varchar(2) DEFAULT NULL,
    `region` varchar(100) DEFAULT NULL,
    `city` varchar(100) DEFAULT NULL,
    `latitude` varchar(20) DEFAULT NULL,
    `longitude` varchar(20) DEFAULT NULL,
    `isp` varchar(200) DEFAULT NULL,
    `user_agent` text,
    `browser` varchar(100) DEFAULT NULL,
    `browser_version` varchar(50) DEFAULT NULL,
    `os` varchar(100) DEFAULT NULL,
    `os_version` varchar(50) DEFAULT NULL,
    `device_type` varchar(50) DEFAULT NULL,
    `device_brand` varchar(100) DEFAULT NULL,
    `device_model` varchar(100) DEFAULT NULL,
    `referrer` text,
    `language` varchar(50) DEFAULT NULL,
    `screen_resolution` varchar(20) DEFAULT NULL,
    `timezone` varchar(50) DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `keyword` (`keyword`),
    KEY `timestamp` (`timestamp`),
    KEY `ip_address` (`ip_address`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

### Dashboard Shows Blank Page

**Possible causes:**
- PHP errors
- Missing admin-page.php file
- Permissions issue

**Solution:**
1. Enable PHP error display temporarily
2. Check file exists: `admin-page.php`
3. Check PHP error logs
4. Verify file permissions (644)

### Charts Not Loading

**Possible causes:**
- No internet connection (Chart.js CDN unreachable)
- JavaScript errors
- Browser blocking scripts

**Solution:**
1. Check browser console for errors (F12)
2. Verify internet access from browser
3. Try different browser
4. Check if ad blocker is interfering

### No Data Being Tracked

**Possible causes:**
- Plugin not fully activated
- Hook not registered properly
- Database connection issue

**Solution:**
1. Deactivate and reactivate plugin
2. Test with a new short URL
3. Check database for new entries:
   ```sql
   SELECT * FROM yourls_url_advanced_tracking ORDER BY timestamp DESC LIMIT 10;
   ```

### Geolocation Not Working

**Possible causes:**
- Server firewall blocking outbound requests
- API rate limit exceeded
- Testing from private IP (localhost, 192.168.x.x)

**Solution:**
- Test from public IP address
- Check server can reach ip-api.com:
  ```bash
  curl http://ip-api.com/json/8.8.8.8
  ```
- Geolocation won't work for private IPs (expected behavior)

## File Permissions

Recommended permissions:

```bash
chmod 755 /path/to/yourls/user/plugins/advanced-tracker/
chmod 644 /path/to/yourls/user/plugins/advanced-tracker/*.php
chmod 644 /path/to/yourls/user/plugins/advanced-tracker/*.css
chmod 644 /path/to/yourls/user/plugins/advanced-tracker/*.md
```

## Server Requirements Check

Verify your server meets requirements:

```php
<?php
// Create a test file: test-requirements.php

echo "PHP Version: " . phpversion() . " (Need 7.0+)\n";
echo "MySQL Available: " . (function_exists('mysqli_connect') ? 'Yes' : 'No') . "\n";
echo "cURL Available: " . (function_exists('curl_init') ? 'Yes' : 'No') . "\n";
echo "allow_url_fopen: " . (ini_get('allow_url_fopen') ? 'Yes' : 'No') . "\n";
?>
```

All should show "Yes" or version 7.0+

## Uninstallation

To completely remove the plugin:

### 1. Deactivate Plugin
- Go to Manage Plugins
- Click "Deactivate" next to Advanced Link Tracker

### 2. Delete Files
```bash
rm -rf /path/to/yourls/user/plugins/advanced-tracker/
```

### 3. Remove Database Table (Optional)
```sql
DROP TABLE yourls_url_advanced_tracking;
```

**Warning**: This deletes all tracked data permanently!

## Upgrading

To upgrade to a newer version:

1. **Backup your data** (export to CSV/JSON first)
2. Deactivate the plugin
3. Replace all plugin files with new version
4. Reactivate the plugin
5. Check dashboard to ensure everything works

## Getting Help

If you encounter issues:

1. Check PHP error logs
2. Review this troubleshooting guide
3. Search existing GitHub issues
4. Create new issue with:
   - YOURLS version
   - PHP version
   - MySQL version
   - Error messages
   - Steps to reproduce

## Next Steps

After successful installation:

1. Review the [README.md](README.md) for features and usage
2. Configure filters to analyze specific data
3. Set up regular data exports if needed
4. Review privacy policy requirements
5. Monitor dashboard for insights

---

**Installation Complete!** Your YOURLS instance now has comprehensive link tracking capabilities.
