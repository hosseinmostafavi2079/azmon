<?php
/**
 * کلاس مدیریت Custom Post Types
 * 
 * @package AzmonyarProfessional
 */

// جلوگیری از دسترسی مستقیم
if (!defined('ABSPATH')) {
    exit;
}

/**
 * کلاس مدیریت انواع پست سفارشی
 */
class Azmonyar_Post_Types {
    
    /**
     * سازنده کلاس
     */
    public function __construct() {
        add_action('init', array($this, 'register_post_types'));
        add_action('init', array($this, 'register_taxonomies'));
        add_action('add_meta_boxes', array($this, 'add_meta_boxes'));
        add_action('save_post', array($this, 'save_meta_boxes'));
    }
    
    /**
     * ثبت انواع پست سفارشی
     */
    public function register_post_types() {
        // ثبت نوع پست آزمون
        $this->register_exam_post_type();
        
        // ثبت نوع پست سوال
        $this->register_question_post_type();
    }
    
    /**
     * ثبت نوع پست آزمون
     */
    private function register_exam_post_type() {
        $labels = array(
            'name'                  => __('آزمون‌ها', 'azmonyar'),
            'singular_name'         => __('آزمون', 'azmonyar'),
            'menu_name'             => __('آزمون‌ها', 'azmonyar'),
            'name_admin_bar'        => __('آزمون', 'azmonyar'),
            'archives'              => __('آرشیو آزمون‌ها', 'azmonyar'),
            'attributes'            => __('ویژگی‌های آزمون', 'azmonyar'),
            'parent_item_colon'     => __('آزمون والد:', 'azmonyar'),
            'all_items'             => __('همه آزمون‌ها', 'azmonyar'),
            'add_new_item'          => __('افزودن آزمون جدید', 'azmonyar'),
            'add_new'               => __('افزودن جدید', 'azmonyar'),
            'new_item'              => __('آزمون جدید', 'azmonyar'),
            'edit_item'             => __('ویرایش آزمون', 'azmonyar'),
            'update_item'           => __('بروزرسانی آزمون', 'azmonyar'),
            'view_item'             => __('مشاهده آزمون', 'azmonyar'),
            'view_items'            => __('مشاهده آزمون‌ها', 'azmonyar'),
            'search_items'          => __('جستجوی آزمون', 'azmonyar'),
            'not_found'             => __('آزمونی یافت نشد', 'azmonyar'),
            'not_found_in_trash'    => __('آزمونی در زباله‌دان یافت نشد', 'azmonyar'),
            'featured_image'        => __('تصویر شاخص', 'azmonyar'),
            'set_featured_image'    => __('تنظیم تصویر شاخص', 'azmonyar'),
            'remove_featured_image' => __('حذف تصویر شاخص', 'azmonyar'),
            'use_featured_image'    => __('استفاده به عنوان تصویر شاخص', 'azmonyar'),
            'insert_into_item'      => __('درج در آزمون', 'azmonyar'),
            'uploaded_to_this_item' => __('آپلود شده برای این آزمون', 'azmonyar'),
            'items_list'            => __('لیست آزمون‌ها', 'azmonyar'),
            'items_list_navigation' => __('ناوبری لیست آزمون‌ها', 'azmonyar'),
            'filter_items_list'     => __('فیلتر لیست آزمون‌ها', 'azmonyar'),
        );
        
        $args = array(
            'label'                 => __('آزمون', 'azmonyar'),
            'description'           => __('آزمون‌های آنلاین', 'azmonyar'),
            'labels'                => $labels,
            'supports'              => array('title', 'editor', 'thumbnail', 'excerpt', 'author'),
            'taxonomies'            => array('azmonyar_subject', 'azmonyar_difficulty'),
            'hierarchical'          => false,
            'public'                => false,
            'show_ui'               => true,
            'show_in_menu'          => false, // خودمان منو را مدیریت می‌کنیم
            'menu_position'         => 25,
            'menu_icon'             => 'dashicons-clipboard',
            'show_in_admin_bar'     => true,
            'show_in_nav_menus'     => false,
            'can_export'            => true,
            'has_archive'           => false,
            'exclude_from_search'   => true,
            'publicly_queryable'    => false,
            'capability_type'       => 'post',
            'show_in_rest'          => false,
        );
        
        register_post_type('azmoon', $args);
    }
    
    /**
     * ثبت نوع پست سوال
     */
    private function register_question_post_type() {
        $labels = array(
            'name'                  => __('سوالات', 'azmonyar'),
            'singular_name'         => __('سوال', 'azmonyar'),
            'menu_name'             => __('سوالات', 'azmonyar'),
            'name_admin_bar'        => __('سوال', 'azmonyar'),
            'archives'              => __('آرشیو سوالات', 'azmonyar'),
            'attributes'            => __('ویژگی‌های سوال', 'azmonyar'),
            'parent_item_colon'     => __('سوال والد:', 'azmonyar'),
            'all_items'             => __('همه سوالات', 'azmonyar'),
            'add_new_item'          => __('افزودن سوال جدید', 'azmonyar'),
            'add_new'               => __('افزودن جدید', 'azmonyar'),
            'new_item'              => __('سوال جدید', 'azmonyar'),
            'edit_item'             => __('ویرایش سوال', 'azmonyar'),
            'update_item'           => __('بروزرسانی سوال', 'azmonyar'),
            'view_item'             => __('مشاهده سوال', 'azmonyar'),
            'view_items'            => __('مشاهده سوالات', 'azmonyar'),
            'search_items'          => __('جستجوی سوال', 'azmonyar'),
            'not_found'             => __('سوالی یافت نشد', 'azmonyar'),
            'not_found_in_trash'    => __('سوالی در زباله‌دان یافت نشد', 'azmonyar'),
            'featured_image'        => __('تصویر سوال', 'azmonyar'),
            'set_featured_image'    => __('تنظیم تصویر سوال', 'azmonyar'),
            'remove_featured_image' => __('حذف تصویر سوال', 'azmonyar'),
            'use_featured_image'    => __('استفاده به عنوان تصویر سوال', 'azmonyar'),
            'insert_into_item'      => __('درج در سوال', 'azmonyar'),
            'uploaded_to_this_item' => __('آپلود شده برای این سوال', 'azmonyar'),
            'items_list'            => __('لیست سوالات', 'azmonyar'),
            'items_list_navigation' => __('ناوبری لیست سوالات', 'azmonyar'),
            'filter_items_list'     => __('فیلتر لیست سوالات', 'azmonyar'),
        );
        
        $args = array(
            'label'                 => __('سوال', 'azmonyar'),
            'description'           => __('بانک سوالات آزمون‌ها', 'azmonyar'),
            'labels'                => $labels,
            'supports'              => array('title', 'editor', 'thumbnail', 'author'),
            'taxonomies'            => array('azmonyar_subject', 'azmonyar_difficulty'),
            'hierarchical'          => false,
            'public'                => false,
            'show_ui'               => true,
            'show_in_menu'          => false, // خودمان منو را مدیریت می‌کنیم
            'menu_position'         => 26,
            'menu_icon'             => 'dashicons-editor-help',
            'show_in_admin_bar'     => true,
            'show_in_nav_menus'     => false,
            'can_export'            => true,
            'has_archive'           => false,
            'exclude_from_search'   => true,
            'publicly_queryable'    => false,
            'capability_type'       => 'post',
            'show_in_rest'          => false,
        );
        
        register_post_type('soal', $args);
    }
    
    /**
     * ثبت طبقه‌بندی‌های سفارشی
     */
    public function register_taxonomies() {
        // طبقه‌بندی رشته/درس
        $this->register_subject_taxonomy();
        
        // طبقه‌بندی سطح دشواری
        $this->register_difficulty_taxonomy();
    }
    
    /**
     * ثبت طبقه‌بندی رشته/درس
     */
    private function register_subject_taxonomy() {
        $labels = array(
            'name'                       => __('رشته‌ها و دروس', 'azmonyar'),
            'singular_name'              => __('رشته/درس', 'azmonyar'),
            'menu_name'                  => __('رشته‌ها و دروس', 'azmonyar'),
            'all_items'                  => __('همه رشته‌ها', 'azmonyar'),
            'parent_item'                => __('رشته والد', 'azmonyar'),
            'parent_item_colon'          => __('رشته والد:', 'azmonyar'),
            'new_item_name'              => __('نام رشته جدید', 'azmonyar'),
            'add_new_item'               => __('افزودن رشته جدید', 'azmonyar'),
            'edit_item'                  => __('ویرایش رشته', 'azmonyar'),
            'update_item'                => __('بروزرسانی رشته', 'azmonyar'),
            'view_item'                  => __('مشاهده رشته', 'azmonyar'),
            'separate_items_with_commas' => __('رشته‌ها را با کاما جدا کنید', 'azmonyar'),
            'add_or_remove_items'        => __('افزودن یا حذف رشته‌ها', 'azmonyar'),
            'choose_from_most_used'      => __('انتخاب از پرکاربردترین‌ها', 'azmonyar'),
            'popular_items'              => __('رشته‌های محبوب', 'azmonyar'),
            'search_items'               => __('جستجوی رشته‌ها', 'azmonyar'),
            'not_found'                  => __('رشته‌ای یافت نشد', 'azmonyar'),
            'no_terms'                   => __('بدون رشته', 'azmonyar'),
            'items_list'                 => __('لیست رشته‌ها', 'azmonyar'),
            'items_list_navigation'      => __('ناوبری لیست رشته‌ها', 'azmonyar'),
        );
        
        $args = array(
            'labels'                     => $labels,
            'hierarchical'               => true,
            'public'                     => false,
            'show_ui'                    => true,
            'show_admin_column'          => true,
            'show_in_nav_menus'          => false,
            'show_tagcloud'              => false,
            'show_in_rest'               => false,
        );
        
        register_taxonomy('azmonyar_subject', array('azmoon', 'soal'), $args);
    }
    
    /**
     * ثبت طبقه‌بندی سطح دشواری
     */
    private function register_difficulty_taxonomy() {
        $labels = array(
            'name'                       => __('سطوح دشواری', 'azmonyar'),
            'singular_name'              => __('سطح دشواری', 'azmonyar'),
            'menu_name'                  => __('سطوح دشواری', 'azmonyar'),
            'all_items'                  => __('همه سطوح', 'azmonyar'),
            'parent_item'                => __('سطح والد', 'azmonyar'),
            'parent_item_colon'          => __('سطح والد:', 'azmonyar'),
            'new_item_name'              => __('نام سطح جدید', 'azmonyar'),
            'add_new_item'               => __('افزودن سطح جدید', 'azmonyar'),
            'edit_item'                  => __('ویرایش سطح', 'azmonyar'),
            'update_item'                => __('بروزرسانی سطح', 'azmonyar'),
            'view_item'                  => __('مشاهده سطح', 'azmonyar'),
            'separate_items_with_commas' => __('سطوح را با کاما جدا کنید', 'azmonyar'),
            'add_or_remove_items'        => __('افزودن یا حذف سطوح', 'azmonyar'),
            'choose_from_most_used'      => __('انتخاب از پرکاربردترین‌ها', 'azmonyar'),
            'popular_items'              => __('سطوح محبوب', 'azmonyar'),
            'search_items'               => __('جستجوی سطوح', 'azmonyar'),
            'not_found'                  => __('سطحی یافت نشد', 'azmonyar'),
            'no_terms'                   => __('بدون سطح', 'azmonyar'),
            'items_list'                 => __('لیست سطوح', 'azmonyar'),
            'items_list_navigation'      => __('ناوبری لیست سطوح', 'azmonyar'),
        );
        
        $args = array(
            'labels'                     => $labels,
            'hierarchical'               => false,
            'public'                     => false,
            'show_ui'                    => true,
            'show_admin_column'          => true,
            'show_in_nav_menus'          => false,
            'show_tagcloud'              => false,
            'show_in_rest'               => false,
        );
        
        register_taxonomy('azmonyar_difficulty', array('azmoon', 'soal'), $args);
    }
    
    /**
     * افزودن متابکس‌ها
     */
    public function add_meta_boxes() {
        // متابکس آزمون
        add_meta_box(
            'azmonyar_exam_settings',
            __('تنظیمات آزمون', 'azmonyar'),
            array($this, 'exam_settings_callback'),
            'azmoon',
            'normal',
            'high'
        );
        
        // متابکس سوالات آزمون
        add_meta_box(
            'azmonyar_exam_questions',
            __('سوالات آزمون', 'azmonyar'),
            array($this, 'exam_questions_callback'),
            'azmoon',
            'normal',
            'high'
        );
        
        // متابکس سوال
        add_meta_box(
            'azmonyar_question_options',
            __('گزینه‌های سوال', 'azmonyar'),
            array($this, 'question_options_callback'),
            'soal',
            'normal',
            'high'
        );
    }
    
    /**
     * نمایش متابکس تنظیمات آزمون
     */
    public function exam_settings_callback($post) {
        wp_nonce_field('azmonyar_exam_settings', 'azmonyar_exam_settings_nonce');
        
        $time_limit = get_post_meta($post->ID, '_azmonyar_time_limit', true) ?: 60;
        $wc_product_id = get_post_meta($post->ID, '_azmonyar_wc_product_id', true);
        $passing_score = get_post_meta($post->ID, '_azmonyar_passing_score', true) ?: 50;
        $randomize_questions = get_post_meta($post->ID, '_azmonyar_randomize_questions', true);
        $randomize_answers = get_post_meta($post->ID, '_azmonyar_randomize_answers', true);
        $show_results = get_post_meta($post->ID, '_azmonyar_show_results', true) ?: 'immediately';
        
        ?>
        <table class="form-table">
            <tr>
                <th scope="row">
                    <label for="azmonyar_time_limit"><?php _e('مدت زمان آزمون (دقیقه)', 'azmonyar'); ?></label>
                </th>
                <td>
                    <input type="number" id="azmonyar_time_limit" name="azmonyar_time_limit" 
                           value="<?php echo esc_attr($time_limit); ?>" min="1" max="300" />
                    <p class="description"><?php _e('مدت زمان آزمون به دقیقه', 'azmonyar'); ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="azmonyar_wc_product_id"><?php _e('محصول ووکامرس', 'azmonyar'); ?></label>
                </th>
                <td>
                    <?php
                    $products = get_posts(array(
                        'post_type' => 'product',
                        'posts_per_page' => -1,
                        'post_status' => 'publish'
                    ));
                    ?>
                    <select id="azmonyar_wc_product_id" name="azmonyar_wc_product_id">
                        <option value=""><?php _e('انتخاب محصول', 'azmonyar'); ?></option>
                        <?php foreach ($products as $product): ?>
                            <option value="<?php echo $product->ID; ?>" 
                                    <?php selected($wc_product_id, $product->ID); ?>>
                                <?php echo $product->post_title; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <p class="description"><?php _e('محصول ووکامرس مرتبط با این آزمون', 'azmonyar'); ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="azmonyar_passing_score"><?php _e('نمره قبولی (درصد)', 'azmonyar'); ?></label>
                </th>
                <td>
                    <input type="number" id="azmonyar_passing_score" name="azmonyar_passing_score" 
                           value="<?php echo esc_attr($passing_score); ?>" min="0" max="100" />
                    <p class="description"><?php _e('حداقل نمره برای قبولی در آزمون', 'azmonyar'); ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row"><?php _e('تنظیمات نمایش', 'azmonyar'); ?></th>
                <td>
                    <fieldset>
                        <label>
                            <input type="checkbox" name="azmonyar_randomize_questions" value="1" 
                                   <?php checked($randomize_questions, '1'); ?> />
                            <?php _e('ترتیب تصادفی سوالات', 'azmonyar'); ?>
                        </label><br />
                        <label>
                            <input type="checkbox" name="azmonyar_randomize_answers" value="1" 
                                   <?php checked($randomize_answers, '1'); ?> />
                            <?php _e('ترتیب تصادفی گزینه‌ها', 'azmonyar'); ?>
                        </label>
                    </fieldset>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="azmonyar_show_results"><?php _e('نمایش نتایج', 'azmonyar'); ?></label>
                </th>
                <td>
                    <select id="azmonyar_show_results" name="azmonyar_show_results">
                        <option value="immediately" <?php selected($show_results, 'immediately'); ?>>
                            <?php _e('بلافاصله پس از آزمون', 'azmonyar'); ?>
                        </option>
                        <option value="after_review" <?php selected($show_results, 'after_review'); ?>>
                            <?php _e('پس از بررسی مدیر', 'azmonyar'); ?>
                        </option>
                        <option value="never" <?php selected($show_results, 'never'); ?>>
                            <?php _e('عدم نمایش نتایج', 'azmonyar'); ?>
                        </option>
                    </select>
                </td>
            </tr>
        </table>
        <?php
    }
    
    /**
     * نمایش متابکس سوالات آزمون
     */
    public function exam_questions_callback($post) {
        wp_nonce_field('azmonyar_exam_questions', 'azmonyar_exam_questions_nonce');
        
        $selected_questions = get_post_meta($post->ID, '_azmonyar_questions', true) ?: array();
        
        ?>
        <div id="azmonyar-questions-selector">
            <p><?php _e('سوالات انتخاب شده برای این آزمون:', 'azmonyar'); ?></p>
            <div id="selected-questions">
                <?php if (!empty($selected_questions)): ?>
                    <?php foreach ($selected_questions as $question_id): ?>
                        <?php $question = get_post($question_id); ?>
                        <?php if ($question): ?>
                            <div class="question-item" data-question-id="<?php echo $question_id; ?>">
                                <span><?php echo $question->post_title; ?></span>
                                <button type="button" class="remove-question">حذف</button>
                            </div>
                        <?php endif; ?>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            
            <button type="button" id="add-questions-btn" class="button">
                <?php _e('افزودن سوالات', 'azmonyar'); ?>
            </button>
            
            <input type="hidden" name="azmonyar_questions" id="azmonyar_questions" 
                   value="<?php echo esc_attr(implode(',', $selected_questions)); ?>" />
        </div>
        
        <div id="questions-modal" style="display: none;">
            <div class="modal-content">
                <h3><?php _e('انتخاب سوالات', 'azmonyar'); ?></h3>
                <div id="questions-list">
                    <!-- سوالات از طریق AJAX بارگذاری می‌شوند -->
                </div>
                <button type="button" id="confirm-questions" class="button button-primary">
                    <?php _e('تأیید انتخاب', 'azmonyar'); ?>
                </button>
                <button type="button" id="cancel-questions" class="button">
                    <?php _e('انصراف', 'azmonyar'); ?>
                </button>
            </div>
        </div>
        <?php
    }
    
    /**
     * نمایش متابکس گزینه‌های سوال
     */
    public function question_options_callback($post) {
        wp_nonce_field('azmonyar_question_options', 'azmonyar_question_options_nonce');
        
        $option_a = get_post_meta($post->ID, '_azmonyar_option_a', true);
        $option_b = get_post_meta($post->ID, '_azmonyar_option_b', true);
        $option_c = get_post_meta($post->ID, '_azmonyar_option_c', true);
        $option_d = get_post_meta($post->ID, '_azmonyar_option_d', true);
        $correct_answer = get_post_meta($post->ID, '_azmonyar_correct_answer', true);
        $explanation = get_post_meta($post->ID, '_azmonyar_explanation', true);
        
        ?>
        <table class="form-table">
            <tr>
                <th scope="row">
                    <label for="azmonyar_option_a"><?php _e('گزینه الف', 'azmonyar'); ?></label>
                </th>
                <td>
                    <input type="text" id="azmonyar_option_a" name="azmonyar_option_a" 
                           value="<?php echo esc_attr($option_a); ?>" class="regular-text" />
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="azmonyar_option_b"><?php _e('گزینه ب', 'azmonyar'); ?></label>
                </th>
                <td>
                    <input type="text" id="azmonyar_option_b" name="azmonyar_option_b" 
                           value="<?php echo esc_attr($option_b); ?>" class="regular-text" />
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="azmonyar_option_c"><?php _e('گزینه ج', 'azmonyar'); ?></label>
                </th>
                <td>
                    <input type="text" id="azmonyar_option_c" name="azmonyar_option_c" 
                           value="<?php echo esc_attr($option_c); ?>" class="regular-text" />
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="azmonyar_option_d"><?php _e('گزینه د', 'azmonyar'); ?></label>
                </th>
                <td>
                    <input type="text" id="azmonyar_option_d" name="azmonyar_option_d" 
                           value="<?php echo esc_attr($option_d); ?>" class="regular-text" />
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="azmonyar_correct_answer"><?php _e('پاسخ صحیح', 'azmonyar'); ?></label>
                </th>
                <td>
                    <select id="azmonyar_correct_answer" name="azmonyar_correct_answer">
                        <option value=""><?php _e('انتخاب پاسخ صحیح', 'azmonyar'); ?></option>
                        <option value="a" <?php selected($correct_answer, 'a'); ?>><?php _e('الف', 'azmonyar'); ?></option>
                        <option value="b" <?php selected($correct_answer, 'b'); ?>><?php _e('ب', 'azmonyar'); ?></option>
                        <option value="c" <?php selected($correct_answer, 'c'); ?>><?php _e('ج', 'azmonyar'); ?></option>
                        <option value="d" <?php selected($correct_answer, 'd'); ?>><?php _e('د', 'azmonyar'); ?></option>
                    </select>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="azmonyar_explanation"><?php _e('توضیح پاسخ', 'azmonyar'); ?></label>
                </th>
                <td>
                    <textarea id="azmonyar_explanation" name="azmonyar_explanation" 
                              rows="4" cols="50" class="large-text"><?php echo esc_textarea($explanation); ?></textarea>
                    <p class="description"><?php _e('توضیح اختیاری برای پاسخ صحیح', 'azmonyar'); ?></p>
                </td>
            </tr>
        </table>
        <?php
    }
    
    /**
     * ذخیره متابکس‌ها
     */
    public function save_meta_boxes($post_id) {
        // بررسی nonce و مجوزها
        if (!isset($_POST['azmonyar_exam_settings_nonce']) && 
            !isset($_POST['azmonyar_exam_questions_nonce']) && 
            !isset($_POST['azmonyar_question_options_nonce'])) {
            return;
        }
        
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }
        
        // ذخیره تنظیمات آزمون
        if (isset($_POST['azmonyar_exam_settings_nonce']) && 
            wp_verify_nonce($_POST['azmonyar_exam_settings_nonce'], 'azmonyar_exam_settings')) {
            
            $this->save_exam_settings($post_id);
        }
        
        // ذخیره سوالات آزمون
        if (isset($_POST['azmonyar_exam_questions_nonce']) && 
            wp_verify_nonce($_POST['azmonyar_exam_questions_nonce'], 'azmonyar_exam_questions')) {
            
            $this->save_exam_questions($post_id);
        }
        
        // ذخیره گزینه‌های سوال
        if (isset($_POST['azmonyar_question_options_nonce']) && 
            wp_verify_nonce($_POST['azmonyar_question_options_nonce'], 'azmonyar_question_options')) {
            
            $this->save_question_options($post_id);
        }
    }
    
    /**
     * ذخیره تنظیمات آزمون
     */
    private function save_exam_settings($post_id) {
        $fields = array(
            'azmonyar_time_limit' => 'intval',
            'azmonyar_wc_product_id' => 'intval',
            'azmonyar_passing_score' => 'intval',
            'azmonyar_show_results' => 'sanitize_text_field',
        );
        
        foreach ($fields as $field => $sanitize_function) {
            if (isset($_POST[$field])) {
                update_post_meta($post_id, '_' . $field, $sanitize_function($_POST[$field]));
            }
        }
        
        // ذخیره چک‌باکس‌ها
        $checkboxes = array('azmonyar_randomize_questions', 'azmonyar_randomize_answers');
        foreach ($checkboxes as $checkbox) {
            $value = isset($_POST[$checkbox]) ? '1' : '0';
            update_post_meta($post_id, '_' . $checkbox, $value);
        }
    }
    
    /**
     * ذخیره سوالات آزمون
     */
    private function save_exam_questions($post_id) {
        if (isset($_POST['azmonyar_questions'])) {
            $questions = sanitize_text_field($_POST['azmonyar_questions']);
            $questions_array = !empty($questions) ? explode(',', $questions) : array();
            $questions_array = array_map('intval', $questions_array);
            update_post_meta($post_id, '_azmonyar_questions', $questions_array);
        }
    }
    
    /**
     * ذخیره گزینه‌های سوال
     */
    private function save_question_options($post_id) {
        $fields = array(
            'azmonyar_option_a' => 'sanitize_text_field',
            'azmonyar_option_b' => 'sanitize_text_field', 
            'azmonyar_option_c' => 'sanitize_text_field',
            'azmonyar_option_d' => 'sanitize_text_field',
            'azmonyar_correct_answer' => 'sanitize_text_field',
            'azmonyar_explanation' => 'sanitize_textarea_field',
        );
        
        foreach ($fields as $field => $sanitize_function) {
            if (isset($_POST[$field])) {
                update_post_meta($post_id, '_' . $field, $sanitize_function($_POST[$field]));
            }
        }
    }
}