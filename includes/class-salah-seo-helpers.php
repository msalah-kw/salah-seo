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
     * Apply internal link rules to HTML content.
     *
     * @param string $content Original content.
     * @param array  $rules   Normalized rules array.
     * @return string Updated content.
     */
    public static function apply_internal_links_to_content($content, $rules) {
        if (empty($content) || empty($rules)) {
            return $content;
        }

        $dom = new DOMDocument();
        @$dom->loadHTML('<?xml encoding="utf-8" ?>' . $content, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);

        $xpath = new DOMXPath($dom);
        foreach ($rules as $rule) {
            $keyword = $rule['keyword'];
            $url = $rule['url'];
            $max_repeats = isset($rule['repeats']) ? max(1, intval($rule['repeats'])) : 1;
            $count = 0;

            $nodes = $xpath->query("//text()[not(ancestor::a) and not(ancestor::script) and not(ancestor::style) and not(ancestor::code) and normalize-space() != '']");

            foreach ($nodes as $node) {
                if ($count >= $max_repeats) {
                    break;
                }

                if (stripos($node->nodeValue, $keyword) === false) {
                    continue;
                }

                $fragment = $dom->createDocumentFragment();
                $parts = preg_split('/(' . preg_quote($keyword, '/') . ')/iu', $node->nodeValue, -1, PREG_SPLIT_DELIM_CAPTURE);

                foreach ($parts as $index => $part) {
                    $is_keyword = ($index % 2 === 1) && $count < $max_repeats;

                    if ($is_keyword) {
                        $link = $dom->createElement('a', $part);
                        $link->setAttribute('href', $url);
                        $link->setAttribute('target', '_self');
                        $link->setAttribute('rel', 'noopener');
                        $fragment->appendChild($link);
                        $count++;
                    } else {
                        $fragment->appendChild($dom->createTextNode($part));
                    }
                }

                if ($node->parentNode) {
                    $node->parentNode->replaceChild($fragment, $node);
                }

                if ($count >= $max_repeats) {
                    break;
                }
            }
        }

        $new_html = $dom->saveHTML();

        return preg_replace('~<(?:!DOCTYPE|/?(?:html|body))[^>]*>\s*~i', '', $new_html);
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
