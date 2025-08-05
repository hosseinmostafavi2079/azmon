<?php
/**
 * کلاس واردسازی CSV
 * 
 * @package AzmonyarProfessional
 */

// جلوگیری از دسترسی مستقیم
if (!defined('ABSPATH')) {
    exit;
}

/**
 * کلاس مدیریت واردسازی سوالات از فایل CSV
 */
class Azmonyar_CSV_Import {
    
    /**
     * حداکثر تعداد سوالات قابل واردسازی در هر بار
     */
    const MAX_IMPORT_LIMIT = 1000;
    
    /**
     * فرمت مورد انتظار CSV
     */
    const EXPECTED_COLUMNS = array(
        'subject',      // رشته
        'course',       // درس
        'difficulty',   // سطح دشواری
        'question',     // سوال
        'option_a',     // گزینه ۱
        'option_b',     // گزینه ۲
        'option_c',     // گزینه ۳
        'option_d',     // گزینه ۴
        'correct_answer' // پاسخ صحیح
    );
    
    /**
     * آرایه خطاها
     */
    private $errors = array();
    
    /**
     * آرایه هشدارها
     */
    private $warnings = array();
    
    /**
     * آمار واردسازی
     */
    private $stats = array(
        'total_rows' => 0,
        'imported' => 0,
        'skipped' => 0,
        'errors' => 0
    );
    
    /**
     * واردسازی از فایل آپلود شده
     */
    public function import_from_upload() {
        try {
            // بررسی فایل آپلود شده
            if (!isset($_FILES['csv_file']) || $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
                throw new Exception(__('خطا در آپلود فایل', 'azmonyar'));
            }
            
            $file_path = $_FILES['csv_file']['tmp_name'];
            $file_name = $_FILES['csv_file']['name'];
            
            // بررسی پسوند فایل
            $file_extension = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
            if ($file_extension !== 'csv') {
                throw new Exception(__('فقط فایل‌های CSV پذیرفته می‌شوند', 'azmonyar'));
            }
            
            // بررسی اندازه فایل (حداکثر ۵ مگابایت)
            if ($_FILES['csv_file']['size'] > 5 * 1024 * 1024) {
                throw new Exception(__('اندازه فایل نباید از ۵ مگابایت بیشتر باشد', 'azmonyar'));
            }
            
            // واردسازی فایل
            $result = $this->process_csv_file($file_path);
            
            return array(
                'success' => true,
                'message' => sprintf(
                    __('واردسازی با موفقیت انجام شد. %d سوال وارد شد، %d نادیده گرفته شد.', 'azmonyar'),
                    $this->stats['imported'],
                    $this->stats['skipped']
                ),
                'stats' => $this->stats,
                'warnings' => $this->warnings,
                'errors' => $this->errors
            );
            
        } catch (Exception $e) {
            return array(
                'success' => false,
                'message' => $e->getMessage(),
                'stats' => $this->stats,
                'warnings' => $this->warnings,
                'errors' => $this->errors
            );
        }
    }
    
    /**
     * پردازش فایل CSV
     */
    private function process_csv_file($file_path) {
        // باز کردن فایل
        $handle = fopen($file_path, 'r');
        if (!$handle) {
            throw new Exception(__('خطا در باز کردن فایل', 'azmonyar'));
        }
        
        // تنظیمات واردسازی
        $default_author = intval($_POST['default_author'] ?? get_current_user_id());
        $skip_duplicates = isset($_POST['skip_duplicates']) && $_POST['skip_duplicates'] === '1';
        
        $row_number = 0;
        $header_validated = false;
        
        // خواندن فایل خط به خط
        while (($data = fgetcsv($handle, 1000, ',')) !== FALSE) {
            $row_number++;
            $this->stats['total_rows']++;
            
            // بررسی هدر در خط اول
            if (!$header_validated) {
                if (!$this->validate_header($data)) {
                    fclose($handle);
                    throw new Exception(__('فرمت فایل CSV صحیح نمی‌باشد. لطفاً فرمت مورد نظر را رعایت کنید.', 'azmonyar'));
                }
                $header_validated = true;
                continue; // رد شدن از خط هدر
            }
            
            // محدودیت تعداد
            if ($this->stats['imported'] >= self::MAX_IMPORT_LIMIT) {
                $this->warnings[] = sprintf(
                    __('حداکثر %d سوال در هر بار قابل واردسازی است. سایر سوالات نادیده گرفته شدند.', 'azmonyar'),
                    self::MAX_IMPORT_LIMIT
                );
                break;
            }
            
            // پردازش هر خط
            $this->process_row($data, $row_number, $default_author, $skip_duplicates);
        }
        
        fclose($handle);
        
        return true;
    }
    
    /**
     * اعتبارسنجی هدر فایل
     */
    private function validate_header($header) {
        // بررسی تعداد ستون‌ها
        if (count($header) < count(self::EXPECTED_COLUMNS)) {
            return false;
        }
        
        // هدرهای مورد انتظار (فارسی)
        $expected_persian_headers = array(
            'رشته',
            'درس', 
            'سطح',
            'سوال',
            'گزینه۱',
            'گزینه۲', 
            'گزینه۳',
            'گزینه۴',
            'پاسخ صحیح'
        );
        
        // بررسی تطبیق هدرها
        for ($i = 0; $i < count($expected_persian_headers); $i++) {
            if (trim($header[$i]) !== $expected_persian_headers[$i]) {
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * پردازش هر خط از فایل
     */
    private function process_row($data, $row_number, $default_author, $skip_duplicates) {
        try {
            // بررسی تعداد ستون‌ها
            if (count($data) < count(self::EXPECTED_COLUMNS)) {
                throw new Exception(sprintf(__('خط %d: تعداد ستون‌ها کافی نیست', 'azmonyar'), $row_number));
            }
            
            // استخراج داده‌ها
            $subject = trim($data[0]);
            $course = trim($data[1]);
            $difficulty = trim($data[2]);
            $question = trim($data[3]);
            $option_a = trim($data[4]);
            $option_b = trim($data[5]);
            $option_c = trim($data[6]);
            $option_d = trim($data[7]);
            $correct_answer = trim($data[8]);
            
            // اعتبارسنجی داده‌ها
            $this->validate_row_data($question, $option_a, $option_b, $option_c, $option_d, $correct_answer, $row_number);
            
            // بررسی تکراری بودن
            if ($skip_duplicates && $this->is_duplicate_question($question)) {
                $this->stats['skipped']++;
                $this->warnings[] = sprintf(__('خط %d: سوال تکراری نادیده گرفته شد', 'azmonyar'), $row_number);
                return;
            }
            
            // ایجاد یا دریافت دسته‌بندی‌ها
            $subject_term = $this->get_or_create_subject($subject, $course);
            $difficulty_term = $this->get_or_create_difficulty($difficulty);
            
            // ایجاد سوال جدید
            $question_id = $this->create_question(
                $question,
                $option_a,
                $option_b, 
                $option_c,
                $option_d,
                $correct_answer,
                $default_author,
                $subject_term,
                $difficulty_term
            );
            
            if ($question_id) {
                $this->stats['imported']++;
            } else {
                throw new Exception(__('خطا در ایجاد سوال', 'azmonyar'));
            }
            
        } catch (Exception $e) {
            $this->stats['errors']++;
            $this->errors[] = sprintf(__('خط %d: %s', 'azmonyar'), $row_number, $e->getMessage());
        }
    }
    
    /**
     * اعتبارسنجی داده‌های هر خط
     */
    private function validate_row_data($question, $option_a, $option_b, $option_c, $option_d, $correct_answer, $row_number) {
        // بررسی خالی نبودن فیلدهای ضروری
        if (empty($question)) {
            throw new Exception(__('متن سوال نمی‌تواند خالی باشد', 'azmonyar'));
        }
        
        if (empty($option_a) || empty($option_b) || empty($option_c) || empty($option_d)) {
            throw new Exception(__('همه گزینه‌ها باید پر باشند', 'azmonyar'));
        }
        
        if (empty($correct_answer)) {
            throw new Exception(__('پاسخ صحیح نمی‌تواند خالی باشد', 'azmonyar'));
        }
        
        // بررسی طول متن‌ها
        if (mb_strlen($question) > 1000) {
            throw new Exception(__('متن سوال بیش از حد طولانی است', 'azmonyar'));
        }
        
        if (mb_strlen($option_a) > 200 || mb_strlen($option_b) > 200 || 
            mb_strlen($option_c) > 200 || mb_strlen($option_d) > 200) {
            throw new Exception(__('متن گزینه‌ها بیش از حد طولانی است', 'azmonyar'));
        }
        
        // بررسی صحت پاسخ
        $valid_answers = array('1', '2', '3', '4', 'الف', 'ب', 'ج', 'د', 'a', 'b', 'c', 'd');
        if (!in_array(mb_strtolower($correct_answer), array_map('mb_strtolower', $valid_answers))) {
            throw new Exception(__('پاسخ صحیح باید یکی از گزینه‌های ۱، ۲، ۳، ۴ یا الف، ب، ج، د باشد', 'azmonyar'));
        }
    }
    
    /**
     * بررسی تکراری بودن سوال
     */
    private function is_duplicate_question($question_text) {
        $existing = get_posts(array(
            'post_type' => 'soal',
            'post_status' => array('publish', 'draft'),
            'meta_query' => array(
                array(
                    'key' => '_azmonyar_question_hash',
                    'value' => md5($question_text),
                    'compare' => '='
                )
            ),
            'posts_per_page' => 1,
            'fields' => 'ids'
        ));
        
        return !empty($existing);
    }
    
    /**
     * ایجاد یا دریافت رشته/درس
     */
    private function get_or_create_subject($subject_name, $course_name = '') {
        // جستجوی رشته موجود
        $subject_term = get_term_by('name', $subject_name, 'azmonyar_subject');
        
        if (!$subject_term) {
            // ایجاد رشته جدید
            $result = wp_insert_term($subject_name, 'azmonyar_subject', array(
                'description' => sprintf(__('رشته %s', 'azmonyar'), $subject_name),
                'slug' => sanitize_title($subject_name)
            ));
            
            if (is_wp_error($result)) {
                throw new Exception(sprintf(__('خطا در ایجاد رشته %s', 'azmonyar'), $subject_name));
            }
            
            $subject_term = get_term($result['term_id'], 'azmonyar_subject');
        }
        
        // اگر نام درس مشخص شده، آن را به عنوان زیرمجموعه ایجاد کن
        if (!empty($course_name) && $course_name !== $subject_name) {
            $course_term = get_term_by('name', $course_name, 'azmonyar_subject');
            
            if (!$course_term) {
                $result = wp_insert_term($course_name, 'azmonyar_subject', array(
                    'description' => sprintf(__('درس %s از رشته %s', 'azmonyar'), $course_name, $subject_name),
                    'slug' => sanitize_title($course_name),
                    'parent' => $subject_term->term_id
                ));
                
                if (!is_wp_error($result)) {
                    return get_term($result['term_id'], 'azmonyar_subject');
                }
            } else {
                return $course_term;
            }
        }
        
        return $subject_term;
    }
    
    /**
     * ایجاد یا دریافت سطح دشواری
     */
    private function get_or_create_difficulty($difficulty_name) {
        if (empty($difficulty_name)) {
            $difficulty_name = 'متوسط'; // پیش‌فرض
        }
        
        // جستجوی سطح دشواری موجود
        $difficulty_term = get_term_by('name', $difficulty_name, 'azmonyar_difficulty');
        
        if (!$difficulty_term) {
            // ایجاد سطح دشواری جدید
            $result = wp_insert_term($difficulty_name, 'azmonyar_difficulty', array(
                'description' => sprintf(__('سطح دشواری %s', 'azmonyar'), $difficulty_name),
                'slug' => sanitize_title($difficulty_name)
            ));
            
            if (is_wp_error($result)) {
                throw new Exception(sprintf(__('خطا در ایجاد سطح دشواری %s', 'azmonyar'), $difficulty_name));
            }
            
            $difficulty_term = get_term($result['term_id'], 'azmonyar_difficulty');
        }
        
        return $difficulty_term;
    }
    
    /**
     * ایجاد سوال جدید
     */
    private function create_question($question_text, $option_a, $option_b, $option_c, $option_d, $correct_answer, $author_id, $subject_term, $difficulty_term) {
        // تبدیل پاسخ صحیح به فرمت استاندارد
        $correct_answer_standard = $this->normalize_correct_answer($correct_answer);
        
        // ایجاد پست سوال
        $post_data = array(
            'post_title' => wp_trim_words($question_text, 10),
            'post_content' => $question_text,
            'post_status' => 'publish',
            'post_type' => 'soal',
            'post_author' => $author_id,
            'meta_input' => array(
                '_azmonyar_option_a' => $option_a,
                '_azmonyar_option_b' => $option_b,
                '_azmonyar_option_c' => $option_c,
                '_azmonyar_option_d' => $option_d,
                '_azmonyar_correct_answer' => $correct_answer_standard,
                '_azmonyar_question_hash' => md5($question_text), // برای شناسایی تکراری‌ها
                '_azmonyar_imported_at' => current_time('mysql')
            )
        );
        
        $question_id = wp_insert_post($post_data);
        
        if (is_wp_error($question_id)) {
            throw new Exception(__('خطا در ایجاد سوال در پایگاه داده', 'azmonyar'));
        }
        
        // اختصاص دسته‌بندی‌ها
        if ($subject_term) {
            wp_set_post_terms($question_id, array($subject_term->term_id), 'azmonyar_subject');
        }
        
        if ($difficulty_term) {
            wp_set_post_terms($question_id, array($difficulty_term->term_id), 'azmonyar_difficulty');
        }
        
        return $question_id;
    }
    
    /**
     * تبدیل پاسخ صحیح به فرمت استاندارد
     */
    private function normalize_correct_answer($answer) {
        $answer = mb_strtolower(trim($answer));
        
        $mapping = array(
            '1' => 'a',
            '۱' => 'a',
            'الف' => 'a',
            'a' => 'a',
            
            '2' => 'b',
            '۲' => 'b',
            'ب' => 'b',
            'b' => 'b',
            
            '3' => 'c',
            '۳' => 'c',
            'ج' => 'c',
            'c' => 'c',
            
            '4' => 'd',
            '۴' => 'd',
            'د' => 'd',
            'd' => 'd'
        );
        
        return $mapping[$answer] ?? 'a';
    }
    
    /**
     * خروجی نمونه فایل CSV
     */
    public static function generate_sample_csv() {
        $sample_data = array(
            array('رشته', 'درس', 'سطح', 'سوال', 'گزینه۱', 'گزینه۲', 'گزینه۳', 'گزینه۴', 'پاسخ صحیح'),
            array('ریاضی', 'جبر', 'آسان', 'حاصل ۲+۲ چیست؟', '۳', '۴', '۵', '۶', '۴'),
            array('فیزیک', 'مکانیک', 'متوسط', 'واحد شتاب در SI چیست؟', 'm/s', 'm/s²', 'm²/s', 'm/s³', 'm/s²'),
            array('شیمی', 'عمومی', 'آسان', 'نماد شیمیایی آب چیست؟', 'H₂O', 'CO₂', 'NaCl', 'CH₄', 'H₂O'),
        );
        
        $filename = 'azmonyar-sample-' . date('Y-m-d') . '.csv';
        
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
        header('Pragma: public');
        
        // افزودن BOM برای پشتیبانی UTF-8 در Excel
        echo "\xEF\xBB\xBF";
        
        $output = fopen('php://output', 'w');
        
        foreach ($sample_data as $row) {
            fputcsv($output, $row);
        }
        
        fclose($output);
        exit;
    }
    
    /**
     * دریافت آمار واردسازی
     */
    public function get_import_stats() {
        return $this->stats;
    }
    
    /**
     * دریافت خطاها
     */
    public function get_errors() {
        return $this->errors;
    }
    
    /**
     * دریافت هشدارها
     */
    public function get_warnings() {
        return $this->warnings;
    }
    
    /**
     * پاک کردن آمار و خطاها
     */
    public function reset_stats() {
        $this->stats = array(
            'total_rows' => 0,
            'imported' => 0,
            'skipped' => 0,
            'errors' => 0
        );
        $this->errors = array();
        $this->warnings = array();
    }
    
    /**
     * اعتبارسنجی فایل قبل از واردسازی
     */
    public function validate_csv_file($file_path) {
        $errors = array();
        
        // بررسی وجود فایل
        if (!file_exists($file_path)) {
            $errors[] = __('فایل یافت نشد', 'azmonyar');
            return $errors;
        }
        
        // بررسی قابلیت خواندن
        if (!is_readable($file_path)) {
            $errors[] = __('فایل قابل خواندن نیست', 'azmonyar');
            return $errors;
        }
        
        // باز کردن فایل
        $handle = fopen($file_path, 'r');
        if (!$handle) {
            $errors[] = __('خطا در باز کردن فایل', 'azmonyar');
            return $errors;
        }
        
        // بررسی هدر
        $header = fgetcsv($handle, 1000, ',');
        if (!$this->validate_header($header)) {
            $errors[] = __('فرمت هدر فایل صحیح نیست', 'azmonyar');
        }
        
        // بررسی چند خط اول
        $line_count = 0;
        while (($data = fgetcsv($handle, 1000, ',')) !== FALSE && $line_count < 5) {
            $line_count++;
            
            if (count($data) < count(self::EXPECTED_COLUMNS)) {
                $errors[] = sprintf(__('خط %d: تعداد ستون‌ها کافی نیست', 'azmonyar'), $line_count + 1);
            }
        }
        
        fclose($handle);
        
        return $errors;
    }
}