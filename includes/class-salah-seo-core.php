<?php
/**
 * Core SEO Optimization Class
 * 
 * Handles the main SEO optimization logic for WooCommerce products
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class Salah_SEO_Core {
    
    /**
     * Settings option name
     */
    private $option_name = 'salah_seo_settings';
    
    /**
     * Plugin settings
     */
    private $settings;
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->settings = get_option($this->option_name, array());
        $this->init_hooks();
    }
    
    /**
     * Initialize WordPress hooks
     */
    private function init_hooks() {
        // Hook into product save
        add_action('save_post_product', array($this, 'run_optimizations'), 10, 2);
        
        // Hook into canonical URL filter if enabled
        if ($this->is_feature_enabled('enable_canonical_fix')) {
            add_filter('rank_math/frontend/canonical', array($this, 'fix_canonical_url'), 10, 1);
        }
    }
    
    /**
     * Main optimization function - runs when product is saved
     */
    public function run_optimizations($post_id, $post) {
        // Skip if this is an autosave or revision
        if (wp_is_post_autosave($post_id) || wp_is_post_revision($post_id)) {
            return;
        }
        
        // Skip if user doesn't have permission to edit
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }
        
        // Skip if not a product
        if ($post->post_type !== 'product') {
            return;
        }
        
        // Get WooCommerce product object
        $product = wc_get_product($post_id);
        if (!$product) {
            return;
        }
        
        // Run optimization functions
        $optimizations_applied = false;
        $applied_optimizations = array();
        
        if ($this->is_feature_enabled('enable_focus_keyword')) {
            if ($this->populate_focus_keyword($post_id)) {
                $optimizations_applied = true;
                $applied_optimizations[] = 'الكلمة المفتاحية (Focus Keyword)';
            }
        }
        
        if ($this->is_feature_enabled('enable_meta_description')) {
            if ($this->populate_meta_description($post_id)) {
                $optimizations_applied = true;
                $applied_optimizations[] = 'الوصف التعريفي (Meta Description)';
            }
        }
        
        if ($this->is_feature_enabled('enable_short_description')) {
            if ($this->populate_short_description($post_id)) {
                $optimizations_applied = true;
                $applied_optimizations[] = 'الوصف القصير (Short Description)';
            }
        }
        
        if ($this->is_feature_enabled('enable_product_tags')) {
            if ($this->populate_product_tags($post_id)) {
                $optimizations_applied = true;
                $applied_optimizations[] = 'وسوم المنتج (Product Tags)';
            }
        }
        
        if ($this->is_feature_enabled('enable_image_optimization')) {
            if ($this->optimize_featured_image($post_id)) {
                $optimizations_applied = true;
                $applied_optimizations[] = 'تحسين الصورة البارزة (Image SEO)';
            }
        }
        
        // Apply full description and internal linking
        if ($this->populate_full_description($post_id)) {
            $optimizations_applied = true;
            $applied_optimizations[] = 'الوصف الكامل (Full Description)';
            
            // Apply internal linking after description is set
            if ($this->is_feature_enabled('enable_internal_linking')) {
                if ($this->apply_internal_linking($post_id)) {
                    $applied_optimizations[] = 'الربط الداخلي (Internal Linking)';
                }
            }
        }
        
        // Show success notice if any optimizations were applied
        if ($optimizations_applied) {
            // Store detailed optimization results
            $optimization_details = get_transient('salah_seo_optimization_details') ?: array();
            $optimization_details[$post_id] = $applied_optimizations;
            set_transient('salah_seo_optimization_details', $optimization_details, 300);
            set_transient('salah_seo_success_notice', true, 30);
        }
    }
    
    /**
     * Populate Rank Math Focus Keyword
     */
    private function populate_focus_keyword($post_id) {
        $current_keyword = get_post_meta($post_id, 'rank_math_focus_keyword', true);
        
        // Only populate if empty
        if (empty($current_keyword)) {
            $product_title = get_the_title($post_id);
            if (!empty($product_title)) {
                update_post_meta($post_id, 'rank_math_focus_keyword', $product_title);
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Populate Rank Math Meta Description
     */
    private function populate_meta_description($post_id) {
        $current_description = get_post_meta($post_id, 'rank_math_description', true);
        
        // Only populate if empty
        if (empty($current_description)) {
            $default_text = $this->get_setting('default_meta_description');
            if (!empty($default_text)) {
                update_post_meta($post_id, 'rank_math_description', $default_text);
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Populate WooCommerce Short Description
     */
    private function populate_short_description($post_id) {
        $post = get_post($post_id);
        
        // Only populate if empty
        if (empty($post->post_excerpt)) {
            $default_text = $this->get_setting('default_short_description');
            if (!empty($default_text)) {
                wp_update_post(array(
                    'ID' => $post_id,
                    'post_excerpt' => $default_text
                ));
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Populate full product description
     */
    private function populate_full_description($post_id) {
        $post = get_post($post_id);
        
        // Only populate if empty
        if (empty($post->post_content)) {
            $default_text = $this->get_setting('default_full_description');
            if (!empty($default_text)) {
                wp_update_post(array(
                    'ID' => $post_id,
                    'post_content' => $default_text
                ));
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Populate Product Tags
     */
    private function populate_product_tags($post_id) {
        $current_tags = wp_get_post_terms($post_id, 'product_tag', array('fields' => 'names'));
        
        // Only populate if no tags exist
        if (empty($current_tags) || is_wp_error($current_tags)) {
            $tags_to_add = array();
            
            // Add product name as tag
            $product_title = get_the_title($post_id);
            if (!empty($product_title)) {
                $tags_to_add[] = $product_title;
            }
            
            // Add last sub-category as tag
            $last_category = Salah_SEO_Helpers::get_last_subcategory($post_id);
            if (!empty($last_category)) {
                $tags_to_add[] = $last_category;
            }
            
            if (!empty($tags_to_add)) {
                wp_set_post_terms($post_id, $tags_to_add, 'product_tag', false);
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Optimize featured image metadata
     */
    private function optimize_featured_image($post_id) {
        $thumbnail_id = get_post_thumbnail_id($post_id);
        
        if (!$thumbnail_id) {
            return false;
        }
        
        $product_title = get_the_title($post_id);
        if (empty($product_title)) {
            return false;
        }
        
        $updated = false;
        
        // Get current image data
        $image_post = get_post($thumbnail_id);
        
        // Update image title if empty
        if (empty($image_post->post_title)) {
            wp_update_post(array(
                'ID' => $thumbnail_id,
                'post_title' => $product_title
            ));
            $updated = true;
        }
        
        // Update image description if empty
        if (empty($image_post->post_content)) {
            wp_update_post(array(
                'ID' => $thumbnail_id,
                'post_content' => $product_title
            ));
            $updated = true;
        }
        
        // Update image caption if empty
        if (empty($image_post->post_excerpt)) {
            wp_update_post(array(
                'ID' => $thumbnail_id,
                'post_excerpt' => $product_title
            ));
            $updated = true;
        }
        
        // Update alt text if empty
        $current_alt = get_post_meta($thumbnail_id, '_wp_attachment_image_alt', true);
        if (empty($current_alt)) {
            update_post_meta($thumbnail_id, '_wp_attachment_image_alt', $product_title);
            $updated = true;
        }
        
        return $updated;
    }
    
    /**
     * Apply internal linking to product description
     */
    private function apply_internal_linking($post_id) {
        $post = get_post($post_id);
        $content = $post->post_content;
        
        if (empty($content)) {
            return false;
        }
        
        $internal_links = $this->get_setting('internal_links');
        if (empty($internal_links) || !is_array($internal_links)) {
            return false;
        }
        
        $updated_content = $content;
        
        foreach ($internal_links as $keyword => $url) {
            if (empty($keyword) || empty($url)) {
                continue;
            }
            
            // Create the link HTML
            $link_html = '<a href="' . esc_url($url) . '">' . esc_html($keyword) . '</a>';
            
            // Replace first occurrence only using preg_replace with limit
            $pattern = '/\b' . preg_quote($keyword, '/') . '\b/u';
            $updated_content = preg_replace($pattern, $link_html, $updated_content, 1);
        }
        
        // Update content if it was modified
        if ($updated_content !== $content) {
            wp_update_post(array(
                'ID' => $post_id,
                'post_content' => $updated_content
            ));
            return true;
        }
        
        return false;
    }
    
    /**
     * Fix canonical URL for products
     */
    public function fix_canonical_url($canonical_url) {
        if (is_product()) {
            global $post;
            if ($post && $post->post_type === 'product') {
                return get_permalink($post->ID);
            }
        }
        
        return $canonical_url;
    }
    
    /**
     * Check if a feature is enabled
     */
    private function is_feature_enabled($feature) {
        return isset($this->settings[$feature]) && $this->settings[$feature];
    }
    
    /**
     * Get a setting value
     */
    private function get_setting($key) {
        return isset($this->settings[$key]) ? $this->settings[$key] : '';
    }
}
