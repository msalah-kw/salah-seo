<?php
/**
 * REST API integration for Salah SEO.
 */

if (!defined('ABSPATH')) {
    exit;
}

class Salah_SEO_REST {

    /**
     * Register hooks.
     *
     * @return void
     */
    public static function init() {
        add_action('rest_api_init', array(__CLASS__, 'register_routes'));
    }

    /**
     * Register plugin routes.
     *
     * @return void
     */
    public static function register_routes() {
        register_rest_route(
            'salah-seo/v1',
            '/autofill',
            array(
                'methods' => WP_REST_Server::CREATABLE,
                'permission_callback' => array(__CLASS__, 'permission_check'),
                'callback' => array(__CLASS__, 'handle_autofill'),
                'args' => array(
                    'posts' => array(
                        'type' => 'string',
                        'default' => 'product',
                    ),
                    'limit' => array(
                        'type' => 'integer',
                        'default' => 20,
                        'minimum' => 1,
                        'maximum' => 500,
                    ),
                    'dry_run' => array(
                        'type' => 'boolean',
                        'default' => false,
                    ),
                ),
            )
        );

        register_rest_route(
            'salah-seo/v1',
            '/link-suggest',
            array(
                'methods' => WP_REST_Server::READABLE,
                'permission_callback' => array(__CLASS__, 'permission_check'),
                'callback' => array(__CLASS__, 'handle_link_suggest'),
                'args' => array(
                    'post' => array(
                        'type' => 'integer',
                        'required' => true,
                    ),
                ),
            )
        );
    }

    /**
     * Verify current user capabilities.
     *
     * @return bool
     */
    public static function permission_check() {
        return Salah_SEO_Helpers::current_user_can_manage_seo();
    }

    /**
     * REST callback: bulk autofill.
     *
     * @param WP_REST_Request $request Request instance.
     * @return WP_REST_Response
     */
    public static function handle_autofill($request) {
        $post_type = sanitize_key($request->get_param('posts'));
        $limit = absint($request->get_param('limit'));
        $dry_run = rest_sanitize_boolean($request->get_param('dry_run'));

        $query_args = array(
            'post_type' => $post_type,
            'post_status' => 'publish',
            'posts_per_page' => $limit,
            'fields' => 'ids',
            'orderby' => 'date',
            'order' => 'DESC',
        );

        $post_ids = get_posts($query_args);
        $results = array();

        $core = Salah_SEO_Core::instance();

        foreach ($post_ids as $id) {
            $results[] = $core->optimize_post($id, array(
                'dry_run' => $dry_run,
                'source' => 'rest',
            ));
        }

        return rest_ensure_response(
            array(
                'count' => count($results),
                'dry_run' => $dry_run,
                'items' => $results,
            )
        );
    }

    /**
     * REST callback: internal link suggestions.
     *
     * @param WP_REST_Request $request Request instance.
     * @return WP_REST_Response
     */
    public static function handle_link_suggest($request) {
        $post_id = absint($request->get_param('post'));
        if (!$post_id) {
            return new WP_Error('invalid_post', __('Invalid post ID provided.', 'salah-seo'), array('status' => 400));
        }

        $suggestions = Salah_SEO_Helpers::generate_internal_link_suggestions($post_id);

        return rest_ensure_response(
            array(
                'post_id' => $post_id,
                'suggestions' => $suggestions,
            )
        );
    }
}
