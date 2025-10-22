<?php
/**
 * Admin Settings Class
 * 
 * Handles the admin interface and settings page for Salah SEO plugin
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class Salah_SEO_Admin {
    
    /**
     * Settings option name
     */
    private $option_name = 'salah_seo_settings';
    
    /**
     * Constructor
     */
    public function __construct() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'register_settings'));
        add_action('admin_notices', array($this, 'show_notices'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));

        // Add metabox for individual product optimization
        add_action('add_meta_boxes', array($this, 'add_product_metabox'));

        // AJAX handlers
        add_action('wp_ajax_salah_seo_optimize_product', array($this, 'ajax_optimize_product'));
        add_action('wp_ajax_salah_seo_bulk_start', array($this, 'ajax_bulk_start'));
        add_action('wp_ajax_salah_seo_bulk_process', array($this, 'ajax_bulk_process'));
        add_action('wp_ajax_salah_seo_links_prepare', array($this, 'ajax_links_prepare'));
        add_action('wp_ajax_salah_seo_links_process', array($this, 'ajax_links_process'));
    }
    
    /**
     * Add admin menu page
     */
    public function add_admin_menu() {
        add_options_page(
            __('NicotineKW SEO Settings', 'salah-seo'),
            __('NicotineKW SEO', 'salah-seo'),
            'manage_options',
            'salah-seo-settings',
            array($this, 'settings_page')
        );
    }
    
    /**
     * Register plugin settings
     */
    public function register_settings() {
        register_setting(
            'salah_seo_settings_group',
            $this->option_name,
            array($this, 'sanitize_settings')
        );
    }
    
    /**
     * Sanitize settings input
     */
    public function sanitize_settings($input) {
        $sanitized = array();

        $checkboxes = array(
            'enable_focus_keyword',
            'enable_meta_description',
            'enable_short_description',
            'enable_product_tags',
            'enable_image_optimization',
            'enable_internal_linking',
            'enable_canonical_fix'
        );

        foreach ($checkboxes as $checkbox) {
            $sanitized[$checkbox] = !empty($input[$checkbox]);
        }

        $sanitized['default_meta_description'] = isset($input['default_meta_description']) ? sanitize_textarea_field($input['default_meta_description']) : '';
        $sanitized['default_short_description'] = isset($input['default_short_description']) ? sanitize_textarea_field($input['default_short_description']) : '';
        $sanitized['default_full_description'] = isset($input['default_full_description']) ? sanitize_textarea_field($input['default_full_description']) : '';

        $sanitized['internal_link_rules'] = array();
        if (!empty($input['internal_link_rules']) && is_array($input['internal_link_rules'])) {
            foreach ($input['internal_link_rules'] as $rule) {
                if (!is_array($rule)) {
                    continue;
                }

                $keyword = isset($rule['keyword']) ? sanitize_text_field($rule['keyword']) : '';
                $url = isset($rule['url']) ? Salah_SEO_Helpers::validate_url($rule['url']) : false;
                $repeats = isset($rule['repeats']) ? max(1, intval($rule['repeats'])) : 1;

                if ($keyword && $url) {
                    $sanitized['internal_link_rules'][] = array(
                        'keyword' => $keyword,
                        'url' => $url,
                        'repeats' => $repeats,
                    );
                }
            }
        }

        return $sanitized;
    }
    
    /**
     * Settings page callback
     */
    public function settings_page() {
        $settings = Salah_SEO_Helpers::get_plugin_settings();
        $link_rules = isset($settings['internal_link_rules']) ? $settings['internal_link_rules'] : array();
        $compatibility = Salah_SEO_Helpers::check_plugin_compatibility();

        include SALAH_SEO_PLUGIN_DIR . 'admin/views/dashboard.php';
    }
    
    /**
     * General section callback
     */
    public function general_section_callback() {
        echo '<p>' . __('Enable or disable specific SEO automation features.', 'salah-seo') . '</p>';
    }
    
    /**
     * Texts section callback
     */
    public function texts_section_callback() {
        echo '<p>' . __('Customize the default texts used for auto-filling SEO fields.', 'salah-seo') . '</p>';
    }
    
    /**
     * Links section callback
     */
    public function links_section_callback() {
        echo '<p>' . __('Manage keywords and their corresponding URLs for internal linking.', 'salah-seo') . '</p>';
    }
    
    /**
     * Bulk section callback
     */
    public function bulk_section_callback() {
        echo '<p>' . __('قم بتشغيل تحسينات SEO على جميع المنتجات الموجودة. ستتم معالجة الحقول الفارغة فقط.', 'salah-seo') . '</p>';
    }
    
    /**
     * Checkbox field callback
     */
    public function checkbox_field_callback($args) {
        $options = get_option($this->option_name);
        $field = $args['field'];
        $value = isset($options[$field]) ? $options[$field] : false;
        
        echo '<input type="checkbox" id="' . $field . '" name="' . $this->option_name . '[' . $field . ']" value="1" ' . checked(1, $value, false) . ' />';
        echo '<label for="' . $field . '">' . __('Enable this feature', 'salah-seo') . '</label>';
    }
    
    /**
     * Textarea field callback
     */
    public function textarea_field_callback($args) {
        $options = get_option($this->option_name);
        $field = $args['field'];
        $value = isset($options[$field]) ? $options[$field] : '';
        
        echo '<textarea id="' . $field . '" name="' . $this->option_name . '[' . $field . ']" rows="4" cols="50" class="large-text">' . esc_textarea($value) . '</textarea>';
    }
    
    /**
     * Internal links field callback
     */
    public function internal_links_field_callback($args) {
        $options = get_option($this->option_name);
        $field = $args['field'];
        $links = isset($options[$field]) ? $options[$field] : array();
        
        echo '<div id="internal-links-container">';
        
        if (!empty($links)) {
            foreach ($links as $keyword => $url) {
                echo '<div class="internal-link-row">';
                echo '<input type="text" name="' . $this->option_name . '[' . $field . '][' . esc_attr($keyword) . ']" value="' . esc_attr($url) . '" placeholder="' . __('URL', 'salah-seo') . '" class="regular-text" />';
                echo '<input type="text" value="' . esc_attr($keyword) . '" placeholder="' . __('Keyword', 'salah-seo') . '" class="regular-text keyword-field" readonly />';
                echo '<button type="button" class="button remove-link">' . __('Remove', 'salah-seo') . '</button>';
                echo '</div>';
            }
        }
        
        echo '</div>';
        echo '<button type="button" id="add-internal-link" class="button">' . __('Add New Link', 'salah-seo') . '</button>';
    }
    
    /**
     * Bulk operations field callback
     */
    public function bulk_operations_field_callback() {
        $product_count = wp_count_posts('product');
        $total_products = $product_count->publish + $product_count->draft + $product_count->private;
        
        ?>
        <input type="hidden" id="salah_seo_bulk_nonce" value="<?php echo wp_create_nonce('salah_seo_bulk_nonce'); ?>" />
        <div id="salah-seo-bulk-operations">
            <div class="bulk-info" style="background: #f9f9f9; padding: 15px; border: 1px solid #ddd; border-radius: 4px; margin-bottom: 15px;">
                <h4 style="margin-top: 0;"><?php _e('معلومات المخزون', 'salah-seo'); ?></h4>
                <p><strong><?php _e('إجمالي المنتجات:', 'salah-seo'); ?></strong> <?php echo number_format($total_products); ?></p>
                <p><strong><?php _e('المنتجات المنشورة:', 'salah-seo'); ?></strong> <?php echo number_format($product_count->publish); ?></p>
                <p style="color: #666; font-size: 13px;"><?php _e('ملاحظة: ستتم معالجة المنتجات المنشورة فقط، وسيتم تحديث الحقول الفارغة فقط.', 'salah-seo'); ?></p>
            </div>
            
            <div class="bulk-controls">
                <button type="button" id="salah-seo-bulk-start" class="button button-primary button-large" style="margin-bottom: 15px;">
                    <span class="dashicons dashicons-performance" style="margin-right: 5px;"></span>
                    <?php _e('بدء التحسين الجماعي', 'salah-seo'); ?>
                </button>
                
                <button type="button" id="salah-seo-bulk-stop" class="button button-secondary" style="display: none; margin-left: 10px;">
                    <span class="dashicons dashicons-no" style="margin-right: 5px;"></span>
                    <?php _e('إيقاف العملية', 'salah-seo'); ?>
                </button>
            </div>
            
            <div id="salah-seo-bulk-progress" style="display: none;">
                <div class="progress-container" style="background: #f0f0f0; border-radius: 5px; overflow: hidden; margin: 15px 0;">
                    <div class="progress-bar" style="background: linear-gradient(90deg, #0073aa, #005a87); height: 25px; width: 0%; transition: width 0.5s; display: flex; align-items: center; justify-content: center; color: white; font-weight: bold; font-size: 12px;">0%</div>
                </div>
                
                <div class="progress-stats" style="display: flex; justify-content: space-between; margin: 10px 0; font-size: 13px;">
                    <span id="progress-current">0</span>
                    <span id="progress-status"><?php _e('جاري التحضير...', 'salah-seo'); ?></span>
                    <span id="progress-total"><?php echo $product_count->publish; ?></span>
                </div>
                
                <div class="progress-details" style="background: #fff; border: 1px solid #ddd; border-radius: 4px; padding: 10px; max-height: 200px; overflow-y: auto;">
                    <div id="progress-log"></div>
                </div>
            </div>
            
            <div id="salah-seo-bulk-results" style="display: none; margin-top: 15px;">
                <h4><?php _e('نتائج العملية', 'salah-seo'); ?></h4>
                <div id="results-summary" style="background: #f9f9f9; padding: 15px; border-radius: 4px;"></div>
            </div>
        </div>
        <?php
    }
    
    /**
     * Show admin notices
     */
    public function show_notices() {
        // Activation notice
        if (get_transient('salah_seo_activation_notice')) {
            echo '<div class="notice notice-success is-dismissible">';
            echo '<p><strong>' . __('Salah SEO', 'salah-seo') . ':</strong> ' . __('Plugin activated successfully! Go to Settings > NicotineKW SEO to configure.', 'salah-seo') . '</p>';
            echo '</div>';
            delete_transient('salah_seo_activation_notice');
        }
        
        // Success notice after SEO optimization
        if (get_transient('salah_seo_success_notice')) {
            echo '<div class="notice notice-success is-dismissible">';
            echo '<p><strong>' . __('Salah SEO', 'salah-seo') . ':</strong> ' . __('SEO optimizations applied successfully!', 'salah-seo') . '</p>';
            echo '</div>';
            delete_transient('salah_seo_success_notice');
        }
    }
    
    /**
     * Add metabox to product edit page
     */
    public function add_product_metabox() {
        add_meta_box(
            'salah-seo-optimize',
            __('Salah SEO - تحسين فوري', 'salah-seo'),
            array($this, 'product_metabox_callback'),
            'product',
            'side',
            'high'
        );
    }
    
    /**
     * Product metabox callback
     */
    public function product_metabox_callback($post) {
        wp_nonce_field('salah_seo_optimize_product', 'salah_seo_nonce');
        ?>
        <div id="salah-seo-product-optimizer">
            <p><?php _e('قم بتشغيل تحسين SEO لهذا المنتج فوراً:', 'salah-seo'); ?></p>
            
            <button type="button" id="salah-seo-optimize-btn" class="button button-primary" style="width: 100%; margin-bottom: 10px;">
                <span class="dashicons dashicons-performance" style="margin-right: 5px;"></span>
                <?php _e('تشغيل التحسين الآن', 'salah-seo'); ?>
            </button>
            
            <div id="salah-seo-progress" style="display: none; margin: 10px 0;">
                <div class="progress-bar" style="background: #f0f0f0; border-radius: 3px; overflow: hidden;">
                    <div class="progress-fill" style="background: #0073aa; height: 20px; width: 0%; transition: width 0.3s;"></div>
                </div>
                <p class="progress-text" style="margin: 5px 0; font-size: 12px; color: #666;">جاري التحسين...</p>
            </div>
            
            <div id="salah-seo-result" style="display: none;"></div>
            
            <div id="salah-seo-status" style="margin-top: 10px; font-size: 12px; color: #666;">
                <p><?php _e('آخر تحسين: لم يتم بعد', 'salah-seo'); ?></p>
            </div>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            $('#salah-seo-optimize-btn').on('click', function() {
                var btn = $(this);
                var progress = $('#salah-seo-progress');
                var result = $('#salah-seo-result');
                var progressFill = $('.progress-fill');
                var progressText = $('.progress-text');
                
                btn.prop('disabled', true).html('<span class="dashicons dashicons-update-alt" style="animation: spin 1s linear infinite; margin-right: 5px;"></span>جاري التحسين...');
                progress.show();
                result.hide();
                
                // Animate progress
                progressFill.css('width', '30%');
                progressText.text('بدء التحسين...');
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'salah_seo_optimize_product',
                        post_id: <?php echo $post->ID; ?>,
                        nonce: $('#salah_seo_nonce').val()
                    },
                    success: function(response) {
                        progressFill.css('width', '100%');
                        progressText.text('اكتمل التحسين!');
                        
                        setTimeout(function() {
                            progress.hide();
                            
                            if (response.success) {
                                result.html('<div class="notice notice-success inline"><p>' + response.data.message + '</p></div>').show();
                                $('#salah-seo-status p').text('آخر تحسين: ' + new Date().toLocaleString('ar'));
                            } else {
                                result.html('<div class="notice notice-error inline"><p>' + response.data.message + '</p></div>').show();
                            }
                            
                            btn.prop('disabled', false).html('<span class="dashicons dashicons-performance" style="margin-right: 5px;"></span>تشغيل التحسين الآن');
                        }, 1000);
                    },
                    error: function() {
                        progress.hide();
                        result.html('<div class="notice notice-error inline"><p>حدث خطأ أثناء التحسين</p></div>').show();
                        btn.prop('disabled', false).html('<span class="dashicons dashicons-performance" style="margin-right: 5px;"></span>تشغيل التحسين الآن');
                    }
                });
            });
        });
        </script>
        
        <style>
        @keyframes spin {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }
        </style>
        <?php
    }
    
    /**
     * AJAX handler for bulk operations start
     */
    public function ajax_bulk_start() {
        // Security check
        if (!wp_verify_nonce($_POST['nonce'], 'salah_seo_bulk_nonce')) {
            wp_die(__('Security check failed', 'salah-seo'));
        }
        
        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions', 'salah-seo'));
        }
        
        // Get all published products
        $products = get_posts(array(
            'post_type' => 'product',
            'post_status' => 'publish',
            'numberposts' => -1,
            'fields' => 'ids'
        ));
        
        // Store product IDs in transient for processing
        set_transient('salah_seo_bulk_products', $products, HOUR_IN_SECONDS);
        set_transient('salah_seo_bulk_progress', array(
            'total' => count($products),
            'processed' => 0,
            'optimized' => 0,
            'skipped' => 0,
            'errors' => 0,
            'status' => 'running'
        ), HOUR_IN_SECONDS);
        
        wp_send_json_success(array(
            'total' => count($products),
            'message' => sprintf(__('تم العثور على %d منتج للمعالجة', 'salah-seo'), count($products))
        ));
    }
    
    /**
     * AJAX handler for bulk operations processing
     */
    public function ajax_bulk_process() {
        // Security check
        if (!wp_verify_nonce($_POST['nonce'], 'salah_seo_bulk_nonce')) {
            wp_die(__('Security check failed', 'salah-seo'));
        }
        
        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions', 'salah-seo'));
        }
        
        $batch_size = 3; // Process 3 products per batch (reduced for stability)
        $products = get_transient('salah_seo_bulk_products');
        $progress = get_transient('salah_seo_bulk_progress');
        
        if (!$products || !$progress) {
            wp_send_json_error(array('message' => __('انتهت صلاحية العملية', 'salah-seo')));
        }
        
        if ($progress['status'] !== 'running') {
            wp_send_json_error(array('message' => __('تم إيقاف العملية', 'salah-seo')));
        }
        
        $start_index = $progress['processed'];
        $batch_products = array_slice($products, $start_index, $batch_size);
        
        $batch_results = array();
        $optimized_count = 0;
        $skipped_count = 0;
        $error_count = 0;
        
        if (class_exists('Salah_SEO_Core')) {
            $core = new Salah_SEO_Core();
            
            foreach ($batch_products as $product_id) {
                $post = get_post($product_id);
                if (!$post) {
                    $error_count++;
                    $batch_results[] = array(
                        'id' => $product_id,
                        'title' => 'منتج غير موجود',
                        'status' => 'error',
                        'message' => 'المنتج غير موجود'
                    );
                    continue;
                }
                
                // Clear any existing transients
                delete_transient('salah_seo_success_notice');
                delete_transient('salah_seo_optimization_details');
                
                // Run optimizations
                ob_start();
                $core->run_optimizations($product_id, $post);
                ob_end_clean();
                
                // Check if optimizations were applied
                if (get_transient('salah_seo_success_notice')) {
                    delete_transient('salah_seo_success_notice');
                    
                    // Get optimization details
                    $optimization_details = get_transient('salah_seo_optimization_details');
                    $applied_optimizations = isset($optimization_details[$product_id]) ? $optimization_details[$product_id] : array();
                    delete_transient('salah_seo_optimization_details');
                    
                    $optimized_count++;
                    $details_text = !empty($applied_optimizations) ? implode(', ', $applied_optimizations) : 'تحسينات عامة';
                    
                    $batch_results[] = array(
                        'id' => $product_id,
                        'title' => get_the_title($product_id),
                        'status' => 'optimized',
                        'message' => 'تم التحسين',
                        'details' => $details_text
                    );
                } else {
                    $skipped_count++;
                    $batch_results[] = array(
                        'id' => $product_id,
                        'title' => get_the_title($product_id),
                        'status' => 'skipped',
                        'message' => 'لا يحتاج تحسين',
                        'details' => 'جميع الحقول محدثة بالفعل'
                    );
                }
            }
        } else {
            wp_send_json_error(array('message' => __('خطأ في تحميل نواة الإضافة', 'salah-seo')));
        }
        
        // Update progress
        $progress['processed'] += count($batch_products);
        $progress['optimized'] += $optimized_count;
        $progress['skipped'] += $skipped_count;
        $progress['errors'] += $error_count;
        
        $is_complete = $progress['processed'] >= $progress['total'];
        if ($is_complete) {
            $progress['status'] = 'completed';
        }
        
        set_transient('salah_seo_bulk_progress', $progress, HOUR_IN_SECONDS);
        
        wp_send_json_success(array(
            'progress' => $progress,
            'batch_results' => $batch_results,
            'is_complete' => $is_complete,
            'percentage' => round(($progress['processed'] / $progress['total']) * 100, 1)
        ));
    }
    
    /**
     * AJAX handler for single product optimization
     */
    public function ajax_optimize_product() {
        // Security check
        if (!wp_verify_nonce($_POST['nonce'], 'salah_seo_optimize_product')) {
            wp_die(__('Security check failed', 'salah-seo'));
        }
        
        if (!current_user_can('edit_posts')) {
            wp_die(__('Insufficient permissions', 'salah-seo'));
        }
        
        $post_id = intval($_POST['post_id']);
        $post = get_post($post_id);
        
        if (!$post || $post->post_type !== 'product') {
            wp_send_json_error(array('message' => __('منتج غير صالح', 'salah-seo')));
        }
        
        // Get core instance and run optimizations
        if (class_exists('Salah_SEO_Core')) {
            $core = new Salah_SEO_Core();
            
            // Temporarily capture optimization results
            ob_start();
            $core->run_optimizations($post_id, $post);
            ob_end_clean();
            
            // Check if optimizations were applied by looking for the transient
            if (get_transient('salah_seo_success_notice')) {
                delete_transient('salah_seo_success_notice'); // Clean up
                wp_send_json_success(array(
                    'message' => __('تم تطبيق تحسينات SEO بنجاح! تم تحديث الحقول الفارغة فقط.', 'salah-seo')
                ));
            } else {
                wp_send_json_success(array(
                    'message' => __('لا توجد حقول فارغة للتحسين. جميع بيانات SEO محدثة بالفعل.', 'salah-seo')
                ));
            }
        } else {
            wp_send_json_error(array('message' => __('خطأ في تحميل نواة الإضافة', 'salah-seo')));
        }
    }

    /**
     * Prepare queue for internal link operations
     */
    public function ajax_links_prepare() {
        check_ajax_referer('salah_seo_links_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('صلاحيات غير كافية لتنفيذ العملية', 'salah-seo')));
        }

        $action = isset($_POST['link_action']) ? sanitize_text_field($_POST['link_action']) : '';
        if (!in_array($action, array('apply', 'remove'), true)) {
            wp_send_json_error(array('message' => __('عملية غير معروفة', 'salah-seo')));
        }

        if ('apply' === $action) {
            $settings = Salah_SEO_Helpers::get_plugin_settings();
            if (empty($settings['internal_link_rules'])) {
                wp_send_json_error(array('message' => __('لا توجد قواعد للربط الداخلي. يرجى إضافة القواعد أولاً.', 'salah-seo')));
            }
        }

        $post_types = array('post', 'product');
        $query = new WP_Query(array(
            'post_type' => $post_types,
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'fields' => 'ids',
        ));

        $post_ids = $query->posts;

        $queue = array(
            'action' => $action,
            'items' => $post_ids,
            'total' => count($post_ids),
        );

        set_transient('salah_seo_links_queue', $queue, HOUR_IN_SECONDS);

        wp_send_json_success(array(
            'total_items' => $queue['total'],
        ));
    }

    /**
     * Process queued internal link operations in batches
     */
    public function ajax_links_process() {
        check_ajax_referer('salah_seo_links_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('صلاحيات غير كافية لتنفيذ العملية', 'salah-seo')));
        }

        $queue = get_transient('salah_seo_links_queue');

        if (false === $queue || empty($queue['items'])) {
            wp_send_json_error(array('message' => __('لا توجد عناصر جاهزة للمعالجة. يرجى البدء من جديد.', 'salah-seo')));
        }

        $action = isset($queue['action']) ? $queue['action'] : 'apply';
        $batch_size = apply_filters('salah_seo_links_batch_size', 5);
        $items = isset($queue['items']) ? $queue['items'] : array();
        $batch_ids = array_splice($items, 0, $batch_size);

        if (empty($batch_ids)) {
            delete_transient('salah_seo_links_queue');
            wp_send_json_success(array(
                'done' => true,
                'processed_count' => 0,
                'batch_total' => 0,
                'remaining' => 0,
                'message' => __('لا توجد عناصر متبقية.', 'salah-seo'),
                'total' => isset($queue['total']) ? (int) $queue['total'] : 0,
            ));
        }

        $messages = array();
        $processed_count = 0;
        $batch_total = count($batch_ids);
        $settings = Salah_SEO_Helpers::get_plugin_settings();
        $rules = isset($settings['internal_link_rules']) ? $settings['internal_link_rules'] : array();

        foreach ($batch_ids as $post_id) {
            $post = get_post($post_id);
            if (!$post || empty($post->post_content)) {
                continue;
            }

            $original_content = $post->post_content;
            $updated_content = $original_content;

            if ('apply' === $action) {
                $updated_content = Salah_SEO_Helpers::apply_internal_links_to_content($original_content, $rules);
            } else {
                $updated_content = Salah_SEO_Helpers::remove_internal_links_from_content($original_content);
            }

            if ($updated_content !== $original_content) {
                wp_update_post(array(
                    'ID' => $post_id,
                    'post_content' => $updated_content,
                ));
                $processed_count++;
                $messages[] = sprintf(
                    '%s: %s',
                    esc_html(get_the_title($post_id)),
                    'apply' === $action ? __('تمت إضافة الروابط', 'salah-seo') : __('تمت إزالة الروابط', 'salah-seo')
                );
            }
        }

        $queue['items'] = $items;

        if (empty($queue['items'])) {
            delete_transient('salah_seo_links_queue');
        } else {
            set_transient('salah_seo_links_queue', $queue, HOUR_IN_SECONDS);
        }

        $remaining = isset($queue['items']) ? count($queue['items']) : 0;
        $total = isset($queue['total']) ? (int) $queue['total'] : $processed_count;

        wp_send_json_success(array(
            'done' => $remaining === 0,
            'processed_count' => $processed_count,
            'batch_total' => $batch_total,
            'remaining' => $remaining,
            'total' => $total,
            'message' => implode("\n", $messages),
        ));
    }

    /**
     * Enqueue admin scripts and styles
     */
    public function enqueue_admin_scripts($hook) {
        // Enqueue on settings page
        if ('settings_page_salah-seo-settings' === $hook) {
            wp_enqueue_script('jquery');
            wp_enqueue_script(
                'salah-seo-tailwind',
                'https://cdn.tailwindcss.com',
                array(),
                SALAH_SEO_VERSION,
                true
            );
            wp_enqueue_script(
                'salah-seo-admin',
                SALAH_SEO_PLUGIN_URL . 'admin/js/admin.js',
                array('jquery'),
                SALAH_SEO_VERSION,
                true
            );

            wp_enqueue_style(
                'salah-seo-admin',
                SALAH_SEO_PLUGIN_URL . 'admin/css/admin.css',
                array(),
                SALAH_SEO_VERSION
            );

            $settings = Salah_SEO_Helpers::get_plugin_settings();

            wp_localize_script('salah-seo-admin', 'salahSeoAjax', array(
                'ajaxurl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('salah_seo_bulk_nonce'),
                'linksNonce' => wp_create_nonce('salah_seo_links_nonce'),
                'strings' => array(
                    'starting' => __('بدء العملية...', 'salah-seo'),
                    'processing' => __('جاري المعالجة...', 'salah-seo'),
                    'completed' => __('اكتملت العملية!', 'salah-seo'),
                    'error' => __('حدث خطأ', 'salah-seo')
                ),
                'linksStrings' => array(
                    'preparing' => __('جاري تجهيز العناصر...', 'salah-seo'),
                    'applying' => __('يتم تطبيق الروابط الداخلية', 'salah-seo'),
                    'removing' => __('يتم إزالة الروابط الداخلية', 'salah-seo'),
                    'completed' => __('اكتملت العملية بنجاح', 'salah-seo'),
                    'stopped' => __('تم إيقاف العملية', 'salah-seo')
                ),
                'linkRules' => $settings['internal_link_rules'],
                'totalProducts' => wp_count_posts('product')->publish,
            ));

            wp_localize_script('salah-seo-admin', 'salahSeoLabels', array(
                'keyword' => __('الكلمة المستهدفة', 'salah-seo'),
                'url' => __('الرابط الداخلي', 'salah-seo'),
                'repeats' => __('أقصى عدد للتكرار', 'salah-seo'),
                'delete' => __('حذف القاعدة', 'salah-seo'),
                'emptyState' => __('لم يتم إضافة أي قواعد بعد. اضغط على زر "إضافة قاعدة جديدة" للبدء.', 'salah-seo'),
                'validationError' => __('يرجى التأكد من إدخال كلمة مفتاحية ورابط صحيح لكل قاعدة.', 'salah-seo'),
                'unsaved' => __('تغييرات غير محفوظة', 'salah-seo'),
                'unsavedWarning' => __('لديك تغييرات غير محفوظة. هل أنت متأكد أنك تريد المغادرة؟', 'salah-seo'),
                'bulkStart' => __('بدء التحسين الجماعي', 'salah-seo'),
                'stoppedByUser' => __('تم إيقاف العملية بواسطة المستخدم', 'salah-seo'),
                'processingProduct' => __('معالجة المنتج %1$s من %2$s', 'salah-seo'),
                'totalProducts' => __('إجمالي المنتجات', 'salah-seo'),
                'optimized' => __('تم تحسينها', 'salah-seo'),
                'skipped' => __('تم تجاهلها', 'salah-seo'),
                'errors' => __('أخطاء', 'salah-seo'),
                'confirmRemove' => __('تحذير: سيؤدي ذلك إلى إزالة كل الروابط الداخلية من المحتوى. هل تريد المتابعة؟', 'salah-seo'),
                'noItems' => __('لا توجد عناصر لمعالجتها.', 'salah-seo'),
                'optionPrefix' => $this->option_name,
            ));
        }
        
        // Enqueue on product edit page
        if ('post.php' === $hook || 'post-new.php' === $hook) {
            global $post_type;
            if ('product' === $post_type) {
                wp_enqueue_script('jquery');
            }
        }
    }
}
