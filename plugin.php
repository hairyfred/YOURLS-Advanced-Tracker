<?php
/*
Plugin Name: YOURLS Advanced Tracker
Plugin URI: https://github.com/hairyfred/YOURLS-Advanced-Tracker
Description: Advanced visitor tracking and analytics for YOURLS with device fingerprinting, geolocation, and comprehensive visitor intelligence
Version: 1.0.0
Author: hairyfred
Author URI: https://github.com/hairyfred/YOURLS-Advanced-Tracker
*/

// No direct call
if( !defined( 'YOURLS_ABSPATH' ) ) die();

// Hook into YOURLS redirect to capture data before redirecting
yourls_add_action('redirect_shorturl', 'advanced_tracker_log_click');

// Add admin page
yourls_add_action('plugins_loaded', 'advanced_tracker_add_page');

// Database table name
define('ADVANCED_TRACKER_TABLE', YOURLS_DB_TABLE_URL . '_advanced_tracking');

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
        `timezone` varchar(50) DEFAULT NULL,
        PRIMARY KEY (`id`),
        KEY `keyword` (`keyword`),
        KEY `timestamp` (`timestamp`),
        KEY `ip_address` (`ip_address`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

    $ydb->query($sql);

    yourls_redirect(yourls_admin_url('plugins.php'), 301);
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

    // Get IP address
    $ip = advanced_tracker_get_ip();

    // Get user agent
    $user_agent = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '';

    // Parse user agent
    $device_info = advanced_tracker_parse_user_agent($user_agent);

    // Get geolocation data (using ip-api.com - free, no API key needed)
    $geo_data = advanced_tracker_get_geolocation($ip);

    // Get referrer
    $referrer = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '';

    // Get language
    $language = isset($_SERVER['HTTP_ACCEPT_LANGUAGE']) ? substr($_SERVER['HTTP_ACCEPT_LANGUAGE'], 0, 50) : '';

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
    yourls_register_plugin_page('advanced_tracker', 'Advanced Tracker', 'advanced_tracker_display_page');
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

    // Handle export requests
    if (isset($_GET['export'])) {
        advanced_tracker_export_data($_GET['export']);
        exit;
    }

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
    $stats['top_countries'] = $ydb->fetchObjects("
        SELECT country, COUNT(*) as count
        FROM `$table` $where_clause AND country IS NOT NULL
        GROUP BY country
        ORDER BY count DESC
        LIMIT 10
    ", $binds);

    // Top browsers
    $stats['top_browsers'] = $ydb->fetchObjects("
        SELECT browser, COUNT(*) as count
        FROM `$table` $where_clause AND browser IS NOT NULL
        GROUP BY browser
        ORDER BY count DESC
        LIMIT 10
    ", $binds);

    // Top OS
    $stats['top_os'] = $ydb->fetchObjects("
        SELECT os, COUNT(*) as count
        FROM `$table` $where_clause AND os IS NOT NULL
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
    $stats['top_referrers'] = $ydb->fetchObjects("
        SELECT referrer, COUNT(*) as count
        FROM `$table` $where_clause AND referrer != ''
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
function advanced_tracker_export_data($format) {
    global $ydb;
    $table = ADVANCED_TRACKER_TABLE;

    // Get all data
    $sql = "SELECT * FROM `$table` ORDER BY timestamp DESC";
    $data = $ydb->fetchObjects($sql);

    if ($format === 'csv') {
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="advanced-tracker-export-' . date('Y-m-d') . '.csv"');

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
        header('Content-Type: application/json');
        header('Content-Disposition: attachment; filename="advanced-tracker-export-' . date('Y-m-d') . '.json"');

        echo json_encode($data, JSON_PRETTY_PRINT);
    }
}
