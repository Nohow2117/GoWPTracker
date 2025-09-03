<?php
// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Handles the /split/{slug} endpoint.
 *
 * It performs weighted rotation to a variant (WP Page), logs the hit, and propagates query parameters.
 */
function gowptracker_handle_split_redirect() {
    $slug = get_query_var('gowptracker_split');
    if (empty($slug)) {
        return;
    }

    // IMPORTANT: Do NOT block bots on /split, as ad/social crawlers need to access PLPs.
    global $wpdb;
    $slug = sanitize_title($slug);
    $tests_table = $wpdb->prefix . 'go_split_tests';
    $variants_table = $wpdb->prefix . 'go_split_variants';

    $test = $wpdb->get_row(
        $wpdb->prepare("SELECT id, slug FROM $tests_table WHERE slug = %s AND status = 1", $slug),
        ARRAY_A
    );
    if (!$test) {
        status_header(404);
        exit('Split test not found or not active.');
    }

    $variants = $wpdb->get_results(
        $wpdb->prepare("SELECT id, post_id, weight FROM $variants_table WHERE test_id = %d", intval($test['id'])),
        ARRAY_A
    );
    if (!$variants) {
        status_header(404);
        exit('No variants found for this test.');
    }

    // Filter for only published posts
    $valid_variants = array_filter($variants, function($v) {
        return get_post_status(intval($v['post_id'])) === 'publish';
    });

    if (empty($valid_variants)) {
        status_header(404);
        exit('No published variants available.');
    }

    // Perform weighted rotation to select a variant.
    $total_weight = 0;
    foreach ($valid_variants as $v) {
        $total_weight += max(1, intval($v['weight']));
    }

    $r = mt_rand(1, $total_weight);
    $acc = 0;
    $choice = reset($valid_variants); // Default to the first variant.
    foreach ($valid_variants as $v) {
        $acc += max(1, intval($v['weight']));
        if ($r <= $acc) {
            $choice = $v;
            break;
        }
    }

    $destination_post_id = intval($choice['post_id']);
    $dest_url = get_permalink($destination_post_id);

    if (empty($dest_url)) {
        status_header(404);
        exit('Could not resolve destination permalink.');
    }

    // Propagate all incoming query parameters
    $incoming_params = $_GET;
    unset($incoming_params['gowptracker_split']);
    if (!empty($incoming_params)) {
        $dest_url = add_query_arg(array_map('sanitize_text_field', $incoming_params), $dest_url);
    }

    // --- Logging ---
    $split_hits_table = $wpdb->prefix . 'go_split_hits';

    // Get or set anonymous client ID
    $cid_cookie_name = 'GoWPTrackerCID';
    if (empty($_COOKIE[$cid_cookie_name])) {
        $client_id = wp_hash(uniqid('gowp_cid_', true));
        setcookie($cid_cookie_name, $client_id, time() + YEAR_IN_SECONDS, '/', '', is_ssl(), true);
    } else {
        $client_id = sanitize_text_field($_COOKIE[$cid_cookie_name]);
    }

    $wpdb->insert(
        $split_hits_table,
        [
            'ts'         => current_time('mysql'),
            'test_slug'  => $slug,
            'variant_id' => intval($choice['id']),
            'client_id'  => $client_id,
            'ip'         => isset($_SERVER['REMOTE_ADDR']) ? inet_pton(sanitize_text_field($_SERVER['REMOTE_ADDR'])) : null,
            'ua'         => isset($_SERVER['HTTP_USER_AGENT']) ? sanitize_text_field($_SERVER['HTTP_USER_AGENT']) : '',
            'referrer'   => isset($_SERVER['HTTP_REFERER']) ? esc_url_raw($_SERVER['HTTP_REFERER']) : '',
        ],
        ['%s', '%s', '%d', '%s', '%s', '%s', '%s']
    );

    // Add cache-busting headers to prevent browser caching of the 302 redirect.
    header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
    header("Pragma: no-cache");
    header("Expires: 0");

    wp_redirect($dest_url, 302);
    exit;
}
