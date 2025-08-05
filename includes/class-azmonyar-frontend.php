<?php
/**
 * کلاس بخش کاربری
 * 
 * @package AzmonyarProfessional
 */

// جلوگیری از دسترسی مستقیم
if (!defined('ABSPATH')) {
    exit;
}

/**
 * کلاس مدیریت بخش کاربری
 */
class Azmonyar_Frontend {
    
    /**
     * سازنده کلاس
     */
    public function __construct() {
        add_action('init', array($this, 'init'));
        add_shortcode('azmonyar_exam_page', array($this, 'exam_page_shortcode'));
        add_shortcode('azmonyar_results_page', array($this, 'results_page_shortcode'));
        add_shortcode('azmonyar_exam_list', array($this, 'exam_list_shortcode'));
        
        // AJAX handlers
        add_action('wp_ajax_azmonyar_start_exam', array($this, 'ajax_start_exam'));
        add_action('wp_ajax_azmonyar_submit_exam', array($this, 'ajax_submit_exam'));
        add_action('wp_ajax_azmonyar_get_exam_questions', array($this, 'ajax_get_exam_questions'));
        add_action('wp_ajax_azmonyar_save_progress', array($this, 'ajax_save_progress'));
        
        // Non-logged in users
        add_action('wp_ajax_nopriv_azmonyar_start_exam', array($this, 'ajax_start_exam'));
        add_action('wp_ajax_nopriv_azmonyar_submit_exam', array($this, 'ajax_submit_exam'));
        add_action('wp_ajax_nopriv_azmonyar_get_exam_questions', array($this, 'ajax_get_exam_questions'));
        add_action('wp_ajax_nopriv_azmonyar_save_progress', array($this, 'ajax_save_progress'));
    }
    
    /**
     * راه‌اندازی اولیه
     */
    public function init() {
        // افزودن rewrite rules برای URL های سفارشی
        add_rewrite_rule(
            '^azmoon/([0-9]+)/?$',
            'index.php?azmonyar_exam_id=$matches[1]',
            'top'
        );
        
        add_rewrite_rule(
            '^natayej-azmoon/([0-9]+)/?$',
            'index.php?azmonyar_result_id=$matches[1]',
            'top'
        );
        
        // افزودن query vars
        add_filter('query_vars', array($this, 'add_query_vars'));
        
        // Template redirect
        add_action('template_redirect', array($this, 'template_redirect'));
    }
    
    /**
     * افزودن متغیرهای کوئری
     */
    public function add_query_vars($vars) {
        $vars[] = 'azmonyar_exam_id';
        $vars[] = 'azmonyar_result_id';
        return $vars;
    }
    
    /**
     * هدایت قالب
     */
    public function template_redirect() {
        $exam_id = get_query_var('azmonyar_exam_id');
        $result_id = get_query_var('azmonyar_result_id');
        
        if ($exam_id) {
            $this->load_exam_template($exam_id);
        } elseif ($result_id) {
            $this->load_result_template($result_id);
        }
    }
    
    /**
     * بارگذاری قالب آزمون
     */
    private function load_exam_template($exam_id) {
        // بررسی وجود آزمون
        $exam = get_post($exam_id);
        if (!$exam || $exam->post_type !== 'azmoon') {
            wp_die(__('آزمون یافت نشد', 'azmonyar'), 404);
        }
        
        // بررسی دسترسی کاربر
        if (!$this->user_has_access($exam_id)) {
            wp_die(__('شما به این آزمون دسترسی ندارید', 'azmonyar'), 403);
        }
        
        // بارگذاری قالب
        include $this->get_template_path('single-exam.php');
        exit;
    }
    
    /**
     * بارگذاری قالب نتیجه
     */
    private function load_result_template($result_id) {
        global $wpdb;
        
        // دریافت نتیجه
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
        
        // بارگذاری قالب
        include $this->get_template_path('single-result.php');
        exit;
    }
    
    /**
     * شورت‌کد صفحه آزمون
     */
    public function exam_page_shortcode($atts) {
        $atts = shortcode_atts(array(
            'exam_id' => 0,
        ), $atts);
        
        if (!$atts['exam_id']) {
            return '<p>' . __('شناسه آزمون مشخص نشده است', 'azmonyar') . '</p>';
        }
        
        $exam = get_post($atts['exam_id']);
        if (!$exam || $exam->post_type !== 'azmoon') {
            return '<p>' . __('آزمون یافت نشد', 'azmonyar') . '</p>';
        }
        
        // بررسی دسترسی
        if (!$this->user_has_access($atts['exam_id'])) {
            return '<p>' . __('برای شرکت در این آزمون ابتدا آن را خریداری کنید', 'azmonyar') . '</p>';
        }
        
        return $this->render_exam_page($exam);
    }
    
    /**
     * شورت‌کد صفحه نتایج
     */
    public function results_page_shortcode($atts) {
        if (!is_user_logged_in()) {
            return '<p>' . __('برای مشاهده نتایج وارد حساب کاربری خود شوید', 'azmonyar') . '</p>';
        }
        
        return $this->render_results_page();
    }
    
    /**
     * شورت‌کد لیست آزمون‌ها
     */
    public function exam_list_shortcode($atts) {
        $atts = shortcode_atts(array(
            'count' => 10,
            'subject' => '',
            'difficulty' => '',
        ), $atts);
        
        return $this->render_exam_list($atts);
    }
    
    /**
     * رندر صفحه آزمون
     */
    private function render_exam_page($exam) {
        $exam_id = $exam->ID;
        $time_limit = get_post_meta($exam_id, '_azmonyar_time_limit', true) ?: 60;
        $questions = get_post_meta($exam_id, '_azmonyar_questions', true) ?: array();
        
        // بررسی وجود آزمون در حال انجام
        $current_result = $this->get_current_exam_result($exam_id);
        
        ob_start();
        ?>
        <div class="azmonyar-exam-container" data-exam-id="<?php echo $exam_id; ?>">
            <?php if (!$current_result): ?>
                <!-- صفحه شروع آزمون -->
                <div class="exam-start-page">
                    <div class="exam-info">
                        <h1><?php echo $exam->post_title; ?></h1>
                        <div class="exam-description">
                            <?php echo wpautop($exam->post_content); ?>
                        </div>
                        
                        <div class="exam-details">
                            <div class="detail-item">
                                <span class="label"><?php _e('مدت زمان:', 'azmonyar'); ?></span>
                                <span class="value"><?php echo $time_limit; ?> <?php _e('دقیقه', 'azmonyar'); ?></span>
                            </div>
                            <div class="detail-item">
                                <span class="label"><?php _e('تعداد سوالات:', 'azmonyar'); ?></span>
                                <span class="value"><?php echo count($questions); ?> <?php _e('سوال', 'azmonyar'); ?></span>
                            </div>
                            
                            <?php
                            $subjects = wp_get_post_terms($exam_id, 'azmonyar_subject');
                            if (!empty($subjects)):
                            ?>
                            <div class="detail-item">
                                <span class="label"><?php _e('رشته/درس:', 'azmonyar'); ?></span>
                                <span class="value">
                                    <?php echo implode(', ', wp_list_pluck($subjects, 'name')); ?>
                                </span>
                            </div>
                            <?php endif; ?>
                            
                            <?php
                            $difficulties = wp_get_post_terms($exam_id, 'azmonyar_difficulty');
                            if (!empty($difficulties)):
                            ?>
                            <div class="detail-item">
                                <span class="label"><?php _e('سطح دشواری:', 'azmonyar'); ?></span>
                                <span class="value">
                                    <?php echo implode(', ', wp_list_pluck($difficulties, 'name')); ?>
                                </span>
                            </div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="exam-instructions">
                            <h3><?php _e('دستورالعمل آزمون', 'azmonyar'); ?></h3>
                            <ul>
                                <li><?php _e('پس از شروع آزمون، تایمر فعال می‌شود', 'azmonyar'); ?></li>
                                <li><?php _e('هر سوال دارای ۴ گزینه است که فقط یکی صحیح می‌باشد', 'azmonyar'); ?></li>
                                <li><?php _e('می‌توانید بین سوالات جابجا شده و پاسخ‌ها را تغییر دهید', 'azmonyar'); ?></li>
                                <li><?php _e('پس از اتمام زمان، آزمون خودکار ارسال می‌شود', 'azmonyar'); ?></li>
                                <li><?php _e('بستن مرورگر یا برگه باعث از دست رفتن پیشرفت می‌شود', 'azmonyar'); ?></li>
                            </ul>
                        </div>
                        
                        <div class="exam-actions">
                            <button type="button" id="start-exam-btn" class="btn btn-primary btn-large">
                                <?php _e('شروع آزمون', 'azmonyar'); ?>
                            </button>
                        </div>
                    </div>
                </div>
            <?php else: ?>
                <!-- ادامه آزمون -->
                <div class="exam-continue-page">
                    <div class="continue-info">
                        <h2><?php _e('ادامه آزمون', 'azmonyar'); ?></h2>
                        <p><?php _e('شما این آزمون را شروع کرده‌اید. می‌توانید از جایی که رها کرده‌اید ادامه دهید.', 'azmonyar'); ?></p>
                        
                        <div class="progress-info">
                            <span><?php _e('زمان شروع:', 'azmonyar'); ?></span>
                            <strong><?php echo date_i18n('Y/m/d H:i', strtotime($current_result->started_at)); ?></strong>
                        </div>
                        
                        <div class="exam-actions">
                            <button type="button" id="continue-exam-btn" class="btn btn-primary btn-large">
                                <?php _e('ادامه آزمون', 'azmonyar'); ?>
                            </button>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
            
            <!-- صفحه آزمون (مخفی) -->
            <div class="exam-page" style="display: none;">
                <div class="exam-header">
                    <div class="exam-title">
                        <h2><?php echo $exam->post_title; ?></h2>
                    </div>
                    <div class="exam-timer">
                        <div class="timer-display">
                            <span id="timer-minutes">00</span>:<span id="timer-seconds">00</span>
                        </div>
                        <div class="timer-label"><?php _e('زمان باقی‌مانده', 'azmonyar'); ?></div>
                    </div>
                </div>
                
                <div class="exam-content">
                    <div class="questions-container">
                        <!-- سوالات از طریق AJAX بارگذاری می‌شوند -->
                    </div>
                    
                    <div class="exam-navigation">
                        <div class="nav-buttons">
                            <button type="button" id="prev-question" class="btn btn-secondary" disabled>
                                <?php _e('سوال قبلی', 'azmonyar'); ?>
                            </button>
                            <button type="button" id="next-question" class="btn btn-secondary">
                                <?php _e('سوال بعدی', 'azmonyar'); ?>
                            </button>
                        </div>
                        
                        <div class="question-numbers">
                            <!-- شماره سوالات -->
                        </div>
                        
                        <div class="exam-actions">
                            <button type="button" id="finish-exam-btn" class="btn btn-success">
                                <?php _e('پایان آزمون', 'azmonyar'); ?>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- صفحه نتیجه (مخفی) -->
            <div class="exam-result-page" style="display: none;">
                <div class="result-container">
                    <div class="result-header">
                        <h2><?php _e('نتیجه آزمون', 'azmonyar'); ?></h2>
                    </div>
                    
                    <div class="result-content">
                        <!-- نتایج از طریق AJAX نمایش داده می‌شوند -->
                    </div>
                </div>
            </div>
        </div>
        
        <script type="text/javascript">
        jQuery(document).ready(function($) {
            // راه‌اندازی آزمون
            window.AzmonyarExam = new AzmonyarExamHandler({
                examId: <?php echo $exam_id; ?>,
                timeLimit: <?php echo $time_limit * 60; ?>, // تبدیل به ثانیه
                ajaxUrl: '<?php echo admin_url('admin-ajax.php'); ?>',
                nonce: '<?php echo wp_create_nonce('azmonyar_nonce'); ?>',
                messages: {
                    timeUp: '<?php _e('زمان آزمون به پایان رسید!', 'azmonyar'); ?>',
                    confirmSubmit: '<?php _e('آیا از ارسال آزمون اطمینان دارید؟', 'azmonyar'); ?>',
                    loading: '<?php _e('در حال بارگذاری...', 'azmonyar'); ?>',
                    error: '<?php _e('خطایی رخ داده است', 'azmonyar'); ?>'
                }
            });
        });
        </script>
        <?php
        return ob_get_clean();
    }
    
    /**
     * رندر صفحه نتایج
     */
    private function render_results_page() {
        global $wpdb;
        
        $user_id = get_current_user_id();
        $results_table = $wpdb->prefix . 'azmonyar_results';
        
        // دریافت نتایج کاربر
        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT r.*, p.post_title as exam_title 
             FROM {$results_table} r
             LEFT JOIN {$wpdb->posts} p ON r.exam_id = p.ID
             WHERE r.user_id = %d AND r.status = 'completed'
             ORDER BY r.completed_at DESC",
            $user_id
        ));
        
        ob_start();
        ?>
        <div class="azmonyar-results-page">
            <div class="results-header">
                <h2><?php _e('نتایج آزمون‌های من', 'azmonyar'); ?></h2>
            </div>
            
            <?php if (!empty($results)): ?>
                <div class="results-list">
                    <?php foreach ($results as $result): ?>
                        <div class="result-item">
                            <div class="result-info">
                                <h3 class="exam-title"><?php echo $result->exam_title ?: __('آزمون حذف شده', 'azmonyar'); ?></h3>
                                <div class="result-meta">
                                    <span class="result-date">
                                        <?php echo date_i18n('Y/m/d H:i', strtotime($result->completed_at)); ?>
                                    </span>
                                    <span class="result-time">
                                        <?php echo $this->format_time($result->time_taken); ?>
                                    </span>
                                </div>
                            </div>
                            
                            <div class="result-score">
                                <div class="score-circle <?php echo $result->percentage >= 50 ? 'passed' : 'failed'; ?>">
                                    <span class="percentage"><?php echo number_format($result->percentage, 1); ?>%</span>
                                </div>
                                <div class="score-details">
                                    <div class="correct-answers">
                                        <?php echo $result->correct_answers; ?>/<?php echo $result->total_questions; ?>
                                        <small><?php _e('پاسخ صحیح', 'azmonyar'); ?></small>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="result-actions">
                                <a href="<?php echo home_url('natayej-azmoon/' . $result->id); ?>" class="btn btn-primary btn-small">
                                    <?php _e('مشاهده جزئیات', 'azmonyar'); ?>
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="no-results">
                    <p><?php _e('هنوز در هیچ آزمونی شرکت نکرده‌اید', 'azmonyar'); ?></p>
                    <a href="<?php echo home_url('azmoon'); ?>" class="btn btn-primary">
                        <?php _e('مشاهده آزمون‌ها', 'azmonyar'); ?>
                    </a>
                </div>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }
    
    /**
     * رندر لیست آزمون‌ها
     */
    private function render_exam_list($atts) {
        $args = array(
            'post_type' => 'azmoon',
            'post_status' => 'publish',
            'posts_per_page' => intval($atts['count']),
            'orderby' => 'date',
            'order' => 'DESC'
        );
        
        // فیلتر بر اساس رشته
        if (!empty($atts['subject'])) {
            $args['tax_query'][] = array(
                'taxonomy' => 'azmonyar_subject',
                'field' => 'slug',
                'terms' => $atts['subject']
            );
        }
        
        // فیلتر بر اساس سطح دشواری
        if (!empty($atts['difficulty'])) {
            $args['tax_query'][] = array(
                'taxonomy' => 'azmonyar_difficulty',
                'field' => 'slug',
                'terms' => $atts['difficulty']
            );
        }
        
        $exams = get_posts($args);
        
        ob_start();
        ?>
        <div class="azmonyar-exam-list">
            <?php if (!empty($exams)): ?>
                <div class="exams-grid">
                    <?php foreach ($exams as $exam): ?>
                        <?php
                        $exam_id = $exam->ID;
                        $time_limit = get_post_meta($exam_id, '_azmonyar_time_limit', true) ?: 60;
                        $questions = get_post_meta($exam_id, '_azmonyar_questions', true) ?: array();
                        $wc_product_id = get_post_meta($exam_id, '_azmonyar_wc_product_id', true);
                        $product = $wc_product_id ? wc_get_product($wc_product_id) : null;
                        ?>
                        <div class="exam-card">
                            <?php if (has_post_thumbnail($exam_id)): ?>
                                <div class="exam-image">
                                    <?php echo get_the_post_thumbnail($exam_id, 'medium'); ?>
                                </div>
                            <?php endif; ?>
                            
                            <div class="exam-content">
                                <h3 class="exam-title">
                                    <a href="<?php echo home_url('azmoon/' . $exam_id); ?>">
                                        <?php echo $exam->post_title; ?>
                                    </a>
                                </h3>
                                
                                <div class="exam-excerpt">
                                    <?php echo wp_trim_words($exam->post_content, 20); ?>
                                </div>
                                
                                <div class="exam-meta">
                                    <div class="meta-item">
                                        <span class="icon">⏱</span>
                                        <span><?php echo $time_limit; ?> <?php _e('دقیقه', 'azmonyar'); ?></span>
                                    </div>
                                    <div class="meta-item">
                                        <span class="icon">❓</span>
                                        <span><?php echo count($questions); ?> <?php _e('سوال', 'azmonyar'); ?></span>
                                    </div>
                                    <?php if ($product): ?>
                                        <div class="meta-item price">
                                            <span class="icon">💰</span>
                                            <span><?php echo $product->get_price_html(); ?></span>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="exam-actions">
                                    <?php if ($this->user_has_access($exam_id)): ?>
                                        <a href="<?php echo home_url('azmoon/' . $exam_id); ?>" class="btn btn-primary">
                                            <?php _e('شروع آزمون', 'azmonyar'); ?>
                                        </a>
                                    <?php elseif ($product): ?>
                                        <a href="<?php echo $product->get_permalink(); ?>" class="btn btn-secondary">
                                            <?php _e('خرید آزمون', 'azmonyar'); ?>
                                        </a>
                                    <?php else: ?>
                                        <span class="btn btn-disabled">
                                            <?php _e('در دسترس نیست', 'azmonyar'); ?>
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="no-exams">
                    <p><?php _e('آزمونی یافت نشد', 'azmonyar'); ?></p>
                </div>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }
    
    /**
     * بررسی دسترسی کاربر به آزمون
     */
    private function user_has_access($exam_id) {
        // اگر کاربر مدیر است
        if (current_user_can('manage_options')) {
            return true;
        }
        
        // دریافت محصول مرتبط
        $wc_product_id = get_post_meta($exam_id, '_azmonyar_wc_product_id', true);
        if (!$wc_product_id) {
            return true; // اگر محصولی تعریف نشده، آزادانه در دسترس است
        }
        
        // بررسی خرید محصول توسط کاربر
        if (!is_user_logged_in()) {
            return false;
        }
        
        $user_id = get_current_user_id();
        return wc_customer_bought_product('', $user_id, $wc_product_id);
    }
    
    /**
     * دریافت نتیجه آزمون جاری
     */
    private function get_current_exam_result($exam_id) {
        if (!is_user_logged_in()) {
            return null;
        }
        
        global $wpdb;
        $user_id = get_current_user_id();
        $results_table = $wpdb->prefix . 'azmonyar_results';
        
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$results_table} 
             WHERE user_id = %d AND exam_id = %d AND status = 'started'
             ORDER BY id DESC LIMIT 1",
            $user_id,
            $exam_id
        ));
    }
    
    /**
     * دریافت مسیر قالب
     */
    private function get_template_path($template_name) {
        // ابتدا در قالب فعال جستجو کن
        $theme_template = locate_template(array(
            'azmonyar/' . $template_name,
            $template_name
        ));
        
        if ($theme_template) {
            return $theme_template;
        }
        
        // اگر در قالب نبود، از قالب پیش‌فرض افزونه استفاده کن
        return AZMONYAR_PLUGIN_DIR . 'templates/' . $template_name;
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
     * AJAX: شروع آزمون
     */
    public function ajax_start_exam() {
        check_ajax_referer('azmonyar_nonce', 'nonce');
        
        if (!is_user_logged_in()) {
            wp_send_json_error(__('برای شرکت در آزمون وارد حساب کاربری خود شوید', 'azmonyar'));
        }
        
        $exam_id = intval($_POST['exam_id']);
        $user_id = get_current_user_id();
        
        // بررسی دسترسی
        if (!$this->user_has_access($exam_id)) {
            wp_send_json_error(__('شما به این آزمون دسترسی ندارید', 'azmonyar'));
        }
        
        // بررسی آزمون جاری
        $current_result = $this->get_current_exam_result($exam_id);
        if ($current_result) {
            wp_send_json_success(array(
                'result_id' => $current_result->id,
                'message' => __('آزمون قبلاً شروع شده است', 'azmonyar')
            ));
        }
        
        // ایجاد رکورد جدید
        global $wpdb;
        $results_table = $wpdb->prefix . 'azmonyar_results';
        
        $questions = get_post_meta($exam_id, '_azmonyar_questions', true) ?: array();
        
        $result = $wpdb->insert(
            $results_table,
            array(
                'user_id' => $user_id,
                'exam_id' => $exam_id,
                'total_questions' => count($questions),
                'status' => 'started',
                'started_at' => current_time('mysql'),
                'answers' => json_encode(array())
            ),
            array('%d', '%d', '%d', '%s', '%s', '%s')
        );
        
        if ($result === false) {
            wp_send_json_error(__('خطا در شروع آزمون', 'azmonyar'));
        }
        
        wp_send_json_success(array(
            'result_id' => $wpdb->insert_id,
            'message' => __('آزمون با موفقیت شروع شد', 'azmonyar')
        ));
    }
    
    /**
     * AJAX: دریافت سوالات آزمون
     */
    public function ajax_get_exam_questions() {
        check_ajax_referer('azmonyar_nonce', 'nonce');
        
        if (!is_user_logged_in()) {
            wp_send_json_error(__('دسترسی غیرمجاز', 'azmonyar'));
        }
        
        $exam_id = intval($_POST['exam_id']);
        
        // بررسی دسترسی
        if (!$this->user_has_access($exam_id)) {
            wp_send_json_error(__('شما به این آزمون دسترسی ندارید', 'azmonyar'));
        }
        
        // دریافت سوالات
        $question_ids = get_post_meta($exam_id, '_azmonyar_questions', true) ?: array();
        
        // تنظیمات آزمون
        $randomize_questions = get_post_meta($exam_id, '_azmonyar_randomize_questions', true);
        $randomize_answers = get_post_meta($exam_id, '_azmonyar_randomize_answers', true);
        
        if ($randomize_questions) {
            shuffle($question_ids);
        }
        
        $questions = array();
        foreach ($question_ids as $index => $question_id) {
            $question = get_post($question_id);
            if (!$question) continue;
            
            $options = array(
                'a' => get_post_meta($question_id, '_azmonyar_option_a', true),
                'b' => get_post_meta($question_id, '_azmonyar_option_b', true),
                'c' => get_post_meta($question_id, '_azmonyar_option_c', true),
                'd' => get_post_meta($question_id, '_azmonyar_option_d', true),
            );
            
            if ($randomize_answers) {
                $correct_answer = get_post_meta($question_id, '_azmonyar_correct_answer', true);
                $correct_text = $options[$correct_answer];
                
                $option_texts = array_values($options);
                shuffle($option_texts);
                
                $new_correct_key = array_search($correct_text, $option_texts);
                $option_keys = array('a', 'b', 'c', 'd');
                
                $options = array_combine($option_keys, $option_texts);
                $correct_answer = $option_keys[$new_correct_key];
            }
            
            $questions[] = array(
                'id' => $question_id,
                'number' => $index + 1,
                'question' => $question->post_content,
                'options' => $options,
                'image' => get_the_post_thumbnail_url($question_id, 'medium')
            );
        }
        
        wp_send_json_success($questions);
    }
    
    /**
     * AJAX: ذخیره پیشرفت
     */
    public function ajax_save_progress() {
        check_ajax_referer('azmonyar_nonce', 'nonce');
        
        if (!is_user_logged_in()) {
            wp_send_json_error(__('دسترسی غیرمجاز', 'azmonyar'));
        }
        
        $result_id = intval($_POST['result_id']);
        $answers = $_POST['answers'] ?? array();
        
        // اعتبارسنجی
        global $wpdb;
        $results_table = $wpdb->prefix . 'azmonyar_results';
        
        $result = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$results_table} WHERE id = %d AND user_id = %d",
            $result_id,
            get_current_user_id()
        ));
        
        if (!$result) {
            wp_send_json_error(__('نتیجه یافت نشد', 'azmonyar'));
        }
        
        // ذخیره پاسخ‌ها
        $wpdb->update(
            $results_table,
            array('answers' => json_encode($answers)),
            array('id' => $result_id),
            array('%s'),
            array('%d')
        );
        
        wp_send_json_success(__('پیشرفت ذخیره شد', 'azmonyar'));
    }
    
    /**
     * AJAX: ارسال آزمون
     */
    public function ajax_submit_exam() {
        check_ajax_referer('azmonyar_nonce', 'nonce');
        
        if (!is_user_logged_in()) {
            wp_send_json_error(__('دسترسی غیرمجاز', 'azmonyar'));
        }
        
        $result_id = intval($_POST['result_id']);
        $answers = $_POST['answers'] ?? array();
        $time_taken = intval($_POST['time_taken'] ?? 0);
        
        // اعتبارسنجی
        global $wpdb;
        $results_table = $wpdb->prefix . 'azmonyar_results';
        
        $result = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$results_table} WHERE id = %d AND user_id = %d",
            $result_id,
            get_current_user_id()
        ));
        
        if (!$result || $result->status !== 'started') {
            wp_send_json_error(__('آزمون یافت نشد یا قبلاً ارسال شده', 'azmonyar'));
        }
        
        // محاسبه نمره
        $score_data = $this->calculate_score($result->exam_id, $answers);
        
        // بروزرسانی نتیجه
        $wpdb->update(
            $results_table,
            array(
                'answers' => json_encode($answers),
                'correct_answers' => $score_data['correct'],
                'wrong_answers' => $score_data['wrong'],
                'percentage' => $score_data['percentage'],
                'time_taken' => $time_taken,
                'completed_at' => current_time('mysql'),
                'status' => 'completed'
            ),
            array('id' => $result_id),
            array('%s', '%d', '%d', '%f', '%d', '%s', '%s'),
            array('%d')
        );
        
        // بروزرسانی آمار کاربر
        $this->update_user_stats(get_current_user_id(), $score_data);
        
        wp_send_json_success(array(
            'message' => __('آزمون با موفقیت ارسال شد', 'azmonyar'),
            'result' => $score_data,
            'result_url' => home_url('natayej-azmoon/' . $result_id)
        ));
    }
    
    /**
     * محاسبه نمره
     */
    private function calculate_score($exam_id, $answers) {
        $question_ids = get_post_meta($exam_id, '_azmonyar_questions', true) ?: array();
        $total_questions = count($question_ids);
        $correct_answers = 0;
        
        foreach ($question_ids as $question_id) {
            $correct_answer = get_post_meta($question_id, '_azmonyar_correct_answer', true);
            $user_answer = $answers[$question_id] ?? '';
            
            if ($user_answer === $correct_answer) {
                $correct_answers++;
            }
        }
        
        $wrong_answers = $total_questions - $correct_answers;
        $percentage = $total_questions > 0 ? ($correct_answers / $total_questions) * 100 : 0;
        
        return array(
            'total' => $total_questions,
            'correct' => $correct_answers,
            'wrong' => $wrong_answers,
            'percentage' => $percentage
        );
    }
    
    /**
     * بروزرسانی آمار کاربر
     */
    private function update_user_stats($user_id, $score_data) {
        global $wpdb;
        
        $stats_table = $wpdb->prefix . 'azmonyar_user_stats';
        $passing_score = 50; // درصد قبولی
        
        $is_passed = $score_data['percentage'] >= $passing_score ? 1 : 0;
        
        // بررسی وجود رکورد
        $existing = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$stats_table} WHERE user_id = %d",
            $user_id
        ));
        
        if ($existing) {
            // بروزرسانی
            $new_total = $existing->total_exams + 1;
            $new_passed = $existing->passed_exams + $is_passed;
            $new_failed = $existing->failed_exams + (1 - $is_passed);
            $new_average = (($existing->average_score * $existing->total_exams) + $score_data['percentage']) / $new_total;
            
            $wpdb->update(
                $stats_table,
                array(
                    'total_exams' => $new_total,
                    'passed_exams' => $new_passed,
                    'failed_exams' => $new_failed,
                    'average_score' => $new_average,
                    'last_exam_date' => current_time('mysql')
                ),
                array('user_id' => $user_id),
                array('%d', '%d', '%d', '%f', '%s'),
                array('%d')
            );
        } else {
            // ایجاد جدید
            $wpdb->insert(
                $stats_table,
                array(
                    'user_id' => $user_id,
                    'total_exams' => 1,
                    'passed_exams' => $is_passed,
                    'failed_exams' => 1 - $is_passed,
                    'average_score' => $score_data['percentage'],
                    'last_exam_date' => current_time('mysql')
                ),
                array('%d', '%d', '%d', '%d', '%f', '%s')
            );
        }
    }
}