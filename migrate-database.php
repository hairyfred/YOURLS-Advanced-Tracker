<?php
/**
 * Database Migration Script for Advanced Tracker
 *
 * Run this file ONCE to add new fingerprinting columns to existing installations
 *
 * Usage: Visit https://u.fr3.uk/admin/plugins.php?page=advanced_tracker&migrate=1
 */

// No direct access
if (!defined('YOURLS_ABSPATH')) die();

function advanced_tracker_migrate_database() {
    global $ydb;

    $table = YOURLS_DB_TABLE_URL . '_advanced_tracking';

    // Check if table exists
    $table_exists = $ydb->fetchValue("SHOW TABLES LIKE '$table'");

    if (!$table_exists) {
        return "Table does not exist. Please activate the plugin first.";
    }

    // List of new columns to add
    $new_columns = array(
        'viewport_size' => "ADD COLUMN `viewport_size` varchar(20) DEFAULT NULL",
        'color_depth' => "ADD COLUMN `color_depth` int(11) DEFAULT NULL",
        'platform' => "ADD COLUMN `platform` varchar(100) DEFAULT NULL",
        'cookies_enabled' => "ADD COLUMN `cookies_enabled` tinyint(1) DEFAULT NULL",
        'do_not_track' => "ADD COLUMN `do_not_track` tinyint(1) DEFAULT NULL",
        'touch_support' => "ADD COLUMN `touch_support` tinyint(1) DEFAULT NULL",
        'cpu_cores' => "ADD COLUMN `cpu_cores` int(11) DEFAULT NULL",
        'device_memory' => "ADD COLUMN `device_memory` float DEFAULT NULL",
        'connection_type' => "ADD COLUMN `connection_type` varchar(50) DEFAULT NULL",
        'webgl_vendor' => "ADD COLUMN `webgl_vendor` varchar(200) DEFAULT NULL",
        'webgl_renderer' => "ADD COLUMN `webgl_renderer` varchar(200) DEFAULT NULL",
        'canvas_fingerprint' => "ADD COLUMN `canvas_fingerprint` varchar(64) DEFAULT NULL",
        'fonts_detected' => "ADD COLUMN `fonts_detected` text DEFAULT NULL",
        'plugins_list' => "ADD COLUMN `plugins_list` text DEFAULT NULL",
        'battery_charging' => "ADD COLUMN `battery_charging` tinyint(1) DEFAULT NULL",
        'battery_level' => "ADD COLUMN `battery_level` int(11) DEFAULT NULL"
    );

    $added = array();
    $skipped = array();

    foreach ($new_columns as $col_name => $alter_sql) {
        // Check if column already exists
        $col_exists = $ydb->fetchValue("SHOW COLUMNS FROM `$table` LIKE '$col_name'");

        if (!$col_exists) {
            // Add the column
            $ydb->query("ALTER TABLE `$table` $alter_sql");
            $added[] = $col_name;
        } else {
            $skipped[] = $col_name;
        }
    }

    // Add index on canvas_fingerprint if it doesn't exist
    $index_exists = $ydb->fetchValue("SHOW INDEX FROM `$table` WHERE Key_name = 'canvas_fingerprint'");
    if (!$index_exists) {
        $ydb->query("ALTER TABLE `$table` ADD KEY `canvas_fingerprint` (`canvas_fingerprint`)");
        $added[] = 'INDEX: canvas_fingerprint';
    }

    $result = "<h3>Database Migration Complete!</h3>";

    if (!empty($added)) {
        $result .= "<p><strong>Added " . count($added) . " new columns/indexes:</strong></p>";
        $result .= "<ul>";
        foreach ($added as $col) {
            $result .= "<li>✓ " . htmlspecialchars($col) . "</li>";
        }
        $result .= "</ul>";
    }

    if (!empty($skipped)) {
        $result .= "<p><strong>Skipped " . count($skipped) . " existing columns:</strong></p>";
        $result .= "<ul>";
        foreach ($skipped as $col) {
            $result .= "<li>○ " . htmlspecialchars($col) . " (already exists)</li>";
        }
        $result .= "</ul>";
    }

    $result .= "<p><strong>Your database is now ready for advanced fingerprinting!</strong></p>";
    $result .= "<p><a href='?page=advanced_tracker' class='button button-primary'>Go to Dashboard</a></p>";

    return $result;
}

// Run migration if requested
if (isset($_GET['migrate']) && $_GET['migrate'] == '1') {
    echo '<div class="wrap" style="max-width: 800px; margin: 50px auto; padding: 20px; background: white; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">';
    echo advanced_tracker_migrate_database();
    echo '</div>';
    exit;
}
?>
