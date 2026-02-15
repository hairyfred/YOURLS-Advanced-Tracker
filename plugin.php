<?php
/*
Plugin Name: YOURLS Advanced Tracker
Plugin URI: https://github.com/hairyfred/YOURLS-Advanced-Tracker
Description: Advanced visitor tracking and analytics for YOURLS with device fingerprinting, geolocation, and comprehensive visitor intelligence
Version: 1.0.2
Author: hairyfred
Author URI: https://github.com/hairyfred/YOURLS-Advanced-Tracker
*/

// No direct call
if( !defined( 'YOURLS_ABSPATH' ) ) die();

// Check for beacon requests before anything else
yourls_add_action('pre_redirect_shorturl', 'advanced_tracker_check_beacon_early', 1);

// NOTE: We don't use redirect_shorturl hook anymore because we intercept in redirect_location filter
// This prevents duplicate entries since we call advanced_tracker_log_click() manually in the filter

// Handle export requests VERY early (before admin page loads)
yourls_add_action('plugins_loaded', 'advanced_tracker_handle_export_early');

// Add admin page
yourls_add_action('plugins_loaded', 'advanced_tracker_add_page');

// Include migration script
require_once(dirname(__FILE__) . '/migrate-database.php');

// Database table name
define('ADVANCED_TRACKER_TABLE', YOURLS_DB_TABLE_URL . '_advanced_tracking');

// Default settings
function advanced_tracker_get_default_settings() {
    return array(
        // Basic tracking (always recommended)
        'track_ip' => true,
        'track_user_agent' => true,
        'track_referrer' => true,
        'track_language' => true,

        // Geolocation
        'track_geolocation' => true,

        // Device & Browser Info
        'track_screen' => true,
        'track_timezone' => true,
        'track_platform' => true,

        // Advanced Fingerprinting (Privacy-sensitive)
        'track_canvas' => true,
        'track_webgl' => true,
        'track_audio' => true,
        'track_fonts' => true,

        // Hardware Info (Privacy-sensitive)
        'track_battery' => true,
        'track_hardware' => true, // CPU cores, device memory

        // Network Info
        'track_network' => true,

        // Additional Features
        'track_plugins' => true,
        'track_touch' => true,
        'track_dnt' => true, // Do Not Track
    );
}

// Get plugin settings
function advanced_tracker_get_settings() {
    $defaults = advanced_tracker_get_default_settings();
    $settings = yourls_get_option('advanced_tracker_settings', array());

    // Merge with defaults
    return array_merge($defaults, $settings);
}

// Save plugin settings
function advanced_tracker_save_settings($settings) {
    yourls_update_option('advanced_tracker_settings', $settings);
}

/**
 * Create database table on plugin activation
 */
yourls_add_action('activated_advanced-tracker/plugin.php', 'advanced_tracker_activate');
function advanced_tracker_activate() {
    global $ydb;

    $table = ADVANCED_TRACKER_TABLE;

    $sql = "CREATE TABLE IF NOT EXISTS `$table` (
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
        `viewport_size` varchar(20) DEFAULT NULL,
        `color_depth` int(11) DEFAULT NULL,
        `timezone` varchar(50) DEFAULT NULL,
        `platform` varchar(100) DEFAULT NULL,
        `cookies_enabled` tinyint(1) DEFAULT NULL,
        `do_not_track` tinyint(1) DEFAULT NULL,
        `touch_support` tinyint(1) DEFAULT NULL,
        `cpu_cores` int(11) DEFAULT NULL,
        `device_memory` float DEFAULT NULL,
        `connection_type` varchar(50) DEFAULT NULL,
        `webgl_vendor` varchar(200) DEFAULT NULL,
        `webgl_renderer` varchar(200) DEFAULT NULL,
        `canvas_fingerprint` varchar(64) DEFAULT NULL,
        `fonts_detected` text DEFAULT NULL,
        `plugins_list` text DEFAULT NULL,
        `battery_charging` tinyint(1) DEFAULT NULL,
        `battery_level` int(11) DEFAULT NULL,
        PRIMARY KEY (`id`),
        KEY `keyword` (`keyword`),
        KEY `timestamp` (`timestamp`),
        KEY `ip_address` (`ip_address`),
        KEY `canvas_fingerprint` (`canvas_fingerprint`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

    $ydb->query($sql);

    yourls_redirect(yourls_admin_url('plugins.php'), 301);
}

/**
 * Handle export requests before admin page wrapper loads
 */
function advanced_tracker_handle_export_early() {
    // Only handle if we're on the advanced tracker page with export parameter
    if (isset($_GET['page']) && $_GET['page'] === 'advanced_tracker' && isset($_GET['export'])) {
        $export_id = isset($_GET['export_id']) ? intval($_GET['export_id']) : null;
        advanced_tracker_export_data($_GET['export'], $export_id);
        // Function calls exit, so this won't be reached
    }
}

/**
 * Check for AJAX beacon requests very early (before redirect logic)
 * Beacon requests come to /__beacon with POST data
 */
function advanced_tracker_check_beacon_early() {
    // Check if this is a beacon request via special keyword
    $request_uri = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '';
    if (preg_match('#^/__beacon#', $request_uri) && isset($_POST['fingerprint'])) {
        advanced_tracker_save_fingerprint($_POST['fingerprint']);

        // Send 1x1 transparent GIF response
        header('Content-Type: image/gif');
        header('Content-Length: 43');
        header('Cache-Control: no-cache, no-store, must-revalidate');

        // 1x1 transparent GIF
        echo base64_decode('R0lGODlhAQABAIAAAP///wAAACH5BAEAAAAALAAAAAABAAEAAAICRAEAOw==');
        exit;
    }
}

/**
 * Override redirect to add fingerprinting JavaScript
 * Using redirect_location filter to intercept ALL redirects before they happen
 */
yourls_add_filter('redirect_location', 'advanced_tracker_inject_fingerprint_js', 1, 2);
function advanced_tracker_inject_fingerprint_js($location, $code) {
    // Check if this is a beacon request - handle it immediately
    $request_uri = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '';
    if (preg_match('#^/__beacon#', $request_uri) && isset($_POST['fingerprint'])) {
        advanced_tracker_save_fingerprint($_POST['fingerprint']);

        // Send 1x1 transparent GIF response
        header('Content-Type: image/gif');
        header('Content-Length: 43');
        header('Cache-Control: no-cache, no-store, must-revalidate');

        // 1x1 transparent GIF
        echo base64_decode('R0lGODlhAQABAIAAAP///wAAACH5BAEAAAAALAAAAAABAAEAAAICRAEAOw==');
        exit;
    }

    // Only intercept if this is a short URL redirect (not admin redirects)
    if (yourls_is_admin()) {
        return $location;
    }

    // Get the keyword from the request URI
    $request = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '';
    $keyword = trim(str_replace('/', '', parse_url($request, PHP_URL_PATH)));

    // Skip if no keyword or if it's a special page or beacon
    if (empty($keyword) || $keyword === 'admin' || $keyword === '__beacon' || strpos($keyword, '?') !== false) {
        return $location;
    }

    // Log the click since we're intercepting before the normal redirect
    // This ensures tracking data is saved with the correct keyword
    advanced_tracker_log_click(array($keyword, $location, $code));

    // Output HTML page with JavaScript fingerprinting and meta refresh
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <meta http-equiv="refresh" content="0;url=<?php echo htmlspecialchars($location); ?>">
        <title>Redirecting...</title>
    </head>
    <body>
        <p>Redirecting...</p>
        <?php echo advanced_tracker_get_fingerprint_script($keyword); ?>
        <script>
        // Also do immediate redirect via JavaScript
        setTimeout(function() {
            window.location.href = "<?php echo addslashes($location); ?>";
        }, 100);
        </script>
    </body>
    </html>
    <?php
    exit; // Prevent YOURLS from doing its own redirect
}

/**
 * Save fingerprint data to database with proper sanitization
 */
function advanced_tracker_save_fingerprint($fingerprint_json) {
    global $ydb;

    // Decode and validate JSON input
    $data = json_decode(stripslashes($fingerprint_json), true);
    if (!$data || !is_array($data) || empty($data['keyword'])) {
        return;
    }

    $table = ADVANCED_TRACKER_TABLE;

    // Sanitize keyword - alphanumeric and hyphens only
    $keyword = preg_replace('/[^a-zA-Z0-9\-_]/', '', $data['keyword']);
    if (empty($keyword)) {
        return;
    }

    // Find the most recent entry for this keyword and IP to update it
    $ip = advanced_tracker_get_ip();
    $sql = "SELECT id FROM `$table` WHERE keyword = :keyword AND ip_address = :ip ORDER BY timestamp DESC LIMIT 1";
    $row = $ydb->fetchObject($sql, array('keyword' => $keyword, 'ip' => $ip));

    if ($row) {
        // Update the existing row with fingerprint data
        $update_sql = "UPDATE `$table` SET
            screen_resolution = :screen_resolution,
            viewport_size = :viewport_size,
            color_depth = :color_depth,
            timezone = :timezone,
            platform = :platform,
            cookies_enabled = :cookies_enabled,
            do_not_track = :do_not_track,
            touch_support = :touch_support,
            cpu_cores = :cpu_cores,
            device_memory = :device_memory,
            connection_type = :connection_type,
            webgl_vendor = :webgl_vendor,
            webgl_renderer = :webgl_renderer,
            canvas_fingerprint = :canvas_fingerprint,
            fonts_detected = :fonts_detected,
            plugins_list = :plugins_list,
            battery_charging = :battery_charging,
            battery_level = :battery_level
            WHERE id = :id";

        // Sanitize all input data for security
        $binds = array(
            'id' => (int)$row->id,
            // Screen resolution: only digits and 'x', max 20 chars
            'screen_resolution' => isset($data['screen_resolution']) ? substr(preg_replace('/[^0-9x]/', '', $data['screen_resolution']), 0, 20) : null,
            // Viewport size: only digits and 'x', max 20 chars
            'viewport_size' => isset($data['viewport_size']) ? substr(preg_replace('/[^0-9x]/', '', $data['viewport_size']), 0, 20) : null,
            // Color depth: integer only, reasonable range (1-64)
            'color_depth' => isset($data['color_depth']) ? max(1, min(64, (int)$data['color_depth'])) : null,
            // Timezone: sanitize to allowed characters, max 50 chars
            'timezone' => isset($data['timezone']) ? substr(preg_replace('/[^a-zA-Z0-9\/_\-+]/', '', $data['timezone']), 0, 50) : null,
            // Platform: sanitize, max 100 chars
            'platform' => isset($data['platform']) ? substr(strip_tags($data['platform']), 0, 100) : null,
            // Boolean values
            'cookies_enabled' => isset($data['cookies_enabled']) ? (int)(bool)$data['cookies_enabled'] : null,
            'do_not_track' => isset($data['do_not_track']) ? (int)(bool)$data['do_not_track'] : null,
            'touch_support' => isset($data['touch_support']) ? (int)(bool)$data['touch_support'] : null,
            // CPU cores: integer, reasonable range (1-256)
            'cpu_cores' => isset($data['cpu_cores']) ? max(0, min(256, (int)$data['cpu_cores'])) : null,
            // Device memory: float, reasonable range (0-1024 GB)
            'device_memory' => isset($data['device_memory']) ? max(0, min(1024, (float)$data['device_memory'])) : null,
            // Connection type: sanitize, max 50 chars
            'connection_type' => isset($data['connection_type']) ? substr(preg_replace('/[^a-zA-Z0-9\-]/', '', $data['connection_type']), 0, 50) : null,
            // WebGL vendor/renderer: sanitize, max 200 chars
            'webgl_vendor' => isset($data['webgl_vendor']) ? substr(strip_tags($data['webgl_vendor']), 0, 200) : null,
            'webgl_renderer' => isset($data['webgl_renderer']) ? substr(strip_tags($data['webgl_renderer']), 0, 200) : null,
            // Canvas fingerprint: alphanumeric only, max 64 chars
            'canvas_fingerprint' => isset($data['canvas_fingerprint']) ? substr(preg_replace('/[^a-zA-Z0-9+\/=]/', '', $data['canvas_fingerprint']), 0, 64) : null,
            // Arrays: validate and re-encode to ensure no malicious content
            'fonts_detected' => isset($data['fonts_detected']) && is_array($data['fonts_detected']) ?
                json_encode(array_map(function($f) { return substr(strip_tags($f), 0, 100); }, array_slice($data['fonts_detected'], 0, 50))) : null,
            'plugins_list' => isset($data['plugins_list']) && is_array($data['plugins_list']) ?
                json_encode(array_map(function($p) { return substr(strip_tags($p), 0, 200); }, array_slice($data['plugins_list'], 0, 50))) : null,
            // Battery: boolean and integer percentage
            'battery_charging' => isset($data['battery_charging']) ? (int)(bool)$data['battery_charging'] : null,
            'battery_level' => isset($data['battery_level']) ? max(0, min(100, (int)$data['battery_level'])) : null
        );

        $ydb->fetchAffected($update_sql, $binds);
    }
}

/**
 * Generate fingerprinting JavaScript
 */
function advanced_tracker_get_fingerprint_script($keyword) {
    $beacon_url = yourls_site_url() . '/__beacon';
    $settings = advanced_tracker_get_settings();

    $js = '<script>
(function() {
    var fp = {
        keyword: "' . addslashes($keyword) . '"';

    // Basic screen info
    if ($settings['track_screen']) {
        $js .= ',
        screen_resolution: screen.width + "x" + screen.height,
        viewport_size: window.innerWidth + "x" + window.innerHeight,
        color_depth: screen.colorDepth';
    }

    // Timezone
    if ($settings['track_timezone']) {
        $js .= ',
        timezone: Intl.DateTimeFormat().resolvedOptions().timeZone || "Unknown"';
    }

    // Platform
    if ($settings['track_platform']) {
        $js .= ',
        platform: navigator.platform,
        cookies_enabled: navigator.cookieEnabled ? 1 : 0';
    }

    // DNT
    if ($settings['track_dnt']) {
        $js .= ',
        do_not_track: navigator.doNotTrack === "1" ? 1 : 0';
    }

    // Touch support
    if ($settings['track_touch']) {
        $js .= ',
        touch_support: "ontouchstart" in window ? 1 : 0';
    }

    // Hardware info
    if ($settings['track_hardware']) {
        $js .= ',
        cpu_cores: navigator.hardwareConcurrency || 0,
        device_memory: navigator.deviceMemory || 0';
    }

    // Network info
    if ($settings['track_network']) {
        $js .= ',
        connection_type: (navigator.connection && navigator.connection.effectiveType) || "Unknown"';
    }

    $js .= '
    };

';

    // WebGL fingerprinting
    if ($settings['track_webgl']) {
        $js .= '
    // WebGL fingerprinting
    try {
        var canvas = document.createElement("canvas");
        var gl = canvas.getContext("webgl") || canvas.getContext("experimental-webgl");
        if (gl) {
            var debugInfo = gl.getExtension("WEBGL_debug_renderer_info");
            if (debugInfo) {
                fp.webgl_vendor = gl.getParameter(debugInfo.UNMASKED_VENDOR_WEBGL);
                fp.webgl_renderer = gl.getParameter(debugInfo.UNMASKED_RENDERER_WEBGL);
            }
        }
    } catch(e) {}

';
    }

    // Canvas fingerprinting
    if ($settings['track_canvas']) {
        $js .= '
    // Canvas fingerprinting
    try {
        var canvas = document.createElement("canvas");
        var ctx = canvas.getContext("2d");
        ctx.textBaseline = "top";
        ctx.font = "14px Arial";
        ctx.fillStyle = "#f60";
        ctx.fillRect(125, 1, 62, 20);
        ctx.fillStyle = "#069";
        ctx.fillText("Canvas FP", 2, 15);
        ctx.fillStyle = "rgba(102, 204, 0, 0.7)";
        ctx.fillText("Canvas FP", 4, 17);
        var hash = canvas.toDataURL().substring(22, 86);
        fp.canvas_fingerprint = hash;
    } catch(e) {}

';
    }

    // Audio fingerprinting
    if ($settings['track_audio']) {
        $js .= '
    // Audio context fingerprinting
    try {
        var AudioContext = window.AudioContext || window.webkitAudioContext;
        if (AudioContext) {
            var context = new AudioContext();
            var oscillator = context.createOscillator();
            var analyser = context.createAnalyser();
            var gainNode = context.createGain();
            var scriptProcessor = context.createScriptProcessor(4096, 1, 1);
            gainNode.gain.value = 0;
            oscillator.connect(analyser);
            analyser.connect(scriptProcessor);
            scriptProcessor.connect(gainNode);
            gainNode.connect(context.destination);
            scriptProcessor.onaudioprocess = function(event) {
                var output = event.outputBuffer.getChannelData(0);
                var hash = 0;
                for (var i = 0; i < output.length; i++) {
                    hash += Math.abs(output[i]);
                }
                fp.audio_fingerprint = hash.toString();
                scriptProcessor.disconnect();
            };
            oscillator.start(0);
            context.close();
        }
    } catch(e) {}

';
    }

    // Font detection
    if ($settings['track_fonts']) {
        $js .= '
    // Font detection
    try {
        var baseFonts = ["monospace", "sans-serif", "serif"];
        var testFonts = ["Arial", "Verdana", "Times New Roman", "Courier New", "Georgia", "Palatino", "Garamond", "Bookman", "Comic Sans MS", "Trebuchet MS", "Impact"];
        var detected = [];
        var testString = "mmmmmmmmmmlli";
        var testSize = "72px";
        var h = document.getElementsByTagName("body")[0];
        var s = document.createElement("span");
        s.style.fontSize = testSize;
        s.innerHTML = testString;
        var defaultWidth = {};
        var defaultHeight = {};
        for (var i = 0; i < baseFonts.length; i++) {
            s.style.fontFamily = baseFonts[i];
            h.appendChild(s);
            defaultWidth[baseFonts[i]] = s.offsetWidth;
            defaultHeight[baseFonts[i]] = s.offsetHeight;
            h.removeChild(s);
        }
        for (var i = 0; i < testFonts.length; i++) {
            var detected_flag = false;
            for (var j = 0; j < baseFonts.length; j++) {
                s.style.fontFamily = testFonts[i] + "," + baseFonts[j];
                h.appendChild(s);
                var matched = (s.offsetWidth !== defaultWidth[baseFonts[j]] || s.offsetHeight !== defaultHeight[baseFonts[j]]);
                h.removeChild(s);
                if (matched) {
                    detected_flag = true;
                }
            }
            if (detected_flag) {
                detected.push(testFonts[i]);
            }
        }
        fp.fonts_detected = detected;
    } catch(e) {}

';
    }

    // Plugin detection
    if ($settings['track_plugins']) {
        $js .= '
    // Plugin detection
    try {
        var plugins = [];
        for (var i = 0; i < navigator.plugins.length; i++) {
            plugins.push(navigator.plugins[i].name);
        }
        fp.plugins_list = plugins;
    } catch(e) {}

';
    }

    // Battery status
    if ($settings['track_battery']) {
        $js .= '
    // Battery status
    if (navigator.getBattery) {
        navigator.getBattery().then(function(battery) {
            fp.battery_charging = battery.charging ? 1 : 0;
            fp.battery_level = Math.round(battery.level * 100);
            sendFingerprint();
        });
    } else {
        sendFingerprint();
    }
';
    } else {
        $js .= '
    sendFingerprint();
';
    }

    $js .= '
    function sendFingerprint() {
        var xhr = new XMLHttpRequest();
        xhr.open("POST", "' . $beacon_url . '", true);
        xhr.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");
        xhr.send("fingerprint=" + encodeURIComponent(JSON.stringify(fp)));
    }
})();
</script>';

    return $js;
}

/**
 * Main tracking function - logs comprehensive visitor data
 */
function advanced_tracker_log_click($args) {
    global $ydb;

    $keyword = isset($args[0]) ? $args[0] : '';
    if (empty($keyword)) {
        return;
    }

    // Get settings
    $settings = advanced_tracker_get_settings();

    // Get IP address (if enabled)
    $ip = $settings['track_ip'] ? advanced_tracker_get_ip() : null;

    // Get user agent (if enabled)
    $user_agent = $settings['track_user_agent'] && isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '';

    // Parse user agent (if enabled)
    $device_info = $settings['track_user_agent'] ? advanced_tracker_parse_user_agent($user_agent) : array();

    // Get geolocation data (if enabled and IP tracking is on)
    $geo_data = ($settings['track_geolocation'] && $ip) ? advanced_tracker_get_geolocation($ip) : array();

    // Get referrer (if enabled)
    $referrer = $settings['track_referrer'] && isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '';

    // Get language (if enabled)
    $language = $settings['track_language'] && isset($_SERVER['HTTP_ACCEPT_LANGUAGE']) ? substr($_SERVER['HTTP_ACCEPT_LANGUAGE'], 0, 50) : '';

    // Prepare data for insertion
    $table = ADVANCED_TRACKER_TABLE;
    $timestamp = date('Y-m-d H:i:s');

    $sql = "INSERT INTO `$table` (
        keyword, timestamp, ip_address, country, country_code, region, city,
        latitude, longitude, isp, user_agent, browser, browser_version,
        os, os_version, device_type, device_brand, device_model, referrer, language
    ) VALUES (
        :keyword, :timestamp, :ip, :country, :country_code, :region, :city,
        :latitude, :longitude, :isp, :user_agent, :browser, :browser_version,
        :os, :os_version, :device_type, :device_brand, :device_model, :referrer, :language
    )";

    $binds = array(
        'keyword' => $keyword,
        'timestamp' => $timestamp,
        'ip' => $ip,
        'country' => $geo_data['country'] ?? null,
        'country_code' => $geo_data['countryCode'] ?? null,
        'region' => $geo_data['regionName'] ?? null,
        'city' => $geo_data['city'] ?? null,
        'latitude' => $geo_data['lat'] ?? null,
        'longitude' => $geo_data['lon'] ?? null,
        'isp' => $geo_data['isp'] ?? null,
        'user_agent' => $user_agent,
        'browser' => $device_info['browser'] ?? null,
        'browser_version' => $device_info['browser_version'] ?? null,
        'os' => $device_info['os'] ?? null,
        'os_version' => $device_info['os_version'] ?? null,
        'device_type' => $device_info['device_type'] ?? null,
        'device_brand' => $device_info['device_brand'] ?? null,
        'device_model' => $device_info['device_model'] ?? null,
        'referrer' => $referrer,
        'language' => $language
    );

    $ydb->fetchAffected($sql, $binds);
}

/**
 * Get visitor's real IP address
 */
function advanced_tracker_get_ip() {
    $ip_keys = array(
        'HTTP_CLIENT_IP',
        'HTTP_X_FORWARDED_FOR',
        'HTTP_X_FORWARDED',
        'HTTP_X_CLUSTER_CLIENT_IP',
        'HTTP_FORWARDED_FOR',
        'HTTP_FORWARDED',
        'REMOTE_ADDR'
    );

    foreach ($ip_keys as $key) {
        if (array_key_exists($key, $_SERVER) === true) {
            foreach (explode(',', $_SERVER[$key]) as $ip) {
                $ip = trim($ip);
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false) {
                    return $ip;
                }
            }
        }
    }

    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

/**
 * Get geolocation data from IP address
 */
function advanced_tracker_get_geolocation($ip) {
    // Skip geolocation for private IPs
    if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
        return array();
    }

    // Use ip-api.com (free, no API key required, 45 requests/minute limit)
    $url = "http://ip-api.com/json/{$ip}?fields=status,country,countryCode,region,regionName,city,lat,lon,isp,org";

    $context = stream_context_create(array(
        'http' => array(
            'timeout' => 3,
            'ignore_errors' => true
        )
    ));

    $response = @file_get_contents($url, false, $context);

    if ($response === false) {
        return array();
    }

    $data = json_decode($response, true);

    if (isset($data['status']) && $data['status'] === 'success') {
        return $data;
    }

    return array();
}

/**
 * Parse user agent to extract device information
 */
function advanced_tracker_parse_user_agent($user_agent) {
    $info = array(
        'browser' => 'Unknown',
        'browser_version' => 'Unknown',
        'os' => 'Unknown',
        'os_version' => 'Unknown',
        'device_type' => 'Desktop',
        'device_brand' => null,
        'device_model' => null
    );

    if (empty($user_agent)) {
        return $info;
    }

    // Detect browser
    $browsers = array(
        'Edg' => 'Edge',
        'Chrome' => 'Chrome',
        'Safari' => 'Safari',
        'Firefox' => 'Firefox',
        'MSIE' => 'Internet Explorer',
        'Trident' => 'Internet Explorer',
        'Opera' => 'Opera',
        'OPR' => 'Opera'
    );

    foreach ($browsers as $pattern => $name) {
        if (stripos($user_agent, $pattern) !== false) {
            $info['browser'] = $name;

            // Extract version
            if ($pattern === 'Edg') {
                preg_match('/Edg\/([0-9.]+)/', $user_agent, $matches);
            } elseif ($pattern === 'OPR') {
                preg_match('/OPR\/([0-9.]+)/', $user_agent, $matches);
            } elseif ($pattern === 'MSIE') {
                preg_match('/MSIE ([0-9.]+)/', $user_agent, $matches);
            } elseif ($pattern === 'Trident') {
                preg_match('/rv:([0-9.]+)/', $user_agent, $matches);
            } else {
                preg_match("/{$pattern}\/([0-9.]+)/", $user_agent, $matches);
            }

            if (isset($matches[1])) {
                $info['browser_version'] = $matches[1];
            }
            break;
        }
    }

    // Detect OS
    $os_array = array(
        'Windows NT 10.0' => array('Windows 10', '10'),
        'Windows NT 6.3' => array('Windows 8.1', '8.1'),
        'Windows NT 6.2' => array('Windows 8', '8'),
        'Windows NT 6.1' => array('Windows 7', '7'),
        'Windows NT 6.0' => array('Windows Vista', 'Vista'),
        'Windows NT 5.1' => array('Windows XP', 'XP'),
        'Mac OS X' => array('macOS', null),
        'Android' => array('Android', null),
        'Linux' => array('Linux', null),
        'iPhone' => array('iOS', null),
        'iPad' => array('iOS', null),
        'iPod' => array('iOS', null)
    );

    foreach ($os_array as $pattern => $os) {
        if (stripos($user_agent, $pattern) !== false) {
            $info['os'] = $os[0];

            // Extract version
            if ($pattern === 'Mac OS X') {
                preg_match('/Mac OS X ([0-9._]+)/', $user_agent, $matches);
                if (isset($matches[1])) {
                    $info['os_version'] = str_replace('_', '.', $matches[1]);
                }
            } elseif ($pattern === 'Android') {
                preg_match('/Android ([0-9.]+)/', $user_agent, $matches);
                if (isset($matches[1])) {
                    $info['os_version'] = $matches[1];
                }
            } elseif (strpos($pattern, 'iPhone') !== false || strpos($pattern, 'iPad') !== false) {
                preg_match('/OS ([0-9_]+)/', $user_agent, $matches);
                if (isset($matches[1])) {
                    $info['os_version'] = str_replace('_', '.', $matches[1]);
                }
            } elseif ($os[1] !== null) {
                $info['os_version'] = $os[1];
            }
            break;
        }
    }

    // Detect device type
    if (preg_match('/(tablet|ipad|playbook|silk)|(android(?!.*mobile))/i', $user_agent)) {
        $info['device_type'] = 'Tablet';
    } elseif (preg_match('/Mobile|iP(hone|od)|Android|BlackBerry|IEMobile/', $user_agent)) {
        $info['device_type'] = 'Mobile';
    }

    // Detect device brand/model (basic detection)
    $devices = array(
        'iPhone' => array('Apple', 'iPhone'),
        'iPad' => array('Apple', 'iPad'),
        'iPod' => array('Apple', 'iPod'),
        'Samsung' => array('Samsung', null),
        'Huawei' => array('Huawei', null),
        'Xiaomi' => array('Xiaomi', null),
        'OnePlus' => array('OnePlus', null),
        'Pixel' => array('Google', 'Pixel')
    );

    foreach ($devices as $pattern => $device) {
        if (stripos($user_agent, $pattern) !== false) {
            $info['device_brand'] = $device[0];
            $info['device_model'] = $device[1];
            break;
        }
    }

    return $info;
}

/**
 * Add admin page
 */
function advanced_tracker_add_page() {
    yourls_register_plugin_page('advanced_tracker', 'Advanced Tracker Dashboard', 'advanced_tracker_display_page');
    yourls_register_plugin_page('advanced_tracker_settings', 'Advanced Tracker Settings', 'advanced_tracker_display_settings_page');
}

/**
 * Display admin page with analytics
 */
function advanced_tracker_display_page() {
    // Check if user is logged in
    if (!defined('YOURLS_USER')) {
        die('Unauthorized');
    }

    global $ydb;
    $table = ADVANCED_TRACKER_TABLE;

    // Export is now handled in advanced_tracker_handle_export_early() before page loads

    // Get filter parameters
    $keyword_filter = isset($_GET['keyword']) ? $_GET['keyword'] : '';
    $date_from = isset($_GET['date_from']) ? $_GET['date_from'] : '';
    $date_to = isset($_GET['date_to']) ? $_GET['date_to'] : '';
    $limit = isset($_GET['limit']) ? intval($_GET['limit']) : 100;
    $page = isset($_GET['paged']) ? intval($_GET['paged']) : 1;
    $offset = ($page - 1) * $limit;

    // Build query
    $where_conditions = array();
    $binds = array();

    if (!empty($keyword_filter)) {
        $where_conditions[] = "keyword = :keyword";
        $binds['keyword'] = $keyword_filter;
    }

    if (!empty($date_from)) {
        $where_conditions[] = "timestamp >= :date_from";
        $binds['date_from'] = $date_from . ' 00:00:00';
    }

    if (!empty($date_to)) {
        $where_conditions[] = "timestamp <= :date_to";
        $binds['date_to'] = $date_to . ' 23:59:59';
    }

    $where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

    // Get total count
    $count_sql = "SELECT COUNT(*) as total FROM `$table` $where_clause";
    $total_clicks = $ydb->fetchValue($count_sql, $binds);

    // Get clicks data
    $sql = "SELECT * FROM `$table` $where_clause ORDER BY timestamp DESC LIMIT $limit OFFSET $offset";
    $clicks = $ydb->fetchObjects($sql, $binds);

    // Get statistics
    $stats = advanced_tracker_get_statistics($where_clause, $binds);

    // Get all keywords for filter dropdown
    $keywords_sql = "SELECT DISTINCT keyword FROM `$table` ORDER BY keyword";
    $keywords = $ydb->fetchObjects($keywords_sql);

    // Display the page
    include(dirname(__FILE__) . '/admin-page.php');
}

/**
 * Get statistics for dashboard
 */
function advanced_tracker_get_statistics($where_clause, $binds) {
    global $ydb;
    $table = ADVANCED_TRACKER_TABLE;

    $stats = array();

    // Total clicks
    $stats['total_clicks'] = $ydb->fetchValue("SELECT COUNT(*) FROM `$table` $where_clause", $binds);

    // Unique visitors (by IP)
    $stats['unique_visitors'] = $ydb->fetchValue("SELECT COUNT(DISTINCT ip_address) FROM `$table` $where_clause", $binds);

    // Top countries
    $where_and_country = $where_clause ? $where_clause . ' AND country IS NOT NULL' : 'WHERE country IS NOT NULL';
    $stats['top_countries'] = $ydb->fetchObjects("
        SELECT country, COUNT(*) as count
        FROM `$table` $where_and_country
        GROUP BY country
        ORDER BY count DESC
        LIMIT 10
    ", $binds);

    // Top browsers
    $where_and_browser = $where_clause ? $where_clause . ' AND browser IS NOT NULL' : 'WHERE browser IS NOT NULL';
    $stats['top_browsers'] = $ydb->fetchObjects("
        SELECT browser, COUNT(*) as count
        FROM `$table` $where_and_browser
        GROUP BY browser
        ORDER BY count DESC
        LIMIT 10
    ", $binds);

    // Top OS
    $where_and_os = $where_clause ? $where_clause . ' AND os IS NOT NULL' : 'WHERE os IS NOT NULL';
    $stats['top_os'] = $ydb->fetchObjects("
        SELECT os, COUNT(*) as count
        FROM `$table` $where_and_os
        GROUP BY os
        ORDER BY count DESC
        LIMIT 10
    ", $binds);

    // Device types
    $stats['device_types'] = $ydb->fetchObjects("
        SELECT device_type, COUNT(*) as count
        FROM `$table` $where_clause
        GROUP BY device_type
        ORDER BY count DESC
    ", $binds);

    // Top referrers
    $where_and_referrer = $where_clause ? $where_clause . " AND referrer != ''" : "WHERE referrer != ''";
    $stats['top_referrers'] = $ydb->fetchObjects("
        SELECT referrer, COUNT(*) as count
        FROM `$table` $where_and_referrer
        GROUP BY referrer
        ORDER BY count DESC
        LIMIT 10
    ", $binds);

    // Clicks by date (last 30 days)
    $stats['clicks_by_date'] = $ydb->fetchObjects("
        SELECT DATE(timestamp) as date, COUNT(*) as count
        FROM `$table` $where_clause
        GROUP BY DATE(timestamp)
        ORDER BY date DESC
        LIMIT 30
    ", $binds);

    return $stats;
}

/**
 * Export data to CSV or JSON
 */
function advanced_tracker_export_data($format, $id = null) {
    global $ydb;
    $table = ADVANCED_TRACKER_TABLE;

    // Get data - either single row or all
    if ($id !== null) {
        $sql = "SELECT * FROM `$table` WHERE id = :id";
        $data = $ydb->fetchObjects($sql, array('id' => $id));
        $filename_prefix = 'tracker-row-' . $id;
    } else {
        $sql = "SELECT * FROM `$table` ORDER BY timestamp DESC";
        $data = $ydb->fetchObjects($sql);
        $filename_prefix = 'advanced-tracker-export';
    }

    if ($format === 'csv') {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename_prefix . '-' . date('Y-m-d') . '.csv"');
        header('Cache-Control: no-cache, no-store, must-revalidate');
        header('Pragma: no-cache');
        header('Expires: 0');

        $output = fopen('php://output', 'w');

        // Headers
        if (!empty($data)) {
            fputcsv($output, array_keys(get_object_vars($data[0])));
        }

        // Data
        foreach ($data as $row) {
            fputcsv($output, get_object_vars($row));
        }

        fclose($output);
    } elseif ($format === 'json') {
        header('Content-Type: application/json; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename_prefix . '-' . date('Y-m-d') . '.json"');
        header('Cache-Control: no-cache, no-store, must-revalidate');
        header('Pragma: no-cache');
        header('Expires: 0');

        echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    exit; // Important: stop execution after export
}

/**
 * Display settings page
 */
function advanced_tracker_display_settings_page() {
    // Check if user is logged in
    if (!defined('YOURLS_USER')) {
        die('Unauthorized');
    }

    // Handle form submission
    $saved = false;
    if (isset($_POST['advanced_tracker_save_settings'])) {
        // Verify nonce
        yourls_verify_nonce('advanced_tracker_settings');

        // Get all tracking options
        $settings_to_save = array();
        $defaults = advanced_tracker_get_default_settings();

        foreach ($defaults as $key => $default_value) {
            // Checkboxes are only present if checked
            $settings_to_save[$key] = isset($_POST[$key]) ? true : false;
        }

        // Save settings
        advanced_tracker_save_settings($settings_to_save);
        $saved = true;
    }

    $settings = advanced_tracker_get_settings();
    $nonce = yourls_create_nonce('advanced_tracker_settings');

    ?>
    <style>
        .settings-container {
            max-width: 900px;
            margin: 20px 0;
        }
        .settings-section {
            background: #fff;
            border: 1px solid #ddd;
            border-radius: 5px;
            padding: 20px;
            margin-bottom: 20px;
        }
        .settings-section h3 {
            margin-top: 0;
            border-bottom: 2px solid #0073aa;
            padding-bottom: 10px;
        }
        .setting-item {
            display: flex;
            align-items: center;
            padding: 10px 0;
            border-bottom: 1px solid #eee;
        }
        .setting-item:last-child {
            border-bottom: none;
        }
        .setting-item label {
            flex: 1;
            display: flex;
            align-items: center;
            cursor: pointer;
        }
        .setting-item input[type="checkbox"] {
            margin-right: 10px;
        }
        .setting-description {
            color: #666;
            font-size: 0.9em;
            margin-left: 30px;
        }
        .privacy-warning {
            background: #fff3cd;
            border-left: 4px solid #ffc107;
            padding: 10px 15px;
            margin-bottom: 20px;
        }
        .privacy-sensitive {
            background: #fff8f0;
        }
        .save-button {
            background: #0073aa;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 3px;
            cursor: pointer;
            font-size: 14px;
        }
        .save-button:hover {
            background: #005a87;
        }
        .success-message {
            background: #d4edda;
            border-left: 4px solid #28a745;
            padding: 10px 15px;
            margin-bottom: 20px;
            color: #155724;
        }
        .preset-buttons {
            margin-bottom: 20px;
        }
        .preset-button {
            padding: 8px 15px;
            margin-right: 10px;
            border: 1px solid #ddd;
            background: #f5f5f5;
            cursor: pointer;
            border-radius: 3px;
        }
        .preset-button:hover {
            background: #e5e5e5;
        }
    </style>

    <div class="wrap">
        <h2>🔧 Advanced Tracker Settings</h2>

        <?php if ($saved): ?>
        <div class="success-message">
            ✓ Settings saved successfully!
        </div>
        <?php endif; ?>

        <div class="privacy-warning">
            ⚠️ <strong>GDPR & Privacy Notice:</strong> Advanced fingerprinting techniques may be subject to privacy regulations in your jurisdiction.
            Ensure you have proper consent mechanisms and privacy policies in place before collecting sensitive data.
        </div>

        <div class="settings-container">
            <form method="post" action="">
                <?php yourls_nonce_field('advanced_tracker_settings'); ?>

                <div class="preset-buttons">
                    <strong>Quick Presets:</strong>
                    <button type="button" class="preset-button" onclick="setPreset('all')">Enable All</button>
                    <button type="button" class="preset-button" onclick="setPreset('minimal')">Minimal (GDPR-Safe)</button>
                    <button type="button" class="preset-button" onclick="setPreset('moderate')">Moderate</button>
                    <button type="button" class="preset-button" onclick="setPreset('none')">Disable All</button>
                </div>

                <!-- Basic Tracking -->
                <div class="settings-section">
                    <h3>📊 Basic Tracking (Recommended)</h3>
                    <p>Essential tracking data that is generally GDPR-compliant when properly disclosed.</p>

                    <div class="setting-item">
                        <label>
                            <input type="checkbox" name="track_ip" <?php echo $settings['track_ip'] ? 'checked' : ''; ?>>
                            <strong>IP Address</strong>
                        </label>
                    </div>
                    <div class="setting-description">Track visitor IP addresses for geolocation and basic analytics</div>

                    <div class="setting-item">
                        <label>
                            <input type="checkbox" name="track_user_agent" <?php echo $settings['track_user_agent'] ? 'checked' : ''; ?>>
                            <strong>User Agent</strong>
                        </label>
                    </div>
                    <div class="setting-description">Browser and operating system information</div>

                    <div class="setting-item">
                        <label>
                            <input type="checkbox" name="track_referrer" <?php echo $settings['track_referrer'] ? 'checked' : ''; ?>>
                            <strong>Referrer URL</strong>
                        </label>
                    </div>
                    <div class="setting-description">Track where visitors came from</div>

                    <div class="setting-item">
                        <label>
                            <input type="checkbox" name="track_language" <?php echo $settings['track_language'] ? 'checked' : ''; ?>>
                            <strong>Browser Language</strong>
                        </label>
                    </div>
                    <div class="setting-description">Visitor's preferred language settings</div>

                    <div class="setting-item">
                        <label>
                            <input type="checkbox" name="track_geolocation" <?php echo $settings['track_geolocation'] ? 'checked' : ''; ?>>
                            <strong>Geolocation (via IP)</strong>
                        </label>
                    </div>
                    <div class="setting-description">Country, region, and city based on IP address</div>
                </div>

                <!-- Device & Browser Info -->
                <div class="settings-section">
                    <h3>💻 Device & Browser Information</h3>
                    <p>Standard device and browser capabilities.</p>

                    <div class="setting-item">
                        <label>
                            <input type="checkbox" name="track_screen" <?php echo $settings['track_screen'] ? 'checked' : ''; ?>>
                            <strong>Screen Resolution</strong>
                        </label>
                    </div>
                    <div class="setting-description">Screen size, resolution, and color depth</div>

                    <div class="setting-item">
                        <label>
                            <input type="checkbox" name="track_timezone" <?php echo $settings['track_timezone'] ? 'checked' : ''; ?>>
                            <strong>Timezone</strong>
                        </label>
                    </div>
                    <div class="setting-description">Visitor's timezone offset</div>

                    <div class="setting-item">
                        <label>
                            <input type="checkbox" name="track_platform" <?php echo $settings['track_platform'] ? 'checked' : ''; ?>>
                            <strong>Platform Details</strong>
                        </label>
                    </div>
                    <div class="setting-description">Operating system platform information</div>

                    <div class="setting-item">
                        <label>
                            <input type="checkbox" name="track_plugins" <?php echo $settings['track_plugins'] ? 'checked' : ''; ?>>
                            <strong>Browser Plugins</strong>
                        </label>
                    </div>
                    <div class="setting-description">Installed browser plugins and MIME types</div>

                    <div class="setting-item">
                        <label>
                            <input type="checkbox" name="track_touch" <?php echo $settings['track_touch'] ? 'checked' : ''; ?>>
                            <strong>Touch Support</strong>
                        </label>
                    </div>
                    <div class="setting-description">Touchscreen capability detection</div>

                    <div class="setting-item">
                        <label>
                            <input type="checkbox" name="track_dnt" <?php echo $settings['track_dnt'] ? 'checked' : ''; ?>>
                            <strong>Do Not Track (DNT)</strong>
                        </label>
                    </div>
                    <div class="setting-description">Visitor's Do Not Track preference</div>
                </div>

                <!-- Advanced Fingerprinting -->
                <div class="settings-section privacy-sensitive">
                    <h3>🔍 Advanced Fingerprinting (Privacy-Sensitive)</h3>
                    <p><strong>⚠️ Warning:</strong> These techniques create unique visitor fingerprints and may require explicit consent under GDPR/CCPA.</p>

                    <div class="setting-item">
                        <label>
                            <input type="checkbox" name="track_canvas" <?php echo $settings['track_canvas'] ? 'checked' : ''; ?>>
                            <strong>Canvas Fingerprinting</strong>
                        </label>
                    </div>
                    <div class="setting-description">Unique browser rendering signature (highly identifying)</div>

                    <div class="setting-item">
                        <label>
                            <input type="checkbox" name="track_webgl" <?php echo $settings['track_webgl'] ? 'checked' : ''; ?>>
                            <strong>WebGL Fingerprinting</strong>
                        </label>
                    </div>
                    <div class="setting-description">GPU and graphics capabilities (highly identifying)</div>

                    <div class="setting-item">
                        <label>
                            <input type="checkbox" name="track_audio" <?php echo $settings['track_audio'] ? 'checked' : ''; ?>>
                            <strong>Audio Context Fingerprinting</strong>
                        </label>
                    </div>
                    <div class="setting-description">Audio processing characteristics (highly identifying)</div>

                    <div class="setting-item">
                        <label>
                            <input type="checkbox" name="track_fonts" <?php echo $settings['track_fonts'] ? 'checked' : ''; ?>>
                            <strong>Font Detection</strong>
                        </label>
                    </div>
                    <div class="setting-description">Installed system fonts enumeration</div>
                </div>

                <!-- Hardware Info -->
                <div class="settings-section privacy-sensitive">
                    <h3>⚙️ Hardware Information (Privacy-Sensitive)</h3>
                    <p>Detailed hardware specifications that may be identifying.</p>

                    <div class="setting-item">
                        <label>
                            <input type="checkbox" name="track_battery" <?php echo $settings['track_battery'] ? 'checked' : ''; ?>>
                            <strong>Battery Status</strong>
                        </label>
                    </div>
                    <div class="setting-description">Device battery level and charging state</div>

                    <div class="setting-item">
                        <label>
                            <input type="checkbox" name="track_hardware" <?php echo $settings['track_hardware'] ? 'checked' : ''; ?>>
                            <strong>Hardware Details</strong>
                        </label>
                    </div>
                    <div class="setting-description">CPU cores, device memory, hardware concurrency</div>

                    <div class="setting-item">
                        <label>
                            <input type="checkbox" name="track_network" <?php echo $settings['track_network'] ? 'checked' : ''; ?>>
                            <strong>Network Information</strong>
                        </label>
                    </div>
                    <div class="setting-description">Connection type and effective bandwidth</div>
                </div>

                <div style="margin-top: 20px;">
                    <button type="submit" name="advanced_tracker_save_settings" class="save-button">💾 Save Settings</button>
                </div>
            </form>
        </div>
    </div>

    <script>
    function setPreset(preset) {
        const checkboxes = document.querySelectorAll('input[type="checkbox"]');

        if (preset === 'all') {
            checkboxes.forEach(cb => cb.checked = true);
        } else if (preset === 'none') {
            checkboxes.forEach(cb => cb.checked = false);
        } else if (preset === 'minimal') {
            // GDPR-safe minimal tracking
            checkboxes.forEach(cb => {
                const name = cb.name;
                cb.checked = ['track_ip', 'track_user_agent', 'track_referrer', 'track_language', 'track_timezone'].includes(name);
            });
        } else if (preset === 'moderate') {
            // Moderate tracking without advanced fingerprinting
            checkboxes.forEach(cb => {
                const name = cb.name;
                const disabledList = ['track_canvas', 'track_webgl', 'track_audio', 'track_fonts', 'track_battery'];
                cb.checked = !disabledList.includes(name);
            });
        }
    }
    </script>
    <?php
}
