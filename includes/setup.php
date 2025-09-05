<?php
// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Plugin activation hook.
 *
 * Creates all necessary database tables for the Go and Split features.
 */
function gowptracker_activate_plugin() {
    global $wpdb;
    require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
    $charset_collate = $wpdb->get_charset_collate();

    // --- Go Clicks Table ---
    $table_name = $wpdb->prefix . 'go_clicks';
    $sql_go_clicks = "CREATE TABLE $table_name (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        ts DATETIME NOT NULL,
        ip VARBINARY(16) NOT NULL,
        ua TEXT,
        referrer TEXT,
        dest TEXT,
        dest_host VARCHAR(191),
        plp VARCHAR(191),
        utm_source VARCHAR(191),
        utm_medium VARCHAR(191),
        utm_campaign VARCHAR(191),
        utm_content VARCHAR(191),
        utm_term VARCHAR(191),
        fbclid VARCHAR(191),
        gclid VARCHAR(191),
        PRIMARY KEY (id),
        KEY idx_ts (ts),
        KEY idx_plp (plp),
        KEY idx_dest_host (dest_host)
    ) $charset_collate;";
    dbDelta($sql_go_clicks);

    // --- Split Tests Table ---
    $split_tests_table = $wpdb->prefix . 'go_split_tests';
    $sql_split_tests = "CREATE TABLE $split_tests_table (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        slug VARCHAR(191) NOT NULL,
        name VARCHAR(191) NOT NULL,
        status TINYINT(1) NOT NULL DEFAULT 1,
        created_at DATETIME NOT NULL,
        updated_at DATETIME NOT NULL,
        PRIMARY KEY (id),
        UNIQUE KEY uniq_slug (slug)
    ) $charset_collate;";
    dbDelta($sql_split_tests);

    // --- Split Variants Table ---
    $split_variants_table = $wpdb->prefix . 'go_split_variants';
    $sql_split_variants = "CREATE TABLE $split_variants_table (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        test_id BIGINT UNSIGNED NOT NULL,
        post_id BIGINT UNSIGNED NOT NULL,
        weight INT UNSIGNED NOT NULL DEFAULT 1,
        created_at DATETIME NOT NULL,
        updated_at DATETIME NOT NULL,
        PRIMARY KEY (id),
        KEY idx_test (test_id),
        KEY idx_post (post_id)
    ) $charset_collate;";
    dbDelta($sql_split_variants);

    // --- Split Hits Table ---
    $split_hits_table = $wpdb->prefix . 'go_split_hits';
    $sql_split_hits = "CREATE TABLE $split_hits_table (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        ts DATETIME NOT NULL,
        test_slug VARCHAR(191) NOT NULL,
        variant_id BIGINT UNSIGNED NOT NULL,
        client_id VARCHAR(191) NULL,
        ip VARBINARY(16) NOT NULL,
        ua TEXT NULL,
        referrer TEXT NULL,
        geo_country VARCHAR(100) NULL,
        geo_city VARCHAR(100) NULL,
        device_type VARCHAR(50) NULL,
        is_bot TINYINT(1) NOT NULL DEFAULT 0,
        PRIMARY KEY (id),
        KEY idx_ts (ts),
        KEY idx_test (test_slug),
        KEY idx_variant (variant_id)
    ) $charset_collate;";
    dbDelta($sql_split_hits);

    // Store plugin version and flush rewrite rules
    update_option('gowptracker_version', GOWPTRACKER_VERSION);
    flush_rewrite_rules();
}

/**
 * Check if the plugin version has changed and run the activation logic if needed.
 *
 * This handles database schema updates between versions.
 */
function gowptracker_maybe_upgrade() {
    $current_version = get_option('gowptracker_version', '0.1.0');
    if (version_compare($current_version, GOWPTRACKER_VERSION, '<')) {
        gowptracker_setup_db();
    }
}
