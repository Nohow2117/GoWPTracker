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
/**
 * Checks if a user agent string belongs to a known bot/crawler.
 * This function is intentionally kept simple and can be expanded.
 *
 * @param string $user_agent The user agent string to check.
 * @return bool True if it's a bot, false otherwise.
 */
function gowptracker_is_bot($user_agent) {
    if (empty($user_agent)) {
        return false;
    }
    // A non-exhaustive list of common bot/crawler user agent substrings.
    $bot_signatures = [
        // Search Engines
        'Googlebot', 'Bingbot', 'Slurp', 'DuckDuckBot', 'Baiduspider', 'YandexBot',
        // Social Media & Ads
        'facebookexternalhit', 'LinkedInBot', 'Pinterest', 'Twitterbot', 'Google-Ads-Bot',
        // Monitoring & SEO Tools
        'UptimeRobot', 'Site24x7', 'Pingdom', 'AhrefsBot', 'SemrushBot', 'DotBot',
        'MJ12bot', 'MegaIndex', 'SEOkicks', 'MojeekBot', 'linkdexbot',
        // Generic & Others
        'bot', 'crawl', 'spider', 'slurp', 'scan', 'python-requests', 'curl', 'wget'
    ];
    $pattern = '/' . implode('|', $bot_signatures) . '/i';
    return preg_match($pattern, $user_agent) > 0;
}

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

    // --- GeoIP and Device Detection ---
    $ip_address = isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field($_SERVER['REMOTE_ADDR']) : null;
    $user_agent = isset($_SERVER['HTTP_USER_AGENT']) ? sanitize_text_field($_SERVER['HTTP_USER_AGENT']) : '';

    $geo_country = null;
    $geo_city = null;
    $device_type = 'desktop';

    // Basic device detection
    if (preg_match('/(android|bb\d+|meego).+mobile|avantgo|bada\/|blackberry|blazer|compal|elaine|fennec|hiptop|iemobile|ip(hone|od)|iris|kindle|lge |maemo|midp|mmp|mobile.+firefox|netfront|opera m(ob|in)i|palm( os)?|phone|p(ixi|rim)|plucker|pocket|psp|series(4|6)0|symbian|treo|up\.(browser|link)|vodafone|wap|windows ce|xda|xiino/i', $user_agent) || preg_match('/1207|6310|6590|3gso|4thp|50[1-6]i|770s|802s|a wa|abac|ac(er|oo|s\-)|ai(ko|rn)|al(av|ca|co)|amoi|an(ex|ny|yw)|aptu|ar(ch|go)|as(te|us)|attw|au(di|\-m|r |s )|avan|be(ck|ll|nq)|bi(lb|rd)|bl(ac|az)|br(e|v)w|bumb|bw\-(n|u)|c55\/|capi|ccwa|cdm\-|cell|chtm|cldc|cmd\-|co(mp|nd)|craw|da(it|ll|ng)|dbte|dc\-s|devi|dica|dmob|do(c|p)o|ds(12|\-d)|el(49|ai)|em(l2|ul)|er(ic|k0)|esl8|ez([4-7]0|os|wa|ze)|fetc|fly(\-|_)|g1 u|g560|gene|gf\-5|g\-mo|go(\.w|od)|gr(ad|un)|haie|hcit|hd\-(m|p|t)|hei\-|hi(pt|ta)|hp( i|ip)|hs\-c|ht(c(\-| |_|a|g|p|s|t)|tp)|hu(aw|tc)|i\-(20|go|ma)|i230|iac( |\-|\/)|ibro|idea|ig01|ikom|im1k|inno|ipaq|iris|ja(t|v)a|jbro|jemu|jigs|kddi|keji|kgt( |\/)|klon|kpt |kwc\-|kyo(c|k)|le(no|xi)|lg( g|\/(k|l|u)|50|54|\-[a-w])|libw|lynx|m1\-w|m3ga|m50\/|ma(te|ui|xo)|mc(01|21|ca)|m\-cr|me(rc|ri)|mi(o8|oa|ts)|mmef|mo(01|02|bi|de|do|t(\-| |o|v)|zz)|mt(50|p1|v )|mwbp|mywa|n10[0-2]|n20[2-3]|n30(0|2)|n50(0|2|5)|n7(0(0|1)|10)|ne((c|m)\-|on|tf|wf|wg|wt)|nok(6|i)|nzph|o2im|op(ti|wv)|oran|owg1|p800|pan(a|d|t)|pdxg|pg(13|\-([1-8]|c))|phil|pire|pl(ay|uc)|pn\-2|po(ck|rt|se)|prox|psio|pt\-g|qa\-a|qc(07|12|21|32|60|\-[2-7]|i\-)|qtek|r380|r600|raks|rim9|ro(ve|zo)|s55\/|sa(ge|ma|mm|ms|ny|va)|sc(01|h\-|oo|p\-)|sdk\/|se(c(\-|0|1)|mc|nd|ri)|sgh\-|shar|sie(\-|m)|sk\-0|sl(45|id)|sm(al|ar|b3|it|t5)|so(ft|ny)|sp(01|h\-|v\-|v )|sy(01|mb)|t2(18|50)|t6(00|10|18)|ta(gt|lk)|tcl\-|tdg\-|tel(i|m)|tim\-|t\-mo|to(pl|sh)|ts(70|m\-|m3|m5)|tx\-9|up(\.b|g1|si)|utst|v400|v750|veri|vi(rg|te)|vk(40|5[0-3]|\-v)|vm40|voda|vulc|vx(52|53|60|61|70|80|81|83|85|98)|w3c(\-| )|webc|whit|wi(g |nc|nw)|wmlb|wonu|x700|yas\-|your|zeto|zte\-/i', substr($user_agent, 0, 4))) {
        $device_type = 'mobile';
    }

    // GeoIP lookup, only for public IPs
    if ($ip_address && filter_var($ip_address, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
        $response = wp_remote_get("http://ip-api.com/json/{$ip_address}?fields=status,country,city");
        if (!is_wp_error($response) && wp_remote_retrieve_response_code($response) === 200) {
            $geo_data = json_decode(wp_remote_retrieve_body($response), true);
            if ($geo_data && $geo_data['status'] === 'success') {
                $geo_country = sanitize_text_field($geo_data['country']);
                $geo_city = sanitize_text_field($geo_data['city']);
            }
        }
    }

    // Bot detection
    $is_bot = gowptracker_is_bot($user_agent);

    $wpdb->insert(
        $split_hits_table,
        [
            'ts'         => current_time('mysql'),
            'test_slug'  => $slug,
            'variant_id' => intval($choice['id']),
            'client_id'  => $client_id,
            'ip'         => inet_pton($ip_address),
            'ua'         => $user_agent,
            'referrer'   => isset($_SERVER['HTTP_REFERER']) ? esc_url_raw($_SERVER['HTTP_REFERER']) : '',
            'geo_country' => $geo_country,
            'geo_city'    => $geo_city,
            'device_type' => $device_type,
            'is_bot'      => $is_bot ? 1 : 0,
        ],
        ['%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d']
    );

    // Add cache-busting headers to prevent browser caching of the 302 redirect.
    header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
    header("Pragma: no-cache");
    header("Expires: 0");

    wp_redirect($dest_url, 302);
    exit;
}
