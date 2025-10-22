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
            'internal_links' => array(
                'أكياس النيكوتين' => 'https://nicotinekw.com/product-category/أكياس-النيكوتين/',
                'نيكوتين' => 'https://nicotinekw.com/natural-nicotine-in-the-body/',
                'فيب' => 'https://nicotinekw.com/الفرق-بين-سحبة-الزقارة-والفيب/',
                'نكهات' => 'https://nicotinekw.com/مكونات-نكهة-الفيب/',
                'الكويت' => 'https://nicotinekw.com/فيب-الكويت-دليلك-الشامل-لأفضل-المنتجات/'
            )
        );
        
        $settings = get_option('salah_seo_settings', array());
        
        return wp_parse_args($settings, $defaults);
    }
}
