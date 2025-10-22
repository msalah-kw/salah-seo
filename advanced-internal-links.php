<?php
/**
 * Plugin Name:       Advanced Internal Link Manager
 * Plugin URI:        https://example.com/
 * Description:       إضافة متقدمة لإزالة وبناء الروابط الداخلية في ووكوميرس بشكل دقيق ومتحكم به مع واجهة حديثة.
 * Version:           1.0.0
 * Author:            Your Name
 * Author URI:        https://example.com/
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       ailm
 * Domain Path:       /languages
 */

// Block direct access to the file
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Advanced_Internal_Link_Manager {

    private static $instance;

    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action( 'admin_menu', [ $this, 'add_admin_menu' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_scripts' ] );
        add_action( 'admin_init', [ $this, 'handle_form_actions' ] );
        
        // AJAX handlers for batch processing
        add_action( 'wp_ajax_ailm_prepare_processing', [ $this, 'ajax_prepare_processing' ] );
        add_action( 'wp_ajax_ailm_process_batch', [ $this, 'ajax_process_batch' ] );
    }

    public function add_admin_menu() {
        add_menu_page(
            __( 'مدير الروابط الداخلية', 'ailm' ),
            __( 'مدير الروابط', 'ailm' ),
            'manage_options',
            'advanced-internal-links',
            [ $this, 'create_admin_page' ],
            'dashicons-admin-links',
            25
        );
    }

    public function enqueue_admin_scripts( $hook ) {
        if ( 'toplevel_page_advanced-internal-links' !== $hook ) {
            return;
        }
        // Tailwind CSS for modern UI
        wp_enqueue_script( 'ailm-tailwindcss', 'https://cdn.tailwindcss.com' );
        // Main admin script
        wp_register_script( 'ailm-admin-script', false );
        wp_enqueue_script( 'ailm-admin-script' );
        wp_localize_script( 'ailm-admin-script', 'ailm_ajax', [
            'ajax_url' => admin_url( 'admin-ajax.php' ),
            'nonce'    => wp_create_nonce( 'ailm-ajax-nonce' ),
        ] );
    }

    public function handle_form_actions() {
        if ( ! isset( $_POST['ailm_form_nonce'] ) || ! wp_verify_nonce( $_POST['ailm_form_nonce'], 'ailm_form_action' ) ) {
            return;
        }

        $rules = get_option( 'ailm_link_rules', [] );

        // Handle adding a new rule
        if ( isset( $_POST['ailm_action'] ) && $_POST['ailm_action'] === 'add_rule' ) {
            $keyword = sanitize_text_field( $_POST['keyword'] );
            $url = esc_url_raw( $_POST['url'] );
            $repeats = absint( $_POST['repeats'] );

            if ( ! empty( $keyword ) && ! empty( $url ) && $repeats > 0 ) {
                $rules[] = [
                    'keyword' => $keyword,
                    'url'     => $url,
                    'repeats' => $repeats,
                ];
                update_option( 'ailm_link_rules', $rules );
            }
        }

        // Handle deleting a rule
        if ( isset( $_POST['ailm_action'] ) && $_POST['ailm_action'] === 'delete_rule' ) {
            $rule_index = absint( $_POST['rule_index'] );
            if ( isset( $rules[ $rule_index ] ) ) {
                unset( $rules[ $rule_index ] );
                update_option( 'ailm_link_rules', array_values( $rules ) );
            }
        }
    }

    public function create_admin_page() {
        $rules = get_option( 'ailm_link_rules', [] );
        ?>
        <div class="wrap" dir="rtl">
            <div class="container mx-auto p-6 bg-gray-50 font-sans">
                <div class="mb-8">
                    <h1 class="text-3xl font-bold text-gray-800">مدير الروابط الداخلية المتقدم</h1>
                    <p class="text-gray-600 mt-2">تحكم كامل في الروابط الداخلية لموقعك لتعزيز السيو.</p>
                </div>

                <!-- Rule Management Section -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                    <div class="md:col-span-1 bg-white p-6 rounded-lg shadow-md">
                        <h2 class="text-xl font-semibold text-gray-700 mb-4 border-b pb-2">إضافة قاعدة جديدة</h2>
                        <form method="POST">
                            <input type="hidden" name="ailm_action" value="add_rule">
                            <?php wp_nonce_field( 'ailm_form_action', 'ailm_form_nonce' ); ?>
                            
                            <div class="mb-4">
                                <label for="keyword" class="block text-gray-700 font-medium mb-2">الكلمة المستهدفة</label>
                                <input type="text" id="keyword" name="keyword" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" required>
                            </div>
                            
                            <div class="mb-4">
                                <label for="url" class="block text-gray-700 font-medium mb-2">الرابط (URL)</label>
                                <input type="url" id="url" name="url" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" required>
                            </div>

                            <div class="mb-4">
                                <label for="repeats" class="block text-gray-700 font-medium mb-2">أقصى عدد للتكرار في المقال الواحد</label>
                                <input type="number" id="repeats" name="repeats" min="1" value="1" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" required>
                            </div>
                            
                            <button type="submit" class="w-full bg-blue-600 text-white font-bold py-2 px-4 rounded-md hover:bg-blue-700 transition duration-300">
                                إضافة القاعدة
                            </button>
                        </form>
                    </div>

                    <div class="md:col-span-2 bg-white p-6 rounded-lg shadow-md">
                        <h2 class="text-xl font-semibold text-gray-700 mb-4 border-b pb-2">قواعد الربط الحالية</h2>
                        <div class="overflow-x-auto">
                            <table class="min-w-full bg-white">
                                <thead class="bg-gray-100">
                                    <tr>
                                        <th class="py-3 px-4 text-right font-semibold text-sm text-gray-600 uppercase">الكلمة المستهدفة</th>
                                        <th class="py-3 px-4 text-right font-semibold text-sm text-gray-600 uppercase">الرابط</th>
                                        <th class="py-3 px-4 text-center font-semibold text-sm text-gray-600 uppercase">التكرار</th>
                                        <th class="py-3 px-4 text-center font-semibold text-sm text-gray-600 uppercase">إجراء</th>
                                    </tr>
                                </thead>
                                <tbody class="text-gray-700">
                                    <?php if ( empty( $rules ) ) : ?>
                                        <tr>
                                            <td colspan="4" class="text-center py-4 text-gray-500">لا توجد قواعد مضافة حالياً.</td>
                                        </tr>
                                    <?php else : ?>
                                        <?php foreach ( $rules as $index => $rule ) : ?>
                                            <tr class="border-b">
                                                <td class="py-3 px-4 font-medium"><?php echo esc_html( $rule['keyword'] ); ?></td>
                                                <td class="py-3 px-4"><a href="<?php echo esc_url( $rule['url'] ); ?>" target="_blank" class="text-blue-500 hover:underline"><?php echo esc_html( $rule['url'] ); ?></a></td>
                                                <td class="py-3 px-4 text-center"><?php echo esc_html( $rule['repeats'] ); ?></td>
                                                <td class="py-3 px-4 text-center">
                                                    <form method="POST" onsubmit="return confirm('هل أنت متأكد من حذف هذه القاعدة؟');">
                                                        <input type="hidden" name="ailm_action" value="delete_rule">
                                                        <input type="hidden" name="rule_index" value="<?php echo $index; ?>">
                                                        <?php wp_nonce_field( 'ailm_form_action', 'ailm_form_nonce' ); ?>
                                                        <button type="submit" class="text-red-500 hover:text-red-700 font-bold">حذف</button>
                                                    </form>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Processing Control Section -->
                <div class="mt-8 bg-white p-6 rounded-lg shadow-md">
                    <h2 class="text-xl font-semibold text-gray-700 mb-4 border-b pb-2">لوحة التحكم بالمعالجة</h2>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                         <div class="p-4 bg-gray-50 rounded-lg border">
                            <h3 class="font-semibold text-lg text-green-700">تطبيق القواعد الجديدة</h3>
                            <p class="text-sm text-gray-600 my-2">سيقوم هذا الإجراء بإضافة الروابط الداخلية بناءً على القواعد التي أضفتها أعلاه. العملية ستتم على كل المقالات والمنتجات.</p>
                            <button id="ailm-apply-links" class="w-full bg-green-600 text-white font-bold py-2 px-4 rounded-md hover:bg-green-700 transition duration-300">
                                بدء إضافة الروابط
                            </button>
                        </div>
                        <div class="p-4 bg-red-50 rounded-lg border border-red-200">
                            <h3 class="font-semibold text-lg text-red-800">إزالة جميع الروابط الداخلية</h3>
                            <p class="text-sm text-gray-600 my-2">تحذير: سيقوم هذا الإجراء بإزالة جميع الروابط الداخلية (التي تشير لنفس نطاق موقعك) من كل المقالات والمنتجات. لا يمكن التراجع عن هذا الإجراء.</p>
                            <button id="ailm-remove-links" class="w-full bg-red-600 text-white font-bold py-2 px-4 rounded-md hover:bg-red-700 transition duration-300">
                                بدء إزالة الروابط
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Progress Section -->
                <div id="ailm-progress-container" class="mt-8 bg-white p-6 rounded-lg shadow-md hidden">
                    <h2 class="text-xl font-semibold text-gray-700 mb-4">حالة التقدم</h2>
                    <div class="w-full bg-gray-200 rounded-full h-4">
                        <div id="ailm-progress-bar" class="bg-blue-600 h-4 rounded-full transition-all duration-500" style="width: 0%"></div>
                    </div>
                    <div id="ailm-progress-status" class="mt-4 text-sm text-gray-600 text-center"></div>
                    <pre id="ailm-progress-log" class="mt-4 bg-gray-900 text-white text-xs p-4 rounded-md max-h-60 overflow-y-auto"></pre>
                </div>

            </div>
        </div>
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                const applyBtn = document.getElementById('ailm-apply-links');
                const removeBtn = document.getElementById('ailm-remove-links');
                
                const progressContainer = document.getElementById('ailm-progress-container');
                const progressBar = document.getElementById('ailm-progress-bar');
                const progressStatus = document.getElementById('ailm-progress-status');
                const progressLog = document.getElementById('ailm-progress-log');
                
                let totalItems = 0;
                let processedItems = 0;
                let currentAction = '';

                applyBtn.addEventListener('click', () => {
                    if (confirm('هل أنت متأكد من أنك تريد تطبيق قواعد الربط على كل المحتوى؟')) {
                        startBatchProcessing('apply_links');
                    }
                });

                removeBtn.addEventListener('click', () => {
                    if (confirm('تحذير خطير! هل أنت متأكد من أنك تريد حذف جميع الروابط الداخلية من موقعك؟ لا يمكن التراجع عن هذا الإجراء.')) {
                        startBatchProcessing('remove_links');
                    }
                });

                function startBatchProcessing(action) {
                    currentAction = action;
                    totalItems = 0;
                    processedItems = 0;

                    applyBtn.disabled = true;
                    removeBtn.disabled = true;
                    applyBtn.classList.add('opacity-50', 'cursor-not-allowed');
                    removeBtn.classList.add('opacity-50', 'cursor-not-allowed');

                    progressContainer.classList.remove('hidden');
                    updateProgress(0, 'جاري التجهيز...');
                    progressLog.innerHTML = 'بدء عملية المعالجة...\n';

                    const formData = new FormData();
                    formData.append('action', 'ailm_prepare_processing');
                    formData.append('nonce', ailm_ajax.nonce);
                    formData.append('process_action', currentAction);

                    fetch(ailm_ajax.ajax_url, { method: 'POST', body: formData })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                totalItems = data.data.total_items;
                                if (totalItems > 0) {
                                    logMessage(`تم العثور على ${totalItems} عنصر للمعالجة. بدء المعالجة...`);
                                    processNextBatch();
                                } else {
                                    logMessage('لم يتم العثور على أي عناصر للمعالجة.');
                                    finishProcessing('اكتملت العملية بنجاح.');
                                }
                            } else {
                                logMessage('خطأ: ' + data.data.message);
                                finishProcessing('فشلت العملية.');
                            }
                        });
                }

                function processNextBatch() {
                    const formData = new FormData();
                    formData.append('action', 'ailm_process_batch');
                    formData.append('nonce', ailm_ajax.nonce);
                    formData.append('process_action', currentAction);

                    fetch(ailm_ajax.ajax_url, { method: 'POST', body: formData })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                processedItems += data.data.processed_count;
                                const percentage = totalItems > 0 ? (processedItems / totalItems) * 100 : 100;
                                updateProgress(percentage, `تمت معالجة ${processedItems} من ${totalItems}`);
                                logMessage(data.data.message);
                                
                                if (data.data.done) {
                                    finishProcessing('اكتملت العملية بنجاح!');
                                } else {
                                    processNextBatch(); // Process next batch
                                }
                            } else {
                                logMessage('خطأ: ' + data.data.message);
                                finishProcessing('فشلت العملية.');
                            }
                        })
                        .catch(error => {
                            logMessage('حدث خطأ في الشبكة. يرجى المحاولة مرة أخرى.');
                            finishProcessing('فشلت العملية.');
                        });
                }

                function updateProgress(percentage, statusText) {
                    progressBar.style.width = percentage + '%';
                    progressStatus.textContent = statusText;
                }
                
                function logMessage(message) {
                    progressLog.innerHTML += message + '\n';
                    progressLog.scrollTop = progressLog.scrollHeight; // Auto-scroll
                }

                function finishProcessing(finalMessage) {
                    updateProgress(100, finalMessage);
                    logMessage(finalMessage);
                    applyBtn.disabled = false;
                    removeBtn.disabled = false;
                    applyBtn.classList.remove('opacity-50', 'cursor-not-allowed');
                    removeBtn.classList.remove('opacity-50', 'cursor-not-allowed');
                }
            });
        </script>
        <?php
    }

    public function ajax_prepare_processing() {
        check_ajax_referer( 'ailm-ajax-nonce', 'nonce' );

        $query = new WP_Query([
            'post_type'      => ['post', 'product'], // You can add other post types here
            'posts_per_page' => -1,
            'post_status'    => 'publish',
            'fields'         => 'ids',
        ]);
        $post_ids = $query->posts;

        set_transient( 'ailm_batch_list', $post_ids, HOUR_IN_SECONDS );

        wp_send_json_success( [ 'total_items' => count( $post_ids ) ] );
    }

    public function ajax_process_batch() {
        check_ajax_referer( 'ailm-ajax-nonce', 'nonce' );
        $action = sanitize_text_field( $_POST['process_action'] );

        $post_ids = get_transient( 'ailm_batch_list' );

        if ( false === $post_ids ) {
            wp_send_json_error( [ 'message' => 'انتهت صلاحية جلسة المعالجة. يرجى البدء من جديد.' ] );
        }

        $batch_size = 5;
        $batch_ids = array_splice( $post_ids, 0, $batch_size );
        $log_message = '';

        if ( empty( $batch_ids ) ) {
            delete_transient( 'ailm_batch_list' );
            wp_send_json_success( [ 'done' => true, 'processed_count' => 0, 'message' => 'لا توجد عناصر متبقية.' ] );
        }

        foreach ( $batch_ids as $post_id ) {
            $post = get_post( $post_id );
            if ( ! $post || empty( $post->post_content ) ) {
                continue;
            }

            $content = $post->post_content;
            $new_content = '';

            if ( 'apply_links' === $action ) {
                $new_content = $this->add_links_to_content( $content );
                $log_message .= "تمت معالجة (إضافة روابط) لـ: " . get_the_title($post_id) . "\n";
            } elseif ( 'remove_links' === $action ) {
                $new_content = $this->remove_all_internal_links( $content );
                $log_message .= "تمت معالجة (إزالة روابط) لـ: " . get_the_title($post_id) . "\n";
            }

            if ( $new_content && $new_content !== $content ) {
                wp_update_post( [
                    'ID'           => $post_id,
                    'post_content' => $new_content,
                ] );
            }
        }

        set_transient( 'ailm_batch_list', $post_ids, HOUR_IN_SECONDS );

        wp_send_json_success( [
            'done'            => empty( $post_ids ),
            'processed_count' => count( $batch_ids ),
            'message'         => $log_message
        ] );
    }
    
    private function remove_all_internal_links( $content ) {
        $site_url = preg_quote( home_url(), '/' );
        // This regex finds <a> tags with an href attribute pointing to the site URL
        $pattern = '/<a\s+(?:[^>]*?\s+)?href=["\'](' . $site_url . '[^"\']*)["\'][^>]*?>(.*?)<\/a>/is';
        // Replaces the entire link with just its text content
        return preg_replace( $pattern, '$2', $content );
    }

    private function add_links_to_content( $content ) {
        $rules = get_option( 'ailm_link_rules', [] );
        if ( empty( $rules ) ) {
            return $content;
        }

        // Use DOMDocument to safely manipulate HTML and avoid breaking the structure
        $dom = new DOMDocument();
        // Suppress warnings from invalid HTML
        @$dom->loadHTML( '<?xml encoding="utf-8" ?>' . $content, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD );

        $xpath = new DOMXPath( $dom );

        foreach ( $rules as $rule ) {
            $keyword = $rule['keyword'];
            $url = $rule['url'];
            $max_repeats = (int) $rule['repeats'];
            $count = 0;
            
            // The magic query: find text nodes that are not inside forbidden tags
            $nodes = $xpath->query( "//text()[not(ancestor::a) and not(ancestor::h1) and not(ancestor::h2) and not(ancestor::script) and not(ancestor::style)]" );
            
            foreach ( $nodes as $node ) {
                if ( $count >= $max_repeats ) {
                    break;
                }
                
                // Case-insensitive search
                if ( stripos( $node->nodeValue, $keyword ) !== false ) {
                    // We need to create a new fragment to replace the text node
                    $fragment = $dom->createDocumentFragment();
                    
                    // Regex to split the text by the keyword, keeping the keyword
                    $parts = preg_split( '/(' . preg_quote( $keyword, '/' ) . ')/i', $node->nodeValue, -1, PREG_SPLIT_DELIM_CAPTURE );
                    
                    foreach ( $parts as $i => $part ) {
                        // If it's the keyword (at an odd index), create a link
                        if ( $i % 2 == 1 && $count < $max_repeats ) {
                            $link = $dom->createElement( 'a', $part );
                            $link->setAttribute( 'href', $url );
                            $fragment->appendChild( $link );
                            $count++;
                        } else {
                            // Otherwise, it's just text
                            $fragment->appendChild( $dom->createTextNode( $part ) );
                        }
                    }
                    
                    // Replace the original text node with our new fragment
                    if ($node->parentNode) {
                       $node->parentNode->replaceChild( $fragment, $node );
                    }
                }
            }
        }
        
        $new_html = $dom->saveHTML();
        // Remove the XML declaration and potentially the body/html tags added by DOMDocument
        return preg_replace('~<(?:!DOCTYPE|/?(?:html|body))[^>]*>\s*~i', '', $new_html);
    }
}

Advanced_Internal_Link_Manager::get_instance();
