<?php
/**
 * Settings Page Template
 * 
 * Admin interface for Salah SEO plugin settings
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
    
    <div class="salah-seo-header">
        <p class="description">
            <?php _e('Configure automated SEO optimization settings for your WooCommerce products. The plugin will only populate empty fields and preserve any manual entries.', 'salah-seo'); ?>
        </p>
    </div>
    
    <form method="post" action="options.php">
        <?php
        settings_fields('salah_seo_settings_group');
        do_settings_sections('salah-seo-settings');
        ?>
        
        <div class="salah-seo-info-box">
            <h3><?php _e('How It Works', 'salah-seo'); ?></h3>
            <ul>
                <li><?php _e('✅ Only operates on empty fields - never overwrites manual entries', 'salah-seo'); ?></li>
                <li><?php _e('⚡ Triggers automatically when you save/update WooCommerce products', 'salah-seo'); ?></li>
                <li><?php _e('🎯 Zero frontend impact - all operations happen in admin panel', 'salah-seo'); ?></li>
                <li><?php _e('🔧 Fully customizable - enable/disable features and modify default texts', 'salah-seo'); ?></li>
            </ul>
        </div>
        
        <?php submit_button(__('Save Settings', 'salah-seo')); ?>
    </form>
    
    <div class="salah-seo-footer">
        <hr>
        <p>
            <strong><?php _e('Salah SEO v' . SALAH_SEO_VERSION, 'salah-seo'); ?></strong> | 
            <?php _e('Designed for nicotinekw.com', 'salah-seo'); ?> | 
            <?php _e('Following constitutional principles: Performance First, Smart Automation, User-Friendly Control', 'salah-seo'); ?>
        </p>
    </div>
</div>

<style>
.salah-seo-header {
    background: #f1f1f1;
    padding: 15px;
    border-left: 4px solid #0073aa;
    margin: 20px 0;
}

.salah-seo-info-box {
    background: #fff;
    border: 1px solid #ddd;
    padding: 20px;
    margin: 20px 0;
    border-radius: 4px;
}

.salah-seo-info-box h3 {
    margin-top: 0;
    color: #0073aa;
}

.salah-seo-info-box ul {
    list-style: none;
    padding: 0;
}

.salah-seo-info-box li {
    padding: 5px 0;
    font-size: 14px;
}

.salah-seo-footer {
    margin-top: 30px;
    color: #666;
    font-size: 12px;
}

.internal-link-row {
    margin-bottom: 10px;
    display: flex;
    gap: 10px;
    align-items: center;
}

.internal-link-row input[type="text"] {
    flex: 1;
}

.remove-link {
    background: #dc3232;
    color: white;
    border: none;
    padding: 5px 10px;
    cursor: pointer;
    border-radius: 3px;
}

.remove-link:hover {
    background: #a00;
}

#add-internal-link {
    margin-top: 10px;
    background: #0073aa;
    color: white;
    border: none;
    padding: 8px 15px;
    cursor: pointer;
    border-radius: 3px;
}

#add-internal-link:hover {
    background: #005a87;
}

.form-table th {
    width: 200px;
}

.form-table td {
    padding: 15px 10px;
}

.form-table input[type="checkbox"] {
    margin-right: 8px;
}

.form-table textarea {
    width: 100%;
    max-width: 500px;
}
</style>

<script>
jQuery(document).ready(function($) {
    // Add new internal link row
    $('#add-internal-link').on('click', function() {
        var container = $('#internal-links-container');
        var newRow = $('<div class="internal-link-row">' +
            '<input type="text" name="salah_seo_settings[internal_links][]" placeholder="<?php _e('URL', 'salah-seo'); ?>" class="regular-text url-field" />' +
            '<input type="text" placeholder="<?php _e('Keyword', 'salah-seo'); ?>" class="regular-text keyword-field" />' +
            '<button type="button" class="button remove-link"><?php _e('Remove', 'salah-seo'); ?></button>' +
            '</div>');
        container.append(newRow);
    });
    
    // Remove internal link row
    $(document).on('click', '.remove-link', function() {
        $(this).closest('.internal-link-row').remove();
    });
    
    // Update name attribute when keyword changes
    $(document).on('input', '.keyword-field', function() {
        var keyword = $(this).val();
        var urlField = $(this).siblings('.url-field');
        if (keyword) {
            urlField.attr('name', 'salah_seo_settings[internal_links][' + keyword + ']');
        }
    });
});
</script>
