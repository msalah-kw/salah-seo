<?php
/**
 * Installation Debug Helper
 * 
 * This file helps verify that all plugin files are properly uploaded
 * Access via: /wp-content/plugins/salah-seo/debug-installation.php
 */

// Security check
if (!defined('ABSPATH') && !isset($_GET['debug'])) {
    die('Direct access not allowed');
}

echo '<h2>Salah SEO Plugin - Installation Verification</h2>';

$plugin_dir = __DIR__;
$required_files = array(
    'salah-seo.php' => 'Main plugin file',
    'readme.txt' => 'Plugin documentation',
    'includes/class-salah-seo-core.php' => 'Core functionality',
    'includes/class-salah-seo-helpers.php' => 'Helper functions',
    'admin/class-salah-seo-admin.php' => 'Admin interface',
    'admin/views/settings-page.php' => 'Settings template',
    'admin/css/admin.css' => 'Admin styles',
    'admin/js/admin.js' => 'Admin scripts'
);

echo '<table border="1" cellpadding="5" cellspacing="0">';
echo '<tr><th>File</th><th>Status</th><th>Size</th><th>Description</th></tr>';

$all_present = true;

foreach ($required_files as $file => $description) {
    $file_path = $plugin_dir . '/' . $file;
    $exists = file_exists($file_path);
    $size = $exists ? filesize($file_path) : 0;
    
    if (!$exists) {
        $all_present = false;
    }
    
    echo '<tr>';
    echo '<td>' . esc_html($file) . '</td>';
    echo '<td style="color: ' . ($exists ? 'green' : 'red') . '">' . ($exists ? '✓ Present' : '✗ Missing') . '</td>';
    echo '<td>' . ($exists ? number_format($size) . ' bytes' : 'N/A') . '</td>';
    echo '<td>' . esc_html($description) . '</td>';
    echo '</tr>';
}

echo '</table>';

if ($all_present) {
    echo '<p style="color: green; font-weight: bold;">✓ All required files are present!</p>';
    echo '<p>You can now activate the plugin safely.</p>';
} else {
    echo '<p style="color: red; font-weight: bold;">✗ Some files are missing!</p>';
    echo '<p>Please re-upload the complete plugin package.</p>';
}

echo '<h3>Plugin Information</h3>';
echo '<ul>';
echo '<li><strong>Plugin Directory:</strong> ' . esc_html($plugin_dir) . '</li>';
echo '<li><strong>WordPress Version:</strong> ' . (function_exists('get_bloginfo') ? get_bloginfo('version') : 'Unknown') . '</li>';
echo '<li><strong>PHP Version:</strong> ' . PHP_VERSION . '</li>';
echo '<li><strong>Server:</strong> ' . $_SERVER['SERVER_SOFTWARE'] . '</li>';
echo '</ul>';

// Check WordPress plugins if available
if (function_exists('is_plugin_active')) {
    echo '<h3>Required Plugin Dependencies</h3>';
    echo '<ul>';
    echo '<li><strong>WooCommerce:</strong> ' . (is_plugin_active('woocommerce/woocommerce.php') ? '✓ Active' : '✗ Not Active') . '</li>';
    echo '<li><strong>Rank Math SEO:</strong> ' . (is_plugin_active('seo-by-rankmath/rank-math.php') ? '✓ Active' : '✗ Not Active') . '</li>';
    echo '</ul>';
}

function esc_html($text) {
    return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
}
?>
