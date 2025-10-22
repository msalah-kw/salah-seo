<?php
/**
 * Scheduler and queue manager for Salah SEO.
 */

if (!defined('ABSPATH')) {
    exit;
}

class Salah_SEO_Scheduler {

    const CRON_HOOK = 'salah_seo_process_queue';

    /**
     * Initialize hooks.
     */
    public static function init() {
        add_filter('cron_schedules', array(__CLASS__, 'register_custom_schedule'));
        add_action('init', array(__CLASS__, 'maybe_schedule_event'));
        add_action(self::CRON_HOOK, array(__CLASS__, 'process_queue'));
    }

    /**
     * Register custom cron schedule based on delay setting.
     *
     * @param array $schedules Existing schedules.
     * @return array
     */
    public static function register_custom_schedule($schedules) {
        $settings = Salah_SEO_Helpers::get_plugin_settings();
        $delay = !empty($settings['batch_delay']) ? (int) $settings['batch_delay'] : 5;
        $interval = max(60, $delay * 2);

        $schedules['salah_seo_queue'] = array(
            'interval' => $interval,
            'display'  => __('Salah SEO Queue Runner', 'salah-seo'),
        );

        return $schedules;
    }

    /**
     * Ensure the cron event is scheduled.
     *
     * @return void
     */
    public static function maybe_schedule_event() {
        if (!wp_next_scheduled(self::CRON_HOOK)) {
            wp_schedule_event(time() + 60, 'salah_seo_queue', self::CRON_HOOK);
        }
    }

    /**
     * Enqueue a post for background optimization.
     *
     * @param int   $post_id Post ID.
     * @param array $context Extra context for processing.
     * @return void
     */
    public static function enqueue($post_id, $context = array()) {
        $post_id = absint($post_id);

        if (!$post_id) {
            return;
        }

        $queue = Salah_SEO_Helpers::get_task_queue();

        foreach ($queue as $task) {
            if ((int) $task['post_id'] === $post_id) {
                return;
            }
        }

        $queue[] = array(
            'post_id' => $post_id,
            'context' => $context,
            'queued_at' => current_time('timestamp'),
        );

        Salah_SEO_Helpers::update_task_queue($queue);
        self::maybe_schedule_event();
    }

    /**
     * Process the queue respecting batch size and safety limits.
     *
     * @return void
     */
    public static function process_queue() {
        $settings = Salah_SEO_Helpers::get_plugin_settings();
        $batch_size = !empty($settings['batch_size']) ? (int) $settings['batch_size'] : 5;
        $task_timeout = !empty($settings['task_timeout']) ? (int) $settings['task_timeout'] : 120;
        $per_item_budget = !empty($settings['per_item_time_budget']) ? (int) $settings['per_item_time_budget'] : 10;
        $lock_ttl = max($task_timeout, $batch_size * max(1, $per_item_budget));

        $lock_token = Salah_SEO_Helpers::acquire_lock(self::CRON_HOOK, $lock_ttl);

        if (!$lock_token) {
            return;
        }

        Salah_SEO_Helpers::register_lock_shutdown(self::CRON_HOOK, $lock_token);
        $queue = Salah_SEO_Helpers::get_task_queue();

        $state = Salah_SEO_Helpers::get_processing_state();
        $state['last_run'] = current_time('timestamp');

        if (empty($queue)) {
            $state['in_progress'] = false;
            $state['last_processed'] = 0;
            Salah_SEO_Helpers::update_processing_state($state);
            Salah_SEO_Helpers::release_lock(self::CRON_HOOK, $lock_token);
            return;
        }

        $delay = !empty($settings['batch_delay']) ? (int) $settings['batch_delay'] : 5;
        $queries_per_minute = !empty($settings['queries_per_minute']) ? (int) $settings['queries_per_minute'] : 120;
        $sleep_between = $queries_per_minute > 0 ? max(0, floor(60 / max(1, $queries_per_minute))) : 0;

        $state['in_progress'] = true;
        Salah_SEO_Helpers::update_processing_state($state);

        $processed = 0;
        $core = Salah_SEO_Core::instance();
        $start_time = time();

        try {
            while (!empty($queue) && $processed < $batch_size) {
                $task = array_shift($queue);
                $post_id = isset($task['post_id']) ? absint($task['post_id']) : 0;

                if ($post_id) {
                    try {
                        $core->optimize_post($post_id, array('source' => 'queue'));
                    } catch (Exception $e) {
                        Salah_SEO_Helpers::log('Queue processing failed for post ' . $post_id . ': ' . $e->getMessage(), 'error');
                    }
                }

                $processed++;

                if (!Salah_SEO_Helpers::refresh_lock(self::CRON_HOOK, $lock_token, $lock_ttl)) {
                    Salah_SEO_Helpers::log('Queue heartbeat refresh failed; lock may have been stolen.', 'warning');
                }

                if ($sleep_between > 0) {
                    usleep($sleep_between * 1000000);
                }

                if ($task_timeout > 0 && (time() - $start_time) >= $task_timeout) {
                    break;
                }
            }

            Salah_SEO_Helpers::update_task_queue($queue);
            $state['last_processed'] = $processed;

            if (!empty($queue)) {
                wp_schedule_single_event(time() + max(1, $delay), self::CRON_HOOK);
            }
        } finally {
            $state['in_progress'] = false;
            if (!isset($state['last_processed'])) {
                $state['last_processed'] = $processed;
            }
            Salah_SEO_Helpers::update_processing_state($state);
            Salah_SEO_Helpers::release_lock(self::CRON_HOOK, $lock_token);
        }
    }
}
