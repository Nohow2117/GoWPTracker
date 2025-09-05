<?php
// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Ottiene l'IP reale del client, gestendo proxy, Cloudflare e header forwarded.
 *
 * @return string L'IP del client o 'UNKNOWN' se non rilevabile.
 */
function gowptracker_get_client_ip() {
    $ip = 'UNKNOWN';

    if (isset($_SERVER['HTTP_CF_CONNECTING_IP'])) {
        $ip = sanitize_text_field($_SERVER['HTTP_CF_CONNECTING_IP']);
    } elseif (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ip_list = explode(',', sanitize_text_field($_SERVER['HTTP_X_FORWARDED_FOR']));
        $ip = trim($ip_list[0]);
    } elseif (isset($_SERVER['HTTP_X_REAL_IP'])) {
        $ip = sanitize_text_field($_SERVER['HTTP_X_REAL_IP']);
    } elseif (isset($_SERVER['HTTP_CLIENT_IP'])) {
        $ip = sanitize_text_field($_SERVER['HTTP_CLIENT_IP']);
    } elseif (isset($_SERVER['HTTP_FORWARDED'])) {
        $ip = sanitize_text_field($_SERVER['HTTP_FORWARDED']);
    } elseif (isset($_SERVER['REMOTE_ADDR'])) {
        $ip = sanitize_text_field($_SERVER['REMOTE_ADDR']);
    }

    if (filter_var($ip, FILTER_VALIDATE_IP)) {
        return $ip;
    }

    return 'UNKNOWN';
}

/**
 * Checks if a user agent string belongs to a known bot/crawler.
 *
 * @param string $user_agent The user agent string to check.
 * @param string $ip_address The client's IP address.
 * @return bool True if it's a bot, false otherwise.
 */
function gowptracker_is_bot($user_agent, $ip_address) {

    if (empty($user_agent)) {
            return false;
    }

    $bot_signatures = [
        'UptimeRobot', 'Pingdom.com_bot_version', 'PingdomTMS', 'StatusCake', 'Uptime/1.0',
        'Better Uptime Bot', 'GoogleStackdriverMonitoring-UptimeChecks', 'Datadog/Synthetics',
        'Amazon-Route53-Health-Check-Service', 'Site24x7', 'FreshpingBot', 'HetrixTools',
        'Googlebot', 'bingbot', 'Applebot', 'YandexBot', 'Baiduspider', 'DuckDuckBot', 'PetalBot',
        'Yahoo! Slurp', 'Amazonbot',
        'facebookexternalhit', 'Facebot', 'Twitterbot', 'LinkedInBot', 'Pinterestbot', 'redditbot',
        'Slackbot', 'Discordbot', 'TelegramBot', 'WhatsApp',
        'GPTBot', 'ChatGPT-User', 'ClaudeBot', 'PerplexityBot', 'CCBot', 'Bytespider',
        'Google-Extended', 'GoogleOther', 'OAI-SearchBot', 'Meta-ExternalAgent', 'YouBot',
        'ImagesiftBot', 'Omgilibot',
        'AhrefsBot', 'SemrushBot', 'MJ12bot', 'DotBot', 'Botify', 'DeepCrawl',
        'Screaming Frog SEO Spider', 'Sitebulb', 'seobilitybot', 'SEOkicks',
        'bot', 'crawl', 'spider', 'slurp', 'scan', 'curl', 'wget', 'python-requests'
    ];

    $quoted_signatures = array_map(function($s) { return preg_quote($s, '/'); }, $bot_signatures);
    $pattern = '/' . implode('|', $quoted_signatures) . '/i';
    $ua_match = preg_match($pattern, $user_agent) > 0;
    if ($ua_match) {
        return true;
    }

    if ($ip_address !== 'UNKNOWN') {
        $rdns_match = gethostbyaddr($ip_address) === $ip_address;
        if ($rdns_match) {
            return true;
        }
    } else {
    }

    return false;
}