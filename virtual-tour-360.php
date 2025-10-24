<?php
/**
 * Plugin Name: WEBRONIC 360 Virtual Tour
 * Description: Create and manage 360-degree virtual tours independently
 * Version: 1.0
 * Author: WEBRONIC
 * Text Domain: webronic-virtual-tour
 */

defined('ABSPATH') or die('No script kiddies please!');

define('WEBRONIC_VIRTUAL_TOUR_VERSION', '1.0');
define('WEBRONIC_VIRTUAL_TOUR_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('WEBRONIC_VIRTUAL_TOUR_PLUGIN_URL', plugin_dir_url(__FILE__));

// Include required files
require_once WEBRONIC_VIRTUAL_TOUR_PLUGIN_DIR . 'includes/database.php';
require_once WEBRONIC_VIRTUAL_TOUR_PLUGIN_DIR . 'includes/admin.php';
require_once WEBRONIC_VIRTUAL_TOUR_PLUGIN_DIR . 'includes/shortcodes.php';
require_once WEBRONIC_VIRTUAL_TOUR_PLUGIN_DIR . 'includes/editor.php';
require_once WEBRONIC_VIRTUAL_TOUR_PLUGIN_DIR . 'includes/ajax-handlers.php';

// Activation/Deactivation hooks
register_activation_hook(__FILE__, 'webronic_virtual_tour_activate');
register_deactivation_hook(__FILE__, 'webronic_virtual_tour_deactivate');

function webronic_virtual_tour_activate() {
    webronic_virtual_tour_create_tables();
    if (!get_option('webronic_virtual_tour_version')) {
        update_option('webronic_virtual_tour_version', WEBRONIC_VIRTUAL_TOUR_VERSION);
    }
    flush_rewrite_rules();
}

function webronic_virtual_tour_deactivate() {
    // Don't delete tables on deactivation to preserve data
    flush_rewrite_rules();
}

// Add admin menu
add_action('admin_menu', 'webronic_virtual_tour_admin_menu');

// Initialize the plugin
function webronic_virtual_tour_init() {
    load_plugin_textdomain(
        'webronic-virtual-tour',
        false,
        dirname(plugin_basename(__FILE__)) . '/languages/'
    );
}
add_action('plugins_loaded', 'webronic_virtual_tour_init');

function enqueue_virtual_tour_admin_scripts($hook) {
    if (strpos($hook, 'webronic-virtual-tour') === false) {
        return;
    }
    
    wp_enqueue_media();
    
    wp_enqueue_script(
        'webronic-virtual-tour-admin',
        WEBRONIC_VIRTUAL_TOUR_PLUGIN_URL . 'assets/js/admin.js',
        array('jquery'),
        WEBRONIC_VIRTUAL_TOUR_VERSION,
        true
    );
    
    wp_enqueue_script(
        'webronic-virtual-tour-editor',
        WEBRONIC_VIRTUAL_TOUR_PLUGIN_URL . 'assets/js/editor.js',
        array('jquery'),
        WEBRONIC_VIRTUAL_TOUR_VERSION,
        true
    );
    
    wp_localize_script('webronic-virtual-tour-admin', 'webronicVirtualTour', array(
        'ajaxurl' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('webronic_virtual_tour_nonce'),
        'pluginUrl' => WEBRONIC_VIRTUAL_TOUR_PLUGIN_URL
    ));

    wp_enqueue_style(
        'webronic-virtual-tour-admin-css',
        WEBRONIC_VIRTUAL_TOUR_PLUGIN_URL . 'assets/css/admin.css',
        array(),
        WEBRONIC_VIRTUAL_TOUR_VERSION
    );
    
    wp_enqueue_style(
        'webronic-virtual-tour-editor-css',
        WEBRONIC_VIRTUAL_TOUR_PLUGIN_URL . 'assets/css/editor.css',
        array(),
        WEBRONIC_VIRTUAL_TOUR_VERSION
    );
}
add_action('admin_enqueue_scripts', 'enqueue_virtual_tour_admin_scripts');

function enqueue_virtual_tour_frontend_scripts() {

    global $post;
    if (is_a($post, 'WP_Post') && has_shortcode($post->post_content, 'webronic_virtual_tour')) {
        
        wp_enqueue_script(
            'webronic-virtual-tour-viewer',
            WEBRONIC_VIRTUAL_TOUR_PLUGIN_URL . 'assets/js/viewer.js',
            array('jquery'),
            WEBRONIC_VIRTUAL_TOUR_VERSION,
            true
        );
        
        wp_enqueue_style(
            'webronic-virtual-tour-viewer-css',
            WEBRONIC_VIRTUAL_TOUR_PLUGIN_URL . 'assets/css/viewer.css',
            array(),
            WEBRONIC_VIRTUAL_TOUR_VERSION
        );
        
        wp_localize_script('webronic-virtual-tour-viewer', 'webronicVirtualTourFront', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('webronic_virtual_tour_nonce')
        ));
    }
}
add_action('wp_enqueue_scripts', 'enqueue_virtual_tour_frontend_scripts');

add_shortcode('webronic_virtual_tour', 'webronic_virtual_tour_shortcode');

// --- Self-hosted updates (Plugin Update Checker) ---
require_once WEBRONIC_VIRTUAL_TOUR_PLUGIN_DIR . 'includes/puc/plugin-update-checker.php';

use YahnisElsts\PluginUpdateChecker\v5\PucFactory;

$webronicUpdateChecker = PucFactory::buildUpdateChecker(
    // URL to your update metadata JSON
    'https://updates.webronic.com/virtual-tour/metadata.json',
    __FILE__,
    'webronic-virtual-tour' // unique slug, same as your plugin folder name
);
