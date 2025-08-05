<?php
/**
 * کلاس مدیریت پایگاه داده
 * 
 * @package AzmonyarProfessional
 */

// جلوگیری از دسترسی مستقیم
if (!defined('ABSPATH')) {
    exit;
}

/**
 * کلاس مدیریت پایگاه داده
 */
class Azmonyar_Database {
    
    /**
     * سازنده کلاس
     */
    public function __construct() {
        add_action('init', array($this, 'init'));
        
        // بهینه‌سازی دوره‌ای
        add_action('azmonyar_daily_cleanup', array($this, 'daily_cleanup'));
        add_action('azmonyar_weekly_optimization', array($this, 'weekly_optimization'));
        
        // زمان‌بندی کرون جاب‌ها
        if (!wp_next_scheduled('azmonyar_daily_cleanup')) {
            wp_schedule_event(time(), 'daily', 'azmonyar_daily_cleanup');
        }
        
        if (!wp_next_scheduled('azmonyar_weekly_optimization')) {
            wp_schedule_event(time(), 'weekly', 'azmonyar_weekly_optimization');
        }
    }
    
    /**
     * راه‌اندازی اولیه
     */
    public function init() {
        // بررسی و بروزرسانی ساختار جداول
        $this->check_database_version();
    }
    
    /**
     * بررسی نسخه پایگاه داده
     */
    private function check_database_version() {
        $current_version = get_option('azmonyar_db_version', '1.0.0');
        
        if (version_compare($current_version, AZMONYAR_VERSION, '<')) {
            $this->upgrade_database($current_version);
            update_option('azmonyar_db_version', AZMONYAR_VERSION);
        }
    }
    
    /**
     * ارتقاء پایگاه داده
     */
    private function upgrade_database($from_version) {
        global $wpdb;
        
        // ارتقاء از نسخه‌های قدیمی‌تر
        if (version_compare($from_version, '1.0.1', '<')) {
            // افزودن ستون‌های جدید به جدول نتایج
            $results_table = $wpdb->prefix . 'azmonyar_results';
            
            $wpdb->query("ALTER TABLE {$results_table} 
                         ADD COLUMN IF NOT EXISTS `ip_address` VARCHAR(45) DEFAULT NULL AFTER `status`,
                         ADD COLUMN IF NOT EXISTS `user_agent` TEXT DEFAULT NULL AFTER `ip_address`");
        }
        
        if (version_compare($from_version, '1.0.2', '<')) {
            // ایجاد جدول لاگ دسترسی
            $this->create_access_log_table();
        }
        
        // بازسازی ایندکس‌ها
        $this->rebuild_indexes();
    }
    
    /**
     * ایجاد جدول لاگ دسترسی
     */
    private function create_access_log_table() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        $table_name = $wpdb->prefix . 'azmonyar_access_log';
        
        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) DEFAULT NULL,
            exam_id bigint(20) NOT NULL,
            ip_address varchar(45) NOT NULL,
            user_agent text,
            access_time datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY user_id (user_id),
            KEY exam_id (exam_id),
            KEY access_time (access_time),
            KEY ip_address (ip_address)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
    
    /**
     * بازسازی ایندکس‌ها
     */
    private function rebuild_indexes() {
        global $wpdb;
        
        $tables = array(
            $wpdb->prefix . 'azmonyar_results',
            $wpdb->prefix . 'azmonyar_categories',
            $wpdb->prefix . 'azmonyar_user_stats'
        );
        
        foreach ($tables as $table) {
            if ($wpdb->get_var("SHOW TABLES LIKE '$table'") == $table) {
                $wpdb->query("OPTIMIZE TABLE $table");
            }
        }
    }
    
    /**
     * پاک‌سازی روزانه
     */
    public function daily_cleanup() {
        $this->cleanup_expired_sessions();
        $this->cleanup_old_logs();
        $this->cleanup_temp_data();
    }
    
    /**
     * بهینه‌سازی هفتگی
     */
    public function weekly_optimization() {
        $this->optimize_tables();
        $this->analyze_performance();
        $this->update_statistics();
    }
    
    /**
     * پاک‌سازی جلسات منقضی شده
     */
    private function cleanup_expired_sessions() {
        global $wpdb;
        
        $results_table = $wpdb->prefix . 'azmonyar_results';
        
        // حذف آزمون‌های شروع شده اما تکمیل نشده بیش از 24 ساعت
        $wpdb->query($wpdb->prepare(
            "UPDATE {$results_table} 
             SET status = 'expired' 
             WHERE status = 'started' 
             AND started_at < %s",
            date('Y-m-d H:i:s', strtotime('-24 hours'))
        ));
        
        // حذف رکوردهای منقضی شده بیش از 7 روز
        $wpdb->query($wpdb->prepare(
            "DELETE FROM {$results_table} 
             WHERE status = 'expired' 
             AND started_at < %s",
            date('Y-m-d H:i:s', strtotime('-7 days'))
        ));
    }
    
    /**
     * پاک‌سازی لاگ‌های قدیمی
     */
    private function cleanup_old_logs() {
        global $wpdb;
        
        $access_log_table = $wpdb->prefix . 'azmonyar_access_log';
        
        // حذف لاگ‌های بیش از 30 روز
        if ($wpdb->get_var("SHOW TABLES LIKE '$access_log_table'") == $access_log_table) {
            $wpdb->query($wpdb->prepare(
                "DELETE FROM {$access_log_table} 
                 WHERE access_time < %s",
                date('Y-m-d H:i:s', strtotime('-30 days'))
            ));
        }
        
        // پاک‌سازی transients منقضی شده
        $wpdb->query(
            "DELETE FROM {$wpdb->options} 
             WHERE option_name LIKE '_transient_timeout_azmonyar_%' 
             AND option_value < UNIX_TIMESTAMP()"
        );
        
        $wpdb->query(
            "DELETE FROM {$wpdb->options} 
             WHERE option_name LIKE '_transient_azmonyar_%' 
             AND option_name NOT IN (
                 SELECT REPLACE(option_name, '_transient_timeout_', '_transient_') 
                 FROM {$wpdb->options} 
                 WHERE option_name LIKE '_transient_timeout_azmonyar_%'
             )"
        );
    }
    
    /**
     * پاک‌سازی داده‌های موقت
     */
    private function cleanup_temp_data() {
        // پاک‌سازی فایل‌های موقت
        $upload_dir = wp_upload_dir();
        $temp_dir = $upload_dir['basedir'] . '/azmonyar-temp/';
        
        if (is_dir($temp_dir)) {
            $files = glob($temp_dir . '*');
            $now = time();
            
            foreach ($files as $file) {
                if (is_file($file) && ($now - filemtime($file)) > 86400) { // 24 ساعت
                    unlink($file);
                }
            }
        }
        
        // پاک‌سازی کش‌های قدیمی
        wp_cache_flush();
    }
    
    /**
     * بهینه‌سازی جداول
     */
    private function optimize_tables() {
        global $wpdb;
        
        $azmonyar_tables = array(
            $wpdb->prefix . 'azmonyar_results',
            $wpdb->prefix . 'azmonyar_categories', 
            $wpdb->prefix . 'azmonyar_user_stats',
            $wpdb->prefix . 'azmonyar_access_log'
        );
        
        foreach ($azmonyar_tables as $table) {
            if ($wpdb->get_var("SHOW TABLES LIKE '$table'") == $table) {
                $wpdb->query("OPTIMIZE TABLE $table");
            }
        }
    }
    
    /**
     * تجزیه و تحلیل عملکرد
     */
    private function analyze_performance() {
        global $wpdb;
        
        $results_table = $wpdb->prefix . 'azmonyar_results';
        
        // بررسی کوئری‌های کند
        $slow_queries = $wpdb->get_results(
            "SELECT exam_id, COUNT(*) as count, AVG(time_taken) as avg_time
             FROM {$results_table} 
             WHERE status = 'completed' 
             AND completed_at > DATE_SUB(NOW(), INTERVAL 7 DAY)
             GROUP BY exam_id
             HAVING avg_time > 3600" // بیش از 1 ساعت
        );
        
        if (!empty($slow_queries)) {
            // ثبت هشدار
            error_log('Azmonyar Performance Warning: Slow exams detected - ' . json_encode($slow_queries));
        }
        
        // بررسی استفاده از فضای دیسک
        $table_sizes = $wpdb->get_results(
            "SELECT table_name, 
                    ROUND(((data_length + index_length) / 1024 / 1024), 2) AS 'size_mb'
             FROM information_schema.TABLES 
             WHERE table_schema = DATABASE() 
             AND table_name LIKE '{$wpdb->prefix}azmonyar_%'"
        );
        
        update_option('azmonyar_table_sizes', $table_sizes);
    }
    
    /**
     * بروزرسانی آمار
     */
    private function update_statistics() {
        global $wpdb;
        
        $results_table = $wpdb->prefix . 'azmonyar_results';
        $stats_table = $wpdb->prefix . 'azmonyar_user_stats';
        
        // آمار کلی سیستم
        $system_stats = array(
            'total_exams' => wp_count_posts('azmoon')->publish,
            'total_questions' => wp_count_posts('soal')->publish,
            'total_results' => $wpdb->get_var("SELECT COUNT(*) FROM {$results_table}"),
            'completed_results' => $wpdb->get_var("SELECT COUNT(*) FROM {$results_table} WHERE status = 'completed'"),
            'average_score' => $wpdb->get_var("SELECT AVG(percentage) FROM {$results_table} WHERE status = 'completed'"),
            'total_users' => $wpdb->get_var("SELECT COUNT(DISTINCT user_id) FROM {$results_table}"),
        );
        
        update_option('azmonyar_system_stats', $system_stats);
        
        // آمار عملکرد آزمون‌ها
        $exam_stats = $wpdb->get_results(
            "SELECT 
                exam_id,
                COUNT(*) as total_attempts,
                COUNT(CASE WHEN status = 'completed' THEN 1 END) as completed_attempts,
                AVG(CASE WHEN status = 'completed' THEN percentage END) as avg_score,
                AVG(CASE WHEN status = 'completed' THEN time_taken END) as avg_time
             FROM {$results_table}
             GROUP BY exam_id"
        );
        
        foreach ($exam_stats as $stat) {
            update_post_meta($stat->exam_id, '_azmonyar_exam_stats', array(
                'total_attempts' => $stat->total_attempts,
                'completed_attempts' => $stat->completed_attempts,
                'completion_rate' => $stat->total_attempts > 0 ? ($stat->completed_attempts / $stat->total_attempts) * 100 : 0,
                'average_score' => round($stat->avg_score, 2),
                'average_time' => round($stat->avg_time, 0)
            ));
        }
    }
    
    /**
     * دریافت آمار آزمون
     */
    public function get_exam_stats($exam_id) {
        global $wpdb;
        
        $results_table = $wpdb->prefix . 'azmonyar_results';
        
        $stats = $wpdb->get_row($wpdb->prepare(
            "SELECT 
                COUNT(*) as total_attempts,
                COUNT(CASE WHEN status = 'completed' THEN 1 END) as completed_attempts,
                AVG(CASE WHEN status = 'completed' THEN percentage END) as avg_score,
                MAX(CASE WHEN status = 'completed' THEN percentage END) as max_score,
                MIN(CASE WHEN status = 'completed' THEN percentage END) as min_score,
                AVG(CASE WHEN status = 'completed' THEN time_taken END) as avg_time
             FROM {$results_table}
             WHERE exam_id = %d",
            $exam_id
        ));
        
        if ($stats) {
            $stats->completion_rate = $stats->total_attempts > 0 ? 
                ($stats->completed_attempts / $stats->total_attempts) * 100 : 0;
            $stats->pass_rate = $wpdb->get_var($wpdb->prepare(
                "SELECT (COUNT(*) / %d) * 100
                 FROM {$results_table}
                 WHERE exam_id = %d AND status = 'completed' AND percentage >= 50",
                $stats->completed_attempts ?: 1,
                $exam_id
            ));
        }
        
        return $stats;
    }
    
    /**
     * دریافت آمار کاربر
     */
    public function get_user_stats($user_id) {
        global $wpdb;
        
        $stats_table = $wpdb->prefix . 'azmonyar_user_stats';
        $results_table = $wpdb->prefix . 'azmonyar_results';
        
        $stats = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$stats_table} WHERE user_id = %d",
            $user_id
        ));
        
        if (!$stats) {
            // ایجاد آمار جدید
            $results = $wpdb->get_results($wpdb->prepare(
                "SELECT 
                    COUNT(*) as total_exams,
                    COUNT(CASE WHEN percentage >= 50 THEN 1 END) as passed_exams,
                    AVG(percentage) as average_score,
                    SUM(time_taken) as total_time_spent
                 FROM {$results_table}
                 WHERE user_id = %d AND status = 'completed'",
                $user_id
            ));
            
            if (!empty($results)) {
                $result = $results[0];
                $stats = (object) array(
                    'user_id' => $user_id,
                    'total_exams' => $result->total_exams,
                    'passed_exams' => $result->passed_exams,
                    'failed_exams' => $result->total_exams - $result->passed_exams,
                    'average_score' => round($result->average_score, 2),
                    'total_time_spent' => $result->total_time_spent
                );
            }
        }
        
        return $stats;
    }
    
    /**
     * جستجوی پیشرفته سوالات
     */
    public function search_questions($args = array()) {
        global $wpdb;
        
        $defaults = array(
            'search' => '',
            'subject' => '',
            'difficulty' => '',
            'limit' => 20,
            'offset' => 0,
            'orderby' => 'date',
            'order' => 'DESC'
        );
        
        $args = wp_parse_args($args, $defaults);
        
        $where_clauses = array("p.post_type = 'soal'", "p.post_status = 'publish'");
        $join_clauses = array();
        
        // جستجو در متن
        if (!empty($args['search'])) {
            $search = '%' . $wpdb->esc_like($args['search']) . '%';
            $where_clauses[] = $wpdb->prepare(
                "(p.post_title LIKE %s OR p.post_content LIKE %s)",
                $search, $search
            );
        }
        
        // فیلتر بر اساس رشته
        if (!empty($args['subject'])) {
            $join_clauses[] = "LEFT JOIN {$wpdb->term_relationships} tr_subject ON p.ID = tr_subject.object_id";
            $join_clauses[] = "LEFT JOIN {$wpdb->term_taxonomy} tt_subject ON tr_subject.term_taxonomy_id = tt_subject.term_taxonomy_id";
            $join_clauses[] = "LEFT JOIN {$wpdb->terms} t_subject ON tt_subject.term_id = t_subject.term_id";
            
            $where_clauses[] = $wpdb->prepare(
                "tt_subject.taxonomy = 'azmonyar_subject' AND t_subject.slug = %s",
                $args['subject']
            );
        }
        
        // فیلتر بر اساس سطح دشواری
        if (!empty($args['difficulty'])) {
            $join_clauses[] = "LEFT JOIN {$wpdb->term_relationships} tr_diff ON p.ID = tr_diff.object_id";
            $join_clauses[] = "LEFT JOIN {$wpdb->term_taxonomy} tt_diff ON tr_diff.term_taxonomy_id = tt_diff.term_taxonomy_id";
            $join_clauses[] = "LEFT JOIN {$wpdb->terms} t_diff ON tt_diff.term_id = t_diff.term_id";
            
            $where_clauses[] = $wpdb->prepare(
                "tt_diff.taxonomy = 'azmonyar_difficulty' AND t_diff.slug = %s",
                $args['difficulty']
            );
        }
        
        // ترتیب
        $orderby = sanitize_sql_orderby($args['orderby']);
        $order = strtoupper($args['order']) === 'ASC' ? 'ASC' : 'DESC';
        
        // ساخت کوئری
        $joins = !empty($join_clauses) ? implode(' ', array_unique($join_clauses)) : '';
        $where = 'WHERE ' . implode(' AND ', $where_clauses);
        $limit = $wpdb->prepare('LIMIT %d, %d', $args['offset'], $args['limit']);
        
        $sql = "SELECT DISTINCT p.* 
                FROM {$wpdb->posts} p 
                {$joins}
                {$where}
                ORDER BY p.{$orderby} {$order}
                {$limit}";
        
        return $wpdb->get_results($sql);
    }
    
    /**
     * گزارش تفصیلی نتایج
     */
    public function get_detailed_results($exam_id, $args = array()) {
        global $wpdb;
        
        $defaults = array(
            'user_id' => null,
            'date_from' => null,
            'date_to' => null,
            'status' => 'completed',
            'limit' => 50,
            'offset' => 0
        );
        
        $args = wp_parse_args($args, $defaults);
        
        $results_table = $wpdb->prefix . 'azmonyar_results';
        $where_clauses = array('exam_id = %d');
        $params = array($exam_id);
        
        if (!empty($args['user_id'])) {
            $where_clauses[] = 'user_id = %d';
            $params[] = $args['user_id'];
        }
        
        if (!empty($args['status'])) {
            $where_clauses[] = 'status = %s';
            $params[] = $args['status'];
        }
        
        if (!empty($args['date_from'])) {
            $where_clauses[] = 'completed_at >= %s';
            $params[] = $args['date_from'];
        }
        
        if (!empty($args['date_to'])) {
            $where_clauses[] = 'completed_at <= %s';
            $params[] = $args['date_to'];
        }
        
        $where = 'WHERE ' . implode(' AND ', $where_clauses);
        $limit = $wpdb->prepare('LIMIT %d, %d', $args['offset'], $args['limit']);
        
        $sql = "SELECT r.*, u.display_name, u.user_email
                FROM {$results_table} r
                LEFT JOIN {$wpdb->users} u ON r.user_id = u.ID
                {$where}
                ORDER BY r.completed_at DESC
                {$limit}";
        
        return $wpdb->get_results($wpdb->prepare($sql, $params));
    }
    
    /**
     * بکاپ داده‌ها
     */
    public function backup_data() {
        global $wpdb;
        
        $backup_data = array();
        
        // بکاپ آزمون‌ها
        $exams = get_posts(array(
            'post_type' => 'azmoon',
            'posts_per_page' => -1,
            'post_status' => 'any'
        ));
        
        foreach ($exams as $exam) {
            $backup_data['exams'][] = array(
                'post' => $exam,
                'meta' => get_post_meta($exam->ID),
                'terms' => wp_get_post_terms($exam->ID, array('azmonyar_subject', 'azmonyar_difficulty'))
            );
        }
        
        // بکاپ سوالات
        $questions = get_posts(array(
            'post_type' => 'soal',
            'posts_per_page' => -1,
            'post_status' => 'any'
        ));
        
        foreach ($questions as $question) {
            $backup_data['questions'][] = array(
                'post' => $question,
                'meta' => get_post_meta($question->ID),
                'terms' => wp_get_post_terms($question->ID, array('azmonyar_subject', 'azmonyar_difficulty'))
            );
        }
        
        // بکاپ جداول سفارشی
        $custom_tables = array(
            'azmonyar_results',
            'azmonyar_categories',
            'azmonyar_user_stats'
        );
        
        foreach ($custom_tables as $table) {
            $full_table_name = $wpdb->prefix . $table;
            if ($wpdb->get_var("SHOW TABLES LIKE '$full_table_name'") == $full_table_name) {
                $backup_data['tables'][$table] = $wpdb->get_results("SELECT * FROM $full_table_name", ARRAY_A);
            }
        }
        
        // ذخیره بکاپ
        $upload_dir = wp_upload_dir();
        $backup_dir = $upload_dir['basedir'] . '/azmonyar-backups/';
        
        if (!file_exists($backup_dir)) {
            wp_mkdir_p($backup_dir);
        }
        
        $backup_file = $backup_dir . 'azmonyar-backup-' . date('Y-m-d-H-i-s') . '.json';
        file_put_contents($backup_file, json_encode($backup_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        
        return $backup_file;
    }
    
    /**
     * بازیابی داده‌ها
     */
    public function restore_data($backup_file) {
        if (!file_exists($backup_file)) {
            return new WP_Error('file_not_found', __('فایل بکاپ یافت نشد', 'azmonyar'));
        }
        
        $backup_data = json_decode(file_get_contents($backup_file), true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            return new WP_Error('invalid_backup', __('فایل بکاپ معتبر نیست', 'azmonyar'));
        }
        
        // بازیابی آزمون‌ها
        if (!empty($backup_data['exams'])) {
            foreach ($backup_data['exams'] as $exam_data) {
                $post_id = wp_insert_post($exam_data['post']);
                if (!is_wp_error($post_id)) {
                    // بازیابی متاداده
                    foreach ($exam_data['meta'] as $key => $values) {
                        foreach ($values as $value) {
                            add_post_meta($post_id, $key, maybe_unserialize($value));
                        }
                    }
                    
                    // بازیابی تکسونومی‌ها
                    foreach ($exam_data['terms'] as $term) {
                        wp_set_post_terms($post_id, array($term->term_id), $term->taxonomy, true);
                    }
                }
            }
        }
        
        return true;
    }
}