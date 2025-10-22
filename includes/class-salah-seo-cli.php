<?php
/**
 * WP-CLI commands for Salah SEO.
 */

if (!defined('ABSPATH')) {
    exit;
}

class Salah_SEO_CLI {

    /**
     * Register WP-CLI commands.
     *
     * @return void
     */
    public static function init() {
        if (!defined('WP_CLI') || !WP_CLI) {
            return;
        }

        WP_CLI::add_command('salah-seo autofill', array(__CLASS__, 'command_autofill'));
        WP_CLI::add_command('salah-seo link-suggest', array(__CLASS__, 'command_link_suggest'));
    }

    /**
     * Execute the autofill command.
     *
     * ## OPTIONS
     *
     * [--posts=<type>]
     * : Post type to target. Defaults to `product`.
     *
     * [--limit=<number>]
     * : Limit the number of posts processed. Defaults to 20.
     *
     * [--dry-run]
     * : Preview changes without saving.
     *
     * @param array $args Positional args.
     * @param array $assoc_args Associative args.
     * @return void
     */
    public static function command_autofill($args, $assoc_args) {
        unset($args);

        $post_type = isset($assoc_args['posts']) ? sanitize_key($assoc_args['posts']) : 'product';
        $limit = isset($assoc_args['limit']) ? max(1, absint($assoc_args['limit'])) : 20;
        $dry_run = isset($assoc_args['dry-run']);

        $query_args = array(
            'post_type' => $post_type,
            'post_status' => 'publish',
            'posts_per_page' => $limit,
            'fields' => 'ids',
            'orderby' => 'date',
            'order' => 'DESC',
        );

        $post_ids = get_posts($query_args);
        if (empty($post_ids)) {
            WP_CLI::warning(__('No posts matched the provided criteria.', 'salah-seo'));
            return;
        }

        $core = Salah_SEO_Core::instance();

        foreach ($post_ids as $post_id) {
            $result = $core->optimize_post($post_id, array(
                'dry_run' => $dry_run,
                'source' => 'cli',
            ));

            if ($dry_run) {
                WP_CLI::log(sprintf(__('Dry-run for %1$s: %2$d changes detected.', 'salah-seo'), $post_id, count($result['changes'])));
            } else {
                WP_CLI::log(sprintf(__('Optimized %1$s with %2$d updates.', 'salah-seo'), $post_id, count($result['changes'])));
            }
        }

        WP_CLI::success($dry_run ? __('Dry-run completed.', 'salah-seo') : __('Optimization completed.', 'salah-seo'));
    }

    /**
     * Provide internal link suggestions.
     *
     * ## OPTIONS
     *
     * --post=<id>
     * : Post ID to analyze.
     *
     * @param array $args Positional args.
     * @param array $assoc_args Associative args.
     * @return void
     */
    public static function command_link_suggest($args, $assoc_args) {
        unset($args);

        if (empty($assoc_args['post'])) {
            WP_CLI::error(__('Please provide a valid post ID via --post.', 'salah-seo'));
        }

        $post_id = absint($assoc_args['post']);
        if (!$post_id) {
            WP_CLI::error(__('The provided post ID is invalid.', 'salah-seo'));
        }

        $suggestions = Salah_SEO_Helpers::generate_internal_link_suggestions($post_id);

        if (empty($suggestions)) {
            WP_CLI::warning(__('No suggestions available for this post.', 'salah-seo'));
            return;
        }

        foreach ($suggestions as $suggestion) {
            WP_CLI::log(sprintf('%1$s → %2$s', $suggestion['keyword'], $suggestion['url']));
        }

        WP_CLI::success(__('Suggestions generated successfully.', 'salah-seo'));
    }
}
