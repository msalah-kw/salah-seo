<?php
/**
 * Modern dashboard view for Salah SEO plugin
 */

if (!defined('ABSPATH')) {
    exit;
}

$feature_toggles = array(
    'enable_focus_keyword' => __('تعبئة الكلمة المفتاحية تلقائياً', 'salah-seo'),
    'enable_meta_description' => __('تعبئة الوصف التعريفي تلقائياً', 'salah-seo'),
    'enable_short_description' => __('تعبئة الوصف القصير تلقائياً', 'salah-seo'),
    'enable_product_tags' => __('إنشاء وسوم المنتج تلقائياً', 'salah-seo'),
    'enable_image_optimization' => __('تحسين بيانات الصور المرفقة', 'salah-seo'),
    'enable_internal_linking' => __('تطبيق قواعد الربط الداخلي', 'salah-seo'),
    'enable_canonical_fix' => __('إصلاح الروابط القانونية (Canonical)', 'salah-seo'),
);

$default_texts = array(
    'default_meta_description' => __('الوصف التعريفي الافتراضي', 'salah-seo'),
    'default_short_description' => __('الوصف القصير الافتراضي', 'salah-seo'),
    'default_full_description' => __('الوصف الكامل الافتراضي', 'salah-seo'),
);

$plugin_settings_url = esc_url(admin_url('admin.php?page=salah-seo-settings'));
$bulk_nonce = wp_create_nonce('salah_seo_bulk_nonce');
$links_nonce = wp_create_nonce('salah_seo_links_nonce');
?>

<div class="wrap salah-seo-dashboard" dir="rtl">
    <h1 class="text-3xl font-bold mb-6 text-gray-900">
        <?php esc_html_e('لوحة تحكم Salah SEO المتكاملة', 'salah-seo'); ?>
    </h1>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4 mb-8">
        <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-5 flex flex-col gap-3">
            <span class="text-sm font-semibold text-slate-500"><?php esc_html_e('التنفيذ التلقائي', 'salah-seo'); ?></span>
            <span class="text-2xl font-bold text-emerald-600 flex items-center gap-2">
                <span class="w-3 h-3 rounded-full bg-emerald-500 animate-pulse"></span>
                <?php esc_html_e('يعمل عند إنشاء أي منتج جديد', 'salah-seo'); ?>
            </span>
            <p class="text-sm text-slate-500 leading-relaxed">
                <?php esc_html_e('يتم تشغيل كل التحسينات تلقائياً عند حفظ المنتجات. لن يتم الكتابة فوق أي حقول تم تعبئتها يدوياً.', 'salah-seo'); ?>
            </p>
        </div>

        <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-5 flex flex-col gap-3">
            <span class="text-sm font-semibold text-slate-500"><?php esc_html_e('حالة التكاملات', 'salah-seo'); ?></span>
            <ul class="text-sm text-slate-600 space-y-2">
                <li class="flex items-center gap-2">
                    <span class="inline-flex items-center justify-center w-5 h-5 rounded-full <?php echo $compatibility['woocommerce']['active'] ? 'bg-emerald-500 text-white' : 'bg-red-500 text-white'; ?>">
                        <?php echo $compatibility['woocommerce']['active'] ? '✓' : '!'; ?>
                    </span>
                    <span><?php esc_html_e('WooCommerce', 'salah-seo'); ?> —
                        <?php echo $compatibility['woocommerce']['active'] ? esc_html($compatibility['woocommerce']['version']) : esc_html__('غير مفعل', 'salah-seo'); ?>
                    </span>
                </li>
                <li class="flex items-center gap-2">
                    <span class="inline-flex items-center justify-center w-5 h-5 rounded-full <?php echo $compatibility['rankmath']['active'] ? 'bg-emerald-500 text-white' : 'bg-red-500 text-white'; ?>">
                        <?php echo $compatibility['rankmath']['active'] ? '✓' : '!'; ?>
                    </span>
                    <span><?php esc_html_e('Rank Math SEO', 'salah-seo'); ?> —
                        <?php echo $compatibility['rankmath']['active'] ? esc_html($compatibility['rankmath']['version']) : esc_html__('غير مفعل', 'salah-seo'); ?>
                    </span>
                </li>
            </ul>
        </div>

        <div class="bg-gradient-to-br from-slate-900 to-slate-700 rounded-xl shadow-lg text-white p-5 flex flex-col gap-3">
            <span class="text-sm font-semibold text-slate-200"><?php esc_html_e('مؤشرات سريعة', 'salah-seo'); ?></span>
            <div class="flex items-baseline gap-2">
                <span class="text-3xl font-black">
                    <?php echo number_format_i18n((int) $GLOBALS['wpdb']->get_var("SELECT COUNT(ID) FROM {$GLOBALS['wpdb']->posts} WHERE post_type='product' AND post_status='publish'")); ?>
                </span>
                <span class="text-sm text-slate-200"><?php esc_html_e('منتج منشور', 'salah-seo'); ?></span>
            </div>
            <p class="text-sm text-slate-200 leading-relaxed">
                <?php esc_html_e('اضغط على زر التحسين الجماعي في أي وقت للتأكد من أن كل المنتجات تستخدم أحدث الإعدادات.', 'salah-seo'); ?>
            </p>
        </div>
    </div>

    <form method="post" action="options.php" class="space-y-8">
        <?php settings_fields('salah_seo_settings_group'); ?>

        <section class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
            <header class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
                <div>
                    <h2 class="text-xl font-semibold text-slate-900"><?php esc_html_e('التحكم في خصائص الأتمتة', 'salah-seo'); ?></h2>
                    <p class="text-sm text-slate-500 mt-1"><?php esc_html_e('قم بتحديد المهام التي ترغب في تنفيذها تلقائياً مع كل منتج جديد.', 'salah-seo'); ?></p>
                </div>
                <span class="text-xs font-medium bg-emerald-100 text-emerald-600 px-3 py-1 rounded-full">
                    <?php esc_html_e('قابل للتخصيص الكامل', 'salah-seo'); ?>
                </span>
            </header>
            <div class="grid gap-4 p-6 sm:grid-cols-2">
                <?php foreach ($feature_toggles as $field => $label) :
                    $enabled = !empty($settings[$field]);
                ?>
                    <label class="flex items-start gap-3 p-4 rounded-xl border border-slate-200 hover:border-slate-300 transition cursor-pointer bg-slate-50">
                        <input type="checkbox" name="<?php echo esc_attr($this->option_name . '[' . $field . ']'); ?>" value="1" class="mt-1 h-5 w-5 text-emerald-600 focus:ring-emerald-500" <?php checked(true, $enabled, true); ?> />
                        <div class="space-y-1">
                            <span class="block text-base font-semibold text-slate-900"><?php echo esc_html($label); ?></span>
                            <span class="block text-xs text-slate-500"><?php esc_html_e('سيتم تطبيق هذه الخطوة فقط إذا كان الحقل فارغاً.', 'salah-seo'); ?></span>
                        </div>
                    </label>
                <?php endforeach; ?>
            </div>
        </section>

        <section class="bg-white rounded-2xl shadow-sm border border-slate-200">
            <header class="px-6 py-4 border-b border-slate-100">
                <h2 class="text-xl font-semibold text-slate-900"><?php esc_html_e('النصوص الافتراضية الذكية', 'salah-seo'); ?></h2>
                <p class="text-sm text-slate-500 mt-1"><?php esc_html_e('قم بتخصيص النصوص التي ستُستخدم في حال كان المحتوى الأصلي فارغاً.', 'salah-seo'); ?></p>
            </header>
            <div class="grid gap-6 p-6">
                <?php foreach ($default_texts as $field => $label) :
                    $value = isset($settings[$field]) ? $settings[$field] : '';
                ?>
                    <div>
                        <label for="<?php echo esc_attr($field); ?>" class="block text-sm font-semibold text-slate-700 mb-2"><?php echo esc_html($label); ?></label>
                        <textarea id="<?php echo esc_attr($field); ?>" name="<?php echo esc_attr($this->option_name . '[' . $field . ']'); ?>" rows="4" class="w-full rounded-xl border border-slate-200 focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 px-4 py-3 text-sm text-slate-700 shadow-inner" placeholder="<?php esc_attr_e('أدخل النص الافتراضي هنا...', 'salah-seo'); ?>"><?php echo esc_textarea($value); ?></textarea>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>

        <section class="bg-white rounded-2xl shadow-sm border border-slate-200">
            <header class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
                <div>
                    <h2 class="text-xl font-semibold text-slate-900"><?php esc_html_e('قواعد الربط الداخلي المتقدمة', 'salah-seo'); ?></h2>
                    <p class="text-sm text-slate-500 mt-1"><?php esc_html_e('أضف كلمات مفتاحية وروابط داخلية مع تحديد أقصى تكرار لكل كلمة.', 'salah-seo'); ?></p>
                </div>
                <button type="button" id="salah-seo-add-link" class="inline-flex items-center gap-2 bg-slate-900 hover:bg-black text-white text-sm font-medium px-4 py-2 rounded-full transition">
                    <span class="dashicons dashicons-plus"></span>
                    <?php esc_html_e('إضافة قاعدة جديدة', 'salah-seo'); ?>
                </button>
            </header>
            <div class="p-6 space-y-4" id="salah-seo-links-wrapper">
                <?php if (!empty($link_rules)) :
                    foreach ($link_rules as $index => $rule) : ?>
                        <div class="salah-seo-link-row bg-slate-50 border border-slate-200 rounded-xl p-4 grid gap-4 md:grid-cols-[1fr_1fr_auto]" data-index="<?php echo esc_attr($index); ?>">
                            <div>
                                <label class="text-xs font-semibold text-slate-500 mb-1 block"><?php esc_html_e('الكلمة المستهدفة', 'salah-seo'); ?></label>
                                <input type="text" name="<?php echo esc_attr($this->option_name . '[internal_link_rules][' . $index . '][keyword]'); ?>" value="<?php echo esc_attr($rule['keyword']); ?>" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:ring-emerald-500 focus:border-emerald-500" required />
                            </div>
                            <div>
                                <label class="text-xs font-semibold text-slate-500 mb-1 block"><?php esc_html_e('الرابط الداخلي', 'salah-seo'); ?></label>
                                <input type="url" name="<?php echo esc_attr($this->option_name . '[internal_link_rules][' . $index . '][url]'); ?>" value="<?php echo esc_attr($rule['url']); ?>" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:ring-emerald-500 focus:border-emerald-500" required />
                            </div>
                            <div class="flex gap-3 items-end">
                                <div class="flex-1">
                                    <label class="text-xs font-semibold text-slate-500 mb-1 block"><?php esc_html_e('أقصى عدد للتكرار', 'salah-seo'); ?></label>
                                    <input type="number" min="1" name="<?php echo esc_attr($this->option_name . '[internal_link_rules][' . $index . '][repeats]'); ?>" value="<?php echo esc_attr(isset($rule['repeats']) ? (int) $rule['repeats'] : 1); ?>" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:ring-emerald-500 focus:border-emerald-500" />
                                </div>
                                <button type="button" class="salah-seo-remove-link inline-flex items-center justify-center h-10 w-10 rounded-full bg-red-100 text-red-600 hover:bg-red-600 hover:text-white transition" aria-label="<?php esc_attr_e('حذف القاعدة', 'salah-seo'); ?>">
                                    <span class="dashicons dashicons-trash"></span>
                                </button>
                            </div>
                        </div>
                    <?php endforeach;
                else : ?>
                    <div class="text-sm text-slate-500 bg-slate-50 border border-dashed border-slate-200 rounded-xl p-5 text-center">
                        <?php esc_html_e('لم يتم إضافة أي قواعد بعد. اضغط على زر "إضافة قاعدة جديدة" للبدء.', 'salah-seo'); ?>
                    </div>
                <?php endif; ?>
            </div>
        </section>

        <div class="flex justify-end">
            <?php submit_button(__('حفظ الإعدادات', 'salah-seo'), 'primary', 'submit', false, array('class' => 'px-6 py-2 text-base font-semibold rounded-full bg-emerald-600 hover:bg-emerald-700 transition')); ?>
        </div>
    </form>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mt-10">
        <section class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6 space-y-5">
            <header class="flex items-center justify-between">
                <div>
                    <h2 class="text-xl font-semibold text-slate-900"><?php esc_html_e('تحسين جماعي لمنتجات المتجر', 'salah-seo'); ?></h2>
                    <p class="text-sm text-slate-500 mt-1"><?php esc_html_e('يقوم بفحص كل منتج وتعبئة الحقول الفارغة فقط.', 'salah-seo'); ?></p>
                </div>
                <span class="inline-flex items-center gap-2 text-xs font-semibold text-emerald-600 bg-emerald-100 px-3 py-1 rounded-full">
                    <span class="dashicons dashicons-update"></span>
                    <?php esc_html_e('آمن ويعمل في الخلفية', 'salah-seo'); ?>
                </span>
            </header>
            <input type="hidden" id="salah_seo_bulk_nonce" value="<?php echo esc_attr($bulk_nonce); ?>" />
            <div class="flex flex-wrap gap-3">
                <button type="button" id="salah-seo-bulk-start" class="inline-flex items-center gap-2 px-5 py-3 rounded-full bg-slate-900 hover:bg-black text-white text-sm font-semibold transition">
                    <span class="dashicons dashicons-performance"></span>
                    <?php esc_html_e('بدء التحسين الجماعي', 'salah-seo'); ?>
                </button>
                <button type="button" id="salah-seo-bulk-stop" class="hidden inline-flex items-center gap-2 px-5 py-3 rounded-full bg-red-100 text-red-600 hover:bg-red-600 hover:text-white text-sm font-semibold transition">
                    <span class="dashicons dashicons-no"></span>
                    <?php esc_html_e('إيقاف العملية', 'salah-seo'); ?>
                </button>
            </div>
            <div id="salah-seo-bulk-progress" class="hidden space-y-4">
                <div class="w-full h-3 bg-slate-100 rounded-full overflow-hidden">
                    <div class="progress-bar h-full bg-gradient-to-r from-emerald-500 to-emerald-600 text-[11px] font-bold flex items-center justify-center text-white transition-all" style="width:0%">0%</div>
                </div>
                <div class="flex justify-between text-xs text-slate-500">
                    <span><?php esc_html_e('المُعالَج', 'salah-seo'); ?>: <span id="progress-current">0</span></span>
                    <span id="progress-status"><?php esc_html_e('جاري التحضير...', 'salah-seo'); ?></span>
                    <span><?php esc_html_e('الإجمالي', 'salah-seo'); ?>: <span id="progress-total">0</span></span>
                </div>
                <div class="border border-slate-200 rounded-xl bg-slate-50 p-4 max-h-48 overflow-y-auto text-xs text-slate-600" id="progress-log"></div>
            </div>
            <div id="salah-seo-bulk-results" class="hidden border border-emerald-200 bg-emerald-50 rounded-xl p-4 text-sm text-emerald-800">
                <h3 class="font-semibold mb-2"><?php esc_html_e('نتائج العملية', 'salah-seo'); ?></h3>
                <div id="results-summary"></div>
            </div>
        </section>

        <section class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6 space-y-5">
            <header class="flex items-center justify-between">
                <div>
                    <h2 class="text-xl font-semibold text-slate-900"><?php esc_html_e('إدارة الروابط الداخلية', 'salah-seo'); ?></h2>
                    <p class="text-sm text-slate-500 mt-1"><?php esc_html_e('تطبيق أو إزالة الروابط الداخلية من المقالات والمنتجات عبر خطوات متدرجة.', 'salah-seo'); ?></p>
                </div>
                <span class="inline-flex items-center gap-2 text-xs font-semibold text-slate-600 bg-slate-100 px-3 py-1 rounded-full">
                    <span class="dashicons dashicons-admin-links"></span>
                    <?php esc_html_e('يدعم المحتوى الكامل للموقع', 'salah-seo'); ?>
                </span>
            </header>
            <input type="hidden" id="salah_seo_links_nonce" value="<?php echo esc_attr($links_nonce); ?>" />
            <div class="flex flex-wrap gap-3">
                <button type="button" id="salah-seo-links-apply" class="inline-flex items-center gap-2 px-5 py-3 rounded-full bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-semibold transition">
                    <span class="dashicons dashicons-plus"></span>
                    <?php esc_html_e('تطبيق القواعد على كل المحتوى', 'salah-seo'); ?>
                </button>
                <button type="button" id="salah-seo-links-remove" class="inline-flex items-center gap-2 px-5 py-3 rounded-full bg-red-600 hover:bg-red-700 text-white text-sm font-semibold transition">
                    <span class="dashicons dashicons-trash"></span>
                    <?php esc_html_e('إزالة كل الروابط الداخلية', 'salah-seo'); ?>
                </button>
            </div>
            <div id="salah-seo-links-progress" class="hidden space-y-4">
                <div class="w-full h-3 bg-slate-100 rounded-full overflow-hidden">
                    <div id="salah-seo-links-bar" class="h-full bg-gradient-to-r from-slate-500 to-slate-700 text-[11px] font-bold flex items-center justify-center text-white transition-all" style="width:0%">0%</div>
                </div>
                <div class="flex justify-between text-xs text-slate-500">
                    <span><?php esc_html_e('المُعالَج', 'salah-seo'); ?>: <span id="links-current">0</span></span>
                    <span id="links-status"><?php esc_html_e('في انتظار البدء...', 'salah-seo'); ?></span>
                    <span><?php esc_html_e('الإجمالي', 'salah-seo'); ?>: <span id="links-total">0</span></span>
                </div>
                <div class="border border-slate-200 rounded-xl bg-slate-50 p-4 max-h-48 overflow-y-auto text-xs text-slate-600" id="links-log"></div>
            </div>
        </section>
    </div>
</div>
