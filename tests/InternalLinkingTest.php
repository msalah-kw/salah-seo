<?php
// Basic bootstrap for running helper tests without full WordPress.

define('ABSPATH', __DIR__);

$GLOBALS['_salah_seo_test_transients'] = array();

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
    function apply_filters($hook, $value, ...$args) {
        if ('salah_seo_paragraph_link_cap' === $hook && isset($GLOBALS['_salah_seo_paragraph_cap'])) {
            return $GLOBALS['_salah_seo_paragraph_cap'];
        }

        return $value;
    }
}

if (!function_exists('wp_using_ext_object_cache')) {
    function wp_using_ext_object_cache() {
        return false;
    }
}

if (!function_exists('get_transient')) {
    function get_transient($key) {
        if (!isset($GLOBALS['_salah_seo_test_transients'][$key])) {
            return false;
        }

        $item = $GLOBALS['_salah_seo_test_transients'][$key];

        if ($item['expires_at'] !== 0 && $item['expires_at'] < time()) {
            unset($GLOBALS['_salah_seo_test_transients'][$key]);

            return false;
        }

        return $item['value'];
    }
}

if (!function_exists('set_transient')) {
    function set_transient($key, $value, $ttl) {
        $GLOBALS['_salah_seo_test_transients'][$key] = array(
            'value'      => $value,
            'expires_at' => $ttl > 0 ? time() + $ttl : 0,
        );

        return true;
    }
}

if (!function_exists('delete_transient')) {
    function delete_transient($key) {
        unset($GLOBALS['_salah_seo_test_transients'][$key]);

        return true;
    }
}

require_once __DIR__ . '/../includes/class-salah-seo-helpers.php';

function reset_test_environment() {
    $GLOBALS['_salah_seo_test_transients'] = array();
    unset($GLOBALS['_salah_seo_paragraph_cap']);
}

function assert_true($condition, $message) {
    if (!$condition) {
        fwrite(STDERR, $message . "\n");
        exit(1);
    }
}

function run_test($name, callable $callback) {
    reset_test_environment();
    $callback();
    echo "✔ {$name}\n";
}

run_test('Shortcode protection', function () {
    $rules = array(
        array(
            'keyword' => 'كلمة',
            'url'     => 'https://example.com/keyword',
        ),
    );

    $content = '<p>هذه كلمة خارجية</p>[custom]كلمة[/custom]';
    $result = Salah_SEO_Helpers::apply_internal_links_to_content($content, $rules);

    assert_true(substr_count($result, '<a href="https://example.com/keyword"') === 1, 'Expected one link outside shortcode region.');
    assert_true(!preg_match('/\[custom\].*<a /u', $result), 'Link leaked into shortcode content.');
});

run_test('Gutenberg block protection', function () {
    $rules = array(
        array(
            'keyword' => 'كلمة',
            'url'     => 'https://example.com/keyword',
        ),
    );

    $content = '<!-- wp:shortcode -->[gallery]كلمة[/gallery]<!-- /wp:shortcode --><p>كلمة ثانية</p>';
    $result = Salah_SEO_Helpers::apply_internal_links_to_content($content, $rules);

    assert_true(substr_count($result, '<a href="https://example.com/keyword"') === 1, 'Expected one link in safe paragraph.');
    assert_true(strpos($result, '[gallery]كلمة[/gallery]') !== false, 'Protected block shortcode altered.');
});

run_test('Unicode word boundaries', function () {
    $rules = array(
        array(
            'keyword' => 'keyword',
            'url'     => 'https://example.com/english',
        ),
        array(
            'keyword' => 'كلمة',
            'url'     => 'https://example.com/arabic',
        ),
    );

    $content = '<p>keyword keywordish كلمة كلمات أخرى</p>';
    $result = Salah_SEO_Helpers::apply_internal_links_to_content($content, $rules);

    assert_true(substr_count($result, 'https://example.com/english') === 1, 'English keyword should link once.');
    assert_true(substr_count($result, 'https://example.com/arabic') === 1, 'Arabic keyword should link once.');
    assert_true(strpos($result, 'keywordish</a>') === false, 'Partial English word was linked.');
    assert_true(strpos($result, 'كلمات</a>') === false, 'Partial Arabic word was linked.');
});

run_test('Per-paragraph cap and idempotency', function () {
    $GLOBALS['_salah_seo_paragraph_cap'] = 1;

    $rules = array(
        array(
            'keyword' => 'alpha',
            'url'     => 'https://example.com/alpha',
        ),
        array(
            'keyword' => 'beta',
            'url'     => 'https://example.com/beta',
        ),
    );

    $content = '<p>alpha beta alpha</p><p>beta alpha beta</p>';
    $first = Salah_SEO_Helpers::apply_internal_links_to_content($content, $rules);
    $second = Salah_SEO_Helpers::apply_internal_links_to_content($first, $rules);

    $matches = array();
    preg_match_all('/<p>(.*?)<\/p>/s', $first, $matches);
    assert_true(isset($matches[1][0]) && substr_count($matches[1][0], '<a ') === 1, 'First paragraph should have one anchor.');
    assert_true(isset($matches[1][1]) && substr_count($matches[1][1], '<a ') === 1, 'Second paragraph should have one anchor.');

    assert_true(substr_count($first, 'https://example.com/alpha') === 1, 'Alpha URL linked once.');
    assert_true(substr_count($first, 'https://example.com/beta') === 1, 'Beta URL linked once.');
    assert_true(substr_count($second, '<a ') === substr_count($first, '<a '), 'Idempotent linking expected.');
    unset($GLOBALS['_salah_seo_paragraph_cap']);
});

run_test('Lock prevents concurrent runners', function () {
    $ttl = Salah_SEO_Helpers::calculate_lock_ttl(2, 2, 1, 1);
    assert_true($ttl >= 3, 'TTL should include safety margin.');

    $token = Salah_SEO_Helpers::acquire_lock('test_lock', $ttl);
    assert_true(false !== $token, 'Initial lock acquisition failed.');

    sleep(1);

    $refreshed = Salah_SEO_Helpers::refresh_lock('test_lock', $token, $ttl);
    assert_true(false !== $refreshed, 'Heartbeat refresh failed.');

    $second = Salah_SEO_Helpers::acquire_lock('test_lock', $ttl);
    assert_true(false === $second, 'Second runner should be blocked while lock is active.');

    $released = Salah_SEO_Helpers::release_lock('test_lock', $token);
    assert_true($released === $token, 'Lock token should be released cleanly.');
});

echo "All helper regression tests passed.\n";
