# YOURLS Advanced Tracker

> **WARNING: AI Slop Coded**
>
> This integration was AI slop coded. While it has been tested, **do not use this in mission-critical situations**. Use at your own risk. The code may contain bugs, security issues, or unexpected behavior.

A comprehensive visitor tracking plugin for YOURLS with advanced device fingerprinting and Grabify-like functionality. Track detailed visitor analytics including IP addresses, geolocation, device information, browsers, operating systems, and advanced browser fingerprints.

## Features

### Server-Side Tracking (Instant)
- **IP Address Tracking** - Real IP detection (handles proxies and CDNs)
- **Geolocation** - Country, region, city, latitude, longitude, ISP
- **Device Detection** - Device type (Mobile/Tablet/Desktop), brand, and model
- **Browser Information** - Browser name and version
- **Operating System** - OS name and version
- **Referrer Tracking** - Where visitors came from
- **Language Detection** - Visitor's browser language preferences
- **Timestamp** - Exact date and time of each click

### Client-Side Fingerprinting (Advanced)
- **Screen Information** - Resolution, viewport size, color depth
- **System Details** - Platform, timezone, CPU cores, device memory
- **Network Info** - Connection type (4G, WiFi, etc.)
- **WebGL Fingerprinting** - GPU vendor and renderer information
- **Canvas Fingerprinting** - Unique browser canvas signature
- **Font Detection** - Enumerate installed fonts
- **Plugin Detection** - Browser plugins and extensions
- **Hardware Features** - Touch support, battery status
- **Privacy Settings** - DNT header, cookies enabled

### Analytics Dashboard
- **Statistics Overview** - Total clicks, unique visitors, device breakdown
- **Interactive Charts** - Powered by Chart.js
  - Clicks over time (30-day trend)
  - Device type distribution
  - Browser usage
  - Operating system breakdown
- **Detailed Tables**
  - Top countries with percentages
  - Top referrers
  - Complete click history with expandable fingerprint details
  - "More" button reveals full device fingerprint data

### Advanced Filtering
- Filter by short URL keyword
- Date range filtering (from/to)
- Adjustable results per page
- Pagination for large datasets

### Data Export
- Export to CSV format
- Export to JSON format
- Full data export with all tracked fields

## Installation

1. **Download the plugin**
   ```bash
   cd /path/to/yourls/user/plugins/
   git clone https://github.com/yourusername/yourls-advanced-tracker.git advanced-tracker
   ```
   Or manually copy the `yourls-advanced-tracker` folder to `user/plugins/` and rename it to `advanced-tracker`

2. **Activate the plugin**
   - Go to your YOURLS admin panel
   - Navigate to the "Manage Plugins" page
   - Find "Advanced Link Tracker" and click "Activate"
   - The plugin will automatically create the necessary database table

3. **Access the dashboard**
   - After activation, go to "Advanced Tracker" in the admin menu
   - Start viewing your analytics!

## Usage

### Viewing Analytics

1. Navigate to **Advanced Tracker** in your YOURLS admin panel
2. View the dashboard with statistics, charts, and detailed click data
3. Use filters to narrow down specific URLs, date ranges, or time periods
4. Export data for external analysis

### Filtering Data

- **Short URL**: Select a specific short URL to view its analytics
- **Date Range**: Filter clicks between specific dates
- **Results per page**: Adjust how many results to display (50/100/500/1000)

### Exporting Data

Click the export buttons to download:
- **CSV**: Spreadsheet-compatible format for Excel, Google Sheets, etc.
- **JSON**: Developer-friendly format for custom analysis

## Database Schema

The plugin creates a table `{yourls_prefix}_url_advanced_tracking` with 37 fields:

```sql
-- Basic Tracking (Server-Side)
- id (Primary Key)
- keyword (Short URL keyword)
- timestamp (Click timestamp)
- ip_address (Visitor IP)
- country, country_code, region, city
- latitude, longitude
- isp (Internet Service Provider)
- user_agent (Full user agent string)
- browser, browser_version
- os, os_version
- device_type, device_brand, device_model
- referrer (HTTP referrer)
- language (Accept-Language header)

-- Advanced Fingerprinting (Client-Side JavaScript)
- screen_resolution, viewport_size
- color_depth, timezone
- platform
- cookies_enabled, do_not_track, touch_support
- cpu_cores, device_memory
- connection_type
- webgl_vendor, webgl_renderer
- canvas_fingerprint
- fonts_detected (JSON array)
- plugins_list (JSON array)
- battery_charging, battery_level
```

## How It Works

1. **Click Interception**: Plugin intercepts redirects using `redirect_location` filter
2. **Server-Side Tracking**: Immediately collects IP, geolocation, user agent data
3. **HTML Injection**: Shows HTML page with JavaScript fingerprinting code (~100ms)
4. **JavaScript Collection**: Browser executes fingerprinting scripts
5. **Async Beacon**: Fingerprint data sent to `/__beacon` endpoint via POST
6. **Auto Redirect**: JavaScript redirects to target URL (meta refresh + setTimeout)
7. **Database Update**: Beacon updates existing record with fingerprint data
8. **Analytics View**: Dashboard displays complete visitor intelligence

## API Services Used

### IP Geolocation
- **Service**: ip-api.com (free tier)
- **Rate Limit**: 45 requests per minute
- **No API Key Required**
- **Data**: Country, region, city, coordinates, ISP

For high-traffic sites, consider:
- Upgrading to ip-api.com Pro
- Using alternative services (ipinfo.io, ipstack.com)
- Implementing caching to reduce API calls

## Privacy Considerations

This plugin collects detailed visitor information. Please ensure compliance with:

- **GDPR** (European Union)
- **CCPA** (California)
- **Other privacy regulations** in your jurisdiction

### Recommendations:
1. Add privacy policy disclosure about data collection
2. Implement data retention policies
3. Provide opt-out mechanisms if required
4. Secure your YOURLS installation (HTTPS, authentication)
5. Consider IP anonymization for privacy-sensitive applications

## Performance

- Minimal impact on redirect speed (< 100ms additional latency)
- Geolocation API calls are non-blocking
- Database indexed for fast queries
- Pagination for large datasets
- Efficient SQL queries with prepared statements

## Customization

### Change Geolocation Provider

Edit `plugin.php`, find the `advanced_tracker_get_geolocation()` function:

```php
// Replace with your preferred API
$url = "https://your-api.com/json/{$ip}";
```

### Extend Device Detection

Modify `advanced_tracker_parse_user_agent()` to add more device patterns:

```php
$devices = array(
    'YourDevice' => array('Brand', 'Model'),
    // Add more devices
);
```

### Add Custom Fields

1. Modify the database schema in `advanced_tracker_activate()`
2. Update the INSERT query in `advanced_tracker_log_click()`
3. Add display columns in `admin-page.php`

## Troubleshooting

### No data appearing
- Check that the plugin is activated
- Verify database table was created: `{prefix}_url_advanced_tracking`
- Test a short URL and check for errors
- Enable PHP error logging to see issues

### Geolocation not working
- Check if your server can reach ip-api.com
- Verify no firewall blocking outbound requests
- API rate limit may be exceeded (45/min)
- Private IPs (localhost, 192.168.x.x) won't geolocate

### Dashboard not loading
- Clear browser cache
- Check browser console for JavaScript errors
- Verify Chart.js CDN is accessible
- Check PHP error logs

### Performance issues
- Implement IP geolocation caching
- Reduce results per page
- Archive old data
- Optimize database with indexes

## Requirements

- **YOURLS**: Version 1.7.10 or higher
- **PHP**: 7.0 or higher (7.4+ recommended)
- **MySQL**: 5.6 or higher (InnoDB support)
- **Internet Connection**: For IP geolocation API

## Security

### Built-In Protection
- **SQL Injection**: Prepared statements with bound parameters
- **Input Validation**: Strict sanitization of all user input
  - Keywords: Alphanumeric characters only
  - Screen resolution: Digits and 'x' only, max 20 chars
  - Color depth: Integer range 1-64
  - CPU cores: Integer range 0-256
  - Device memory: Float range 0-1024GB
  - Timezone: Sanitized characters, max 50 chars
  - Canvas fingerprint: Alphanumeric only, 64 chars max
- **XSS Protection**: HTML tag stripping and output escaping
- **Length Limits**: Maximum field lengths enforced on all inputs
- **Array Validation**: Font/plugin arrays sanitized and limited to 50 items
- **Authentication**: Requires YOURLS admin authentication for dashboard
- **No Direct Access**: Dies if accessed outside YOURLS context

## Changelog

### Version 1.0.0
- Initial release
- Comprehensive visitor tracking
- Interactive analytics dashboard
- Data export functionality (CSV/JSON)
- Advanced filtering options
- IP geolocation support
- Device, browser, OS detection
- Referrer tracking

## License

MIT License - Feel free to modify and distribute

## Support

For issues, feature requests, or contributions:
- GitHub Issues: https://github.com/yourusername/yourls-advanced-tracker/issues
- Documentation: See this README

## Credits

- Built for YOURLS (https://yourls.org)
- Charts powered by Chart.js
- Geolocation by ip-api.com
- Inspired by Grabify and YOURLS IP Viewer

## Disclaimer

This plugin is intended for legitimate analytics and security purposes only. Users are responsible for ensuring compliance with applicable privacy laws and using this tool ethically and legally. The developers are not responsible for misuse of this plugin.

---

**Note**: Always respect user privacy and comply with local laws when collecting visitor data.
