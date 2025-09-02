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
        add_action( 'init', [ $this, 'register_go_endpoint' ] );
        add_action( 'admin_menu', [ $this, 'add_admin_page' ] );
    }

    public function add_admin_page() {
        add_menu_page(
            'GO Tracker',
            'GO Tracker',
            'manage_options',
            'gowptracker-admin',
            [ $this, 'render_admin_page' ],
            'dashicons-chart-bar',
            80
        );
    }

    public function render_admin_page() {
        global $wpdb;
        $table = $wpdb->prefix . 'go_clicks';
        $since = date('Y-m-d H:i:s', strtotime('-7 days'));
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT referrer, utm_campaign FROM $table WHERE ts >= %s",
                $since
            ),
            ARRAY_A
        );
        // Raggruppa per percorso referrer e campagna
        $agg = [];
        foreach ($rows as $row) {
            $ref = $row['referrer'];
            $parsed = wp_parse_url($ref);
            $plp_path = isset($parsed['path']) ? $parsed['path'] : '(nessun referrer)';
            $camp = $row['utm_campaign'];
            $key = $plp_path . '|' . $camp;
            if (!isset($agg[$key])) {
                $agg[$key] = ['plp' => $plp_path, 'utm_campaign' => $camp, 'clicks' => 0];
            }
            $agg[$key]['clicks']++;
        }
        echo '<div class="wrap"><h1>GO Tracker – Clicks ultimi 7 giorni</h1>';
        echo '<table class="widefat"><thead><tr><th>PLP (da referrer)</th><th>Campagna</th><th>Clicks</th></tr></thead><tbody>';
        if ($agg) {
            foreach ($agg as $row) {
                echo '<tr><td>' . esc_html($row['plp']) . '</td><td>' . esc_html($row['utm_campaign']) . '</td><td>' . intval($row['clicks']) . '</td></tr>';
            }
        } else {
            echo '<tr><td colspan="3">Nessun dato</td></tr>';
        }
        echo '</tbody></table></div>';
    }

    public static function activate_plugin() {
        error_log('GoWPTracker: activate_plugin START');
        global $wpdb;
        $table_name = $wpdb->prefix . 'go_clicks';
        $charset_collate = $wpdb->get_charset_collate();
        error_log('GoWPTracker: before require_once');
        require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
        error_log('GoWPTracker: after require_once');
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
        error_log('GoWPTracker: before dbDelta');
        try {
            dbDelta($sql);
            error_log('GoWPTracker: after dbDelta');
        } catch (Exception $e) {
            error_log('GoWPTracker: EXCEPTION - ' . $e->getMessage());
        }
        error_log('GoWPTracker: activate_plugin END');
    }

    public function register_go_endpoint() {
        add_rewrite_rule( '^go/?$', 'index.php?gowptracker_go=1', 'top' );
        add_rewrite_tag( '%gowptracker_go%', '1' );
        add_action( 'template_redirect', [ $this, 'handle_go_redirect' ], 9 );
    }

    public function handle_go_redirect() {
        // DEBUG: logga tutto ciò che arriva
        error_log('REQUEST_URI: ' . $_SERVER['REQUEST_URI']);
        error_log('QUERY_STRING: ' . $_SERVER['QUERY_STRING']);
        error_log('GET: ' . print_r($_GET, true));
        if ( get_query_var( 'gowptracker_go' ) ) {
            // Blocca richieste HEAD e bot PRIMA di ogni logica
            if ($_SERVER['REQUEST_METHOD'] === 'HEAD') {
                error_log('GoWPTracker: BLOCCO HEAD');
                status_header(403);
                exit('Forbidden');
            }
            $ua_check = isset($_SERVER['HTTP_USER_AGENT']) ? strtolower($_SERVER['HTTP_USER_AGENT']) : '';
            $bot_signals = ['bot','crawl','spider','slurp','facebookexternalhit','mediapartners-google','adsbot','bingpreview'];
            foreach ($bot_signals as $signal) {
                if (strpos($ua_check, $signal) !== false) {
                    error_log('GoWPTracker: BLOCCO BOT - UA: ' . $ua_check);
                    status_header(403);
                    exit('Forbidden');
                }
            }
            $allowed_domains = [
                'milano-bags.com',
                // aggiungi altri domini consentiti qui
            ];
            $dest = isset($_GET['dest']) ? esc_url_raw($_GET['dest']) : '';
            if (empty($dest)) {
                wp_die('Errore: parametro dest mancante.');
            }
            $parsed = wp_parse_url($dest);
            $scheme = isset($parsed['scheme']) ? strtolower($parsed['scheme']) : '';
            if ($scheme !== 'http' && $scheme !== 'https') {
                wp_die('Protocollo di destinazione non consentito.');
            }
            $host = isset($parsed['host']) ? sanitize_text_field($parsed['host']) : '';
            // Blocca destinazioni pericolose: IP, localhost, reti locali
            if (
                $host === 'localhost' ||
                filter_var($host, FILTER_VALIDATE_IP) && (
                    preg_match('/^127\./', $host) || // loopback IPv4
                    preg_match('/^10\./', $host) ||   // privato IPv4
                    preg_match('/^192\.168\./', $host) || // privato IPv4
                    preg_match('/^172\.(1[6-9]|2[0-9]|3[0-1])\./', $host) // privato IPv4
                )
            ) {
                wp_die('Destinazione IP/localhost/rete privata non consentita.');
            }
            if (!in_array($host, $allowed_domains, true)) {
                wp_die('Dominio di destinazione non consentito.');
            }
            // Blocca richieste HEAD e bot
            if ($_SERVER['REQUEST_METHOD'] === 'HEAD') {
                status_header(403);
                exit('Forbidden');
            }
            $ua_check = isset($_SERVER['HTTP_USER_AGENT']) ? strtolower($_SERVER['HTTP_USER_AGENT']) : '';
            $bot_signals = ['bot','crawl','spider','slurp','facebookexternalhit','mediapartners-google','adsbot','bingpreview'];
            foreach ($bot_signals as $signal) {
                if (strpos($ua_check, $signal) !== false) {
                    status_header(403);
                    exit('Forbidden');
                }
            }
            // Logging del click
            global $wpdb;
            $table = $wpdb->prefix . 'go_clicks';
            $ts = current_time('mysql');
            $ip = isset($_SERVER['REMOTE_ADDR']) ? inet_pton(sanitize_text_field($_SERVER['REMOTE_ADDR'])) : null;
            $ua = isset($_SERVER['HTTP_USER_AGENT']) ? sanitize_text_field($_SERVER['HTTP_USER_AGENT']) : '';
            $referrer = isset($_SERVER['HTTP_REFERER']) ? esc_url_raw($_SERVER['HTTP_REFERER']) : '';
            $dest_host = $host;
            $plp = isset($_GET['plp']) ? sanitize_text_field($_GET['plp']) : '';
            $utm_source = isset($_GET['utm_source']) ? sanitize_text_field($_GET['utm_source']) : '';
            $utm_medium = isset($_GET['utm_medium']) ? sanitize_text_field($_GET['utm_medium']) : '';
            $utm_campaign = isset($_GET['utm_campaign']) ? sanitize_text_field($_GET['utm_campaign']) : '';
            $utm_content = isset($_GET['utm_content']) ? sanitize_text_field($_GET['utm_content']) : '';
            $utm_term = isset($_GET['utm_term']) ? sanitize_text_field($_GET['utm_term']) : '';
            $fbclid = isset($_GET['fbclid']) ? sanitize_text_field($_GET['fbclid']) : '';
            $gclid = isset($_GET['gclid']) ? sanitize_text_field($_GET['gclid']) : '';
            $wpdb->insert(
                $table,
                [
                    'ts' => $ts,
                    'ip' => $ip,
                    'ua' => $ua,
                    'referrer' => $referrer,
                    'dest' => $dest,
                    'dest_host' => $dest_host,
                    'plp' => $plp,
                    'utm_source' => $utm_source,
                    'utm_medium' => $utm_medium,
                    'utm_campaign' => $utm_campaign,
                    'utm_content' => $utm_content,
                    'utm_term' => $utm_term,
                    'fbclid' => $fbclid,
                    'gclid' => $gclid,
                ],
                [
                    '%s','%s','%s','%s','%s','%s','%s','%s','%s','%s','%s','%s','%s','%s','%s'
                ]
            );
            // Propagazione automatica UTM e PLP
            $params_to_propagate = [
                'plp',
                'utm_source',
                'utm_medium',
                'utm_campaign',
                'utm_content',
                'utm_term',
                'fbclid',
                'gclid'
            ];
            $query = [];
            foreach ($params_to_propagate as $k) {
                if (!empty($_GET[$k])) {
                    $query[$k] = sanitize_text_field($_GET[$k]);
                }
            }
            if (!empty($query)) {
                $parsed_dest = wp_parse_url($dest);
                $dest_query = [];
                if (isset($parsed_dest['query'])) {
                    parse_str($parsed_dest['query'], $dest_query);
                }
                $merged_query = array_merge($dest_query, $query);
                $query_str = http_build_query($merged_query);
                $base = $parsed_dest['scheme'] . '://' . $parsed_dest['host'];
                if (isset($parsed_dest['port'])) {
                    $base .= ':' . $parsed_dest['port'];
                }
                $base .= isset($parsed_dest['path']) ? $parsed_dest['path'] : '';
                $dest = $base . '?' . $query_str;
            }
            // Redirect 302 verso la destinazione
            wp_redirect($dest, 302);
            exit;
        }
    }
}

new GoWPTracker();

register_activation_hook(__FILE__, ['GoWPTracker', 'activate_plugin']);
