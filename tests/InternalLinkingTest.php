<?php
// Basic bootstrap for running helper tests without full WordPress.

define('ABSPATH', __DIR__);

if (!function_exists('wp_parse_args')) {
    function wp_parse_args($args, $defaults = array()) {
        return array_merge($defaults, (array) $args);
    }
}

if (!function_exists('get_option')) {
    function get_option($name, $default = array()) {
        return $default;
    }
}

if (!function_exists('update_option')) {
    function update_option($name, $value, $autoload = false) {
        return true;
    }
}

if (!function_exists('sanitize_text_field')) {
    function sanitize_text_field($str) {
        return is_string($str) ? trim($str) : $str;
    }
}

if (!function_exists('esc_url_raw')) {
    function esc_url_raw($url) {
        return $url;
    }
}

if (!function_exists('wp_strip_all_tags')) {
    function wp_strip_all_tags($text) {
        return strip_tags($text);
    }
}

if (!function_exists('apply_filters')) {
    function apply_filters($hook, $value) {
        return $value;
    }
}

require_once __DIR__ . '/../includes/class-salah-seo-helpers.php';

$rules = array(
    array(
        'keyword' => 'كلمة',
        'url' => 'https://example.com/keyword',
        'repeats' => 1,
    ),
);

$content = '<p>هذه كلمة خارجية</p>[custom]كلمة[/custom]';
$result = Salah_SEO_Helpers::apply_internal_links_to_content($content, $rules);

if (substr_count($result, '<a href="https://example.com/keyword"') !== 1) {
    fwrite(STDERR, "Failed asserting that exactly one link was inserted outside shortcode.\n");
    exit(1);
}

if (preg_match('/\[custom\].*<a /u', $result)) {
    fwrite(STDERR, "Internal link leaked into shortcode content.\n");
    exit(1);
}

$block_content = '<!-- wp:shortcode -->[gallery]كلمة[/gallery]<!-- /wp:shortcode --><p>كلمة ثانية</p>';
$block_result = Salah_SEO_Helpers::apply_internal_links_to_content($block_content, $rules);

if (substr_count($block_result, '<a href="https://example.com/keyword"') !== 1) {
    fwrite(STDERR, "Failed asserting that block-safe content received a single link.\n");
    exit(1);
}

if (strpos($block_result, '[gallery]كلمة[/gallery]') === false) {
    fwrite(STDERR, "Protected block shortcode content was altered.\n");
    exit(1);
}

echo "All internal linking safety tests passed.\n";
