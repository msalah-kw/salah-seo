<?php
/**
 * Core SEO Optimization Class
 */

if (!defined('ABSPATH')) {
    exit;
}

class Salah_SEO_Core {

    /**
     * Singleton instance.
     *
     * @var self|null
     */
    private static $instance = null;

    /**
     * Settings option name.
     *
     * @var string
     */
    private $option_name = 'salah_seo_settings';

    /**
     * Cached plugin settings.
     *
     * @var array
     */
    private $settings = array();

    /**
     * Redirect storage option.
     *
     * @var string
     */
    private $redirect_option = 'salah_seo_redirects';

    /**
     * Cached schema payload for fallback printing.
     *
     * @var array
     */
    private $schema_payload = array();

    /**
     * Whether schema has been injected via Rank Math.
     *
     * @var bool
     */
    private $schema_injected = false;

    /**
     * Retrieve singleton instance.
     *
     * @return self
     */
    public static function instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * Constructor.
     */
    private function __construct() {
        $this->settings = Salah_SEO_Helpers::get_plugin_settings();
        $this->init_hooks();
    }

    /**
     * Refresh settings cache when options change.
     *
     * @param mixed $old_value Previous value.
     * @param mixed $value     New value.
     * @return void
     */
    public function on_settings_updated($old_value, $value, $option) {
        unset($old_value);
        unset($value);
        unset($option);
        $this->settings = Salah_SEO_Helpers::get_plugin_settings();
    }

    /**
     * Initialize WordPress hooks.
     *
     * @return void
     */
    private function init_hooks() {
        add_action('save_post_product', array($this, 'run_optimizations'), 10, 2);
        add_action('post_updated', array($this, 'maybe_track_slug_change'), 10, 3);
        add_action('template_redirect', array($this, 'maybe_handle_redirect'));
        add_action('update_option_' . $this->option_name, array($this, 'on_settings_updated'), 10, 3);

        if ($this->is_feature_enabled('enable_canonical_fix')) {
            add_filter('rank_math/frontend/canonical', array($this, 'fix_canonical_url'), 10, 2);
        }

        if ($this->is_feature_enabled('enable_schema_markup')) {
            add_filter('rank_math/json_ld', array($this, 'maybe_extend_rankmath_schema'), 99, 2);
            add_action('wp_head', array($this, 'maybe_print_schema_fallback'), 91);
        }

        if ($this->is_feature_enabled('enable_social_meta')) {
            add_filter('rank_math/opengraph/facebook/title', array($this, 'maybe_filter_og_title'), 10, 2);
            add_filter('rank_math/opengraph/facebook/description', array($this, 'maybe_filter_og_description'), 10, 2);
            add_filter('rank_math/opengraph/facebook/image', array($this, 'maybe_filter_og_image'), 10, 2);
            add_filter('rank_math/opengraph/twitter/title', array($this, 'maybe_filter_og_title'), 10, 2);
            add_filter('rank_math/opengraph/twitter/description', array($this, 'maybe_filter_og_description'), 10, 2);
            add_filter('rank_math/opengraph/twitter/image', array($this, 'maybe_filter_og_image'), 10, 2);
            add_action('wp_head', array($this, 'maybe_output_social_meta_fallback'), 5);
        }
    }

    /**
     * Entry point when a product is saved.
     *
     * @param int     $post_id Post ID.
     * @param WP_Post $post    Post object.
     * @return void
     */
    public function run_optimizations($post_id, $post) {
        if (wp_is_post_autosave($post_id) || wp_is_post_revision($post_id)) {
            return;
        }

        if (!isset($post->post_type) || 'product' !== $post->post_type) {
            return;
        }

        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        if (!empty($this->settings['background_processing'])) {
            Salah_SEO_Scheduler::enqueue($post_id, array('source' => 'auto-save'));
            return;
        }

        $this->optimize_post($post_id, array('source' => 'save_post'));
    }

    /**
     * Optimize a specific post or product.
     *
     * @param int   $post_id Post ID.
     * @param array $args    Additional arguments.
     * @return array Result payload describing modifications.
     */
    public function optimize_post($post_id, $args = array()) {
        $defaults = array(
            'dry_run' => false,
            'source' => 'manual',
        );
        $args = wp_parse_args($args, $defaults);
        $dry_run = (bool) $args['dry_run'];

        $post = get_post($post_id);
        if (!$post || 'trash' === $post->post_status) {
            return array(
                'post_id' => $post_id,
                'dry_run' => $dry_run,
                'changes' => array(),
                'skipped' => array(__('Invalid post or product.', 'salah-seo')),
            );
        }

        $this->settings = Salah_SEO_Helpers::get_plugin_settings();

        $result = array(
            'post_id' => $post_id,
            'dry_run' => $dry_run,
            'changes' => array(),
            'skipped' => array(),
        );

        if ('product' === $post->post_type) {
            $product = wc_get_product($post_id);
            if (!$product) {
                $result['skipped'][] = __('Unable to load WooCommerce product.', 'salah-seo');
                return $result;
            }

            $this->handle_product_optimizations($product, $result, $dry_run);
            $this->maybe_populate_brand_metadata($product, $result, $dry_run);
        } else {
            $result['skipped'][] = __('Post type not handled by Salah SEO.', 'salah-seo');
        }

        if (!$dry_run && !empty($result['changes'])) {
            $labels = array();
            foreach ($result['changes'] as $change) {
                if (!empty($change['label'])) {
                    $labels[] = $change['label'];
                }
            }

            if (!empty($labels)) {
                $optimization_details = get_transient('salah_seo_optimization_details');
                if (!is_array($optimization_details)) {
                    $optimization_details = array();
                }
                $optimization_details[$post_id] = $labels;
                set_transient('salah_seo_optimization_details', $optimization_details, 300);
                set_transient('salah_seo_success_notice', true, 30);
            }
        }

        return $result;
    }

    /**
     * Handle product-specific optimizations.
     *
     * @param WC_Product $product Product instance.
     * @param array      $result  Result payload by reference.
     * @param bool       $dry_run Whether to skip persistence.
     * @return void
     */
    private function handle_product_optimizations($product, array &$result, $dry_run) {
        $post_id = $product->get_id();

        if ($this->is_feature_enabled('enable_focus_keyword')) {
            $change = $this->populate_focus_keyword($post_id, $dry_run);
            $this->record_change($result, 'focus_keyword', __('الكلمة المفتاحية (Focus Keyword)', 'salah-seo'), $change);
        }

        if ($this->is_feature_enabled('enable_meta_description')) {
            $change = $this->populate_meta_description($post_id, $dry_run);
            $this->record_change($result, 'meta_description', __('الوصف التعريفي (Meta Description)', 'salah-seo'), $change);
        }

        if ($this->is_feature_enabled('enable_short_description')) {
            $change = $this->populate_short_description($post_id, $dry_run);
            $this->record_change($result, 'short_description', __('الوصف القصير (Short Description)', 'salah-seo'), $change);
        }

        $change = $this->populate_full_description($post_id, $dry_run);
        $this->record_change($result, 'full_description', __('الوصف الكامل (Full Description)', 'salah-seo'), $change);

        if ($this->is_feature_enabled('enable_product_tags')) {
            $change = $this->populate_product_tags($post_id, $dry_run);
            $this->record_change($result, 'product_tags', __('وسوم المنتج (Tags)', 'salah-seo'), $change);
        }

        if ($this->is_feature_enabled('enable_image_optimization')) {
            $change = $this->optimize_featured_image($post_id, $dry_run);
            $this->record_change($result, 'image_optimization', __('تحسين بيانات الصور', 'salah-seo'), $change);
        }

        if ($this->is_feature_enabled('enable_internal_linking')) {
            $change = $this->apply_internal_linking($post_id, $dry_run);
            $this->record_change($result, 'internal_links', __('الربط الداخلي', 'salah-seo'), $change);
        }
    }

    /**
     * Register a change into the result payload.
     *
     * @param array $result Result array by reference.
     * @param string $key   Change identifier.
     * @param string $label Human readable label.
     * @param array  $change Change response.
     * @return void
     */
    private function record_change(array &$result, $key, $label, $change) {
        if (empty($change['changed'])) {
            return;
        }

        $result['changes'][] = array(
            'key' => $key,
            'label' => $label,
            'value' => isset($change['value']) ? $change['value'] : null,
        );
    }

    /**
     * Populate focus keyword if empty.
     *
     * @param int  $post_id Post ID.
     * @param bool $dry_run Whether to skip updates.
     * @return array
     */
    private function populate_focus_keyword($post_id, $dry_run = false) {
        $current_keyword = get_post_meta($post_id, 'rank_math_focus_keyword', true);

        if (!empty($current_keyword) && !$this->is_placeholder_value($current_keyword)) {
            return array('changed' => false);
        }

        $new_keyword = get_the_title($post_id);
        if (empty($new_keyword)) {
            return array('changed' => false);
        }

        if (!$dry_run) {
            update_post_meta($post_id, 'rank_math_focus_keyword', $new_keyword);
        }

        return array(
            'changed' => true,
            'value' => $new_keyword,
        );
    }

    /**
     * Populate meta description if empty.
     *
     * @param int  $post_id Post ID.
     * @param bool $dry_run Whether to skip updates.
     * @return array
     */
    private function populate_meta_description($post_id, $dry_run = false) {
        $current_description = get_post_meta($post_id, 'rank_math_description', true);

        if (!empty($current_description) && !$this->is_placeholder_value($current_description)) {
            return array('changed' => false);
        }

        $default_text = $this->get_setting('default_meta_description');
        if (empty($default_text)) {
            return array('changed' => false);
        }

        if (!$dry_run) {
            update_post_meta($post_id, 'rank_math_description', $default_text);
        }

        return array(
            'changed' => true,
            'value' => $default_text,
        );
    }

    /**
     * Populate short description if empty.
     *
     * @param int  $post_id Post ID.
     * @param bool $dry_run Whether to skip updates.
     * @return array
     */
    private function populate_short_description($post_id, $dry_run = false) {
        $post = get_post($post_id);

        if (!empty($post->post_excerpt) && !$this->is_placeholder_value($post->post_excerpt)) {
            return array('changed' => false);
        }

        $default_text = $this->get_setting('default_short_description');
        if (empty($default_text)) {
            return array('changed' => false);
        }

        if (!$dry_run) {
            wp_update_post(
                array(
                    'ID' => $post_id,
                    'post_excerpt' => $default_text,
                )
            );
        }

        return array(
            'changed' => true,
            'value' => $default_text,
        );
    }

    /**
     * Populate full description if empty.
     *
     * @param int  $post_id Post ID.
     * @param bool $dry_run Whether to skip updates.
     * @return array
     */
    private function populate_full_description($post_id, $dry_run = false) {
        $post = get_post($post_id);

        if (!empty($post->post_content) && !$this->is_placeholder_value($post->post_content)) {
            return array('changed' => false);
        }

        $default_text = $this->get_setting('default_full_description');
        if (empty($default_text)) {
            return array('changed' => false);
        }

        if (!$dry_run) {
            wp_update_post(
                array(
                    'ID' => $post_id,
                    'post_content' => wp_kses_post($default_text),
                )
            );
        }

        return array(
            'changed' => true,
            'value' => $default_text,
        );
    }

    /**
     * Populate product tags if empty.
     *
     * @param int  $post_id Post ID.
     * @param bool $dry_run Whether to skip updates.
     * @return array
     */
    private function populate_product_tags($post_id, $dry_run = false) {
        $existing_terms = wp_get_post_terms($post_id, 'product_tag', array('fields' => 'names'));
        if (!empty($existing_terms)) {
            $filtered = array_filter($existing_terms, function ($term) {
                return !$this->is_placeholder_value($term);
            });
            if (!empty($filtered)) {
                return array('changed' => false);
            }
        }

        $new_tags = array();
        $product = wc_get_product($post_id);
        if ($product) {
            $new_tags[] = $product->get_name();
        }

        $category_string = Salah_SEO_Helpers::get_product_category_string($post_id);
        if (!empty($category_string)) {
            $new_tags = array_merge($new_tags, array_map('trim', explode(',', $category_string)));
        }

        $new_tags = array_filter(array_unique(array_map('sanitize_text_field', $new_tags)));

        if (empty($new_tags)) {
            return array('changed' => false);
        }

        if (!$dry_run) {
            wp_set_post_terms($post_id, $new_tags, 'product_tag', false);
        }

        return array(
            'changed' => true,
            'value' => $new_tags,
        );
    }

    /**
     * Optimize featured image metadata.
     *
     * @param int  $post_id Post ID.
     * @param bool $dry_run Whether to skip updates.
     * @return array
     */
    private function optimize_featured_image($post_id, $dry_run = false) {
        $thumbnail_id = get_post_thumbnail_id($post_id);
        if (!$thumbnail_id) {
            return array('changed' => false);
        }

        $product_title = get_the_title($post_id);
        $changed = false;

        $image_post = get_post($thumbnail_id);
        if (!$image_post) {
            return array('changed' => false);
        }

        $payload = array();

        if (empty($image_post->post_title) || $this->is_placeholder_value($image_post->post_title)) {
            $payload['post_title'] = $product_title;
        }

        if (empty($image_post->post_content) || $this->is_placeholder_value($image_post->post_content)) {
            $payload['post_content'] = $product_title;
        }

        if (empty($image_post->post_excerpt) || $this->is_placeholder_value($image_post->post_excerpt)) {
            $payload['post_excerpt'] = $product_title;
        }

        $current_alt = get_post_meta($thumbnail_id, '_wp_attachment_image_alt', true);
        if (empty($current_alt) || $this->is_placeholder_value($current_alt)) {
            if (!$dry_run) {
                update_post_meta($thumbnail_id, '_wp_attachment_image_alt', $product_title);
            }
            $changed = true;
        }

        if (!empty($payload)) {
            $payload['ID'] = $thumbnail_id;
            if (!$dry_run) {
                wp_update_post($payload);
            }
            $changed = true;
        }

        return array(
            'changed' => $changed,
            'value' => $changed ? $product_title : null,
        );
    }

    /**
     * Apply internal linking rules to product content.
     *
     * @param int  $post_id Post ID.
     * @param bool $dry_run Whether to skip updates.
     * @return array
     */
    private function apply_internal_linking($post_id, $dry_run = false) {
        $post = get_post($post_id);
        if (!$post || empty($post->post_content)) {
            return array('changed' => false);
        }

        $internal_link_rules = $this->get_setting('internal_link_rules');
        if (empty($internal_link_rules)) {
            return array('changed' => false);
        }

        $updated_content = Salah_SEO_Helpers::apply_internal_links_to_content($post->post_content, $internal_link_rules);
        if ($updated_content === $post->post_content) {
            return array('changed' => false);
        }

        if (!$dry_run) {
            wp_update_post(
                array(
                    'ID' => $post_id,
                    'post_content' => $updated_content,
                )
            );
        }

        return array(
            'changed' => true,
            'value' => $updated_content,
        );
    }

    /**
     * Generate brand and attribute descriptions when missing.
     *
     * @param WC_Product $product Product instance.
     * @param array      $result  Result payload.
     * @param bool       $dry_run Whether to skip persistence.
     * @return void
     */
    private function maybe_populate_brand_metadata($product, array &$result, $dry_run) {
        $post_id = $product->get_id();
        $changes = array();

        if (taxonomy_exists('product_brand')) {
            $brands = wp_get_post_terms($post_id, 'product_brand');
            foreach ($brands as $brand) {
                if (empty($brand->description)) {
                    $description = sprintf(
                        /* translators: %s brand name */
                        __('اكتشف أفضل منتجات %s المتوفرة لدينا مع شحن سريع وخدمة دعم متخصصة.', 'salah-seo'),
                        $brand->name
                    );

                    if ($dry_run) {
                        $changes[] = sprintf('%s → %s', $brand->name, $description);
                    } else {
                        $updated = wp_update_term($brand->term_id, 'product_brand', array('description' => wp_kses_post($description)));
                        if (!is_wp_error($updated)) {
                            $changes[] = sprintf('%s → %s', $brand->name, $description);
                        }
                    }
                }
            }
        }

        $attributes = $product->get_attributes();
        foreach ($attributes as $attribute) {
            if (!is_a($attribute, 'WC_Product_Attribute')) {
                continue;
            }

            if (!$attribute->is_taxonomy()) {
                continue;
            }

            $taxonomy = $attribute->get_taxonomy();
            $terms = wp_get_post_terms($post_id, $taxonomy);
            foreach ($terms as $term) {
                if (empty($term->description)) {
                    $description = sprintf(
                        /* translators: %s attribute name */
                        __('تعرف على تشكيلة %s المختارة بعناية لتوفير أفضل تجربة للمستخدم.', 'salah-seo'),
                        $term->name
                    );

                    if ($dry_run) {
                        $changes[] = sprintf('%s → %s', $term->name, $description);
                    } else {
                        $updated = wp_update_term($term->term_id, $taxonomy, array('description' => wp_kses_post($description)));
                        if (!is_wp_error($updated)) {
                            $changes[] = sprintf('%s → %s', $term->name, $description);
                        }
                    }
                }
            }
        }

        if (!empty($changes)) {
            $result['changes'][] = array(
                'key' => 'taxonomy_metadata',
                'label' => __('تحسين وصف العلامات التجارية والسمات', 'salah-seo'),
                'value' => $changes,
            );
        }
    }

    /**
     * Track slug changes to build redirect map.
     *
     * @param int      $post_id Post ID.
     * @param WP_Post  $post_after Post object after update.
     * @param WP_Post  $post_before Post object before update.
     * @return void
     */
    public function maybe_track_slug_change($post_id, $post_after, $post_before) {
        if (!$this->is_feature_enabled('enable_redirect_manager')) {
            return;
        }

        if (wp_is_post_revision($post_id) || $post_after->post_name === $post_before->post_name) {
            return;
        }

        $old_url = get_permalink($post_before);
        $new_url = get_permalink($post_after);

        if (!$old_url || !$new_url) {
            return;
        }

        $old_path = trailingslashit(wp_make_link_relative($old_url));
        $redirects = get_option($this->redirect_option, array());
        $redirects[$old_path] = $new_url;
        update_option($this->redirect_option, $redirects, false);
    }

    /**
     * Perform redirect if matching an old slug.
     *
     * @return void
     */
    public function maybe_handle_redirect() {
        if (is_admin() || wp_doing_ajax()) {
            return;
        }

        if (!$this->is_feature_enabled('enable_redirect_manager')) {
            return;
        }

        $redirects = get_option($this->redirect_option, array());
        if (empty($redirects)) {
            return;
        }

        $request_uri = isset($_SERVER['REQUEST_URI']) ? wp_unslash($_SERVER['REQUEST_URI']) : '';
        $path = wp_parse_url($request_uri, PHP_URL_PATH);
        $path = trailingslashit($path);

        foreach ($redirects as $old_path => $target) {
            if (trailingslashit($old_path) === $path) {
                if ($target && home_url($path) !== $target) {
                    wp_safe_redirect($target, 301);
                    exit;
                }
            }
        }
    }

    /**
     * Fix canonical URL for products and posts.
     *
     * @param string $canonical_url Suggested canonical.
     * @param WP_Post $post Optional post object from Rank Math.
     * @return string
     */
    public function fix_canonical_url($canonical_url, $post = null) {
        if (is_admin()) {
            return $canonical_url;
        }

        if (!$post instanceof WP_Post) {
            $post = get_post();
        }

        if (!$post instanceof WP_Post) {
            return $canonical_url;
        }

        if (in_array($post->post_type, array('product', 'post', 'page'), true)) {
            $permalink = get_permalink($post);
            if ($permalink) {
                return $permalink;
            }
        }

        return $canonical_url;
    }

    /**
     * Build structured data for Rank Math or manual output.
     *
     * @param array $data  Existing data.
     * @param array $jsonld Original JSON-LD array.
     * @return array
     */
    public function maybe_extend_rankmath_schema($data, $jsonld) {
        unset($jsonld);

        $schema = $this->build_schema_payload();
        if (empty($schema)) {
            return $data;
        }

        $this->schema_payload = $schema;
        $this->schema_injected = true;

        $index = null;
        foreach ($data as $key => $node) {
            if (isset($node['@type']) && $node['@type'] === $schema['@type']) {
                $index = $key;
                break;
            }
        }

        if (null !== $index) {
            $data[$index] = array_merge($data[$index], $schema);
        } else {
            $data[] = $schema;
        }

        return $data;
    }

    /**
     * Print schema if Rank Math did not render it.
     *
     * @return void
     */
    public function maybe_print_schema_fallback() {
        if ($this->schema_injected) {
            return;
        }

        if (empty($this->schema_payload)) {
            $this->schema_payload = $this->build_schema_payload();
        }

        if (empty($this->schema_payload)) {
            return;
        }

        echo '<script type="application/ld+json">' . wp_json_encode($this->schema_payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . '</script>';
    }

    /**
     * Filter OpenGraph title fallback.
     *
     * @param string $value Existing value.
     * @return string
     */
    public function maybe_filter_og_title($value, $context = null) {
        unset($context);
        if (!empty($value)) {
            return $value;
        }

        if (is_singular()) {
            return wp_get_document_title();
        }

        return get_bloginfo('name');
    }

    /**
     * Filter OpenGraph description fallback.
     *
     * @param string $value Existing value.
     * @return string
     */
    public function maybe_filter_og_description($value, $context = null) {
        unset($context);
        if (!empty($value)) {
            return $value;
        }

        if (is_singular()) {
            $post = get_post();
            if ($post) {
                return Salah_SEO_Helpers::clean_seo_text($post->post_excerpt ?: $post->post_content, 200);
            }
        }

        return get_bloginfo('description');
    }

    /**
     * Filter OpenGraph image fallback.
     *
     * @param string $value Existing value.
     * @return string
     */
    public function maybe_filter_og_image($value, $context = null) {
        unset($context);
        if (!empty($value)) {
            return $value;
        }

        if (is_singular() && has_post_thumbnail()) {
            $image = wp_get_attachment_image_src(get_post_thumbnail_id(), 'full');
            if (!empty($image[0])) {
                return esc_url_raw($image[0]);
            }
        }

        return Salah_SEO_Helpers::get_fallback_image();
    }

    /**
     * Output social meta tags if no SEO plugin handles them.
     *
     * @return void
     */
    public function maybe_output_social_meta_fallback() {
        if (did_action('rank_math/head')) {
            return;
        }

        $title = $this->maybe_filter_og_title('');
        $description = $this->maybe_filter_og_description('');
        $image = $this->maybe_filter_og_image('');

        if (!$title && !$description && !$image) {
            return;
        }

        if ($title) {
            printf('<meta property="og:title" content="%s" />' . PHP_EOL, esc_attr($title));
            printf('<meta name="twitter:title" content="%s" />' . PHP_EOL, esc_attr($title));
        }

        if ($description) {
            printf('<meta property="og:description" content="%s" />' . PHP_EOL, esc_attr($description));
            printf('<meta name="twitter:description" content="%s" />' . PHP_EOL, esc_attr($description));
        }

        if ($image) {
            printf('<meta property="og:image" content="%s" />' . PHP_EOL, esc_url($image));
            printf('<meta name="twitter:image" content="%s" />' . PHP_EOL, esc_url($image));
        }

        echo '<meta name="twitter:card" content="summary_large_image" />' . PHP_EOL;
    }

    /**
     * Build JSON-LD schema payload.
     *
     * @return array
     */
    private function build_schema_payload() {
        if (!is_singular()) {
            return array();
        }

        $post = get_post();
        if (!$post) {
            return array();
        }

        if ('product' === $post->post_type) {
            $product = wc_get_product($post->ID);
            if (!$product) {
                return array();
            }

            $price = wc_get_price_to_display($product);
            if (function_exists('wc_format_decimal')) {
                $price = wc_format_decimal($price, wc_get_price_decimals());
            }

            $schema = array(
                '@context' => 'https://schema.org/',
                '@type' => 'Product',
                '@id' => get_permalink($post),
                'name' => $product->get_name(),
                'description' => Salah_SEO_Helpers::clean_seo_text($product->get_description(), 300),
                'sku' => $product->get_sku(),
                'brand' => array(
                    '@type' => 'Brand',
                    'name' => $this->get_first_term_name($post->ID, 'product_brand'),
                ),
                'offers' => array(
                    '@type' => 'Offer',
                    'priceCurrency' => get_woocommerce_currency(),
                    'price' => $price,
                    'availability' => $product->is_in_stock() ? 'https://schema.org/InStock' : 'https://schema.org/OutOfStock',
                    'url' => get_permalink($post),
                ),
            );

            $rating = $product->get_average_rating();
            if ($rating) {
                $schema['aggregateRating'] = array(
                    '@type' => 'AggregateRating',
                    'ratingValue' => $rating,
                    'ratingCount' => max(1, $product->get_review_count()),
                );
            }

            if ($product->get_image_id()) {
                $image = wp_get_attachment_image_src($product->get_image_id(), 'full');
                if (!empty($image[0])) {
                    $schema['image'] = array($image[0]);
                }
            }

            return array_filter($schema);
        }

        return array(
            '@context' => 'https://schema.org/',
            '@type' => 'Article',
            '@id' => get_permalink($post),
            'headline' => get_the_title($post),
            'datePublished' => get_post_time('c', true, $post),
            'dateModified' => get_post_modified_time('c', true, $post),
            'author' => array(
                '@type' => 'Person',
                'name' => get_the_author_meta('display_name', $post->post_author),
            ),
            'image' => $this->maybe_filter_og_image(''),
            'articleBody' => Salah_SEO_Helpers::clean_seo_text($post->post_content, 500),
        );
    }

    /**
     * Helper to fetch first term name from taxonomy.
     *
     * @param int    $post_id Post ID.
     * @param string $taxonomy Taxonomy slug.
     * @return string|null
     */
    private function get_first_term_name($post_id, $taxonomy) {
        if (!$taxonomy || !taxonomy_exists($taxonomy)) {
            return null;
        }

        $terms = wp_get_post_terms($post_id, $taxonomy);
        if (is_wp_error($terms) || empty($terms)) {
            return null;
        }

        return $terms[0]->name;
    }

    /**
     * Check if feature flag enabled.
     *
     * @param string $feature Feature key.
     * @return bool
     */
    private function is_feature_enabled($feature) {
        return !empty($this->settings[$feature]);
    }

    /**
     * Get setting value.
     *
     * @param string $key Setting key.
     * @return mixed
     */
    private function get_setting($key) {
        if ('internal_link_rules' === $key) {
            $rules = isset($this->settings[$key]) ? $this->settings[$key] : array();
            return Salah_SEO_Helpers::format_internal_link_rules($rules);
        }

        return isset($this->settings[$key]) ? $this->settings[$key] : '';
    }

    /**
     * Determine whether value is placeholder from auto-draft.
     *
     * @param string $value Value to inspect.
     * @return bool
     */
    private function is_placeholder_value($value) {
        if (!is_string($value)) {
            return false;
        }

        $normalized = strtolower(trim($value));
        return in_array($normalized, array('auto-draft', 'auto draft'), true);
    }
}
