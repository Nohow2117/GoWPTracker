<?php
/*
Plugin Name: GoWPTracker
Description: Server-side tracking of outbound clicks from pre-landing pages (PLP) to e-commerce, with logging and redirect.
Version: 0.1.0
Author: Nohow2117
*/

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Main plugin class
class GoWPTracker {
    public function __construct() {
        register_activation_hook(__FILE__, [ $this, 'activate_plugin' ] );
        add_action( 'init', [ $this, 'register_go_endpoint' ] );
    }

    public function activate_plugin() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'go_clicks';
        $charset_collate = $wpdb->get_charset_collate();
        require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
        $sql = "CREATE TABLE $table_name (
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
        dbDelta($sql);
    }
    public function __construct() {
        add_action( 'init', [ $this, 'register_go_endpoint' ] );
    }

    public function register_go_endpoint() {
        add_rewrite_rule( '^go$', 'index.php?gowptracker_go=1', 'top' );
        add_rewrite_tag( '%gowptracker_go%', '1' );
        add_action( 'template_redirect', [ $this, 'handle_go_redirect' ] );
    }

    public function handle_go_redirect() {
        if ( get_query_var( 'gowptracker_go' ) ) {
            $allowed_domains = [
                'milano-bags.com',
                // aggiungi altri domini consentiti qui
            ];
            $dest = isset($_GET['dest']) ? esc_url_raw($_GET['dest']) : '';
            if (empty($dest)) {
                wp_die('Errore: parametro dest mancante.');
            }
            $parsed = wp_parse_url($dest);
            $host = isset($parsed['host']) ? $parsed['host'] : '';
            if (!in_array($host, $allowed_domains, true)) {
                wp_die('Dominio di destinazione non consentito.');
            }
            // Redirect 302 verso la destinazione
            wp_redirect($dest, 302);
            exit;
        }
    }
}

new GoWPTracker();
