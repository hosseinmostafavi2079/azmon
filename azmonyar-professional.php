<?php
/**
 * Plugin Name: آزمون‌یار حرفه‌ای
 * Plugin URI: https://hoseinmos.com
 * Description: افزونه کاملاً حرفه‌ای برای برگزاری آزمون‌های آنلاین چندگزینه‌ای، پولی و زمان‌دار به زبان فارسی
 * Version: 1.0.0
 * Author: hoseinmos
 * Author URI: https://hoseinmos.com
 * Text Domain: azmonyar
 * Domain Path: /languages
 * Requires at least: 5.0
 * Tested up to: 6.4
 * Requires PHP: 7.4
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * 
 * @package AzmonyarProfessional
 */

// جلوگیری از دسترسی مستقیم
if (!defined('ABSPATH')) {
    exit;
}

// تعریف ثابت‌های اصلی افزونه
define('AZMONYAR_VERSION', '1.0.0');
define('AZMONYAR_PLUGIN_FILE', __FILE__);
define('AZMONYAR_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('AZMONYAR_PLUGIN_URL', plugin_dir_url(__FILE__));
define('AZMONYAR_PLUGIN_BASENAME', plugin_basename(__FILE__));

/**
 * کلاس اصلی افزونه آزمون‌یار حرفه‌ای
 */
class AzmonyarProfessional {
    
    /**
     * نمونه منحصر به فرد کلاس
     */
    private static $instance = null;
    
    /**
     * دریافت نمونه منحصر به فرد کلاس
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * سازنده کلاس
     */
    private function __construct() {
        $this->init_hooks();
        $this->load_dependencies();
    }
    
    /**
     * بارگذاری وابستگی‌ها
     */
    private function load_dependencies() {
        // بارگذاری کلاس‌های اصلی
        require_once AZMONYAR_PLUGIN_DIR . 'includes/class-azmonyar-activator.php';
        require_once AZMONYAR_PLUGIN_DIR . 'includes/class-azmonyar-deactivator.php';
        require_once AZMONYAR_PLUGIN_DIR . 'includes/class-azmonyar-post-types.php';
        require_once AZMONYAR_PLUGIN_DIR . 'includes/class-azmonyar-admin.php';
        require_once AZMONYAR_PLUGIN_DIR . 'includes/class-azmonyar-frontend.php';
        require_once AZMONYAR_PLUGIN_DIR . 'includes/class-azmonyar-woocommerce.php';
        require_once AZMONYAR_PLUGIN_DIR . 'includes/class-azmonyar-security.php';
        require_once AZMONYAR_PLUGIN_DIR . 'includes/class-azmonyar-csv-import.php';
        require_once AZMONYAR_PLUGIN_DIR . 'includes/class-azmonyar-database.php';
    }
    
    /**
     * راه‌اندازی هوک‌های اصلی
     */
    private function init_hooks() {
        // هوک فعال‌سازی و غیرفعال‌سازی
        register_activation_hook(__FILE__, array('Azmonyar_Activator', 'activate'));
        register_deactivation_hook(__FILE__, array('Azmonyar_Deactivator', 'deactivate'));
        
        // بارگذاری متن‌های ترجمه
        add_action('plugins_loaded', array($this, 'load_textdomain'));
        
        // راه‌اندازی افزونه پس از بارگذاری وردپرس
        add_action('init', array($this, 'init'));
        
        // بارگذاری اسکریپت‌ها و استایل‌ها
        add_action('wp_enqueue_scripts', array($this, 'enqueue_frontend_scripts'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
    }
    
    /**
     * بارگذاری فایل‌های ترجمه
     */
    public function load_textdomain() {
        load_plugin_textdomain('azmonyar', false, dirname(AZMONYAR_PLUGIN_BASENAME) . '/languages');
    }
    
    /**
     * راه‌اندازی اولیه افزونه
     */
    public function init() {
        // بررسی وابستگی‌های ضروری
        if (!$this->check_requirements()) {
            return;
        }
        
        // راه‌اندازی Custom Post Types
        new Azmonyar_Post_Types();
        
        // راه‌اندازی پنل مدیریت
        if (is_admin()) {
            new Azmonyar_Admin();
        }
        
        // راه‌اندازی بخش کاربری
        new Azmonyar_Frontend();
        
        // ادغام با ووکامرس
        if (class_exists('WooCommerce')) {
            new Azmonyar_WooCommerce();
        }
        
        // راه‌اندازی امنیت
        new Azmonyar_Security();
        
        // راه‌اندازی پایگاه داده
        new Azmonyar_Database();
    }
    
    /**
     * بررسی وابستگی‌های ضروری
     */
    private function check_requirements() {
        $requirements_met = true;
        
        // بررسی ووکامرس
        if (!class_exists('WooCommerce')) {
            add_action('admin_notices', function() {
                echo '<div class="notice notice-error"><p>';
                echo __('افزونه آزمون‌یار حرفه‌ای نیاز به نصب و فعال‌سازی افزونه ووکامرس دارد.', 'azmonyar');
                echo '</p></div>';
            });
            $requirements_met = false;
        }
        
        return $requirements_met;
    }
    
    /**
     * بارگذاری اسکریپت‌های بخش کاربری
     */
    public function enqueue_frontend_scripts() {
        wp_enqueue_style(
            'azmonyar-frontend',
            AZMONYAR_PLUGIN_URL . 'assets/css/frontend.css',
            array(),
            AZMONYAR_VERSION
        );
        
        wp_enqueue_script(
            'azmonyar-frontend',
            AZMONYAR_PLUGIN_URL . 'assets/js/frontend.js',
            array('jquery'),
            AZMONYAR_VERSION,
            true
        );
        
        // ارسال متغیرهای JavaScript
        wp_localize_script('azmonyar-frontend', 'azmonyar_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('azmonyar_nonce'),
            'messages' => array(
                'time_up' => __('زمان آزمون به پایان رسید!', 'azmonyar'),
                'confirm_submit' => __('آیا از ارسال آزمون اطمینان دارید؟', 'azmonyar'),
            )
        ));
    }
    
    /**
     * بارگذاری اسکریپت‌های پنل مدیریت
     */
    public function enqueue_admin_scripts($hook) {
        // فقط در صفحات مربوط به آزمون‌یار
        if (strpos($hook, 'azmonyar') === false) {
            return;
        }
        
        wp_enqueue_style(
            'azmonyar-admin',
            AZMONYAR_PLUGIN_URL . 'assets/css/admin.css',
            array(),
            AZMONYAR_VERSION
        );
        
        wp_enqueue_script(
            'azmonyar-admin',
            AZMONYAR_PLUGIN_URL . 'assets/js/admin.js',
            array('jquery', 'jquery-ui-sortable'),
            AZMONYAR_VERSION,
            true
        );
        
        wp_localize_script('azmonyar-admin', 'azmonyar_admin_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('azmonyar_admin_nonce'),
        ));
    }
}

/**
 * راه‌اندازی افزونه
 */
function azmonyar_professional() {
    return AzmonyarProfessional::get_instance();
}

// شروع افزونه
azmonyar_professional();