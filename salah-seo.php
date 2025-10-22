<?php
/**
 * Plugin Name: Salah SEO
 * Plugin URI: https://nicotinekw.com
 * Description: Automated SEO optimization for WooCommerce products on nicotinekw.com. Automatically populates Rank Math SEO fields, optimizes images, adds internal links, and fixes canonical URLs while preserving manual entries.
 * Version: 1.0.0
 * Author: Salah
 * Author URI: https://nicotinekw.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: salah-seo
 * Domain Path: /languages
 * Requires at least: 6.0
 * Tested up to: 6.4
 * Requires PHP: 7.4
 * WC requires at least: 7.0
 * WC tested up to: 8.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('SALAH_SEO_VERSION', '1.0.0');
define('SALAH_SEO_PLUGIN_FILE', __FILE__);
define('SALAH_SEO_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('SALAH_SEO_PLUGIN_URL', plugin_dir_url(__FILE__));
define('SALAH_SEO_PLUGIN_BASENAME', plugin_basename(__FILE__));

/**
 * Main Salah SEO Plugin Class
 */
class Salah_SEO_Plugin {
    
    /**
     * Single instance of the plugin
     */
    private static $instance = null;
    
    /**
     * Get single instance
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     */
    private function __construct() {
        add_action('init', array($this, 'init'));
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
    }
    
    /**
     * Initialize the plugin
     */
    public function init() {
        // Check if required plugins are active
        if (!$this->check_dependencies()) {
            add_action('admin_notices', array($this, 'dependency_notice'));
            return;
        }
        
        // Load plugin files
        if (!$this->load_includes()) {
            return; // Stop initialization if files are missing
        }
        
        // Initialize admin interface
        if (is_admin()) {
            $this->init_admin();
        }
        
        // Initialize core functionality
        $this->init_core();
    }
    
    /**
     * Check plugin dependencies
     */
    private function check_dependencies() {
        // Check if WooCommerce is active
        if (!class_exists('WooCommerce')) {
            return false;
        }
        
        // Check if Rank Math is active
        if (!class_exists('RankMath')) {
            return false;
        }
        
        return true;
    }
    
    /**
     * Display dependency notice
     */
    public function dependency_notice() {
        ?>
        <div class="notice notice-error">
            <p>
                <strong><?php _e('Salah SEO', 'salah-seo'); ?>:</strong>
                <?php _e('This plugin requires WooCommerce and Rank Math SEO to be installed and activated.', 'salah-seo'); ?>
            </p>
        </div>
        <?php
    }
    
    /**
     * Load plugin includes
     */
    private function load_includes() {
        $required_files = array(
            'includes/class-salah-seo-core.php',
            'includes/class-salah-seo-helpers.php'
        );
        
        if (is_admin()) {
            $required_files[] = 'admin/class-salah-seo-admin.php';
        }
        
        foreach ($required_files as $file) {
            $file_path = SALAH_SEO_PLUGIN_DIR . $file;
            
            if (!file_exists($file_path)) {
                add_action('admin_notices', function() use ($file) {
                    echo '<div class="notice notice-error">';
                    echo '<p><strong>Salah SEO Error:</strong> Missing required file: ' . esc_html($file) . '</p>';
                    echo '<p>Please re-upload the complete plugin files or contact support.</p>';
                    echo '</div>';
                });
                return false;
            }
            
            require_once $file_path;
        }
        
        return true;
    }
    
    /**
     * Initialize admin interface
     */
    private function init_admin() {
        if (class_exists('Salah_SEO_Admin')) {
            new Salah_SEO_Admin();
        }
    }
    
    /**
     * Initialize core functionality
     */
    private function init_core() {
        if (class_exists('Salah_SEO_Core')) {
            new Salah_SEO_Core();
        }
    }
    
    /**
     * Plugin activation
     */
    public function activate() {
        // Set default options
        $default_options = array(
            'enable_focus_keyword' => true,
            'enable_meta_description' => true,
            'enable_short_description' => true,
            'enable_product_tags' => true,
            'enable_image_optimization' => true,
            'enable_internal_linking' => true,
            'enable_canonical_fix' => true,
            'default_meta_description' => 'متجر نيكوتين هو مصدرك الموثوق لمنتجات الفيب بالكويت حيث نوفر توصيل مجاني خلال ساعة واحدة',
            'default_short_description' => 'متجر نيكوتين هو مصدرك الموثوق لمنتجات الفيب بالكويت حيث نوفر توصيل مجاني خلال ساعة واحدة',
            'default_full_description' => 'أفضل منتجات الفيب وأكياس النيكوتين في الكويت. نوفر لك تشكيلة واسعة من أجهزة الفيب، بودات، نكهات، وأظرف نيكوتين أصلية 100%. تمتع بتجربة تدخين الكتروني آمنة، سهلة الاستخدام، وبأسعار تنافسية مع خدمة توصيل سريعة ومجانية داخل الكويت. منتجاتنا تناسب المبتدئين والمحترفين، وتشمل أشهر العلامات التجارية في مجال الفيب. اختر الآن البديل العصري للتدخين التقليدي واستمتع بجودة عالية وتجربة مختلفة.',
            'internal_links' => array(
                'أكياس النيكوتين' => 'https://nicotinekw.com/product-category/أكياس-النيكوتين/',
                'نيكوتين' => 'https://nicotinekw.com/natural-nicotine-in-the-body/',
                'فيب' => 'https://nicotinekw.com/الفرق-بين-سحبة-الزقارة-والفيب/',
                'نكهات' => 'https://nicotinekw.com/مكونات-نكهة-الفيب/',
                'الكويت' => 'https://nicotinekw.com/فيب-الكويت-دليلك-الشامل-لأفضل-المنتجات/'
            )
        );
        
        add_option('salah_seo_settings', $default_options);
        
        // Create a transient to show activation notice
        set_transient('salah_seo_activation_notice', true, 30);
    }
    
    /**
     * Plugin deactivation
     */
    public function deactivate() {
        // Clean up transients
        delete_transient('salah_seo_activation_notice');
        delete_transient('salah_seo_success_notice');
    }
}

// Initialize the plugin
Salah_SEO_Plugin::get_instance();
