<?php
/**
 * کلاس فعال‌سازی افزونه
 * 
 * @package AzmonyarProfessional
 */

// جلوگیری از دسترسی مستقیم
if (!defined('ABSPATH')) {
    exit;
}

/**
 * کلاس مدیریت فعال‌سازی افزونه
 */
class Azmonyar_Activator {
    
    /**
     * فعال‌سازی افزونه
     */
    public static function activate() {
        // ایجاد جداول پایگاه داده
        self::create_tables();
        
        // ایجاد صفحات پیش‌فرض
        self::create_default_pages();
        
        // تنظیمات پیش‌فرض
        self::set_default_options();
        
        // Flush rewrite rules
        flush_rewrite_rules();
    }
    
    /**
     * ایجاد جداول پایگاه داده
     */
    private static function create_tables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        // جدول نتایج آزمون‌ها
        $table_results = $wpdb->prefix . 'azmonyar_results';
        $sql_results = "CREATE TABLE $table_results (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            exam_id bigint(20) NOT NULL,
            score int(11) NOT NULL DEFAULT 0,
            total_questions int(11) NOT NULL DEFAULT 0,
            correct_answers int(11) NOT NULL DEFAULT 0,
            wrong_answers int(11) NOT NULL DEFAULT 0,
            percentage decimal(5,2) NOT NULL DEFAULT 0.00,
            time_taken int(11) NOT NULL DEFAULT 0,
            answers longtext,
            started_at datetime DEFAULT CURRENT_TIMESTAMP,
            completed_at datetime DEFAULT NULL,
            status varchar(20) DEFAULT 'started',
            PRIMARY KEY (id),
            KEY user_id (user_id),
            KEY exam_id (exam_id),
            KEY status (status)
        ) $charset_collate;";
        
        // جدول دسته‌بندی‌ها
        $table_categories = $wpdb->prefix . 'azmonyar_categories';
        $sql_categories = "CREATE TABLE $table_categories (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            name varchar(255) NOT NULL,
            slug varchar(255) NOT NULL,
            type varchar(50) NOT NULL,
            parent_id bigint(20) DEFAULT 0,
            description text,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY slug (slug),
            KEY type (type),
            KEY parent_id (parent_id)
        ) $charset_collate;";
        
        // جدول آمار کاربران
        $table_stats = $wpdb->prefix . 'azmonyar_user_stats';
        $sql_stats = "CREATE TABLE $table_stats (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            total_exams int(11) DEFAULT 0,
            passed_exams int(11) DEFAULT 0,
            failed_exams int(11) DEFAULT 0,
            average_score decimal(5,2) DEFAULT 0.00,
            total_time_spent int(11) DEFAULT 0,
            last_exam_date datetime DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY user_id (user_id)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql_results);
        dbDelta($sql_categories);
        dbDelta($sql_stats);
        
        // ایجاد ایندکس‌های اضافی برای بهینه‌سازی
        $wpdb->query("CREATE INDEX idx_results_user_exam ON $table_results (user_id, exam_id)");
        $wpdb->query("CREATE INDEX idx_results_completed ON $table_results (completed_at)");
    }
    
    /**
     * ایجاد صفحات پیش‌فرض
     */
    private static function create_default_pages() {
        // صفحه آزمون‌ها
        $exam_page = get_page_by_path('azmoon');
        if (!$exam_page) {
            wp_insert_post(array(
                'post_title' => __('آزمون‌ها', 'azmonyar'),
                'post_name' => 'azmoon',
                'post_content' => '[azmonyar_exam_page]',
                'post_status' => 'publish',
                'post_type' => 'page',
                'post_author' => 1,
            ));
        }
        
        // صفحه نتایج
        $results_page = get_page_by_path('natayej-azmoon');
        if (!$results_page) {
            wp_insert_post(array(
                'post_title' => __('نتایج آزمون', 'azmonyar'),
                'post_name' => 'natayej-azmoon',
                'post_content' => '[azmonyar_results_page]',
                'post_status' => 'publish',
                'post_type' => 'page',
                'post_author' => 1,
            ));
        }
    }
    
    /**
     * تنظیمات پیش‌فرض
     */
    private static function set_default_options() {
        $default_options = array(
            'azmonyar_exam_time_limit' => 60, // دقیقه
            'azmonyar_questions_per_page' => 1,
            'azmonyar_show_results_immediately' => 'yes',
            'azmonyar_passing_score' => 50, // درصد
            'azmonyar_allow_retake' => 'no',
            'azmonyar_randomize_questions' => 'yes',
            'azmonyar_randomize_answers' => 'yes',
            'azmonyar_email_results' => 'yes',
            'azmonyar_certificate_enabled' => 'no',
            'azmonyar_rtl_support' => 'yes',
        );
        
        foreach ($default_options as $option => $value) {
            if (get_option($option) === false) {
                add_option($option, $value);
            }
        }
        
        // ایجاد دسته‌بندی‌های پیش‌فرض
        self::create_default_categories();
    }
    
    /**
     * ایجاد دسته‌بندی‌های پیش‌فرض
     */
    private static function create_default_categories() {
        global $wpdb;
        
        $table_categories = $wpdb->prefix . 'azmonyar_categories';
        
        $default_categories = array(
            array(
                'name' => 'ریاضی',
                'slug' => 'math',
                'type' => 'subject',
                'parent_id' => 0,
                'description' => 'دروس ریاضی'
            ),
            array(
                'name' => 'فیزیک',
                'slug' => 'physics',
                'type' => 'subject',
                'parent_id' => 0,
                'description' => 'دروس فیزیک'
            ),
            array(
                'name' => 'شیمی',
                'slug' => 'chemistry',
                'type' => 'subject',
                'parent_id' => 0,
                'description' => 'دروس شیمی'
            ),
            array(
                'name' => 'زبان انگلیسی',
                'slug' => 'english',
                'type' => 'subject',
                'parent_id' => 0,
                'description' => 'دروس زبان انگلیسی'
            ),
            array(
                'name' => 'آسان',
                'slug' => 'easy',
                'type' => 'difficulty',
                'parent_id' => 0,
                'description' => 'سطح آسان'
            ),
            array(
                'name' => 'متوسط',
                'slug' => 'medium',
                'type' => 'difficulty',
                'parent_id' => 0,
                'description' => 'سطح متوسط'
            ),
            array(
                'name' => 'سخت',
                'slug' => 'hard',
                'type' => 'difficulty',
                'parent_id' => 0,
                'description' => 'سطح سخت'
            ),
        );
        
        foreach ($default_categories as $category) {
            $exists = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM $table_categories WHERE slug = %s",
                $category['slug']
            ));
            
            if (!$exists) {
                $wpdb->insert($table_categories, $category);
            }
        }
    }
}