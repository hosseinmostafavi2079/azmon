<?php
/**
 * کلاس امنیت
 * 
 * @package AzmonyarProfessional
 */

// جلوگیری از دسترسی مستقیم
if (!defined('ABSPATH')) {
    exit;
}

/**
 * کلاس مدیریت امنیت افزونه
 */
class Azmonyar_Security {
    
    /**
     * سازنده کلاس
     */
    public function __construct() {
        add_action('init', array($this, 'init'));
        
        // محافظت از فرم‌ها
        add_action('wp_loaded', array($this, 'process_forms'));
        
        // محدودیت‌های دسترسی
        add_action('wp', array($this, 'check_access_restrictions'));
        
        // پاک‌سازی ورودی‌ها
        add_filter('azmonyar_sanitize_input', array($this, 'sanitize_input'), 10, 2);
    }
    
    /**
     * راه‌اندازی اولیه
     */
    public function init() {
        // تنظیمات امنیتی
        $this->setup_security_headers();
        
        // محدودیت نرخ درخواست
        $this->setup_rate_limiting();
    }
    
    /**
     * تنظیم هدرهای امنیتی
     */
    private function setup_security_headers() {
        // فقط برای صفحات آزمون
        if ($this->is_exam_page()) {
            add_action('wp_head', array($this, 'add_security_headers'));
        }
    }
    
    /**
     * افزودن هدرهای امنیتی
     */
    public function add_security_headers() {
        ?>
        <script>
        // جلوگیری از کپی متن
        document.addEventListener('selectstart', function(e) {
            if (document.body.classList.contains('azmonyar-exam-active')) {
                e.preventDefault();
            }
        });
        
        // جلوگیری از کلیک راست
        document.addEventListener('contextmenu', function(e) {
            if (document.body.classList.contains('azmonyar-exam-active')) {
                e.preventDefault();
            }
        });
        
        // جلوگیری از کلیدهای میانبر
        document.addEventListener('keydown', function(e) {
            if (document.body.classList.contains('azmonyar-exam-active')) {
                // F12, Ctrl+Shift+I, Ctrl+U, Ctrl+S
                if (e.keyCode === 123 || 
                    (e.ctrlKey && e.shiftKey && e.keyCode === 73) ||
                    (e.ctrlKey && e.keyCode === 85) ||
                    (e.ctrlKey && e.keyCode === 83)) {
                    e.preventDefault();
                    return false;
                }
            }
        });
        
        // هشدار هنگام خروج از صفحه
        window.addEventListener('beforeunload', function(e) {
            if (window.azmonyarExamActive) {
                var message = '<?php _e('خروج از صفحه باعث از دست رفتن پیشرفت آزمون می‌شود. آیا مطمئن هستید؟', 'azmonyar'); ?>';
                e.returnValue = message;
                return message;
            }
        });
        </script>
        
        <style>
        .azmonyar-exam-active {
            -webkit-user-select: none;
            -moz-user-select: none;
            -ms-user-select: none;
            user-select: none;
        }
        
        .azmonyar-exam-active img {
            -webkit-user-drag: none;
            -khtml-user-drag: none;
            -moz-user-drag: none;
            -o-user-drag: none;
            user-drag: none;
        }
        </style>
        <?php
    }
    
    /**
     * تنظیم محدودیت نرخ درخواست
     */
    private function setup_rate_limiting() {
        // محدودیت برای درخواست‌های AJAX
        add_action('wp_ajax_azmonyar_start_exam', array($this, 'check_rate_limit'), 1);
        add_action('wp_ajax_azmonyar_submit_exam', array($this, 'check_rate_limit'), 1);
        add_action('wp_ajax_azmonyar_save_progress', array($this, 'check_rate_limit'), 1);
        
        add_action('wp_ajax_nopriv_azmonyar_start_exam', array($this, 'check_rate_limit'), 1);
        add_action('wp_ajax_nopriv_azmonyar_submit_exam', array($this, 'check_rate_limit'), 1);
        add_action('wp_ajax_nopriv_azmonyar_save_progress', array($this, 'check_rate_limit'), 1);
    }
    
    /**
     * بررسی محدودیت نرخ درخواست
     */
    public function check_rate_limit() {
        $user_ip = $this->get_user_ip();
        $action = $_POST['action'] ?? '';
        
        // کلید کش برای این IP و اکشن
        $cache_key = 'azmonyar_rate_limit_' . md5($user_ip . $action);
        
        // دریافت تعداد درخواست‌های قبلی
        $requests = get_transient($cache_key) ?: 0;
        
        // محدودیت‌های مختلف بر اساس نوع اکشن
        $limits = array(
            'azmonyar_start_exam' => array('max' => 5, 'period' => 300), // 5 بار در 5 دقیقه
            'azmonyar_submit_exam' => array('max' => 3, 'period' => 60), // 3 بار در دقیقه
            'azmonyar_save_progress' => array('max' => 60, 'period' => 60), // 60 بار در دقیقه
        );
        
        $limit = $limits[$action] ?? array('max' => 10, 'period' => 60);
        
        if ($requests >= $limit['max']) {
            wp_send_json_error(__('تعداد درخواست‌های شما بیش از حد مجاز است. لطفاً کمی صبر کنید.', 'azmonyar'));
        }
        
        // افزایش شمارنده
        set_transient($cache_key, $requests + 1, $limit['period']);
    }
    
    /**
     * پردازش فرم‌ها
     */
    public function process_forms() {
        // پردازش فرم واردسازی CSV
        if (isset($_POST['azmonyar_csv_import']) && wp_verify_nonce($_POST['csv_import_nonce'], 'azmonyar_csv_import')) {
            $this->process_csv_import_form();
        }
        
        // پردازش فرم تنظیمات
        if (isset($_POST['azmonyar_save_settings']) && wp_verify_nonce($_POST['settings_nonce'], 'azmonyar_settings')) {
            $this->process_settings_form();
        }
    }
    
    /**
     * پردازش فرم واردسازی CSV
     */
    private function process_csv_import_form() {
        // بررسی دسترسی
        if (!current_user_can('manage_options')) {
            wp_die(__('دسترسی غیرمجاز', 'azmonyar'));
        }
        
        // اعتبارسنجی فایل
        if (!isset($_FILES['csv_file']) || $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
            wp_redirect(add_query_arg('message', 'upload_error', wp_get_referer()));
            exit;
        }
        
        // بررسی نوع فایل
        $file_type = wp_check_filetype($_FILES['csv_file']['name']);
        if ($file_type['ext'] !== 'csv') {
            wp_redirect(add_query_arg('message', 'invalid_file_type', wp_get_referer()));
            exit;
        }
        
        // بررسی اندازه فایل (حداکثر 5MB)
        if ($_FILES['csv_file']['size'] > 5 * 1024 * 1024) {
            wp_redirect(add_query_arg('message', 'file_too_large', wp_get_referer()));
            exit;
        }
        
        // انجام واردسازی
        require_once AZMONYAR_PLUGIN_DIR . 'includes/class-azmonyar-csv-import.php';
        $importer = new Azmonyar_CSV_Import();
        $result = $importer->import_from_upload();
        
        if ($result['success']) {
            wp_redirect(add_query_arg('message', 'import_success', wp_get_referer()));
        } else {
            wp_redirect(add_query_arg('message', 'import_error', wp_get_referer()));
        }
        exit;
    }
    
    /**
     * پردازش فرم تنظیمات
     */
    private function process_settings_form() {
        // بررسی دسترسی
        if (!current_user_can('manage_options')) {
            wp_die(__('دسترسی غیرمجاز', 'azmonyar'));
        }
        
        // پاک‌سازی و ذخیره تنظیمات
        $settings = array();
        $allowed_settings = array(
            'exam_time_limit',
            'questions_per_page',
            'show_results_immediately',
            'passing_score',
            'allow_retake',
            'randomize_questions',
            'randomize_answers',
            'email_results',
            'certificate_enabled'
        );
        
        foreach ($allowed_settings as $setting) {
            if (isset($_POST[$setting])) {
                $settings[$setting] = $this->sanitize_input($_POST[$setting], $setting);
            }
        }
        
        update_option('azmonyar_options', $settings);
        
        wp_redirect(add_query_arg('message', 'settings_saved', wp_get_referer()));
        exit;
    }
    
    /**
     * بررسی محدودیت‌های دسترسی
     */
    public function check_access_restrictions() {
        // بررسی دسترسی به صفحات آزمون
        if ($this->is_exam_page()) {
            $this->check_exam_access();
        }
        
        // بررسی دسترسی به صفحات نتایج
        if ($this->is_result_page()) {
            $this->check_result_access();
        }
    }
    
    /**
     * بررسی دسترسی به آزمون
     */
    private function check_exam_access() {
        $exam_id = get_query_var('azmonyar_exam_id');
        if (!$exam_id) {
            return;
        }
        
        // بررسی وجود آزمون
        $exam = get_post($exam_id);
        if (!$exam || $exam->post_type !== 'azmoon') {
            wp_die(__('آزمون یافت نشد', 'azmonyar'), 404);
        }
        
        // بررسی وضعیت انتشار
        if ($exam->post_status !== 'publish') {
            wp_die(__('این آزمون در دسترس نیست', 'azmonyar'), 403);
        }
        
        // بررسی دسترسی کاربر
        if (!$this->user_has_exam_access($exam_id)) {
            wp_die(__('شما به این آزمون دسترسی ندارید', 'azmonyar'), 403);
        }
        
        // ثبت لاگ دسترسی
        $this->log_exam_access($exam_id);
    }
    
    /**
     * بررسی دسترسی به نتیجه
     */
    private function check_result_access() {
        $result_id = get_query_var('azmonyar_result_id');
        if (!$result_id) {
            return;
        }
        
        global $wpdb;
        $results_table = $wpdb->prefix . 'azmonyar_results';
        
        $result = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$results_table} WHERE id = %d",
            $result_id
        ));
        
        if (!$result) {
            wp_die(__('نتیجه یافت نشد', 'azmonyar'), 404);
        }
        
        // بررسی مالکیت
        if (!is_user_logged_in() || get_current_user_id() != $result->user_id) {
            wp_die(__('شما به این نتیجه دسترسی ندارید', 'azmonyar'), 403);
        }
    }
    
    /**
     * بررسی دسترسی کاربر به آزمون
     */
    private function user_has_exam_access($exam_id) {
        // مدیران همیشه دسترسی دارند
        if (current_user_can('manage_options')) {
            return true;
        }
        
        // بررسی محصول مرتبط
        $wc_product_id = get_post_meta($exam_id, '_azmonyar_wc_product_id', true);
        if (!$wc_product_id) {
            return true; // آزمون رایگان
        }
        
        // بررسی ورود کاربر
        if (!is_user_logged_in()) {
            return false;
        }
        
        // بررسی خرید محصول
        $user_id = get_current_user_id();
        return wc_customer_bought_product('', $user_id, $wc_product_id);
    }
    
    /**
     * ثبت لاگ دسترسی به آزمون
     */
    private function log_exam_access($exam_id) {
        $user_id = is_user_logged_in() ? get_current_user_id() : 0;
        $user_ip = $this->get_user_ip();
        
        // ثبت در لاگ
        error_log(sprintf(
            'Azmonyar Exam Access: User %d (IP: %s) accessed exam %d at %s',
            $user_id,
            $user_ip,
            $exam_id,
            current_time('mysql')
        ));
        
        // ذخیره در دیتابیس (اختیاری)
        if (get_option('azmonyar_log_access', false)) {
            global $wpdb;
            
            $wpdb->insert(
                $wpdb->prefix . 'azmonyar_access_log',
                array(
                    'user_id' => $user_id,
                    'exam_id' => $exam_id,
                    'ip_address' => $user_ip,
                    'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
                    'access_time' => current_time('mysql')
                ),
                array('%d', '%d', '%s', '%s', '%s')
            );
        }
    }
    
    /**
     * پاک‌سازی ورودی
     */
    public function sanitize_input($input, $type = 'text') {
        switch ($type) {
            case 'email':
                return sanitize_email($input);
                
            case 'url':
                return esc_url_raw($input);
                
            case 'int':
            case 'integer':
                return intval($input);
                
            case 'float':
                return floatval($input);
                
            case 'bool':
            case 'boolean':
                return (bool) $input;
                
            case 'textarea':
                return sanitize_textarea_field($input);
                
            case 'html':
                return wp_kses_post($input);
                
            case 'slug':
                return sanitize_title($input);
                
            case 'key':
                return sanitize_key($input);
                
            case 'filename':
                return sanitize_file_name($input);
                
            case 'array':
                if (is_array($input)) {
                    return array_map('sanitize_text_field', $input);
                }
                return array();
                
            case 'json':
                if (is_string($input)) {
                    $decoded = json_decode($input, true);
                    if (json_last_error() === JSON_ERROR_NONE) {
                        return $this->sanitize_input($decoded, 'array');
                    }
                }
                return array();
                
            case 'exam_time_limit':
            case 'questions_per_page':
            case 'passing_score':
                $value = intval($input);
                return max(1, min($value, 300)); // بین 1 تا 300
                
            case 'show_results_immediately':
            case 'allow_retake':
            case 'randomize_questions':
            case 'randomize_answers':
            case 'email_results':
            case 'certificate_enabled':
                return $input ? '1' : '0';
                
            default:
                return sanitize_text_field($input);
        }
    }
    
    /**
     * اعتبارسنجی داده‌های آزمون
     */
    public function validate_exam_data($data) {
        $errors = array();
        
        // بررسی عنوان
        if (empty($data['title'])) {
            $errors[] = __('عنوان آزمون الزامی است', 'azmonyar');
        }
        
        // بررسی مدت زمان
        if (!isset($data['time_limit']) || intval($data['time_limit']) < 1) {
            $errors[] = __('مدت زمان آزمون باید حداقل 1 دقیقه باشد', 'azmonyar');
        }
        
        // بررسی سوالات
        if (empty($data['questions']) || !is_array($data['questions'])) {
            $errors[] = __('آزمون باید حداقل یک سوال داشته باشد', 'azmonyar');
        }
        
        // بررسی نمره قبولی
        if (isset($data['passing_score'])) {
            $score = intval($data['passing_score']);
            if ($score < 0 || $score > 100) {
                $errors[] = __('نمره قبولی باید بین 0 تا 100 باشد', 'azmonyar');
            }
        }
        
        return $errors;
    }
    
    /**
     * اعتبارسنجی داده‌های سوال
     */
    public function validate_question_data($data) {
        $errors = array();
        
        // بررسی متن سوال
        if (empty($data['question'])) {
            $errors[] = __('متن سوال الزامی است', 'azmonyar');
        }
        
        // بررسی گزینه‌ها
        $options = array('option_a', 'option_b', 'option_c', 'option_d');
        foreach ($options as $option) {
            if (empty($data[$option])) {
                $errors[] = sprintf(__('گزینه %s الزامی است', 'azmonyar'), strtoupper(substr($option, -1)));
            }
        }
        
        // بررسی پاسخ صحیح
        if (empty($data['correct_answer']) || !in_array($data['correct_answer'], array('a', 'b', 'c', 'd'))) {
            $errors[] = __('پاسخ صحیح باید یکی از گزینه‌های a، b، c، d باشد', 'azmonyar');
        }
        
        return $errors;
    }
    
    /**
     * دریافت IP کاربر
     */
    private function get_user_ip() {
        $ip_keys = array(
            'HTTP_CF_CONNECTING_IP',     // Cloudflare
            'HTTP_CLIENT_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_FORWARDED',
            'HTTP_X_CLUSTER_CLIENT_IP',
            'HTTP_FORWARDED_FOR',
            'HTTP_FORWARDED',
            'REMOTE_ADDR'
        );
        
        foreach ($ip_keys as $key) {
            if (array_key_exists($key, $_SERVER) === true) {
                $ip = $_SERVER[$key];
                if (strpos($ip, ',') !== false) {
                    $ip = explode(',', $ip)[0];
                }
                $ip = trim($ip);
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }
        
        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }
    
    /**
     * بررسی اینکه آیا صفحه فعلی صفحه آزمون است
     */
    private function is_exam_page() {
        return get_query_var('azmonyar_exam_id') || 
               (is_page() && has_shortcode(get_post()->post_content, 'azmonyar_exam_page'));
    }
    
    /**
     * بررسی اینکه آیا صفحه فعلی صفحه نتیجه است
     */
    private function is_result_page() {
        return get_query_var('azmonyar_result_id') || 
               (is_page() && has_shortcode(get_post()->post_content, 'azmonyar_results_page'));
    }
    
    /**
     * رمزگذاری داده‌ها
     */
    public function encrypt_data($data) {
        if (!function_exists('openssl_encrypt')) {
            return base64_encode($data);
        }
        
        $key = $this->get_encryption_key();
        $iv = openssl_random_pseudo_bytes(16);
        $encrypted = openssl_encrypt($data, 'AES-256-CBC', $key, 0, $iv);
        
        return base64_encode($iv . $encrypted);
    }
    
    /**
     * رمزگشایی داده‌ها
     */
    public function decrypt_data($encrypted_data) {
        if (!function_exists('openssl_decrypt')) {
            return base64_decode($encrypted_data);
        }
        
        $key = $this->get_encryption_key();
        $data = base64_decode($encrypted_data);
        $iv = substr($data, 0, 16);
        $encrypted = substr($data, 16);
        
        return openssl_decrypt($encrypted, 'AES-256-CBC', $key, 0, $iv);
    }
    
    /**
     * دریافت کلید رمزگذاری
     */
    private function get_encryption_key() {
        $key = get_option('azmonyar_encryption_key');
        
        if (!$key) {
            $key = wp_generate_password(32, false);
            update_option('azmonyar_encryption_key', $key);
        }
        
        return hash('sha256', $key . SECURE_AUTH_KEY);
    }
    
    /**
     * پاک‌سازی فایل‌های موقت
     */
    public function cleanup_temp_files() {
        $upload_dir = wp_upload_dir();
        $temp_dir = $upload_dir['basedir'] . '/azmonyar-temp/';
        
        if (!is_dir($temp_dir)) {
            return;
        }
        
        $files = glob($temp_dir . '*');
        $now = time();
        
        foreach ($files as $file) {
            if (is_file($file) && ($now - filemtime($file)) > 3600) { // 1 ساعت
                unlink($file);
            }
        }
    }
    
    /**
     * بررسی سلامت فایل‌ها
     */
    public function check_file_integrity() {
        $core_files = array(
            AZMONYAR_PLUGIN_DIR . 'azmonyar-professional.php',
            AZMONYAR_PLUGIN_DIR . 'includes/class-azmonyar-security.php',
        );
        
        foreach ($core_files as $file) {
            if (!file_exists($file)) {
                error_log('Azmonyar Security Alert: Core file missing - ' . $file);
                continue;
            }
            
            $content = file_get_contents($file);
            if (strpos($content, '<?php') !== 0) {
                error_log('Azmonyar Security Alert: Suspicious content in - ' . $file);
            }
        }
    }
}