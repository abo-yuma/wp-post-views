<?php

/**
 * Plugin Name: ABO YUMA Post Views Counter
 * Description: Track unique post views using IP + Transients (anti-bot, admin ignore, 24h unique).
 * Version: 1.0.2
 * Author: ABO YUMA
 */

if (!defined('ABSPATH')) exit;

if (!defined('HOUR_IN_SECONDS')) {
    define('HOUR_IN_SECONDS', 3600);
}

// =========================
// Get Real IP (Cloudflare Safe)
// =========================
function aboyuma_get_real_ip() {
    if (!empty($_SERVER['HTTP_CF_CONNECTING_IP'])) {
        $ip = $_SERVER['HTTP_CF_CONNECTING_IP'];
    } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ip = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0];
    } else {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    }

    return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : '0.0.0.0';
}

// =========================
// Bot Detection
// =========================
function aboyuma_visitor_is_bot() {
    if (empty($_SERVER['HTTP_USER_AGENT'])) return true;

    $bots = [
        'bot',
        'crawl',
        'slurp',
        'spider',
        'curl',
        'wget',
        'python',
        'scrapy',
        'facebookexternalhit',
        'discordbot',
        'whatsapp',
        'telegrambot',
        'twitterbot',
        'apache-httpclient',
        'java',
        'go-http-client',
        'axios'
    ];

    $ua = strtolower($_SERVER['HTTP_USER_AGENT']);

    foreach ($bots as $bot) {
        if (strpos($ua, $bot) !== false) return true;
    }
    return false;
}

// =========================
// Set Post Views
// =========================
function aboyuma_set_post_views($postID) {

    if (get_post_type($postID) !== 'post') return;

    if (aboyuma_visitor_is_bot()) return;

    // ignore admin
    if (is_user_logged_in() && current_user_can('manage_options')) return;

    $ip = aboyuma_get_real_ip();
    $ip_key = 'post_viewed_' . $postID . '_' . md5($ip);

    if (false === get_transient($ip_key)) {

        $count_key = 'post_views_count';
        $transient_key = 'post_views_' . $postID;

        $count = get_transient($transient_key);

        if ($count === false) {
            $count = get_post_meta($postID, $count_key, true);
            if ($count == '') $count = 0;
        }

        $count++;
        update_post_meta($postID, $count_key, $count);

        set_transient($transient_key, $count, 12 * HOUR_IN_SECONDS);
        set_transient($ip_key, true, 24 * HOUR_IN_SECONDS);
    }
}

// =========================
// Get Post Views
// =========================
function aboyuma_get_post_views($postID) {

    if (get_post_type($postID) !== 'post') return '';

    $count_key = 'post_views_count';
    $transient_key = 'post_views_' . $postID;

    $count = get_transient($transient_key);

    if ($count === false) {
        $count = get_post_meta($postID, $count_key, true);
        if ($count == '') {
            $count = 0;
            update_post_meta($postID, $count_key, $count);
        }
        set_transient($transient_key, $count, 12 * HOUR_IN_SECONDS);
    }

    return '<span style="margin-left: 5px;">📊 ' . $count . '</span>';
}

// =========================
// Auto Track (Post Only)
// =========================
function aboyuma_track_post_views() {
    if (is_single()) {
        global $post;
        if ($post) {
            aboyuma_set_post_views($post->ID);
        }
    }
}
add_action('wp_head', 'aboyuma_track_post_views');
