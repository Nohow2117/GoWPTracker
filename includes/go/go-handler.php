<?php
// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Handles the /go endpoint redirect and logging.
 *
 * This function validates the destination, logs the click, and redirects the user.
 */
function gowptracker_handle_go_redirect() {
    if ( ! get_query_var( 'gowptracker_go' ) ) {
        return;
    }

    // Block HEAD requests and known bots before any other logic.
    if ($_SERVER['REQUEST_METHOD'] === 'HEAD') {
        status_header(403);
        exit('Forbidden: HEAD requests disallowed.');
    }

    $ua_check = isset($_SERVER['HTTP_USER_AGENT']) ? strtolower($_SERVER['HTTP_USER_AGENT']) : '';
    $bot_signals = ['bot', 'crawl', 'spider', 'slurp', 'facebookexternalhit', 'mediapartners-google', 'adsbot', 'bingpreview'];
    foreach ($bot_signals as $signal) {
        if (strpos($ua_check, $signal) !== false) {
            status_header(403);
            exit('Forbidden: Bot traffic disallowed.');
        }
    }

    // --- Validation ---
    $dest = isset($_GET['dest']) ? esc_url_raw($_GET['dest']) : '';
    if (empty($dest)) {
        wp_die('Error: Missing destination parameter.');
    }

    $parsed = wp_parse_url($dest);
    $scheme = isset($parsed['scheme']) ? strtolower($parsed['scheme']) : '';
    if ($scheme !== 'http' && $scheme !== 'https') {
        wp_die('Destination protocol not allowed.');
    }

    $host = isset($parsed['host']) ? sanitize_text_field($parsed['host']) : '';

    // Block dangerous destinations: IP addresses, localhost, private networks.
    if (
        $host === 'localhost' ||
        (filter_var($host, FILTER_VALIDATE_IP) && (
            preg_match('/^127\./', $host) || // loopback IPv4
            preg_match('/^10\./', $host) ||   // private IPv4
            preg_match('/^192\.168\./', $host) || // private IPv4
            preg_match('/^172\.(1[6-9]|2[0-9]|3[0-1])\./', $host) // private IPv4
        ))
    ) {
        wp_die('Destination to IP/localhost/private network is not allowed.');
    }

    // Whitelist of allowed domains.
    $allowed_domains = [
        'milano-bags.com',
        // Add other allowed domains here
    ];
    if (!in_array($host, $allowed_domains, true)) {
        wp_die('Destination domain is not allowed.');
    }

    // --- PLP Parameter Injection from Referrer ---
    if (empty($_GET['plp']) && !empty($_SERVER['HTTP_REFERER'])) {
        $referrer_url = esc_url_raw($_SERVER['HTTP_REFERER']);
        $site_host = wp_parse_url(home_url(), PHP_URL_HOST);
        $referrer_host = wp_parse_url($referrer_url, PHP_URL_HOST);

        // Only proceed if the referrer is from the same site.
        if ($site_host && $referrer_host && $site_host === $referrer_host) {
            $post_id = url_to_postid($referrer_url);
            if ($post_id > 0) {
                $post = get_post($post_id);
                if ($post) {
                    // Inject the slug into the GET parameters to be logged and propagated.
                    $_GET['plp'] = $post->post_name;
                }
            }
        }
    }

    // --- Logging ---
    global $wpdb;
    $table = $wpdb->prefix . 'go_clicks';
    $ip = isset($_SERVER['REMOTE_ADDR']) ? inet_pton(sanitize_text_field($_SERVER['REMOTE_ADDR'])) : null;

    $wpdb->insert(
        $table,
        [
            'ts'           => current_time('mysql'),
            'ip'           => $ip,
            'ua'           => isset($_SERVER['HTTP_USER_AGENT']) ? sanitize_text_field($_SERVER['HTTP_USER_AGENT']) : '',
            'referrer'     => isset($_SERVER['HTTP_REFERER']) ? esc_url_raw($_SERVER['HTTP_REFERER']) : '',
            'dest'         => $dest,
            'dest_host'    => $host,
            'plp'          => isset($_GET['plp']) ? sanitize_text_field($_GET['plp']) : '',
            'utm_source'   => isset($_GET['utm_source']) ? sanitize_text_field($_GET['utm_source']) : '',
            'utm_medium'   => isset($_GET['utm_medium']) ? sanitize_text_field($_GET['utm_medium']) : '',
            'utm_campaign' => isset($_GET['utm_campaign']) ? sanitize_text_field($_GET['utm_campaign']) : '',
            'utm_content'  => isset($_GET['utm_content']) ? sanitize_text_field($_GET['utm_content']) : '',
            'utm_term'     => isset($_GET['utm_term']) ? sanitize_text_field($_GET['utm_term']) : '',
            'fbclid'       => isset($_GET['fbclid']) ? sanitize_text_field($_GET['fbclid']) : '',
            'gclid'        => isset($_GET['gclid']) ? sanitize_text_field($_GET['gclid']) : '',
        ],
        [
            '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s'
        ]
    );

    // --- Parameter Propagation ---
    $params_to_propagate = [
        'plp', 'utm_source', 'utm_medium', 'utm_campaign', 'utm_content', 'utm_term', 'fbclid', 'gclid'
    ];
    $query_to_add = [];
    foreach ($params_to_propagate as $k) {
        if (!empty($_GET[$k])) {
            $query_to_add[$k] = sanitize_text_field($_GET[$k]);
        }
    }

    if (!empty($query_to_add)) {
        $dest = add_query_arg($query_to_add, $dest);
    }

    // --- Redirect ---
    wp_redirect($dest, 302);
    exit;
}
