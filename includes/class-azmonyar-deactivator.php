<?php
/**
 * کلاس غیرفعال‌سازی افزونه
 * 
 * @package AzmonyarProfessional
 */

// جلوگیری از دسترسی مستقیم
if (!defined('ABSPATH')) {
    exit;
}

/**
 * کلاس مدیریت غیرفعال‌سازی افزونه
 */
class Azmonyar_Deactivator {
    
    /**
     * غیرفعال‌سازی افزونه
     */
    public static function deactivate() {
        // Flush rewrite rules
        flush_rewrite_rules();
        
        // پاک کردن کش‌های موقت
        self::clear_temp_cache();
        
        // لغو کرون جاب‌ها
        self::clear_scheduled_events();
    }
    
    /**
     * پاک کردن کش‌های موقت
     */
    private static function clear_temp_cache() {
        // پاک کردن transients مربوط به افزونه
        global $wpdb;
        
        $wpdb->query(
            "DELETE FROM {$wpdb->options} 
             WHERE option_name LIKE '_transient_azmonyar_%' 
             OR option_name LIKE '_transient_timeout_azmonyar_%'"
        );
    }
    
    /**
     * لغو رویدادهای زمان‌بندی شده
     */
    private static function clear_scheduled_events() {
        // لغو کرون جاب‌های افزونه
        wp_clear_scheduled_hook('azmonyar_cleanup_expired_results');
        wp_clear_scheduled_hook('azmonyar_send_reminder_emails');
        wp_clear_scheduled_hook('azmonyar_generate_reports');
    }
}