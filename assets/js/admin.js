/**
 * اسکریپت‌های پنل مدیریت آزمون‌یار حرفه‌ای
 * 
 * @package AzmonyarProfessional
 */

jQuery(document).ready(function($) {
    'use strict';
    
    // متغیرهای سراسری
    var selectedQuestions = [];
    var isImporting = false;
    
    /**
     * مدیریت تب‌ها
     */
    function initTabs() {
        $('.nav-tab-wrapper .nav-tab').on('click', function(e) {
            e.preventDefault();
            
            var targetTab = $(this).attr('href');
            
            // حذف کلاس active از همه تب‌ها
            $('.nav-tab').removeClass('nav-tab-active');
            $('.tab-content').removeClass('active').hide();
            
            // فعال کردن تب جدید
            $(this).addClass('nav-tab-active');
            $(targetTab).addClass('active').fadeIn(300);
        });
    }
    
    /**
     * انتخابگر سوالات
     */
    function initQuestionSelector() {
        // نمایش مودال انتخاب سوالات
        $('#add-questions-btn').on('click', function() {
            $('#questions-modal').fadeIn(300);
            loadQuestionsList();
        });
        
        // بستن مودال
        $('#cancel-questions, #questions-modal').on('click', function(e) {
            if (e.target === this) {
                $('#questions-modal').fadeOut(300);
            }
        });
        
        // تأیید انتخاب سوالات
        $('#confirm-questions').on('click', function() {
            updateSelectedQuestions();
            $('#questions-modal').fadeOut(300);
        });
        
        // حذف سوال
        $(document).on('click', '.remove-question', function() {
            var questionId = $(this).closest('.question-item').data('question-id');
            selectedQuestions = selectedQuestions.filter(function(id) {
                return id != questionId;
            });
            
            $(this).closest('.question-item').fadeOut(300, function() {
                $(this).remove();
                updateQuestionsInput();
            });
        });
        
        // انتخاب/لغو انتخاب سوال در مودال
        $(document).on('click', '.question-option', function() {
            $(this).toggleClass('selected');
        });
    }
    
    /**
     * بارگذاری لیست سوالات
     */
    function loadQuestionsList() {
        var $questionsList = $('#questions-list');
        $questionsList.html('<div class="azmonyar-loading"></div>');
        
        $.ajax({
            url: azmonyar_admin_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'azmonyar_get_questions',
                nonce: azmonyar_admin_ajax.nonce
            },
            success: function(response) {
                if (response.success) {
                    var html = '';
                    $.each(response.data, function(index, question) {
                        var isSelected = selectedQuestions.indexOf(question.id.toString()) !== -1;
                        html += '<div class="question-option' + (isSelected ? ' selected' : '') + '" data-question-id="' + question.id + '">';
                        html += '<strong>' + question.title + '</strong>';
                        html += '<p>' + question.content + '</p>';
                        html += '</div>';
                    });
                    $questionsList.html(html);
                } else {
                    $questionsList.html('<p>خطا در بارگذاری سوالات</p>');
                }
            },
            error: function() {
                $questionsList.html('<p>خطا در اتصال به سرور</p>');
            }
        });
    }
    
    /**
     * بروزرسانی سوالات انتخاب شده
     */
    function updateSelectedQuestions() {
        selectedQuestions = [];
        var $selectedQuestions = $('#selected-questions');
        
        $('.question-option.selected').each(function() {
            var questionId = $(this).data('question-id');
            var questionTitle = $(this).find('strong').text();
            
            selectedQuestions.push(questionId.toString());
            
            // افزودن به لیست نمایشی
            if ($selectedQuestions.find('[data-question-id="' + questionId + '"]').length === 0) {
                var html = '<div class="question-item" data-question-id="' + questionId + '">';
                html += '<span>' + questionTitle + '</span>';
                html += '<button type="button" class="remove-question">حذف</button>';
                html += '</div>';
                
                $selectedQuestions.append(html);
            }
        });
        
        updateQuestionsInput();
    }
    
    /**
     * بروزرسانی فیلد مخفی سوالات
     */
    function updateQuestionsInput() {
        $('#azmonyar_questions').val(selectedQuestions.join(','));
    }
    
    /**
     * واردسازی CSV
     */
    function initCSVImport() {
        $('#csv-import-form').on('submit', function(e) {
            e.preventDefault();
            
            if (isImporting) {
                return false;
            }
            
            var formData = new FormData(this);
            formData.append('action', 'azmonyar_import_csv');
            formData.append('nonce', azmonyar_admin_ajax.nonce);
            
            startImport(formData);
        });
    }
    
    /**
     * شروع واردسازی
     */
    function startImport(formData) {
        isImporting = true;
        
        // نمایش نوار پیشرفت
        $('#import-progress').show();
        $('#import-results').hide();
        $('#import-submit').prop('disabled', true).val('در حال واردسازی...');
        
        $.ajax({
            url: azmonyar_admin_ajax.ajax_url,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            xhr: function() {
                var xhr = new window.XMLHttpRequest();
                xhr.upload.addEventListener('progress', function(evt) {
                    if (evt.lengthComputable) {
                        var percentComplete = (evt.loaded / evt.total) * 100;
                        updateProgress(percentComplete);
                    }
                }, false);
                return xhr;
            },
            success: function(response) {
                completeImport(response);
            },
            error: function() {
                completeImport({
                    success: false,
                    message: 'خطا در اتصال به سرور'
                });
            }
        });
    }
    
    /**
     * بروزرسانی نوار پیشرفت
     */
    function updateProgress(percent) {
        $('.progress-fill').css('width', percent + '%');
        $('.progress-text').text(Math.round(percent) + '%');
    }
    
    /**
     * تکمیل واردسازی
     */
    function completeImport(response) {
        isImporting = false;
        
        // مخفی کردن نوار پیشرفت
        $('#import-progress').hide();
        
        // نمایش نتایج
        var $results = $('#import-results');
        var $summary = $('.import-summary');
        
        if (response.success) {
            $summary.html('<div class="azmonyar-notice notice-success"><p>' + response.message + '</p></div>');
            
            if (response.stats) {
                var statsHtml = '<h4>آمار واردسازی:</h4>';
                statsHtml += '<ul>';
                statsHtml += '<li>کل ردیف‌ها: ' + response.stats.total_rows + '</li>';
                statsHtml += '<li>وارد شده: ' + response.stats.imported + '</li>';
                statsHtml += '<li>نادیده گرفته شده: ' + response.stats.skipped + '</li>';
                statsHtml += '<li>خطاها: ' + response.stats.errors + '</li>';
                statsHtml += '</ul>';
                
                $summary.append(statsHtml);
            }
            
            if (response.warnings && response.warnings.length > 0) {
                $summary.append('<h4>هشدارها:</h4><ul>');
                $.each(response.warnings, function(index, warning) {
                    $summary.append('<li>' + warning + '</li>');
                });
                $summary.append('</ul>');
            }
            
        } else {
            $summary.html('<div class="azmonyar-notice notice-error"><p>' + response.message + '</p></div>');
            
            if (response.errors && response.errors.length > 0) {
                $summary.append('<h4>خطاها:</h4><ul>');
                $.each(response.errors, function(index, error) {
                    $summary.append('<li>' + error + '</li>');
                });
                $summary.append('</ul>');
            }
        }
        
        $results.show();
        $('#import-submit').prop('disabled', false).val('شروع واردسازی');
        
        // ریست فرم
        $('#csv-import-form')[0].reset();
    }
    
    /**
     * خروجی نتایج
     */
    function initResultsExport() {
        $('#export-results').on('click', function() {
            var $btn = $(this);
            var originalText = $btn.text();
            
            $btn.prop('disabled', true).text('در حال تهیه فایل...');
            
            $.ajax({
                url: azmonyar_admin_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'azmonyar_export_results',
                    nonce: azmonyar_admin_ajax.nonce,
                    filters: getResultsFilters()
                },
                success: function(response) {
                    if (response.success) {
                        downloadCSV(response.data.data, response.data.filename);
                        showNotice('فایل با موفقیت تهیه شد', 'success');
                    } else {
                        showNotice('خطا در تهیه فایل', 'error');
                    }
                },
                error: function() {
                    showNotice('خطا در اتصال به سرور', 'error');
                },
                complete: function() {
                    $btn.prop('disabled', false).text(originalText);
                }
            });
        });
    }
    
    /**
     * دریافت فیلترهای نتایج
     */
    function getResultsFilters() {
        return {
            exam_filter: $('select[name="exam_filter"]').val(),
            status_filter: $('select[name="status_filter"]').val(),
            date_from: $('input[name="date_from"]').val(),
            date_to: $('input[name="date_to"]').val()
        };
    }
    
    /**
     * دانلود فایل CSV
     */
    function downloadCSV(data, filename) {
        var csv = '';
        
        // تبدیل آرایه به CSV
        data.forEach(function(row) {
            csv += row.map(function(field) {
                // Escape quotes and wrap in quotes if necessary
                if (typeof field === 'string' && (field.includes(',') || field.includes('"') || field.includes('\n'))) {
                    return '"' + field.replace(/"/g, '""') + '"';
                }
                return field;
            }).join(',') + '\n';
        });
        
        // ایجاد لینک دانلود
        var blob = new Blob(['\ufeff' + csv], { type: 'text/csv;charset=utf-8;' });
        var link = document.createElement('a');
        
        if (link.download !== undefined) {
            var url = URL.createObjectURL(blob);
            link.setAttribute('href', url);
            link.setAttribute('download', filename);
            link.style.visibility = 'hidden';
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        }
    }
    
    /**
     * مدیریت دسته‌بندی‌ها
     */
    function initCategoryManagement() {
        // افزودن رشته
        $('#add-subject-form').on('submit', function(e) {
            e.preventDefault();
            
            var formData = {
                action: 'azmonyar_add_subject',
                nonce: azmonyar_admin_ajax.nonce,
                name: $('#subject_name').val(),
                parent: $('#subject_parent').val(),
                description: $('#subject_description').val()
            };
            
            submitCategoryForm(formData, this);
        });
        
        // افزودن سطح دشواری
        $('#add-difficulty-form').on('submit', function(e) {
            e.preventDefault();
            
            var formData = {
                action: 'azmonyar_add_difficulty',
                nonce: azmonyar_admin_ajax.nonce,
                name: $('#difficulty_name').val(),
                description: $('#difficulty_description').val()
            };
            
            submitCategoryForm(formData, this);
        });
        
        // ویرایش دسته‌بندی
        $('.edit-subject, .edit-difficulty').on('click', function() {
            var categoryId = $(this).data('id');
            var categoryType = $(this).hasClass('edit-subject') ? 'subject' : 'difficulty';
            editCategory(categoryId, categoryType);
        });
        
        // حذف دسته‌بندی
        $('.delete-subject, .delete-difficulty').on('click', function() {
            if (confirm('آیا از حذف این دسته‌بندی اطمینان دارید؟')) {
                var categoryId = $(this).data('id');
                var categoryType = $(this).hasClass('delete-subject') ? 'subject' : 'difficulty';
                deleteCategory(categoryId, categoryType);
            }
        });
    }
    
    /**
     * ارسال فرم دسته‌بندی
     */
    function submitCategoryForm(formData, form) {
        var $form = $(form);
        var $submitBtn = $form.find('input[type="submit"]');
        var originalValue = $submitBtn.val();
        
        $submitBtn.prop('disabled', true).val('در حال ذخیره...');
        
        $.ajax({
            url: azmonyar_admin_ajax.ajax_url,
            type: 'POST',
            data: formData,
            success: function(response) {
                if (response.success) {
                    showNotice('دسته‌بندی با موفقیت افزوده شد', 'success');
                    $form[0].reset();
                    setTimeout(function() {
                        location.reload();
                    }, 1000);
                } else {
                    showNotice('خطا در افزودن دسته‌بندی: ' + response.data, 'error');
                }
            },
            error: function() {
                showNotice('خطا در اتصال به سرور', 'error');
            },
            complete: function() {
                $submitBtn.prop('disabled', false).val(originalValue);
            }
        });
    }
    
    /**
     * ویرایش دسته‌بندی
     */
    function editCategory(categoryId, categoryType) {
        // پیاده‌سازی ویرایش دسته‌بندی
        // می‌توان از مودال یا صفحه جداگانه استفاده کرد
        showNotice('قابلیت ویرایش در نسخه آینده اضافه خواهد شد', 'info');
    }
    
    /**
     * حذف دسته‌بندی
     */
    function deleteCategory(categoryId, categoryType) {
        $.ajax({
            url: azmonyar_admin_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'azmonyar_delete_category',
                nonce: azmonyar_admin_ajax.nonce,
                category_id: categoryId,
                category_type: categoryType
            },
            success: function(response) {
                if (response.success) {
                    showNotice('دسته‌بندی با موفقیت حذف شد', 'success');
                    setTimeout(function() {
                        location.reload();
                    }, 1000);
                } else {
                    showNotice('خطا در حذف دسته‌بندی: ' + response.data, 'error');
                }
            },
            error: function() {
                showNotice('خطا در اتصال به سرور', 'error');
            }
        });
    }
    
    /**
     * مشاهده جزئیات نتیجه
     */
    function initResultDetails() {
        $('.view-details').on('click', function(e) {
            e.preventDefault();
            
            var resultId = $(this).data('result-id');
            showResultDetails(resultId);
        });
    }
    
    /**
     * نمایش جزئیات نتیجه
     */
    function showResultDetails(resultId) {
        // ایجاد مودال برای نمایش جزئیات
        var modalHtml = '<div id="result-details-modal" class="azmonyar-modal">';
        modalHtml += '<div class="modal-content">';
        modalHtml += '<div class="modal-header">';
        modalHtml += '<h3>جزئیات نتیجه آزمون</h3>';
        modalHtml += '<button type="button" class="modal-close">&times;</button>';
        modalHtml += '</div>';
        modalHtml += '<div class="modal-body">';
        modalHtml += '<div class="azmonyar-loading"></div>';
        modalHtml += '</div>';
        modalHtml += '</div>';
        modalHtml += '</div>';
        
        $('body').append(modalHtml);
        
        // بارگذاری داده‌ها
        $.ajax({
            url: azmonyar_admin_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'azmonyar_get_result_details',
                nonce: azmonyar_admin_ajax.nonce,
                result_id: resultId
            },
            success: function(response) {
                if (response.success) {
                    $('.modal-body').html(response.data.html);
                } else {
                    $('.modal-body').html('<p>خطا در بارگذاری جزئیات</p>');
                }
            },
            error: function() {
                $('.modal-body').html('<p>خطا در اتصال به سرور</p>');
            }
        });
        
        // بستن مودال
        $(document).on('click', '.modal-close, #result-details-modal', function(e) {
            if (e.target === this) {
                $('#result-details-modal').fadeOut(300, function() {
                    $(this).remove();
                });
            }
        });
    }
    
    /**
     * نمایش پیام اطلاع‌رسانی
     */
    function showNotice(message, type) {
        var noticeClass = 'azmonyar-notice';
        
        switch (type) {
            case 'success':
                noticeClass += ' notice-success';
                break;
            case 'error':
                noticeClass += ' notice-error';
                break;
            case 'warning':
                noticeClass += ' notice-warning';
                break;
            default:
                noticeClass += ' notice-info';
                break;
        }
        
        var noticeHtml = '<div class="' + noticeClass + ' fade-in">';
        noticeHtml += '<p>' + message + '</p>';
        noticeHtml += '</div>';
        
        // حذف پیام‌های قبلی
        $('.azmonyar-notice').fadeOut(300, function() {
            $(this).remove();
        });
        
        // افزودن پیام جدید
        $('.wrap').prepend(noticeHtml);
        
        // حذف خودکار پس از 5 ثانیه
        setTimeout(function() {
            $('.azmonyar-notice').fadeOut(300, function() {
                $(this).remove();
            });
        }, 5000);
    }
    
    /**
     * مرتب‌سازی سوالات
     */
    function initSortableQuestions() {
        if ($.fn.sortable) {
            $('#selected-questions').sortable({
                items: '.question-item',
                cursor: 'move',
                opacity: 0.8,
                update: function() {
                    // بروزرسانی ترتیب سوالات
                    selectedQuestions = [];
                    $('#selected-questions .question-item').each(function() {
                        selectedQuestions.push($(this).data('question-id').toString());
                    });
                    updateQuestionsInput();
                }
            });
        }
    }
    
    /**
     * تأیید حذف
     */
    function initDeleteConfirmations() {
        $('.delete-action').on('click', function(e) {
            if (!confirm('آیا از حذف این مورد اطمینان دارید؟')) {
                e.preventDefault();
                return false;
            }
        });
    }
    
    /**
     * جستجوی زنده
     */
    function initLiveSearch() {
        var searchTimeout;
        
        $('#questions-search').on('input', function() {
            var searchTerm = $(this).val();
            
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(function() {
                filterQuestions(searchTerm);
            }, 300);
        });
    }
    
    /**
     * فیلتر سوالات
     */
    function filterQuestions(searchTerm) {
        $('.question-option').each(function() {
            var questionText = $(this).text().toLowerCase();
            var shouldShow = searchTerm === '' || questionText.includes(searchTerm.toLowerCase());
            
            $(this).toggle(shouldShow);
        });
    }
    
    /**
     * راه‌اندازی تولتیپ‌ها
     */
    function initTooltips() {
        $('.azmonyar-tooltip').hover(
            function() {
                var tooltip = $(this).data('tooltip');
                if (tooltip) {
                    $(this).attr('title', tooltip);
                }
            },
            function() {
                $(this).removeAttr('title');
            }
        );
    }
    
    /**
     * مدیریت فرم‌ها
     */
    function initFormHandlers() {
        // اعتبارسنجی فرم‌ها
        $('form').on('submit', function() {
            var $form = $(this);
            var $requiredFields = $form.find('[required]');
            var isValid = true;
            
            $requiredFields.each(function() {
                if (!$(this).val().trim()) {
                    $(this).addClass('error');
                    isValid = false;
                } else {
                    $(this).removeClass('error');
                }
            });
            
            if (!isValid) {
                showNotice('لطفاً همه فیلدهای ضروری را پر کنید', 'error');
                return false;
            }
        });
        
        // حذف کلاس خطا هنگام تایپ
        $('input, textarea, select').on('input change', function() {
            $(this).removeClass('error');
        });
    }
    
    /**
     * کلیدهای میانبر
     */
    function initKeyboardShortcuts() {
        $(document).on('keydown', function(e) {
            // Ctrl+S برای ذخیره
            if (e.ctrlKey && e.keyCode === 83) {
                e.preventDefault();
                var $saveBtn = $('.button-primary[type="submit"]');
                if ($saveBtn.length) {
                    $saveBtn.click();
                }
            }
            
            // Escape برای بستن مودال‌ها
            if (e.keyCode === 27) {
                $('.azmonyar-modal:visible').fadeOut(300);
            }
        });
    }
    
    /**
     * راه‌اندازی همه عملکردها
     */
    function initialize() {
        // بارگذاری سوالات انتخاب شده از فیلد مخفی
        var questionsValue = $('#azmonyar_questions').val();
        if (questionsValue) {
            selectedQuestions = questionsValue.split(',').filter(function(id) {
                return id.trim() !== '';
            });
        }
        
        // راه‌اندازی عملکردها
        initTabs();
        initQuestionSelector();
        initCSVImport();
        initResultsExport();
        initResultDetails();
        initCategoryManagement();
        initSortableQuestions();
        initDeleteConfirmations();
        initLiveSearch();
        initTooltips();
        initFormHandlers();
        initKeyboardShortcuts();
        
        // افکت‌های ظاهری
        $('.azmonyar-admin').addClass('fade-in');
        
        console.log('Azmonyar Admin Scripts Loaded Successfully');
    }
    
    // شروع اسکریپت
    initialize();
    
    /**
     * تابع سراسری برای استفاده در سایر قسمت‌ها
     */
    window.AzmonyarAdmin = {
        showNotice: showNotice,
        updateProgress: updateProgress,
        downloadCSV: downloadCSV
    };
});