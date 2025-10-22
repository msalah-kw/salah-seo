<?php
/**
 * Helper Functions Class
 * 
 * Utility functions for Salah SEO plugin
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class Salah_SEO_Helpers {
    
    /**
     * Get the last (deepest) subcategory for a product
     * 
     * @param int $post_id Product post ID
     * @return string|null Category name or null if not found
     */
    public static function get_last_subcategory($post_id) {
        $categories = get_the_terms($post_id, 'product_cat');
        
        if (empty($categories) || is_wp_error($categories)) {
            return null;
        }
        
        $deepest_category = null;
        $max_level = -1;
        
        foreach ($categories as $category) {
            $level = self::get_category_level($category->term_id);
            if ($level > $max_level) {
                $max_level = $level;
                $deepest_category = $category;
            }
        }
        
        return $deepest_category ? $deepest_category->name : null;
    }
    
    /**
     * Get the hierarchy level of a category
     * 
     * @param int $term_id Category term ID
     * @return int Category level (0 for top-level)
     */
    private static function get_category_level($term_id) {
        $level = 0;
        $parent_id = wp_get_term_taxonomy_parent_id($term_id, 'product_cat');
        
        while ($parent_id > 0) {
            $level++;
            $parent_id = wp_get_term_taxonomy_parent_id($parent_id, 'product_cat');
        }
        
        return $level;
    }
    
    /**
     * Check if a string is empty or contains only whitespace
     * 
     * @param string $string String to check
     * @return bool True if empty or whitespace only
     */
    public static function is_empty_string($string) {
        return empty(trim($string));
    }
    
    /**
     * Sanitize and validate URL
     * 
     * @param string $url URL to validate
     * @return string|false Sanitized URL or false if invalid
     */
    public static function validate_url($url) {
        $sanitized_url = esc_url_raw($url);
        
        if (filter_var($sanitized_url, FILTER_VALIDATE_URL)) {
            return $sanitized_url;
        }
        
        return false;
    }
    
    /**
     * Get product categories as a hierarchical string
     * 
     * @param int $post_id Product post ID
     * @return string Formatted category string
     */
    public static function get_product_category_string($post_id) {
        $categories = get_the_terms($post_id, 'product_cat');
        
        if (empty($categories) || is_wp_error($categories)) {
            return '';
        }
        
        $category_names = array();
        foreach ($categories as $category) {
            $category_names[] = $category->name;
        }
        
        return implode(', ', $category_names);
    }
    
    /**
     * Check if required plugins are active and compatible
     * 
     * @return array Status array with plugin checks
     */
    public static function check_plugin_compatibility() {
        $status = array(
            'woocommerce' => array(
                'active' => false,
                'version' => null,
                'compatible' => false
            ),
            'rankmath' => array(
                'active' => false,
                'version' => null,
                'compatible' => false
            )
        );
        
        // Check WooCommerce
        if (class_exists('WooCommerce')) {
            $status['woocommerce']['active'] = true;
            
            if (defined('WC_VERSION')) {
                $status['woocommerce']['version'] = WC_VERSION;
                $status['woocommerce']['compatible'] = version_compare(WC_VERSION, '7.0', '>=');
            }
        }
        
        // Check Rank Math
        if (class_exists('RankMath')) {
            $status['rankmath']['active'] = true;
            
            if (defined('RANK_MATH_VERSION')) {
                $status['rankmath']['version'] = RANK_MATH_VERSION;
                $status['rankmath']['compatible'] = version_compare(RANK_MATH_VERSION, '1.0', '>=');
            }
        }
        
        return $status;
    }
    
    /**
     * Log plugin activity for debugging
     * 
     * @param string $message Log message
     * @param string $level Log level (info, warning, error)
     */
    public static function log($message, $level = 'info') {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            $log_message = sprintf(
                '[Salah SEO] [%s] %s',
                strtoupper($level),
                $message
            );
            
            error_log($log_message);
        }
    }
    
    /**
     * Get formatted product information for debugging
     * 
     * @param int $post_id Product post ID
     * @return array Product information array
     */
    public static function get_product_debug_info($post_id) {
        $product = wc_get_product($post_id);
        
        if (!$product) {
            return array('error' => 'Product not found');
        }
        
        return array(
            'id' => $post_id,
            'title' => get_the_title($post_id),
            'type' => $product->get_type(),
            'status' => get_post_status($post_id),
            'categories' => self::get_product_category_string($post_id),
            'last_subcategory' => self::get_last_subcategory($post_id),
            'has_thumbnail' => has_post_thumbnail($post_id),
            'rank_math_keyword' => get_post_meta($post_id, 'rank_math_focus_keyword', true),
            'rank_math_description' => get_post_meta($post_id, 'rank_math_description', true)
        );
    }
    
    /**
     * Clean and prepare text for SEO fields
     * 
     * @param string $text Text to clean
     * @param int $max_length Maximum length (0 for no limit)
     * @return string Cleaned text
     */
    public static function clean_seo_text($text, $max_length = 0) {
        // Remove extra whitespace
        $text = trim(preg_replace('/\s+/', ' ', $text));
        
        // Remove HTML tags
        $text = wp_strip_all_tags($text);
        
        // Truncate if needed
        if ($max_length > 0 && mb_strlen($text) > $max_length) {
            $text = mb_substr($text, 0, $max_length - 3) . '...';
        }
        
        return $text;
    }
    
    /**
     * Check if current user can manage SEO settings
     * 
     * @return bool True if user has permission
     */
    public static function current_user_can_manage_seo() {
        return current_user_can('manage_options') || current_user_can('manage_woocommerce');
    }
    
    /**
     * Get plugin settings with defaults
     * 
     * @return array Plugin settings
     */
    public static function get_plugin_settings() {
        $defaults = array(
            'enable_focus_keyword' => true,
            'enable_meta_description' => true,
            'enable_short_description' => true,
            'enable_product_tags' => true,
            'enable_image_optimization' => true,
            'enable_internal_linking' => true,
            'enable_canonical_fix' => true,
            'enable_redirect_manager' => true,
            'enable_schema_markup' => true,
            'enable_social_meta' => true,
            'background_processing' => true,
            'batch_size' => 5,
            'batch_delay' => 5,
            'task_timeout' => 120,
            'queries_per_minute' => 120,
            'per_item_time_budget' => 10,
            'dry_run_enabled' => true,
            'fallback_og_image' => '',
            'default_meta_description' => 'متجر نيكوتين هو مصدرك الموثوق لمنتجات الفيب بالكويت حيث نوفر توصيل مجاني خلال ساعة واحدة',
            'default_short_description' => 'متجر نيكوتين هو مصدرك الموثوق لمنتجات الفيب بالكويت حيث نوفر توصيل مجاني خلال ساعة واحدة',
            'default_full_description' => 'أفضل منتجات الفيب وأكياس النيكوتين في الكويت. نوفر لك تشكيلة واسعة من أجهزة الفيب، بودات، نكهات، وأظرف نيكوتين أصلية 100%. تمتع بتجربة تدخين الكتروني آمنة، سهلة الاستخدام، وبأسعار تنافسية مع خدمة توصيل سريعة ومجانية داخل الكويت. منتجاتنا تناسب المبتدئين والمحترفين، وتشمل أشهر العلامات التجارية في مجال الفيب. اختر الآن البديل العصري للتدخين التقليدي واستمتع بجودة عالية وتجربة مختلفة.',
            'internal_link_rules' => array(
                array('keyword' => 'أكياس النيكوتين', 'url' => 'https://nicotinekw.com/product-category/أكياس-النيكوتين/', 'repeats' => 1),
                array('keyword' => 'نيكوتين', 'url' => 'https://nicotinekw.com/natural-nicotine-in-the-body/', 'repeats' => 1),
                array('keyword' => 'فيب', 'url' => 'https://nicotinekw.com/الفرق-بين-سحبة-الزقارة-والفيب/', 'repeats' => 1),
                array('keyword' => 'نكهات', 'url' => 'https://nicotinekw.com/مكونات-نكهة-الفيب/', 'repeats' => 1),
                array('keyword' => 'الكويت', 'url' => 'https://nicotinekw.com/فيب-الكويت-دليلك-الشامل-لأفضل-المنتجات/', 'repeats' => 1)
            )
        );

        $settings = get_option('salah_seo_settings', array());
        $settings = wp_parse_args($settings, $defaults);

        // Backward compatibility: convert legacy associative links to structured rules
        if (!empty($settings['internal_links']) && empty($settings['internal_link_rules'])) {
            $settings['internal_link_rules'] = self::format_internal_link_rules($settings['internal_links']);
        }

        return $settings;
    }

    /**
     * Retrieve the persistent queue state.
     *
     * @return array
     */
    public static function get_task_queue() {
        $queue = get_option('salah_seo_task_queue', array());

        if (!is_array($queue)) {
            $queue = array();
        }

        return $queue;
    }

    /**
     * Persist the queue back to the database.
     *
     * @param array $queue Queue payload.
     * @return void
     */
    public static function update_task_queue($queue) {
        update_option('salah_seo_task_queue', array_values($queue), false);
    }

    /**
     * Get processing state metadata.
     *
     * @return array
     */
    public static function get_processing_state() {
        $state = get_option('salah_seo_task_state', array());

        if (!is_array($state)) {
            $state = array();
        }

        return $state;
    }

    /**
     * Update processing state metadata.
     *
     * @param array $state State payload.
     * @return void
     */
    public static function update_processing_state($state) {
        update_option('salah_seo_task_state', $state, false);
    }

    /**
     * Attempt to acquire an exclusive lock to avoid duplicate work.
     *
     * @param string $key Lock key.
     * @param int    $ttl Lock lifetime in seconds.
     * @return string|false Lock token string on success, false if the lock is held elsewhere.
     */
    public static function acquire_lock($key, $ttl = 60) {
        $lock_key = self::get_lock_key($key);
        $token = function_exists('wp_generate_uuid4') ? wp_generate_uuid4() : uniqid('salah_seo_', true);
        $payload = array(
            'token'      => $token,
            'expires_at' => time() + max(1, (int) $ttl),
        );

        if (self::use_object_cache()) {
            $added = wp_cache_add($lock_key, $payload, self::get_lock_cache_group(), $ttl);

            if (!$added) {
                $existing = wp_cache_get($lock_key, self::get_lock_cache_group());
                if (is_array($existing) && !self::is_lock_expired($existing)) {
                    return false;
                }

                wp_cache_set($lock_key, $payload, self::get_lock_cache_group(), $ttl);
            }

            return $token;
        }

        $existing = get_transient($lock_key);

        if (is_array($existing) && !self::is_lock_expired($existing)) {
            return false;
        }

        set_transient($lock_key, $payload, $ttl);

        return $token;
    }

    /**
     * Refresh an existing lock heartbeat.
     *
     * @param string $key   Lock key.
     * @param string $token Lock token returned on acquire.
     * @param int    $ttl   Lock lifetime in seconds.
     * @return string|false Lock token string on success, false on failure.
     */
    public static function refresh_lock($key, $token, $ttl) {
        $lock_key = self::get_lock_key($key);
        $payload = array(
            'token'      => $token,
            'expires_at' => time() + max(1, (int) $ttl),
        );

        if (self::use_object_cache()) {
            $existing = wp_cache_get($lock_key, self::get_lock_cache_group());

            if (!is_array($existing) || empty($existing['token']) || $existing['token'] !== $token) {
                return false;
            }

            wp_cache_set($lock_key, $payload, self::get_lock_cache_group(), $ttl);

            return $token;
        }

        $existing = get_transient($lock_key);

        if (!is_array($existing) || empty($existing['token']) || $existing['token'] !== $token) {
            return false;
        }

        set_transient($lock_key, $payload, $ttl);

        return $token;
    }

    /**
     * Release an existing lock.
     *
     * @param string      $key   Lock key.
     * @param string|null $token Expected token. Null to force release.
     * @return string|false Released token (or empty string when forced) on success, false on failure.
     */
    public static function release_lock($key, $token = null) {
        $lock_key = self::get_lock_key($key);

        if (self::use_object_cache()) {
            if (null === $token) {
                wp_cache_delete($lock_key, self::get_lock_cache_group());

                return '';
            }

            $existing = wp_cache_get($lock_key, self::get_lock_cache_group());

            if (!is_array($existing) || empty($existing['token']) || $existing['token'] !== $token) {
                return false;
            }

            wp_cache_delete($lock_key, self::get_lock_cache_group());

            return $existing['token'];
        }

        if (null === $token) {
            delete_transient($lock_key);

            return '';
        }

        $existing = get_transient($lock_key);

        if (!is_array($existing) || empty($existing['token']) || $existing['token'] !== $token) {
            return false;
        }

        delete_transient($lock_key);

        return $existing['token'];
    }

    /**
     * Register a shutdown handler to safely release a lock on fatal termination.
     *
     * @param string $key   Lock key.
     * @param string $token Lock token.
     * @return void
     */
    public static function register_lock_shutdown($key, $token) {
        register_shutdown_function(array(__CLASS__, 'release_lock'), $key, $token);
    }

    /**
     * Determine if an existing lock payload has expired.
     *
     * @param array $payload Lock payload.
     * @return bool
     */
    private static function is_lock_expired($payload) {
        $expires_at = isset($payload['expires_at']) ? (int) $payload['expires_at'] : 0;

        return $expires_at > 0 && $expires_at < time();
    }

    /**
     * Get the cache key for a lock identifier.
     *
     * @param string $key Raw lock key.
     * @return string
     */
    private static function get_lock_key($key) {
        return 'salah_seo_lock_' . md5($key);
    }

    /**
     * Determine whether an object cache should be used for locking.
     *
     * @return bool
     */
    private static function use_object_cache() {
        return function_exists('wp_using_ext_object_cache') && wp_using_ext_object_cache();
    }

    /**
     * Retrieve the cache group used for lock payloads.
     *
     * @return string
     */
    private static function get_lock_cache_group() {
        return 'salah-seo';
    }

    /**
     * Calculate the minimum lock TTL required for a batch.
     *
     * @param int $task_timeout        Configured task timeout.
     * @param int $batch_size          Number of items processed per batch.
     * @param int $per_item_time_budget Estimated seconds per item.
     * @param int $safety_margin       Additional buffer seconds.
     * @return int
     */
    public static function calculate_lock_ttl($task_timeout, $batch_size, $per_item_time_budget, $safety_margin = 15) {
        $task_timeout = max(0, (int) $task_timeout);
        $batch_size = max(1, (int) $batch_size);
        $per_item_time_budget = max(1, (int) $per_item_time_budget);
        $safety_margin = max(0, (int) $safety_margin);

        $estimated_duration = $batch_size * $per_item_time_budget;
        $baseline = max($task_timeout, $estimated_duration);

        if (0 === $baseline) {
            $baseline = $estimated_duration;
        }

        return (int) max(1, $baseline + $safety_margin);
    }

    /**
     * Get fallback image URL used for social sharing metadata.
     *
     * @return string
     */
    public static function get_fallback_image() {
        $settings = self::get_plugin_settings();

        if (!empty($settings['fallback_og_image'])) {
            return esc_url_raw($settings['fallback_og_image']);
        }

        $site_icon = get_site_icon_url();

        if (!empty($site_icon)) {
            return esc_url_raw($site_icon);
        }

        $custom_logo = get_theme_mod('custom_logo');

        if ($custom_logo) {
            $image = wp_get_attachment_image_src($custom_logo, 'full');
            if (!empty($image[0])) {
                return esc_url_raw($image[0]);
            }
        }

        return '';
    }

    /**
     * Normalize internal link rules from multiple formats
     *
     * @param array $raw_rules Raw rules array
     * @return array
     */
    public static function format_internal_link_rules($raw_rules) {
        $rules = array();

        if (empty($raw_rules) || !is_array($raw_rules)) {
            return $rules;
        }

        // If associative array keyword => url
        if (array_keys($raw_rules) !== range(0, count($raw_rules) - 1)) {
            foreach ($raw_rules as $keyword => $url) {
                $valid_url = self::validate_url($url);
                $keyword = sanitize_text_field($keyword);
                if ($keyword && $valid_url) {
                    $rules[] = array(
                        'keyword' => $keyword,
                        'url' => $valid_url,
                        'repeats' => 1,
                    );
                }
            }
            return $rules;
        }

        foreach ($raw_rules as $rule) {
            if (!is_array($rule)) {
                continue;
            }

            $keyword = isset($rule['keyword']) ? sanitize_text_field($rule['keyword']) : '';
            $url = isset($rule['url']) ? self::validate_url($rule['url']) : false;
            $repeats = isset($rule['repeats']) ? max(1, intval($rule['repeats'])) : 1;

            if ($keyword && $url) {
                $rules[] = array(
                    'keyword' => $keyword,
                    'url' => $url,
                    'repeats' => $repeats,
                );
            }
        }

        return $rules;
    }

    /**
     * Apply internal link rules to HTML content while preserving protected regions.
     *
     * @param string $content Original content.
     * @param array  $rules   Normalized rules array.
     * @return string Updated content.
     */
    public static function apply_internal_links_to_content($content, $rules) {
        if (empty($content) || empty($rules)) {
            return $content;
        }

        $normalized_rules = array();

        foreach ($rules as $rule) {
            if (empty($rule['keyword']) || empty($rule['url'])) {
                continue;
            }

            $normalized_rules[] = array(
                'keyword' => $rule['keyword'],
                'url'     => $rule['url'],
            );
        }

        if (empty($normalized_rules)) {
            return $content;
        }

        $placeholders = array();
        $protected_content = self::protect_content_regions($content, $placeholders);

        $libxml_previous_state = libxml_use_internal_errors(true);
        $dom = new DOMDocument('1.0', 'UTF-8');

        if (!$dom->loadHTML('<?xml encoding="utf-8" ?>' . $protected_content, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD)) {
            libxml_clear_errors();
            libxml_use_internal_errors($libxml_previous_state);

            return $content;
        }

        libxml_clear_errors();
        libxml_use_internal_errors($libxml_previous_state);

        $xpath = new DOMXPath($dom);
        $used_urls = self::collect_existing_link_urls($xpath);
        $paragraph_counts = array();

        foreach ($normalized_rules as $rule) {
            $keyword = $rule['keyword'];
            $url = $rule['url'];

            if (isset($used_urls[$url])) {
                continue;
            }

            if (false === stripos($protected_content, $keyword)) {
                continue;
            }

            $paragraph_cap = apply_filters('salah_seo_paragraph_link_cap', 2, $rule);
            $paragraph_cap = max(0, (int) $paragraph_cap);

            $candidate_nodes = self::collect_safe_text_nodes($xpath);

            foreach ($candidate_nodes as $node) {
                if (!$node instanceof DOMText || !$node->parentNode || isset($used_urls[$url])) {
                    continue;
                }

                $text = $node->nodeValue;

                if ('' === trim($text) || false === stripos($text, $keyword)) {
                    continue;
                }

                $paragraph_key = self::identify_paragraph_key($node);

                if ($paragraph_cap > 0) {
                    $current = isset($paragraph_counts[$paragraph_key]) ? $paragraph_counts[$paragraph_key] : 0;

                    if ($current >= $paragraph_cap) {
                        continue;
                    }
                }

                $inserted = self::inject_link_into_text_node($dom, $node, $keyword, $url);

                if ($inserted > 0) {
                    $used_urls[$url] = true;

                    if ($paragraph_cap > 0) {
                        $paragraph_counts[$paragraph_key] = isset($paragraph_counts[$paragraph_key]) ? $paragraph_counts[$paragraph_key] + $inserted : $inserted;
                    }

                    break; // Enforce single anchor per destination URL.
                }
            }
        }

        $new_html = $dom->saveHTML();
        $new_html = preg_replace('~<(?:!DOCTYPE|/?(?:html|body))[^>]*>\s*~i', '', $new_html);

        return self::restore_protected_regions($new_html, $placeholders);
    }

    /**
     * Collect safe text nodes that can receive injected anchors.
     *
     * @param DOMXPath $xpath DOMXPath instance.
     * @return DOMText[]
     */
    private static function collect_safe_text_nodes(DOMXPath $xpath) {
        $expression = "//text()[normalize-space() != ''"
            . " and not(ancestor::a)"
            . " and not(ancestor::script)"
            . " and not(ancestor::style)"
            . " and not(ancestor::code)"
            . " and not(ancestor::pre)"
            . " and not(ancestor::kbd)"
            . " and not(ancestor::samp)"
            . " and not(ancestor::var)"
            . " and not(ancestor::button)"
            . " and not(ancestor::nav)"
            . " and not(ancestor::figcaption)"
            . " and not(ancestor::h1)"
            . " and not(ancestor::h2)"
            . " and not(ancestor::h3)"
            . " and not(ancestor::h4)"
            . " and not(ancestor::h5)"
            . " and not(ancestor::h6)"
            . " and not(ancestor::*[contains(concat(' ', normalize-space(@class), ' '), ' wp-block-buttons ')])"
            . " and not(ancestor::*[contains(concat(' ', normalize-space(@class), ' '), ' wp-block-button ')])"
            . " and not(ancestor::*[contains(concat(' ', normalize-space(@class), ' '), ' wp-block-code ')])"
            . " and not(ancestor::*[contains(concat(' ', normalize-space(@class), ' '), ' wp-block-preformatted ')])"
            . " and not(ancestor::*[contains(concat(' ', normalize-space(@class), ' '), ' wp-block-navigation ')])"
            . " and not(ancestor::*[contains(concat(' ', normalize-space(@class), ' '), ' wp-block-navigation-link ')])"
            . " and not(ancestor::ul[contains(concat(' ', normalize-space(@class), ' '), ' toc ')])"
            . " and not(ancestor::ol[contains(concat(' ', normalize-space(@class), ' '), ' toc ')])"
            . " and not(ancestor::*[contains(concat(' ', normalize-space(@class), ' '), ' wp-block-table-of-contents ')])"
            . ']';

        $nodes = $xpath->query($expression);

        if (!$nodes instanceof DOMNodeList) {
            return array();
        }

        $safe_nodes = array();

        foreach ($nodes as $node) {
            if ($node instanceof DOMText) {
                $safe_nodes[] = $node;
            }
        }

        return $safe_nodes;
    }

    /**
     * Inject a single anchor into the provided text node when a safe keyword match is found.
     *
     * @param DOMDocument $dom     DOM document.
     * @param DOMText     $node    Text node.
     * @param string      $keyword Keyword to match.
     * @param string      $url     Destination URL.
     * @return int Number of anchors inserted (0 or 1).
     */
    private static function inject_link_into_text_node(DOMDocument $dom, DOMText $node, $keyword, $url) {
        $pattern = self::build_keyword_pattern($keyword);
        $segments = preg_split($pattern, $node->nodeValue, 2, PREG_SPLIT_DELIM_CAPTURE);

        if (empty($segments) || count($segments) < 3) {
            return 0;
        }

        list($before, $matched, $after) = $segments;

        if ($matched === '') {
            return 0;
        }

        $fragment = $dom->createDocumentFragment();

        if ($before !== '') {
            $fragment->appendChild($dom->createTextNode($before));
        }

        $link = $dom->createElement('a');
        $link->appendChild($dom->createTextNode($matched));
        $link->setAttribute('href', $url);
        $link->setAttribute('target', '_self');
        $link->setAttribute('rel', 'noopener');
        $fragment->appendChild($link);

        if ($after !== '') {
            $fragment->appendChild($dom->createTextNode($after));
        }

        if ($node->parentNode) {
            $node->parentNode->replaceChild($fragment, $node);

            return 1;
        }

        return 0;
    }

    /**
     * Build a Unicode-aware boundary-safe regex pattern for a keyword.
     *
     * @param string $keyword Keyword text.
     * @return string
     */
    private static function build_keyword_pattern($keyword) {
        $quoted = preg_quote($keyword, '/');

        return '/(?<![\p{L}\p{N}_])(' . $quoted . ')(?![\p{L}\p{N}_])/iu';
    }

    /**
     * Identify a stable key for the paragraph-like ancestor of a node.
     *
     * @param DOMNode $node Node to inspect.
     * @return string
     */
    private static function identify_paragraph_key(DOMNode $node) {
        $element = $node->parentNode;

        while ($element && $element->nodeType !== XML_ELEMENT_NODE) {
            $element = $element->parentNode;
        }

        while ($element && $element->nodeType === XML_ELEMENT_NODE) {
            $tag = strtolower($element->nodeName);

            if (in_array($tag, array('p', 'li', 'div', 'section', 'article', 'td', 'th'), true)) {
                if (method_exists($element, 'getNodePath')) {
                    $path = $element->getNodePath();

                    if (!empty($path)) {
                        return $path;
                    }
                }

                return $tag . '_' . spl_object_hash($element);
            }

            $element = $element->parentNode;
        }

        return 'global';
    }

    /**
     * Gather URLs already linked in the DOM to keep processing idempotent.
     *
     * @param DOMXPath $xpath DOMXPath instance.
     * @return array<string,bool>
     */
    private static function collect_existing_link_urls(DOMXPath $xpath) {
        $anchors = $xpath->query('//a[@href]');

        if (!$anchors instanceof DOMNodeList) {
            return array();
        }

        $urls = array();

        foreach ($anchors as $anchor) {
            $href = trim($anchor->getAttribute('href'));

            if ($href !== '') {
                $urls[$href] = true;
            }
        }

        return $urls;
    }

    /**
     * Replace shortcode and protected block regions with placeholders.
     *
     * @param string $content Original content.
     * @param array  $placeholders Reference placeholder map.
     * @return string
     */
    private static function protect_content_regions($content, array &$placeholders) {
        $counter = 0;

        $content = self::protect_shortcodes($content, $placeholders, $counter);
        $content = self::protect_block_regions($content, $placeholders, $counter);

        return $content;
    }

    /**
     * Restore placeholder regions back to their original markup.
     *
     * @param string $content Filtered content.
     * @param array  $placeholders Placeholder map.
     * @return string
     */
    private static function restore_protected_regions($content, array $placeholders) {
        if (empty($placeholders)) {
            return $content;
        }

        $tokens = array_keys($placeholders);

        usort(
            $tokens,
            function ($a, $b) {
                return strlen($b) - strlen($a);
            }
        );

        foreach ($tokens as $token) {
            $original = $placeholders[$token];
            $content = str_replace($token, $original, $content);
        }

        return $content;
    }

    /**
     * Protect inline shortcodes with placeholders.
     *
     * @param string $content Content.
     * @param array  $placeholders Placeholder map.
     * @param int    $counter Placeholder index.
     * @return string
     */
    private static function protect_shortcodes($content, array &$placeholders, &$counter) {
        if (function_exists('get_shortcode_regex')) {
            $regex = '/' . get_shortcode_regex() . '/s';
        } else {
            $regex = '/\[([a-zA-Z0-9_-]+)(?:[^\]]*)\](?:.*?\[\/\1\])?/s';
        }

        return preg_replace_callback(
            $regex,
            function ($match) use (&$placeholders, &$counter) {
                $raw = $match[0];
                $token = self::generate_placeholder_token(++$counter);
                $placeholders[$token] = $raw;

                return $token;
            },
            $content
        );
    }

    /**
     * Protect block regions that should not be modified.
     *
     * @param string $content Content.
     * @param array  $placeholders Placeholder map.
     * @param int    $counter Placeholder index.
     * @return string
     */
    private static function protect_block_regions($content, array &$placeholders, &$counter) {
        $has_blocks = function_exists('has_blocks') ? has_blocks($content) : (false !== strpos($content, '<!-- wp:'));

        if (!function_exists('parse_blocks') || !$has_blocks) {
            return self::protect_block_comments($content, $placeholders, $counter);
        }

        $blocks = parse_blocks($content);
        $protected = self::collect_protected_blocks($blocks);

        foreach ($protected as $original) {
            $token = self::generate_placeholder_token(++$counter);
            $placeholders[$token] = $original;
            $content = str_replace($original, $token, $content);
        }

        return $content;
    }

    /**
     * Fallback block protection when block parser is unavailable.
     *
     * @param string $content Content string.
     * @param array  $placeholders Placeholder map.
     * @param int    $counter Placeholder counter.
     * @return string
     */
    private static function protect_block_comments($content, array &$placeholders, &$counter) {
        $pattern = '/<!--\s+wp:([^\s]+)[^>]*-->.*?<!--\s+\/wp:\1\s+-->/is';

        $protected_blocks = array('core/shortcode', 'core/html', 'core/button', 'core/buttons', 'core/navigation', 'core/navigation-link', 'core/code');

        if (function_exists('apply_filters')) {
            $protected_blocks = apply_filters('salah_seo_protected_block_types', $protected_blocks);
        }

        return preg_replace_callback(
            $pattern,
            function ($match) use (&$placeholders, &$counter, $protected_blocks) {
                $name = isset($match[1]) ? trim($match[1]) : '';

                if (!in_array($name, $protected_blocks, true)) {
                    return $match[0];
                }

                $token = self::generate_placeholder_token(++$counter);
                $placeholders[$token] = $match[0];

                return $token;
            },
            $content
        );
    }

    /**
     * Gather serialized markup for protected blocks using the block parser.
     *
     * @param array $blocks Parsed blocks.
     * @return array
     */
    private static function collect_protected_blocks($blocks) {
        $protected = array();
        $protected_types = array(
            'core/shortcode',
            'core/html',
            'core/button',
            'core/buttons',
            'core/navigation',
            'core/navigation-link',
            'core/code',
        );

        if (function_exists('apply_filters')) {
            $protected_types = apply_filters('salah_seo_protected_block_types', $protected_types);
        }

        foreach ($blocks as $block) {
            if (!is_array($block)) {
                continue;
            }

            $block_name = isset($block['blockName']) ? $block['blockName'] : '';

            if (in_array($block_name, $protected_types, true)) {
                if (function_exists('serialize_block')) {
                    $protected[] = serialize_block($block);
                } elseif (!empty($block['innerHTML'])) {
                    $protected[] = $block['innerHTML'];
                }
                continue;
            }

            if (!empty($block['innerBlocks'])) {
                $protected = array_merge($protected, self::collect_protected_blocks($block['innerBlocks']));
            }
        }

        return array_unique($protected);
    }

    /**
     * Generate a deterministic placeholder token.
     *
     * @param int $counter Placeholder index.
     * @return string
     */
    private static function generate_placeholder_token($counter) {
        return sprintf('%%SALAHSEO_PROTECTED_%d%%', $counter);
    }

    /**
     * Build internal link suggestions without altering content.
     *
     * @param int $post_id Post ID.
     * @return array
     */
    public static function generate_internal_link_suggestions($post_id) {
        $post = get_post($post_id);
        if (!$post || empty($post->post_content)) {
            return array();
        }

        $settings = self::get_plugin_settings();
        $rules = isset($settings['internal_link_rules']) ? self::format_internal_link_rules($settings['internal_link_rules']) : array();

        if (empty($rules)) {
            return array();
        }

        $content = $post->post_content;
        $suggestions = array();

        foreach ($rules as $rule) {
            if (empty($rule['keyword']) || empty($rule['url'])) {
                continue;
            }

            if (false !== stripos($content, $rule['url'])) {
                continue;
            }

            if (false === stripos($content, $rule['keyword'])) {
                continue;
            }

            $suggestions[] = array(
                'keyword' => $rule['keyword'],
                'url' => $rule['url'],
                'potential_matches' => (int) preg_match_all('/' . preg_quote($rule['keyword'], '/') . '/iu', $content, $dummy),
            );
        }

        return $suggestions;
    }

    /**
     * Remove internal links that point to the current site from content.
     *
     * @param string $content Original content.
     * @return string Updated content without internal links.
     */
    public static function remove_internal_links_from_content($content) {
        if (empty($content)) {
            return $content;
        }

        $site_url = preg_quote(home_url(), '/');
        $pattern = '/<a\s+(?:[^>]*?\s+)?href=["\'](' . $site_url . '[^"\']*)["\'][^>]*?>(.*?)<\/a>/is';

        return preg_replace($pattern, '$2', $content);
    }
}
