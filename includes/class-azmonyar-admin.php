<?php
/**
 * کلاس مدیریت پنل ادمین
 * 
 * @package AzmonyarProfessional
 */

// جلوگیری از دسترسی مستقیم
if (!defined('ABSPATH')) {
    exit;
}

/**
 * کلاس مدیریت پنل ادمین
 */
class Azmonyar_Admin {
    
    /**
     * سازنده کلاس
     */
    public function __construct() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'admin_init'));
        add_action('wp_ajax_azmonyar_get_questions', array($this, 'ajax_get_questions'));
        add_action('wp_ajax_azmonyar_import_csv', array($this, 'ajax_import_csv'));
        add_action('wp_ajax_azmonyar_export_results', array($this, 'ajax_export_results'));
    }
    
    /**
     * افزودن منوی ادمین
     */
    public function add_admin_menu() {
        // منوی اصلی آزمون‌یار
        add_menu_page(
            __('آزمون‌یار حرفه‌ای', 'azmonyar'),
            __('آزمون‌یار', 'azmonyar'),
            'manage_options',
            'azmonyar',
            array($this, 'dashboard_page'),
            'dashicons-clipboard',
            25
        );
        
        // زیرمنو داشبورد
        add_submenu_page(
            'azmonyar',
            __('داشبورد', 'azmonyar'),
            __('داشبورد', 'azmonyar'),
            'manage_options',
            'azmonyar',
            array($this, 'dashboard_page')
        );
        
        // زیرمنو آزمون‌ها
        add_submenu_page(
            'azmonyar',
            __('آزمون‌ها', 'azmonyar'),
            __('آزمون‌ها', 'azmonyar'),
            'manage_options',
            'edit.php?post_type=azmoon'
        );
        
        // زیرمنو افزودن آزمون جدید
        add_submenu_page(
            'azmonyar',
            __('افزودن آزمون', 'azmonyar'),
            __('افزودن آزمون', 'azmonyar'),
            'manage_options',
            'post-new.php?post_type=azmoon'
        );
        
        // زیرمنو سوالات
        add_submenu_page(
            'azmonyar',
            __('سوالات', 'azmonyar'),
            __('سوالات', 'azmonyar'),
            'manage_options',
            'edit.php?post_type=soal'
        );
        
        // زیرمنو افزودن سوال جدید
        add_submenu_page(
            'azmonyar',
            __('افزودن سوال', 'azmonyar'),
            __('افزودن سوال', 'azmonyar'),
            'manage_options',
            'post-new.php?post_type=soal'
        );
        
        // زیرمنو دسته‌بندی‌ها
        add_submenu_page(
            'azmonyar',
            __('دسته‌بندی‌ها', 'azmonyar'),
            __('دسته‌بندی‌ها', 'azmonyar'),
            'manage_options',
            'azmonyar-categories',
            array($this, 'categories_page')
        );
        
        // زیرمنو نتایج کاربران
        add_submenu_page(
            'azmonyar',
            __('نتایج کاربران', 'azmonyar'),
            __('نتایج کاربران', 'azmonyar'),
            'manage_options',
            'azmonyar-results',
            array($this, 'results_page')
        );
        
        // زیرمنو گزارش‌ها
        add_submenu_page(
            'azmonyar',
            __('گزارش‌ها', 'azmonyar'),
            __('گزارش‌ها', 'azmonyar'),
            'manage_options',
            'azmonyar-reports',
            array($this, 'reports_page')
        );
        
        // زیرمنو واردسازی CSV
        add_submenu_page(
            'azmonyar',
            __('واردسازی CSV', 'azmonyar'),
            __('واردسازی CSV', 'azmonyar'),
            'manage_options',
            'azmonyar-import',
            array($this, 'import_page')
        );
        
        // زیرمنو تنظیمات
        add_submenu_page(
            'azmonyar',
            __('تنظیمات', 'azmonyar'),
            __('تنظیمات', 'azmonyar'),
            'manage_options',
            'azmonyar-settings',
            array($this, 'settings_page')
        );
    }
    
    /**
     * راه‌اندازی اولیه ادمین
     */
    public function admin_init() {
        // ثبت تنظیمات
        register_setting('azmonyar_settings', 'azmonyar_options');
        
        // افزودن بخش‌های تنظیمات
        add_settings_section(
            'azmonyar_general_section',
            __('تنظیمات عمومی', 'azmonyar'),
            array($this, 'general_section_callback'),
            'azmonyar_settings'
        );
        
        // افزودن فیلدهای تنظیمات
        $this->add_settings_fields();
    }
    
    /**
     * افزودن فیلدهای تنظیمات
     */
    private function add_settings_fields() {
        $fields = array(
            'exam_time_limit' => __('مدت زمان پیش‌فرض آزمون (دقیقه)', 'azmonyar'),
            'questions_per_page' => __('تعداد سوالات در هر صفحه', 'azmonyar'),
            'show_results_immediately' => __('نمایش فوری نتایج', 'azmonyar'),
            'passing_score' => __('نمره قبولی پیش‌فرض (درصد)', 'azmonyar'),
            'allow_retake' => __('امکان تکرار آزمون', 'azmonyar'),
            'randomize_questions' => __('ترتیب تصادفی سوالات', 'azmonyar'),
            'randomize_answers' => __('ترتیب تصادفی گزینه‌ها', 'azmonyar'),
            'email_results' => __('ارسال نتایج به ایمیل', 'azmonyar'),
            'certificate_enabled' => __('فعال‌سازی گواهی‌نامه', 'azmonyar'),
        );
        
        foreach ($fields as $field => $title) {
            add_settings_field(
                $field,
                $title,
                array($this, 'field_callback'),
                'azmonyar_settings',
                'azmonyar_general_section',
                array('field' => $field)
            );
        }
    }
    
    /**
     * صفحه داشبورد
     */
    public function dashboard_page() {
        // آمار کلی
        $stats = $this->get_dashboard_stats();
        
        ?>
        <div class="wrap azmonyar-admin">
            <h1><?php _e('داشبورد آزمون‌یار حرفه‌ای', 'azmonyar'); ?></h1>
            
            <div class="azmonyar-dashboard">
                <div class="azmonyar-stats-cards">
                    <div class="stats-card">
                        <div class="stats-icon">
                            <span class="dashicons dashicons-clipboard"></span>
                        </div>
                        <div class="stats-content">
                            <h3><?php echo number_format($stats['total_exams']); ?></h3>
                            <p><?php _e('کل آزمون‌ها', 'azmonyar'); ?></p>
                        </div>
                    </div>
                    
                    <div class="stats-card">
                        <div class="stats-icon">
                            <span class="dashicons dashicons-editor-help"></span>
                        </div>
                        <div class="stats-content">
                            <h3><?php echo number_format($stats['total_questions']); ?></h3>
                            <p><?php _e('کل سوالات', 'azmonyar'); ?></p>
                        </div>
                    </div>
                    
                    <div class="stats-card">
                        <div class="stats-icon">
                            <span class="dashicons dashicons-groups"></span>
                        </div>
                        <div class="stats-content">
                            <h3><?php echo number_format($stats['total_participants']); ?></h3>
                            <p><?php _e('شرکت‌کنندگان', 'azmonyar'); ?></p>
                        </div>
                    </div>
                    
                    <div class="stats-card">
                        <div class="stats-icon">
                            <span class="dashicons dashicons-chart-line"></span>
                        </div>
                        <div class="stats-content">
                            <h3><?php echo number_format($stats['completed_exams']); ?></h3>
                            <p><?php _e('آزمون‌های تکمیل شده', 'azmonyar'); ?></p>
                        </div>
                    </div>
                </div>
                
                <div class="azmonyar-dashboard-content">
                    <div class="dashboard-section">
                        <h2><?php _e('آزمون‌های اخیر', 'azmonyar'); ?></h2>
                        <div class="recent-exams">
                            <?php $this->display_recent_exams(); ?>
                        </div>
                    </div>
                    
                    <div class="dashboard-section">
                        <h2><?php _e('آخرین نتایج', 'azmonyar'); ?></h2>
                        <div class="recent-results">
                            <?php $this->display_recent_results(); ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * صفحه دسته‌بندی‌ها
     */
    public function categories_page() {
        ?>
        <div class="wrap azmonyar-admin">
            <h1><?php _e('مدیریت دسته‌بندی‌ها', 'azmonyar'); ?></h1>
            
            <div class="azmonyar-categories">
                <div class="categories-tabs">
                    <ul class="nav-tab-wrapper">
                        <li><a href="#subjects-tab" class="nav-tab nav-tab-active"><?php _e('رشته‌ها و دروس', 'azmonyar'); ?></a></li>
                        <li><a href="#difficulties-tab" class="nav-tab"><?php _e('سطوح دشواری', 'azmonyar'); ?></a></li>
                    </ul>
                </div>
                
                <div id="subjects-tab" class="tab-content active">
                    <h2><?php _e('مدیریت رشته‌ها و دروس', 'azmonyar'); ?></h2>
                    <?php $this->display_subjects_manager(); ?>
                </div>
                
                <div id="difficulties-tab" class="tab-content">
                    <h2><?php _e('مدیریت سطوح دشواری', 'azmonyar'); ?></h2>
                    <?php $this->display_difficulties_manager(); ?>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * صفحه نتایج کاربران
     */
    public function results_page() {
        global $wpdb;
        
        // دریافت نتایج با فیلتر
        $results = $this->get_filtered_results();
        
        ?>
        <div class="wrap azmonyar-admin">
            <h1><?php _e('نتایج کاربران', 'azmonyar'); ?></h1>
            
            <div class="azmonyar-results">
                <div class="results-filters">
                    <form method="get" action="">
                        <input type="hidden" name="page" value="azmonyar-results" />
                        
                        <div class="filter-row">
                            <select name="exam_filter">
                                <option value=""><?php _e('همه آزمون‌ها', 'azmonyar'); ?></option>
                                <?php
                                $exams = get_posts(array('post_type' => 'azmoon', 'posts_per_page' => -1));
                                foreach ($exams as $exam) {
                                    $selected = selected($_GET['exam_filter'] ?? '', $exam->ID, false);
                                    echo "<option value='{$exam->ID}' {$selected}>{$exam->post_title}</option>";
                                }
                                ?>
                            </select>
                            
                            <select name="status_filter">
                                <option value=""><?php _e('همه وضعیت‌ها', 'azmonyar'); ?></option>
                                <option value="completed" <?php selected($_GET['status_filter'] ?? '', 'completed'); ?>><?php _e('تکمیل شده', 'azmonyar'); ?></option>
                                <option value="started" <?php selected($_GET['status_filter'] ?? '', 'started'); ?>><?php _e('شروع شده', 'azmonyar'); ?></option>
                            </select>
                            
                            <input type="date" name="date_from" value="<?php echo esc_attr($_GET['date_from'] ?? ''); ?>" placeholder="<?php _e('از تاریخ', 'azmonyar'); ?>" />
                            <input type="date" name="date_to" value="<?php echo esc_attr($_GET['date_to'] ?? ''); ?>" placeholder="<?php _e('تا تاریخ', 'azmonyar'); ?>" />
                            
                            <input type="submit" class="button" value="<?php _e('فیلتر', 'azmonyar'); ?>" />
                            <a href="<?php echo admin_url('admin.php?page=azmonyar-results'); ?>" class="button"><?php _e('پاک کردن فیلتر', 'azmonyar'); ?></a>
                        </div>
                    </form>
                </div>
                
                <div class="results-table">
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th><?php _e('کاربر', 'azmonyar'); ?></th>
                                <th><?php _e('آزمون', 'azmonyar'); ?></th>
                                <th><?php _e('نمره', 'azmonyar'); ?></th>
                                <th><?php _e('درصد', 'azmonyar'); ?></th>
                                <th><?php _e('زمان صرف شده', 'azmonyar'); ?></th>
                                <th><?php _e('وضعیت', 'azmonyar'); ?></th>
                                <th><?php _e('تاریخ', 'azmonyar'); ?></th>
                                <th><?php _e('عملیات', 'azmonyar'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($results)): ?>
                                <?php foreach ($results as $result): ?>
                                    <tr>
                                        <td>
                                            <?php
                                            $user = get_user_by('id', $result->user_id);
                                            echo $user ? $user->display_name : __('کاربر حذف شده', 'azmonyar');
                                            ?>
                                        </td>
                                        <td>
                                            <?php
                                            $exam = get_post($result->exam_id);
                                            echo $exam ? $exam->post_title : __('آزمون حذف شده', 'azmonyar');
                                            ?>
                                        </td>
                                        <td><?php echo $result->correct_answers . '/' . $result->total_questions; ?></td>
                                        <td><?php echo number_format($result->percentage, 2) . '%'; ?></td>
                                        <td><?php echo $this->format_time($result->time_taken); ?></td>
                                        <td>
                                            <span class="status-badge status-<?php echo $result->status; ?>">
                                                <?php echo $this->get_status_label($result->status); ?>
                                            </span>
                                        </td>
                                        <td><?php echo date_i18n('Y/m/d H:i', strtotime($result->completed_at)); ?></td>
                                        <td>
                                            <a href="#" class="button button-small view-details" data-result-id="<?php echo $result->id; ?>">
                                                <?php _e('جزئیات', 'azmonyar'); ?>
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="8" class="no-results"><?php _e('نتیجه‌ای یافت نشد', 'azmonyar'); ?></td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                
                <div class="results-actions">
                    <button type="button" id="export-results" class="button button-primary">
                        <?php _e('خروجی Excel', 'azmonyar'); ?>
                    </button>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * صفحه گزارش‌ها
     */
    public function reports_page() {
        ?>
        <div class="wrap azmonyar-admin">
            <h1><?php _e('گزارش‌ها و آمار', 'azmonyar'); ?></h1>
            
            <div class="azmonyar-reports">
                <div class="reports-tabs">
                    <ul class="nav-tab-wrapper">
                        <li><a href="#overview-tab" class="nav-tab nav-tab-active"><?php _e('نمای کلی', 'azmonyar'); ?></a></li>
                        <li><a href="#exams-tab" class="nav-tab"><?php _e('گزارش آزمون‌ها', 'azmonyar'); ?></a></li>
                        <li><a href="#users-tab" class="nav-tab"><?php _e('گزارش کاربران', 'azmonyar'); ?></a></li>
                        <li><a href="#performance-tab" class="nav-tab"><?php _e('عملکرد سوالات', 'azmonyar'); ?></a></li>
                    </ul>
                </div>
                
                <div id="overview-tab" class="tab-content active">
                    <?php $this->display_overview_report(); ?>
                </div>
                
                <div id="exams-tab" class="tab-content">
                    <?php $this->display_exams_report(); ?>
                </div>
                
                <div id="users-tab" class="tab-content">
                    <?php $this->display_users_report(); ?>
                </div>
                
                <div id="performance-tab" class="tab-content">
                    <?php $this->display_performance_report(); ?>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * صفحه واردسازی CSV
     */
    public function import_page() {
        ?>
        <div class="wrap azmonyar-admin">
            <h1><?php _e('واردسازی سوالات از فایل CSV', 'azmonyar'); ?></h1>
            
            <div class="azmonyar-import">
                <div class="import-instructions">
                    <h2><?php _e('راهنمای واردسازی', 'azmonyar'); ?></h2>
                    <p><?php _e('فایل CSV باید دارای فرمت زیر باشد:', 'azmonyar'); ?></p>
                    <code>رشته,درس,سطح,سوال,گزینه۱,گزینه۲,گزینه۳,گزینه۴,پاسخ صحیح</code>
                    
                    <h3><?php _e('نمونه:', 'azmonyar'); ?></h3>
                    <pre>ریاضی,جبر,آسان,حاصل ۲+۲ چیست؟,۳,۴,۵,۶,۴</pre>
                    
                    <div class="import-tips">
                        <h3><?php _e('نکات مهم:', 'azmonyar'); ?></h3>
                        <ul>
                            <li><?php _e('فایل باید با کدگذاری UTF-8 ذخیره شود', 'azmonyar'); ?></li>
                            <li><?php _e('پاسخ صحیح باید یکی از گزینه‌های ۱ تا ۴ باشد', 'azmonyar'); ?></li>
                            <li><?php _e('در صورت عدم وجود رشته یا درس، خودکار ایجاد می‌شود', 'azmonyar'); ?></li>
                            <li><?php _e('حداکثر ۱۰۰۰ سوال در هر بار واردسازی', 'azmonyar'); ?></li>
                        </ul>
                    </div>
                </div>
                
                <div class="import-form">
                    <h2><?php _e('انتخاب فایل CSV', 'azmonyar'); ?></h2>
                    <form id="csv-import-form" enctype="multipart/form-data">
                        <?php wp_nonce_field('azmonyar_csv_import', 'csv_import_nonce'); ?>
                        
                        <table class="form-table">
                            <tr>
                                <th scope="row">
                                    <label for="csv_file"><?php _e('فایل CSV', 'azmonyar'); ?></label>
                                </th>
                                <td>
                                    <input type="file" id="csv_file" name="csv_file" accept=".csv" required />
                                    <p class="description"><?php _e('فایل CSV حاوی سوالات را انتخاب کنید', 'azmonyar'); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">
                                    <label for="default_author"><?php _e('نویسنده پیش‌فرض', 'azmonyar'); ?></label>
                                </th>
                                <td>
                                    <?php
                                    wp_dropdown_users(array(
                                        'name' => 'default_author',
                                        'id' => 'default_author',
                                        'selected' => get_current_user_id(),
                                        'show_option_none' => __('انتخاب نویسنده', 'azmonyar'),
                                    ));
                                    ?>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">
                                    <label for="skip_duplicates"><?php _e('رفتار با سوالات تکراری', 'azmonyar'); ?></label>
                                </th>
                                <td>
                                    <label>
                                        <input type="checkbox" id="skip_duplicates" name="skip_duplicates" value="1" checked />
                                        <?php _e('نادیده گرفتن سوالات تکراری', 'azmonyar'); ?>
                                    </label>
                                    <p class="description"><?php _e('در صورت فعال بودن، سوالات مشابه وارد نخواهند شد', 'azmonyar'); ?></p>
                                </td>
                            </tr>
                        </table>
                        
                        <p class="submit">
                            <input type="submit" id="import-submit" class="button button-primary" value="<?php _e('شروع واردسازی', 'azmonyar'); ?>" />
                        </p>
                    </form>
                    
                    <div id="import-progress" style="display: none;">
                        <h3><?php _e('در حال واردسازی...', 'azmonyar'); ?></h3>
                        <div class="progress-bar">
                            <div class="progress-fill"></div>
                        </div>
                        <div class="progress-text">0%</div>
                    </div>
                    
                    <div id="import-results" style="display: none;">
                        <h3><?php _e('نتیجه واردسازی', 'azmonyar'); ?></h3>
                        <div class="import-summary"></div>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * صفحه تنظیمات
     */
    public function settings_page() {
        ?>
        <div class="wrap azmonyar-admin">
            <h1><?php _e('تنظیمات آزمون‌یار', 'azmonyar'); ?></h1>
            
            <form method="post" action="options.php">
                <?php
                settings_fields('azmonyar_settings');
                do_settings_sections('azmonyar_settings');
                submit_button(__('ذخیره تنظیمات', 'azmonyar'));
                ?>
            </form>
        </div>
        <?php
    }
    
    /**
     * کال‌بک بخش عمومی تنظیمات
     */
    public function general_section_callback() {
        echo '<p>' . __('تنظیمات عمومی افزونه آزمون‌یار حرفه‌ای', 'azmonyar') . '</p>';
    }
    
    /**
     * کال‌بک فیلدهای تنظیمات
     */
    public function field_callback($args) {
        $options = get_option('azmonyar_options');
        $field = $args['field'];
        $value = $options[$field] ?? '';
        
        switch ($field) {
            case 'exam_time_limit':
            case 'questions_per_page':
            case 'passing_score':
                echo "<input type='number' name='azmonyar_options[{$field}]' value='{$value}' min='1' max='300' />";
                break;
                
            case 'show_results_immediately':
            case 'allow_retake':
            case 'randomize_questions':
            case 'randomize_answers':
            case 'email_results':
            case 'certificate_enabled':
                $checked = checked($value, '1', false);
                echo "<input type='checkbox' name='azmonyar_options[{$field}]' value='1' {$checked} />";
                break;
                
            default:
                echo "<input type='text' name='azmonyar_options[{$field}]' value='{$value}' />";
                break;
        }
    }
    
    /**
     * دریافت آمار داشبورد
     */
    private function get_dashboard_stats() {
        global $wpdb;
        
        $stats = array();
        
        // تعداد کل آزمون‌ها
        $stats['total_exams'] = wp_count_posts('azmoon')->publish;
        
        // تعداد کل سوالات
        $stats['total_questions'] = wp_count_posts('soal')->publish;
        
        // تعداد شرکت‌کنندگان
        $results_table = $wpdb->prefix . 'azmonyar_results';
        $stats['total_participants'] = $wpdb->get_var(
            "SELECT COUNT(DISTINCT user_id) FROM {$results_table}"
        );
        
        // تعداد آزمون‌های تکمیل شده
        $stats['completed_exams'] = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$results_table} WHERE status = 'completed'"
        );
        
        return $stats;
    }
    
    /**
     * نمایش آزمون‌های اخیر
     */
    private function display_recent_exams() {
        $exams = get_posts(array(
            'post_type' => 'azmoon',
            'posts_per_page' => 5,
            'post_status' => 'publish',
            'orderby' => 'date',
            'order' => 'DESC'
        ));
        
        if (empty($exams)) {
            echo '<p>' . __('آزمونی یافت نشد', 'azmonyar') . '</p>';
            return;
        }
        
        echo '<ul class="recent-items-list">';
        foreach ($exams as $exam) {
            $edit_link = get_edit_post_link($exam->ID);
            echo '<li>';
            echo '<a href="' . $edit_link . '">' . $exam->post_title . '</a>';
            echo '<span class="item-date">' . date_i18n('Y/m/d', strtotime($exam->post_date)) . '</span>';
            echo '</li>';
        }
        echo '</ul>';
    }
    
    /**
     * نمایش آخرین نتایج
     */
    private function display_recent_results() {
        global $wpdb;
        
        $results_table = $wpdb->prefix . 'azmonyar_results';
        $results = $wpdb->get_results(
            "SELECT r.*, u.display_name, p.post_title 
             FROM {$results_table} r
             LEFT JOIN {$wpdb->users} u ON r.user_id = u.ID
             LEFT JOIN {$wpdb->posts} p ON r.exam_id = p.ID
             WHERE r.status = 'completed'
             ORDER BY r.completed_at DESC
             LIMIT 5"
        );
        
        if (empty($results)) {
            echo '<p>' . __('نتیجه‌ای یافت نشد', 'azmonyar') . '</p>';
            return;
        }
        
        echo '<ul class="recent-items-list">';
        foreach ($results as $result) {
            echo '<li>';
            echo '<strong>' . ($result->display_name ?: __('کاربر حذف شده', 'azmonyar')) . '</strong>';
            echo ' - ' . ($result->post_title ?: __('آزمون حذف شده', 'azmonyar'));
            echo '<span class="item-score">' . number_format($result->percentage, 1) . '%</span>';
            echo '</li>';
        }
        echo '</ul>';
    }
    
    /**
     * نمایش مدیر رشته‌ها
     */
    private function display_subjects_manager() {
        $subjects = get_terms(array(
            'taxonomy' => 'azmonyar_subject',
            'hide_empty' => false,
        ));
        
        ?>
        <div class="subjects-manager">
            <div class="add-subject-form">
                <h3><?php _e('افزودن رشته/درس جدید', 'azmonyar'); ?></h3>
                <form id="add-subject-form">
                    <table class="form-table">
                        <tr>
                            <th><label for="subject_name"><?php _e('نام', 'azmonyar'); ?></label></th>
                            <td><input type="text" id="subject_name" name="subject_name" required /></td>
                        </tr>
                        <tr>
                            <th><label for="subject_parent"><?php _e('رشته والد', 'azmonyar'); ?></label></th>
                            <td>
                                <select id="subject_parent" name="subject_parent">
                                    <option value="0"><?php _e('بدون والد', 'azmonyar'); ?></option>
                                    <?php foreach ($subjects as $subject): ?>
                                        <option value="<?php echo $subject->term_id; ?>">
                                            <?php echo $subject->name; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="subject_description"><?php _e('توضیحات', 'azmonyar'); ?></label></th>
                            <td><textarea id="subject_description" name="subject_description"></textarea></td>
                        </tr>
                    </table>
                    <p class="submit">
                        <input type="submit" class="button button-primary" value="<?php _e('افزودن', 'azmonyar'); ?>" />
                    </p>
                </form>
            </div>
            
            <div class="subjects-list">
                <h3><?php _e('رشته‌ها و دروس موجود', 'azmonyar'); ?></h3>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th><?php _e('نام', 'azmonyar'); ?></th>
                            <th><?php _e('والد', 'azmonyar'); ?></th>
                            <th><?php _e('تعداد آزمون‌ها', 'azmonyar'); ?></th>
                            <th><?php _e('تعداد سوالات', 'azmonyar'); ?></th>
                            <th><?php _e('عملیات', 'azmonyar'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($subjects as $subject): ?>
                            <tr>
                                <td><?php echo $subject->name; ?></td>
                                <td>
                                    <?php
                                    if ($subject->parent) {
                                        $parent = get_term($subject->parent);
                                        echo $parent ? $parent->name : '-';
                                    } else {
                                        echo '-';
                                    }
                                    ?>
                                </td>
                                <td><?php echo $subject->count; ?></td>
                                <td>
                                    <?php
                                    $question_count = get_posts(array(
                                        'post_type' => 'soal',
                                        'tax_query' => array(
                                            array(
                                                'taxonomy' => 'azmonyar_subject',
                                                'field' => 'term_id',
                                                'terms' => $subject->term_id,
                                            ),
                                        ),
                                        'posts_per_page' => -1,
                                        'fields' => 'ids',
                                    ));
                                    echo count($question_count);
                                    ?>
                                </td>
                                <td>
                                    <button class="button button-small edit-subject" data-id="<?php echo $subject->term_id; ?>">
                                        <?php _e('ویرایش', 'azmonyar'); ?>
                                    </button>
                                    <button class="button button-small delete-subject" data-id="<?php echo $subject->term_id; ?>">
                                        <?php _e('حذف', 'azmonyar'); ?>
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php
    }
    
    /**
     * نمایش مدیر سطوح دشواری
     */
    private function display_difficulties_manager() {
        $difficulties = get_terms(array(
            'taxonomy' => 'azmonyar_difficulty',
            'hide_empty' => false,
        ));
        
        ?>
        <div class="difficulties-manager">
            <div class="add-difficulty-form">
                <h3><?php _e('افزودن سطح دشواری جدید', 'azmonyar'); ?></h3>
                <form id="add-difficulty-form">
                    <table class="form-table">
                        <tr>
                            <th><label for="difficulty_name"><?php _e('نام', 'azmonyar'); ?></label></th>
                            <td><input type="text" id="difficulty_name" name="difficulty_name" required /></td>
                        </tr>
                        <tr>
                            <th><label for="difficulty_description"><?php _e('توضیحات', 'azmonyar'); ?></label></th>
                            <td><textarea id="difficulty_description" name="difficulty_description"></textarea></td>
                        </tr>
                    </table>
                    <p class="submit">
                        <input type="submit" class="button button-primary" value="<?php _e('افزودن', 'azmonyar'); ?>" />
                    </p>
                </form>
            </div>
            
            <div class="difficulties-list">
                <h3><?php _e('سطوح دشواری موجود', 'azmonyar'); ?></h3>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th><?php _e('نام', 'azmonyar'); ?></th>
                            <th><?php _e('تعداد آزمون‌ها', 'azmonyar'); ?></th>
                            <th><?php _e('تعداد سوالات', 'azmonyar'); ?></th>
                            <th><?php _e('عملیات', 'azmonyar'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($difficulties as $difficulty): ?>
                            <tr>
                                <td><?php echo $difficulty->name; ?></td>
                                <td><?php echo $difficulty->count; ?></td>
                                <td>
                                    <?php
                                    $question_count = get_posts(array(
                                        'post_type' => 'soal',
                                        'tax_query' => array(
                                            array(
                                                'taxonomy' => 'azmonyar_difficulty',
                                                'field' => 'term_id',
                                                'terms' => $difficulty->term_id,
                                            ),
                                        ),
                                        'posts_per_page' => -1,
                                        'fields' => 'ids',
                                    ));
                                    echo count($question_count);
                                    ?>
                                </td>
                                <td>
                                    <button class="button button-small edit-difficulty" data-id="<?php echo $difficulty->term_id; ?>">
                                        <?php _e('ویرایش', 'azmonyar'); ?>
                                    </button>
                                    <button class="button button-small delete-difficulty" data-id="<?php echo $difficulty->term_id; ?>">
                                        <?php _e('حذف', 'azmonyar'); ?>
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php
    }
    
    /**
     * دریافت نتایج با فیلتر
     */
    private function get_filtered_results() {
        global $wpdb;
        
        $results_table = $wpdb->prefix . 'azmonyar_results';
        $where_clauses = array('1=1');
        
        // فیلتر آزمون
        if (!empty($_GET['exam_filter'])) {
            $where_clauses[] = $wpdb->prepare('exam_id = %d', intval($_GET['exam_filter']));
        }
        
        // فیلتر وضعیت
        if (!empty($_GET['status_filter'])) {
            $where_clauses[] = $wpdb->prepare('status = %s', sanitize_text_field($_GET['status_filter']));
        }
        
        // فیلتر تاریخ
        if (!empty($_GET['date_from'])) {
            $where_clauses[] = $wpdb->prepare('DATE(completed_at) >= %s', sanitize_text_field($_GET['date_from']));
        }
        
        if (!empty($_GET['date_to'])) {
            $where_clauses[] = $wpdb->prepare('DATE(completed_at) <= %s', sanitize_text_field($_GET['date_to']));
        }
        
        $where_sql = implode(' AND ', $where_clauses);
        
        return $wpdb->get_results(
            "SELECT * FROM {$results_table} 
             WHERE {$where_sql} 
             ORDER BY completed_at DESC 
             LIMIT 50"
        );
    }
    
    /**
     * فرمت زمان
     */
    private function format_time($seconds) {
        $hours = floor($seconds / 3600);
        $minutes = floor(($seconds % 3600) / 60);
        $seconds = $seconds % 60;
        
        if ($hours > 0) {
            return sprintf('%02d:%02d:%02d', $hours, $minutes, $seconds);
        } else {
            return sprintf('%02d:%02d', $minutes, $seconds);
        }
    }
    
    /**
     * دریافت برچسب وضعیت
     */
    private function get_status_label($status) {
        $labels = array(
            'started' => __('شروع شده', 'azmonyar'),
            'completed' => __('تکمیل شده', 'azmonyar'),
            'expired' => __('منقضی شده', 'azmonyar'),
        );
        
        return $labels[$status] ?? $status;
    }
    
    /**
     * نمایش گزارش کلی
     */
    private function display_overview_report() {
        // پیاده‌سازی گزارش کلی
        echo '<div class="overview-report">';
        echo '<h3>' . __('گزارش کلی عملکرد', 'azmonyar') . '</h3>';
        echo '<p>' . __('این بخش شامل نمودارها و آمار کلی سیستم می‌باشد.', 'azmonyar') . '</p>';
        echo '</div>';
    }
    
    /**
     * نمایش گزارش آزمون‌ها
     */
    private function display_exams_report() {
        echo '<div class="exams-report">';
        echo '<h3>' . __('گزارش عملکرد آزمون‌ها', 'azmonyar') . '</h3>';
        echo '<p>' . __('آمار تفصیلی هر آزمون و میزان موفقیت شرکت‌کنندگان.', 'azmonyar') . '</p>';
        echo '</div>';
    }
    
    /**
     * نمایش گزارش کاربران
     */
    private function display_users_report() {
        echo '<div class="users-report">';
        echo '<h3>' . __('گزارش عملکرد کاربران', 'azmonyar') . '</h3>';
        echo '<p>' . __('آمار فعالیت و عملکرد کاربران در آزمون‌ها.', 'azmonyar') . '</p>';
        echo '</div>';
    }
    
    /**
     * نمایش گزارش عملکرد
     */
    private function display_performance_report() {
        echo '<div class="performance-report">';
        echo '<h3>' . __('گزارش عملکرد سوالات', 'azmonyar') . '</h3>';
        echo '<p>' . __('تحلیل سختی سوالات و میزان پاسخ صحیح.', 'azmonyar') . '</p>';
        echo '</div>';
    }
    
    /**
     * AJAX: دریافت سوالات
     */
    public function ajax_get_questions() {
        check_ajax_referer('azmonyar_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die(__('دسترسی غیرمجاز', 'azmonyar'));
        }
        
        $questions = get_posts(array(
            'post_type' => 'soal',
            'posts_per_page' => -1,
            'post_status' => 'publish',
        ));
        
        $response = array();
        foreach ($questions as $question) {
            $response[] = array(
                'id' => $question->ID,
                'title' => $question->post_title,
                'content' => wp_trim_words($question->post_content, 20),
            );
        }
        
        wp_send_json_success($response);
    }
    
    /**
     * AJAX: واردسازی CSV
     */
    public function ajax_import_csv() {
        check_ajax_referer('azmonyar_csv_import', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die(__('دسترسی غیرمجاز', 'azmonyar'));
        }
        
        // پیاده‌سازی واردسازی CSV
        require_once AZMONYAR_PLUGIN_DIR . 'includes/class-azmonyar-csv-import.php';
        $importer = new Azmonyar_CSV_Import();
        $result = $importer->import_from_upload();
        
        wp_send_json($result);
    }
    
    /**
     * AJAX: خروجی نتایج
     */
    public function ajax_export_results() {
        check_ajax_referer('azmonyar_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die(__('دسترسی غیرمجاز', 'azmonyar'));
        }
        
        // پیاده‌سازی خروجی Excel
        $results = $this->get_filtered_results();
        
        // ایجاد فایل CSV
        $csv_data = array();
        $csv_data[] = array(
            __('کاربر', 'azmonyar'),
            __('آزمون', 'azmonyar'),
            __('نمره', 'azmonyar'),
            __('درصد', 'azmonyar'),
            __('زمان', 'azmonyar'),
            __('وضعیت', 'azmonyar'),
            __('تاریخ', 'azmonyar'),
        );
        
        foreach ($results as $result) {
            $user = get_user_by('id', $result->user_id);
            $exam = get_post($result->exam_id);
            
            $csv_data[] = array(
                $user ? $user->display_name : __('کاربر حذف شده', 'azmonyar'),
                $exam ? $exam->post_title : __('آزمون حذف شده', 'azmonyar'),
                $result->correct_answers . '/' . $result->total_questions,
                number_format($result->percentage, 2) . '%',
                $this->format_time($result->time_taken),
                $this->get_status_label($result->status),
                date_i18n('Y/m/d H:i', strtotime($result->completed_at)),
            );
        }
        
        wp_send_json_success(array(
            'data' => $csv_data,
            'filename' => 'azmonyar-results-' . date('Y-m-d') . '.csv'
        ));
    }
}