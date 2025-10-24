<?php
/**
 * Uninstall WEBRONIC 360 Virtual Tour
 * 
 * @package WEBRONIC-Virtual-Tour
 */

// Prevent direct access
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Prevent unauthorized access
if (!current_user_can('activate_plugins')) {
    exit;
}

// Verify this is our plugin being uninstalled
if (__FILE__ != WP_UNINSTALL_PLUGIN) {
    exit;
}

global $wpdb;

// Plugin options to delete
$options = array(
    'webronic_virtual_tour_version',
    'webronic_virtual_tour_db_version'
);

// Delete options
foreach ($options as $option) {
    delete_option($option);
}

// For multisite, delete site options too
if (is_multisite()) {
    foreach ($options as $option) {
        delete_site_option($option);
    }
}

// Check if user wants to delete all data (you can make this configurable)
$delete_all_data = get_option('webronic_virtual_tour_delete_data_on_uninstall', false);

if ($delete_all_data) {
    // Database tables to drop
    $tables = array(
        $wpdb->prefix . 'webronic_virtual_tours',
        $wpdb->prefix . 'webronic_tour_scenes',
        $wpdb->prefix . 'webronic_hotspots'
    );

    // Drop custom tables
    foreach ($tables as $table) {
        $wpdb->query("DROP TABLE IF EXISTS $table");
    }

    // Delete any post meta if you stored data there
    $wpdb->query(
        "DELETE FROM $wpdb->postmeta WHERE meta_key LIKE '%webronic_virtual_tour%'"
    );

    // Delete any options related to the plugin
    $wpdb->query(
        "DELETE FROM $wpdb->options WHERE option_name LIKE '%webronic_virtual_tour%'"
    );
}

// Clear any cached data that might be related
wp_cache_flush();