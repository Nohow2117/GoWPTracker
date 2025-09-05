<?php
/*
Plugin Name: GoWPTracker
Description: Server-side tracking of outbound clicks (GO) and A/B split testing (SPLIT) for WordPress.
Version: 0.8.0
Author: Nohow2117
Author URI: https://nohow2117.com/
License: GPLv2 or later
Text Domain: gowptracker
*/

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// --- Constants ---
define( 'GOWPTRACKER_VERSION', '0.8.0' );
define( 'GOWPTRACKER_PATH', plugin_dir_path( __FILE__ ) );

// --- Includes ---
require_once GOWPTRACKER_PATH . 'includes/functions.php';
require_once GOWPTRACKER_PATH . 'includes/setup.php';
require_once GOWPTRACKER_PATH . 'includes/go/go-handler.php';
require_once GOWPTRACKER_PATH . 'includes/go/go-admin.php';
require_once GOWPTRACKER_PATH . 'includes/split/split-handler.php';
require_once GOWPTRACKER_PATH . 'includes/split/split-admin.php';


/**
 * Main GoWPTracker Class.
 *
 * This class acts as the main orchestrator, setting up hooks that point
 * to the separated functional units of the plugin.
 */
class GoWPTracker {

    public function __construct() {
        // --- Endpoint Registration ---
        add_action( 'init', [ $this, 'register_endpoints' ] );

        // --- Endpoint Handlers ---
        add_action( 'template_redirect', 'gowptracker_handle_go_redirect', 9 );
        add_action( 'template_redirect', 'gowptracker_handle_split_redirect', 9 );

        // --- Admin Menu ---
        add_action( 'admin_menu', [ $this, 'add_admin_pages' ] );

        // --- Admin Page Form Handlers ---
        // Hook the action handlers to the 'load' hook of their respective pages.
        add_action( 'load-admin_page_gowptracker-split-tests', 'gowptracker_split_handle_actions' );
    }

    /**
     * Registers the rewrite rules for /go and /split endpoints.
     */
    public function register_endpoints() {
        // /go endpoint
        add_rewrite_rule( '^go/?$', 'index.php?gowptracker_go=1', 'top' );
        add_rewrite_tag( '%gowptracker_go%', '1' );

        // /split/{slug} endpoint
        add_rewrite_rule( '^split/([^/]+)/?$', 'index.php?gowptracker_split=$matches[1]', 'top' );
        add_rewrite_tag( '%gowptracker_split%', '([^&]+)' );
    }

    /**
     * Adds the admin menu and submenu pages.
     */
    public function add_admin_pages() {
        // Main Menu Page (Go Report)
        add_menu_page(
            'GO Tracker',
            'GO Tracker',
            'manage_options',
            'gowptracker-admin',
            'gowptracker_render_go_admin_page', // Callback to function in go-admin.php
            'dashicons-chart-bar',
            80
        );

        // Split Tests Submenu Page
        add_submenu_page(
            'gowptracker-admin',
            'Split Tests',
            'Split Tests',
            'manage_options',
            'gowptracker-split-tests',
            'gowptracker_render_split_admin_page' // Callback to function in split-admin.php
        );
    }
}

// --- Initialization ---
new GoWPTracker();


// --- Activation & Upgrade Hooks ---
register_activation_hook( __FILE__, 'gowptracker_activate_plugin' );
/**
 * One-time function to backfill the is_bot flag for existing records.
 * This ensures that historical data is also filtered correctly.
 */
function gowptracker_backfill_bot_data() {
    // Run this update only once.
    if (get_option('gowptracker_bot_backfill_done')) {
        return;
    }

    global $wpdb;
    $hits_table = $wpdb->prefix . 'go_split_hits';

    // Get all hits that haven't been checked yet (is_bot = 0)
    $hits_to_check = $wpdb->get_results("SELECT id, ua, ip FROM {$hits_table} WHERE is_bot = 0");

    if (empty($hits_to_check)) {
        update_option('gowptracker_bot_backfill_done', true);
        return;
    }

    foreach ($hits_to_check as $hit) {
        if (gowptracker_is_bot($hit->ua, inet_ntop($hit->ip))) {
            $wpdb->update($hits_table, ['is_bot' => 1], ['id' => $hit->id]);
        }
    }

    // Mark the backfill as complete to prevent it from running again.
    update_option('gowptracker_bot_backfill_done', true);
}
add_action('admin_init', 'gowptracker_backfill_bot_data');
