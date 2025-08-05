<?php
/**
 * کلاس ادغام با ووکامرس
 * 
 * @package AzmonyarProfessional
 */

// جلوگیری از دسترسی مستقیم
if (!defined('ABSPATH')) {
    exit;
}

/**
 * کلاس مدیریت ادغام با ووکامرس
 */
class Azmonyar_WooCommerce {
    
    /**
     * سازنده کلاس
     */
    public function __construct() {
        add_action('init', array($this, 'init'));
        
        // هوک‌های ووکامرس
        add_action('woocommerce_order_status_completed', array($this, 'on_order_completed'));
        add_action('woocommerce_order_status_processing', array($this, 'on_order_processing'));
        add_action('woocommerce_order_status_refunded', array($this, 'on_order_refunded'));
        
        // افزودن تب آزمون‌ها به حساب کاربری
        add_filter('woocommerce_account_menu_items', array($this, 'add_account_menu_items'));
        add_action('woocommerce_account_azmonyar-exams_endpoint', array($this, 'exams_endpoint_content'));
        
        // ثبت endpoint
        add_action('init', array($this, 'add_endpoints'));
        
        // افزودن اطلاعات آزمون به صفحه محصول
        add_action('woocommerce_single_product_summary', array($this, 'add_exam_info_to_product'), 25);
        
        // محدود کردن خرید محصولات آزمون
        add_filter('woocommerce_is_purchasable', array($this, 'restrict_exam_purchase'), 10, 2);
        
        // افزودن ستون آزمون به لیست سفارشات
        add_filter('woocommerce_my_account_my_orders_columns', array($this, 'add_orders_column'));
        add_action('woocommerce_my_account_my_orders_column_exam-access', array($this, 'orders_column_content'));
    }
    
    /**
     * راه‌اندازی اولیه
     */
    public function init() {
        // بررسی فعال بودن ووکامرس
        if (!class_exists('WooCommerce')) {
            return;
        }
        
        // افزودن نوع محصول آزمون
        add_filter('product_type_selector', array($this, 'add_exam_product_type'));
        add_action('woocommerce_product_options_general_product_data', array($this, 'exam_product_options'));
        add_action('woocommerce_process_product_meta', array($this, 'save_exam_product_options'));
        
        // کلاس محصول آزمون
        add_action('woocommerce_loaded', array($this, 'load_exam_product_class'));
    }
    
    /**
     * افزودن endpoint ها
     */
    public function add_endpoints() {
        add_rewrite_endpoint('azmonyar-exams', EP_ROOT | EP_PAGES);
        add_rewrite_endpoint('exam-result', EP_ROOT | EP_PAGES);
    }
    
    /**
     * افزودن آیتم منو به حساب کاربری
     */
    public function add_account_menu_items($items) {
        // افزودن تب آزمون‌ها قبل از خروج
        $new_items = array();
        foreach ($items as $key => $item) {
            if ($key === 'customer-logout') {
                $new_items['azmonyar-exams'] = __('آزمون‌های من', 'azmonyar');
            }
            $new_items[$key] = $item;
        }
        
        return $new_items;
    }
    
    /**
     * محتوای صفحه آزمون‌های من
     */
    public function exams_endpoint_content() {
        if (!is_user_logged_in()) {
            echo '<p>' . __('برای مشاهده آزمون‌ها وارد حساب کاربری خود شوید', 'azmonyar') . '</p>';
            return;
        }
        
        $this->render_user_exams_page();
    }
    
    /**
     * رندر صفحه آزمون‌های کاربر
     */
    private function render_user_exams_page() {
        global $wpdb;
        
        $user_id = get_current_user_id();
        
        // دریافت آزمون‌های خریداری شده
        $purchased_exams = $this->get_user_purchased_exams($user_id);
        
        // دریافت نتایج آزمون‌ها
        $results_table = $wpdb->prefix . 'azmonyar_results';
        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT r.*, p.post_title as exam_title 
             FROM {$results_table} r
             LEFT JOIN {$wpdb->posts} p ON r.exam_id = p.ID
             WHERE r.user_id = %d
             ORDER BY r.started_at DESC",
            $user_id
        ));
        
        // سازماندهی نتایج بر اساس آزمون
        $exam_results = array();
        foreach ($results as $result) {
            $exam_results[$result->exam_id][] = $result;
        }
        
        ?>
        <div class="azmonyar-user-exams">
            <div class="exams-header">
                <h2><?php _e('آزمون‌های من', 'azmonyar'); ?></h2>
                <p><?php _e('لیست آزمون‌های خریداری شده و نتایج آن‌ها', 'azmonyar'); ?></p>
            </div>
            
            <?php if (!empty($purchased_exams)): ?>
                <div class="purchased-exams">
                    <?php foreach ($purchased_exams as $exam): ?>
                        <?php
                        $exam_id = $exam['exam_id'];
                        $exam_post = get_post($exam_id);
                        if (!$exam_post) continue;
                        
                        $time_limit = get_post_meta($exam_id, '_azmonyar_time_limit', true) ?: 60;
                        $questions = get_post_meta($exam_id, '_azmonyar_questions', true) ?: array();
                        $exam_result_list = $exam_results[$exam_id] ?? array();
                        $latest_result = !empty($exam_result_list) ? $exam_result_list[0] : null;
                        ?>
                        
                        <div class="exam-item">
                            <div class="exam-header">
                                <h3 class="exam-title"><?php echo $exam_post->post_title; ?></h3>
                                <div class="exam-meta">
                                    <span class="purchase-date">
                                        <?php _e('خریداری شده در:', 'azmonyar'); ?>
                                        <?php echo date_i18n('Y/m/d', strtotime($exam['purchase_date'])); ?>
                                    </span>
                                </div>
                            </div>
                            
                            <div class="exam-details">
                                <div class="exam-info">
                                    <div class="info-item">
                                        <span class="label"><?php _e('مدت زمان:', 'azmonyar'); ?></span>
                                        <span class="value"><?php echo $time_limit; ?> <?php _e('دقیقه', 'azmonyar'); ?></span>
                                    </div>
                                    <div class="info-item">
                                        <span class="label"><?php _e('تعداد سوالات:', 'azmonyar'); ?></span>
                                        <span class="value"><?php echo count($questions); ?> <?php _e('سوال', 'azmonyar'); ?></span>
                                    </div>
                                </div>
                                
                                <div class="exam-status">
                                    <?php if ($latest_result): ?>
                                        <?php if ($latest_result->status === 'completed'): ?>
                                            <div class="status-completed">
                                                <div class="score-display">
                                                    <span class="percentage <?php echo $latest_result->percentage >= 50 ? 'passed' : 'failed'; ?>">
                                                        <?php echo number_format($latest_result->percentage, 1); ?>%
                                                    </span>
                                                    <small><?php echo $latest_result->correct_answers; ?>/<?php echo $latest_result->total_questions; ?> <?php _e('صحیح', 'azmonyar'); ?></small>
                                                </div>
                                                <div class="completion-date">
                                                    <?php echo date_i18n('Y/m/d H:i', strtotime($latest_result->completed_at)); ?>
                                                </div>
                                            </div>
                                        <?php elseif ($latest_result->status === 'started'): ?>
                                            <div class="status-in-progress">
                                                <span class="status-label"><?php _e('در حال انجام', 'azmonyar'); ?></span>
                                                <small><?php _e('شروع شده در:', 'azmonyar'); ?> <?php echo date_i18n('Y/m/d H:i', strtotime($latest_result->started_at)); ?></small>
                                            </div>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <div class="status-not-started">
                                            <span class="status-label"><?php _e('شروع نشده', 'azmonyar'); ?></span>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <div class="exam-actions">
                                <?php if (!$latest_result || $latest_result->status !== 'completed'): ?>
                                    <a href="<?php echo home_url('azmoon/' . $exam_id); ?>" class="button alt">
                                        <?php echo $latest_result && $latest_result->status === 'started' ? __('ادامه آزمون', 'azmonyar') : __('شروع آزمون', 'azmonyar'); ?>
                                    </a>
                                <?php endif; ?>
                                
                                <?php if ($latest_result && $latest_result->status === 'completed'): ?>
                                    <a href="<?php echo home_url('natayej-azmoon/' . $latest_result->id); ?>" class="button">
                                        <?php _e('مشاهده نتیجه', 'azmonyar'); ?>
                                    </a>
                                <?php endif; ?>
                                
                                <?php if (count($exam_result_list) > 1): ?>
                                    <button type="button" class="button view-history" data-exam-id="<?php echo $exam_id; ?>">
                                        <?php _e('تاریخچه آزمون‌ها', 'azmonyar'); ?> (<?php echo count($exam_result_list); ?>)
                                    </button>
                                <?php endif; ?>
                            </div>
                            
                            <?php if (count($exam_result_list) > 1): ?>
                                <div class="exam-history" id="history-<?php echo $exam_id; ?>" style="display: none;">
                                    <h4><?php _e('تاریخچه آزمون‌ها', 'azmonyar'); ?></h4>
                                    <div class="history-list">
                                        <?php foreach ($exam_result_list as $result): ?>
                                            <div class="history-item">
                                                <div class="result-info">
                                                    <?php if ($result->status === 'completed'): ?>
                                                        <span class="score <?php echo $result->percentage >= 50 ? 'passed' : 'failed'; ?>">
                                                            <?php echo number_format($result->percentage, 1); ?>%
                                                        </span>
                                                        <span class="details">
                                                            <?php echo $result->correct_answers; ?>/<?php echo $result->total_questions; ?>
                                                            - <?php echo $this->format_time($result->time_taken); ?>
                                                        </span>
                                                        <span class="date">
                                                            <?php echo date_i18n('Y/m/d H:i', strtotime($result->completed_at)); ?>
                                                        </span>
                                                    <?php else: ?>
                                                        <span class="status"><?php _e('شروع شده', 'azmonyar'); ?></span>
                                                        <span class="date">
                                                            <?php echo date_i18n('Y/m/d H:i', strtotime($result->started_at)); ?>
                                                        </span>
                                                    <?php endif; ?>
                                                </div>
                                                <?php if ($result->status === 'completed'): ?>
                                                    <div class="result-actions">
                                                        <a href="<?php echo home_url('natayej-azmoon/' . $result->id); ?>" class="view-result">
                                                            <?php _e('مشاهده', 'azmonyar'); ?>
                                                        </a>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="no-exams">
                    <p><?php _e('هنوز آزمونی خریداری نکرده‌اید', 'azmonyar'); ?></p>
                    <a href="<?php echo home_url('shop'); ?>" class="button alt">
                        <?php _e('مشاهده آزمون‌ها', 'azmonyar'); ?>
                    </a>
                </div>
            <?php endif; ?>
        </div>
        
        <script type="text/javascript">
        jQuery(document).ready(function($) {
            $('.view-history').on('click', function() {
                var examId = $(this).data('exam-id');
                var historyDiv = $('#history-' + examId);
                
                if (historyDiv.is(':visible')) {
                    historyDiv.slideUp();
                    $(this).text('<?php _e('تاریخچه آزمون‌ها', 'azmonyar'); ?> (' + historyDiv.find('.history-item').length + ')');
                } else {
                    historyDiv.slideDown();
                    $(this).text('<?php _e('بستن تاریخچه', 'azmonyar'); ?>');
                }
            });
        });
        </script>
        
        <style>
        .azmonyar-user-exams {
            max-width: 800px;
        }
        
        .exam-item {
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
            background: #fff;
        }
        
        .exam-header h3 {
            margin: 0 0 10px 0;
            color: #333;
        }
        
        .exam-meta {
            color: #666;
            font-size: 14px;
            margin-bottom: 15px;
        }
        
        .exam-details {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            flex-wrap: wrap;
            gap: 15px;
        }
        
        .exam-info {
            display: flex;
            gap: 20px;
            flex-wrap: wrap;
        }
        
        .info-item {
            display: flex;
            flex-direction: column;
            gap: 2px;
        }
        
        .info-item .label {
            font-size: 12px;
            color: #666;
        }
        
        .info-item .value {
            font-weight: bold;
            color: #333;
        }
        
        .exam-status {
            text-align: center;
        }
        
        .score-display .percentage {
            font-size: 24px;
            font-weight: bold;
            display: block;
        }
        
        .percentage.passed {
            color: #28a745;
        }
        
        .percentage.failed {
            color: #dc3545;
        }
        
        .exam-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        
        .exam-history {
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid #eee;
        }
        
        .history-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px;
            border: 1px solid #f0f0f0;
            border-radius: 4px;
            margin-bottom: 10px;
        }
        
        .result-info {
            display: flex;
            gap: 15px;
            align-items: center;
        }
        
        .result-info .score {
            font-weight: bold;
            padding: 4px 8px;
            border-radius: 4px;
            background: #f8f9fa;
        }
        
        .no-exams {
            text-align: center;
            padding: 40px 20px;
            color: #666;
        }
        
        @media (max-width: 768px) {
            .exam-details {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .exam-actions {
                width: 100%;
            }
            
            .exam-actions .button {
                flex: 1;
                text-align: center;
            }
        }
        </style>
        <?php
    }
    
    /**
     * دریافت آزمون‌های خریداری شده توسط کاربر
     */
    private function get_user_purchased_exams($user_id) {
        global $wpdb;
        
        // دریافت سفارشات تکمیل شده کاربر
        $orders = wc_get_orders(array(
            'customer' => $user_id,
            'status' => array('completed', 'processing'),
            'limit' => -1,
        ));
        
        $purchased_exams = array();
        
        foreach ($orders as $order) {
            foreach ($order->get_items() as $item) {
                $product_id = $item->get_product_id();
                
                // بررسی اینکه آیا این محصول مربوط به آزمونی است
                $exam_id = $this->get_exam_by_product($product_id);
                if ($exam_id) {
                    $purchased_exams[] = array(
                        'exam_id' => $exam_id,
                        'product_id' => $product_id,
                        'order_id' => $order->get_id(),
                        'purchase_date' => $order->get_date_created()->date('Y-m-d H:i:s'),
                        'order_status' => $order->get_status()
                    );
                }
            }
        }
        
        // حذف تکراری‌ها
        $unique_exams = array();
        foreach ($purchased_exams as $exam) {
            $unique_exams[$exam['exam_id']] = $exam;
        }
        
        return array_values($unique_exams);
    }
    
    /**
     * دریافت آزمون مرتبط با محصول
     */
    private function get_exam_by_product($product_id) {
        global $wpdb;
        
        $exam_id = $wpdb->get_var($wpdb->prepare(
            "SELECT post_id FROM {$wpdb->postmeta} 
             WHERE meta_key = '_azmonyar_wc_product_id' 
             AND meta_value = %d
             LIMIT 1",
            $product_id
        ));
        
        return $exam_id ? intval($exam_id) : null;
    }
    
    /**
     * افزودن اطلاعات آزمون به صفحه محصول
     */
    public function add_exam_info_to_product() {
        global $product;
        
        if (!$product) {
            return;
        }
        
        // بررسی اینکه آیا این محصول مربوط به آزمونی است
        $exam_id = $this->get_exam_by_product($product->get_id());
        if (!$exam_id) {
            return;
        }
        
        $exam = get_post($exam_id);
        if (!$exam) {
            return;
        }
        
        $time_limit = get_post_meta($exam_id, '_azmonyar_time_limit', true) ?: 60;
        $questions = get_post_meta($exam_id, '_azmonyar_questions', true) ?: array();
        $passing_score = get_post_meta($exam_id, '_azmonyar_passing_score', true) ?: 50;
        
        ?>
        <div class="azmonyar-exam-info">
            <h3><?php _e('اطلاعات آزمون', 'azmonyar'); ?></h3>
            
            <div class="exam-details-grid">
                <div class="detail-item">
                    <span class="icon">⏱</span>
                    <div class="detail-content">
                        <strong><?php _e('مدت زمان', 'azmonyar'); ?></strong>
                        <span><?php echo $time_limit; ?> <?php _e('دقیقه', 'azmonyar'); ?></span>
                    </div>
                </div>
                
                <div class="detail-item">
                    <span class="icon">❓</span>
                    <div class="detail-content">
                        <strong><?php _e('تعداد سوالات', 'azmonyar'); ?></strong>
                        <span><?php echo count($questions); ?> <?php _e('سوال', 'azmonyar'); ?></span>
                    </div>
                </div>
                
                <div class="detail-item">
                    <span class="icon">🎯</span>
                    <div class="detail-content">
                        <strong><?php _e('نمره قبولی', 'azmonyar'); ?></strong>
                        <span><?php echo $passing_score; ?>%</span>
                    </div>
                </div>
                
                <?php
                $subjects = wp_get_post_terms($exam_id, 'azmonyar_subject');
                if (!empty($subjects)):
                ?>
                <div class="detail-item">
                    <span class="icon">📚</span>
                    <div class="detail-content">
                        <strong><?php _e('رشته/درس', 'azmonyar'); ?></strong>
                        <span><?php echo implode(', ', wp_list_pluck($subjects, 'name')); ?></span>
                    </div>
                </div>
                <?php endif; ?>
                
                <?php
                $difficulties = wp_get_post_terms($exam_id, 'azmonyar_difficulty');
                if (!empty($difficulties)):
                ?>
                <div class="detail-item">
                    <span class="icon">📊</span>
                    <div class="detail-content">
                        <strong><?php _e('سطح دشواری', 'azmonyar'); ?></strong>
                        <span><?php echo implode(', ', wp_list_pluck($difficulties, 'name')); ?></span>
                    </div>
                </div>
                <?php endif; ?>
            </div>
            
            <?php if ($exam->post_content): ?>
                <div class="exam-description">
                    <h4><?php _e('توضیحات آزمون', 'azmonyar'); ?></h4>
                    <?php echo wpautop($exam->post_content); ?>
                </div>
            <?php endif; ?>
            
            <?php if (is_user_logged_in()): ?>
                <?php
                $user_id = get_current_user_id();
                $has_purchased = wc_customer_bought_product('', $user_id, $product->get_id());
                ?>
                
                <?php if ($has_purchased): ?>
                    <div class="exam-access-notice">
                        <div class="notice-content">
                            <span class="icon">✅</span>
                            <div>
                                <strong><?php _e('شما این آزمون را خریداری کرده‌اید', 'azmonyar'); ?></strong>
                                <p><?php _e('می‌توانید از طریق حساب کاربری خود وارد آزمون شوید', 'azmonyar'); ?></p>
                            </div>
                        </div>
                        <div class="access-actions">
                            <a href="<?php echo home_url('azmoon/' . $exam_id); ?>" class="button alt">
                                <?php _e('ورود به آزمون', 'azmonyar'); ?>
                            </a>
                            <a href="<?php echo wc_get_account_endpoint_url('azmonyar-exams'); ?>" class="button">
                                <?php _e('آزمون‌های من', 'azmonyar'); ?>
                            </a>
                        </div>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
        
        <style>
        .azmonyar-exam-info {
            background: #f8f9fa;
            border: 1px solid #e9ecef;
            border-radius: 8px;
            padding: 20px;
            margin: 20px 0;
        }
        
        .azmonyar-exam-info h3 {
            margin: 0 0 15px 0;
            color: #333;
            border-bottom: 2px solid #007cba;
            padding-bottom: 8px;
        }
        
        .exam-details-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }
        
        .detail-item {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 10px;
            background: white;
            border-radius: 6px;
            border: 1px solid #e0e0e0;
        }
        
        .detail-item .icon {
            font-size: 20px;
            width: 30px;
            text-align: center;
        }
        
        .detail-content {
            display: flex;
            flex-direction: column;
            gap: 2px;
        }
        
        .detail-content strong {
            font-size: 14px;
            color: #333;
        }
        
        .detail-content span {
            font-size: 13px;
            color: #666;
        }
        
        .exam-description {
            margin-top: 20px;
            padding-top: 15px;
            border-top: 1px solid #e9ecef;
        }
        
        .exam-description h4 {
            margin: 0 0 10px 0;
            color: #333;
        }
        
        .exam-access-notice {
            background: #d4edda;
            border: 1px solid #c3e6cb;
            border-radius: 6px;
            padding: 15px;
            margin-top: 20px;
        }
        
        .notice-content {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            margin-bottom: 15px;
        }
        
        .notice-content .icon {
            font-size: 18px;
            margin-top: 2px;
        }
        
        .notice-content strong {
            color: #155724;
            display: block;
            margin-bottom: 5px;
        }
        
        .notice-content p {
            margin: 0;
            color: #155724;
            font-size: 14px;
        }
        
        .access-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        </style>
        <?php
    }
    
    /**
     * محدود کردن خرید محصولات آزمون
     */
    public function restrict_exam_purchase($is_purchasable, $product) {
        // اگر کاربر وارد نشده، نمی‌تواند خرید کند
        if (!is_user_logged_in()) {
            $exam_id = $this->get_exam_by_product($product->get_id());
            if ($exam_id) {
                return false;
            }
        }
        
        return $is_purchasable;
    }
    
    /**
     * افزودن ستون آزمون به لیست سفارشات
     */
    public function add_orders_column($columns) {
        $new_columns = array();
        
        foreach ($columns as $key => $name) {
            $new_columns[$key] = $name;
            
            if ($key === 'order-status') {
                $new_columns['exam-access'] = __('دسترسی آزمون', 'azmonyar');
            }
        }
        
        return $new_columns;
    }
    
    /**
     * محتوای ستون دسترسی آزمون
     */
    public function orders_column_content($order) {
        $exam_items = array();
        
        foreach ($order->get_items() as $item) {
            $product_id = $item->get_product_id();
            $exam_id = $this->get_exam_by_product($product_id);
            
            if ($exam_id) {
                $exam = get_post($exam_id);
                if ($exam) {
                    $exam_items[] = array(
                        'exam_id' => $exam_id,
                        'exam_title' => $exam->post_title
                    );
                }
            }
        }
        
        if (!empty($exam_items)) {
            echo '<div class="exam-access-links">';
            foreach ($exam_items as $item) {
                echo '<a href="' . home_url('azmoon/' . $item['exam_id']) . '" class="exam-link" target="_blank">';
                echo esc_html($item['exam_title']);
                echo '</a><br>';
            }
            echo '</div>';
        } else {
            echo '-';
        }
    }
    
    /**
     * هنگام تکمیل سفارش
     */
    public function on_order_completed($order_id) {
        $this->process_exam_access($order_id, 'completed');
    }
    
    /**
     * هنگام پردازش سفارش
     */
    public function on_order_processing($order_id) {
        $this->process_exam_access($order_id, 'processing');
    }
    
    /**
     * هنگام بازگشت وجه
     */
    public function on_order_refunded($order_id) {
        $this->revoke_exam_access($order_id);
    }
    
    /**
     * پردازش دسترسی آزمون
     */
    private function process_exam_access($order_id, $status) {
        $order = wc_get_order($order_id);
        if (!$order) {
            return;
        }
        
        $user_id = $order->get_user_id();
        if (!$user_id) {
            return;
        }
        
        // ارسال ایمیل اطلاع‌رسانی
        $this->send_exam_access_email($order, $status);
        
        // ثبت لاگ
        $order->add_order_note(
            sprintf(__('دسترسی آزمون برای کاربر فعال شد (وضعیت: %s)', 'azmonyar'), $status)
        );
    }
    
    /**
     * لغو دسترسی آزمون
     */
    private function revoke_exam_access($order_id) {
        $order = wc_get_order($order_id);
        if (!$order) {
            return;
        }
        
        // ثبت لاگ
        $order->add_order_note(__('دسترسی آزمون لغو شد (بازگشت وجه)', 'azmonyar'));
        
        // اطلاع‌رسانی به کاربر
        $this->send_access_revoked_email($order);
    }
    
    /**
     * ارسال ایمیل دسترسی آزمون
     */
    private function send_exam_access_email($order, $status) {
        $user_id = $order->get_user_id();
        $user = get_user_by('id', $user_id);
        
        if (!$user) {
            return;
        }
        
        $exam_items = array();
        foreach ($order->get_items() as $item) {
            $product_id = $item->get_product_id();
            $exam_id = $this->get_exam_by_product($product_id);
            
            if ($exam_id) {
                $exam = get_post($exam_id);
                if ($exam) {
                    $exam_items[] = array(
                        'title' => $exam->post_title,
                        'url' => home_url('azmoon/' . $exam_id)
                    );
                }
            }
        }
        
        if (empty($exam_items)) {
            return;
        }
        
        $subject = sprintf(__('دسترسی آزمون فعال شد - سفارش #%s', 'azmonyar'), $order->get_order_number());
        
        $message = sprintf(__('سلام %s،', 'azmonyar'), $user->display_name) . "\n\n";
        $message .= __('دسترسی شما به آزمون‌های زیر فعال شد:', 'azmonyar') . "\n\n";
        
        foreach ($exam_items as $item) {
            $message .= "• " . $item['title'] . "\n";
            $message .= "  " . $item['url'] . "\n\n";
        }
        
        $message .= __('برای شروع آزمون، روی لینک‌های بالا کلیک کنید.', 'azmonyar') . "\n\n";
        $message .= __('موفق باشید!', 'azmonyar');
        
        wp_mail($user->user_email, $subject, $message);
    }
    
    /**
     * ارسال ایمیل لغو دسترسی
     */
    private function send_access_revoked_email($order) {
        $user_id = $order->get_user_id();
        $user = get_user_by('id', $user_id);
        
        if (!$user) {
            return;
        }
        
        $subject = sprintf(__('لغو دسترسی آزمون - سفارش #%s', 'azmonyar'), $order->get_order_number());
        
        $message = sprintf(__('سلام %s،', 'azmonyar'), $user->display_name) . "\n\n";
        $message .= sprintf(__('دسترسی شما به آزمون‌های سفارش #%s به دلیل بازگشت وجه لغو شد.', 'azmonyar'), $order->get_order_number()) . "\n\n";
        $message .= __('در صورت داشتن سوال، با پشتیبانی تماس بگیرید.', 'azmonyar');
        
        wp_mail($user->user_email, $subject, $message);
    }
    
    /**
     * افزودن نوع محصول آزمون
     */
    public function add_exam_product_type($types) {
        $types['azmonyar_exam'] = __('آزمون آنلاین', 'azmonyar');
        return $types;
    }
    
    /**
     * گزینه‌های محصول آزمون
     */
    public function exam_product_options() {
        global $post;
        
        // نمایش فقط برای نوع محصول آزمون
        echo '<div class="options_group show_if_azmonyar_exam">';
        
        // انتخاب آزمون
        $exams = get_posts(array(
            'post_type' => 'azmoon',
            'posts_per_page' => -1,
            'post_status' => 'publish'
        ));
        
        $selected_exam = get_post_meta($post->ID, '_azmonyar_linked_exam', true);
        
        echo '<p class="form-field">';
        echo '<label for="_azmonyar_linked_exam">' . __('آزمون مرتبط', 'azmonyar') . '</label>';
        echo '<select id="_azmonyar_linked_exam" name="_azmonyar_linked_exam" class="select short">';
        echo '<option value="">' . __('انتخاب آزمون', 'azmonyar') . '</option>';
        
        foreach ($exams as $exam) {
            $selected = selected($selected_exam, $exam->ID, false);
            echo '<option value="' . $exam->ID . '" ' . $selected . '>' . $exam->post_title . '</option>';
        }
        
        echo '</select>';
        echo '</p>';
        
        echo '</div>';
    }
    
    /**
     * ذخیره گزینه‌های محصول آزمون
     */
    public function save_exam_product_options($post_id) {
        if (isset($_POST['_azmonyar_linked_exam'])) {
            $exam_id = intval($_POST['_azmonyar_linked_exam']);
            update_post_meta($post_id, '_azmonyar_linked_exam', $exam_id);
            
            // بروزرسانی آزمون با شناسه محصول
            if ($exam_id) {
                update_post_meta($exam_id, '_azmonyar_wc_product_id', $post_id);
            }
        }
    }
    
    /**
     * بارگذاری کلاس محصول آزمون
     */
    public function load_exam_product_class() {
        if (!class_exists('WC_Product_Azmonyar_Exam')) {
            require_once AZMONYAR_PLUGIN_DIR . 'includes/class-wc-product-exam.php';
        }
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
}