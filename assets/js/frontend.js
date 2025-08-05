/**
 * اسکریپت‌های بخش کاربری آزمون‌یار حرفه‌ای
 * 
 * @package AzmonyarProfessional
 */

jQuery(document).ready(function($) {
    'use strict';
    
    // متغیرهای سراسری
    var examTimer = null;
    var timeRemaining = 0;
    var currentQuestion = 1;
    var totalQuestions = 0;
    var userAnswers = {};
    var examStarted = false;
    var examCompleted = false;
    var autoSaveInterval = null;
    
    /**
     * راه‌اندازی آزمون
     */
    function initExam() {
        // دریافت اطلاعات آزمون
        var examData = $('.azmonyar-exam-container').data('exam');
        if (!examData) {
            return;
        }
        
        timeRemaining = parseInt(examData.time_limit) * 60; // تبدیل دقیقه به ثانیه
        totalQuestions = parseInt(examData.total_questions) || 0;
        
        // راه‌اندازی تایمر
        if (timeRemaining > 0) {
            startTimer();
        }
        
        // راه‌اندازی ذخیره خودکار
        startAutoSave();
        
        // نشان دادن آزمون به عنوان شروع شده
        examStarted = true;
        
        // ثبت شروع آزمون
        recordExamStart();
    }
    
    /**
     * شروع تایمر
     */
    function startTimer() {
        examTimer = setInterval(function() {
            timeRemaining--;
            
            updateTimerDisplay();
            
            // هشدار ۵ دقیقه مانده
            if (timeRemaining === 300) {
                showNotification('۵ دقیقه تا پایان آزمون باقی مانده!', 'warning');
            }
            
            // هشدار ۱ دقیقه مانده
            if (timeRemaining === 60) {
                showNotification('۱ دقیقه تا پایان آزمون باقی مانده!', 'warning');
            }
            
            // پایان زمان
            if (timeRemaining <= 0) {
                timeUp();
            }
            
        }, 1000);
    }
    
    /**
     * بروزرسانی نمایش تایمر
     */
    function updateTimerDisplay() {
        var hours = Math.floor(timeRemaining / 3600);
        var minutes = Math.floor((timeRemaining % 3600) / 60);
        var seconds = timeRemaining % 60;
        
        var display = '';
        if (hours > 0) {
            display = String(hours).padStart(2, '0') + ':';
        }
        display += String(minutes).padStart(2, '0') + ':' + String(seconds).padStart(2, '0');
        
        $('.exam-timer').text(display);
        
        // تغییر رنگ در ۵ دقیقه آخر
        if (timeRemaining <= 300) {
            $('.exam-timer').addClass('time-warning');
        }
        
        // تغییر رنگ در ۱ دقیقه آخر
        if (timeRemaining <= 60) {
            $('.exam-timer').addClass('time-critical');
        }
    }
    
    /**
     * پایان زمان آزمون
     */
    function timeUp() {
        clearInterval(examTimer);
        showNotification(azmonyar_ajax.messages.time_up, 'error');
        
        // ارسال خودکار آزمون
        setTimeout(function() {
            submitExam(true); // true = ارسال خودکار
        }, 2000);
    }
    
    /**
     * مدیریت پاسخ‌ها
     */
    function initAnswerHandling() {
        // انتخاب پاسخ
        $(document).on('change', '.question-option input[type="radio"]', function() {
            var questionId = $(this).closest('.question-item').data('question-id');
            var selectedAnswer = $(this).val();
            
            // ذخیره پاسخ
            userAnswers[questionId] = selectedAnswer;
            
            // بروزرسانی نمایش پیشرفت
            updateProgress();
            
            // ذخیره خودکار
            autoSaveAnswers();
        });
        
        // تغییر پاسخ
        $(document).on('change', '.question-option input[type="radio"]', function() {
            var $questionItem = $(this).closest('.question-item');
            
            // حذف کلاس answered از همه گزینه‌ها
            $questionItem.find('.question-option').removeClass('answered');
            
            // افزودن کلاس answered به گزینه انتخاب شده
            $(this).closest('.question-option').addClass('answered');
        });
    }
    
    /**
     * ناوبری سوالات
     */
    function initQuestionNavigation() {
        // سوال بعدی
        $('.next-question').on('click', function() {
            if (currentQuestion < totalQuestions) {
                showQuestion(currentQuestion + 1);
            }
        });
        
        // سوال قبلی
        $('.prev-question').on('click', function() {
            if (currentQuestion > 1) {
                showQuestion(currentQuestion - 1);
            }
        });
        
        // رفتن به سوال خاص
        $(document).on('click', '.question-nav-item', function() {
            var questionNumber = parseInt($(this).data('question'));
            showQuestion(questionNumber);
        });
        
        // کلیدهای میانبر
        $(document).on('keydown', function(e) {
            if (!examStarted || examCompleted) return;
            
            // فلش چپ - سوال قبلی
            if (e.keyCode === 37 && currentQuestion > 1) {
                e.preventDefault();
                showQuestion(currentQuestion - 1);
            }
            
            // فلش راست - سوال بعدی
            if (e.keyCode === 39 && currentQuestion < totalQuestions) {
                e.preventDefault();
                showQuestion(currentQuestion + 1);
            }
            
            // اعداد ۱-۴ برای انتخاب گزینه
            if (e.keyCode >= 49 && e.keyCode <= 52) {
                var optionIndex = e.keyCode - 49;
                var $currentQuestion = $('.question-item.active');
                var $option = $currentQuestion.find('.question-option').eq(optionIndex).find('input[type="radio"]');
                
                if ($option.length) {
                    $option.prop('checked', true).trigger('change');
                }
            }
        });
    }
    
    /**
     * نمایش سوال
     */
    function showQuestion(questionNumber) {
        if (questionNumber < 1 || questionNumber > totalQuestions) {
            return;
        }
        
        // مخفی کردن همه سوالات
        $('.question-item').removeClass('active').hide();
        
        // نمایش سوال مورد نظر
        $('.question-item[data-question-number="' + questionNumber + '"]').addClass('active').fadeIn(300);
        
        // بروزرسانی شماره سوال جاری
        currentQuestion = questionNumber;
        
        // بروزرسانی ناوبری
        updateNavigation();
        
        // بروزرسانی دکمه‌های کنترل
        updateControlButtons();
        
        // اسکرول به بالا
        $('html, body').animate({ scrollTop: 0 }, 300);
    }
    
    /**
     * بروزرسانی ناوبری سوالات
     */
    function updateNavigation() {
        $('.question-nav-item').removeClass('active current');
        $('.question-nav-item[data-question="' + currentQuestion + '"]').addClass('active current');
        
        // نمایش شماره سوال جاری
        $('.current-question-number').text(currentQuestion);
        $('.total-questions-number').text(totalQuestions);
    }
    
    /**
     * بروزرسانی دکمه‌های کنترل
     */
    function updateControlButtons() {
        // دکمه قبلی
        if (currentQuestion <= 1) {
            $('.prev-question').prop('disabled', true).addClass('disabled');
        } else {
            $('.prev-question').prop('disabled', false).removeClass('disabled');
        }
        
        // دکمه بعدی
        if (currentQuestion >= totalQuestions) {
            $('.next-question').hide();
            $('.submit-exam').show();
        } else {
            $('.next-question').show();
            $('.submit-exam').hide();
        }
    }
    
    /**
     * بروزرسانی نمایش پیشرفت
     */
    function updateProgress() {
        var answeredQuestions = Object.keys(userAnswers).length;
        var progressPercent = (answeredQuestions / totalQuestions) * 100;
        
        $('.progress-bar-fill').css('width', progressPercent + '%');
        $('.progress-text').text(answeredQuestions + ' از ' + totalQuestions + ' سوال');
        
        // بروزرسانی ناوبری سوالات
        $('.question-nav-item').each(function() {
            var questionId = $(this).data('question-id');
            if (userAnswers.hasOwnProperty(questionId)) {
                $(this).addClass('answered');
            } else {
                $(this).removeClass('answered');
            }
        });
    }
    
    /**
     * ذخیره خودکار پاسخ‌ها
     */
    function startAutoSave() {
        autoSaveInterval = setInterval(function() {
            if (examStarted && !examCompleted && Object.keys(userAnswers).length > 0) {
                autoSaveAnswers();
            }
        }, 30000); // هر ۳۰ ثانیه
    }
    
    /**
     * ذخیره خودکار
     */
    function autoSaveAnswers() {
        if (!examStarted || examCompleted) return;
        
        $.ajax({
            url: azmonyar_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'azmonyar_save_answers',
                nonce: azmonyar_ajax.nonce,
                exam_id: $('.azmonyar-exam-container').data('exam-id'),
                answers: JSON.stringify(userAnswers),
                time_remaining: timeRemaining
            },
            success: function(response) {
                if (response.success) {
                    showSaveIndicator();
                }
            }
        });
    }
    
    /**
     * نمایش نشانگر ذخیره
     */
    function showSaveIndicator() {
        $('.auto-save-indicator').addClass('saved').text('ذخیره شد');
        setTimeout(function() {
            $('.auto-save-indicator').removeClass('saved').text('');
        }, 2000);
    }
    
    /**
     * ارسال آزمون
     */
    function initExamSubmission() {
        $('.submit-exam').on('click', function(e) {
            e.preventDefault();
            
            if (examCompleted) return;
            
            // بررسی پاسخ دادن به همه سوالات
            var unansweredCount = totalQuestions - Object.keys(userAnswers).length;
            var confirmMessage = azmonyar_ajax.messages.confirm_submit;
            
            if (unansweredCount > 0) {
                confirmMessage += '\n\nشما به ' + unansweredCount + ' سوال پاسخ نداده‌اید. آیا مطمئن هستید؟';
            }
            
            if (confirm(confirmMessage)) {
                submitExam(false);
            }
        });
        
        // جلوگیری از خروج بدون ذخیره
        $(window).on('beforeunload', function(e) {
            if (examStarted && !examCompleted) {
                return 'آزمون شما هنوز تکمیل نشده است. آیا مطمئن هستید که می‌خواهید خارج شوید؟';
            }
        });
    }
    
    /**
     * ارسال آزمون
     */
    function submitExam(isAutoSubmit) {
        if (examCompleted) return;
        
        examCompleted = true;
        
        // متوقف کردن تایمر
        if (examTimer) {
            clearInterval(examTimer);
        }
        
        // متوقف کردن ذخیره خودکار
        if (autoSaveInterval) {
            clearInterval(autoSaveInterval);
        }
        
        // نمایش لودینگ
        showSubmissionLoading();
        
        // ارسال داده‌ها
        $.ajax({
            url: azmonyar_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'azmonyar_submit_exam',
                nonce: azmonyar_ajax.nonce,
                exam_id: $('.azmonyar-exam-container').data('exam-id'),
                answers: JSON.stringify(userAnswers),
                time_taken: $('.azmonyar-exam-container').data('exam').time_limit * 60 - timeRemaining,
                is_auto_submit: isAutoSubmit ? 1 : 0
            },
            success: function(response) {
                if (response.success) {
                    showExamResults(response.data);
                } else {
                    showNotification('خطا در ارسال آزمون: ' + response.data, 'error');
                    examCompleted = false; // اجازه تلاش مجدد
                }
            },
            error: function() {
                showNotification('خطا در اتصال به سرور. لطفاً مجدداً تلاش کنید.', 'error');
                examCompleted = false; // اجازه تلاش مجدد
            },
            complete: function() {
                hideSubmissionLoading();
            }
        });
    }
    
    /**
     * نمایش لودینگ ارسال
     */
    function showSubmissionLoading() {
        var loadingHtml = '<div class="submission-loading">';
        loadingHtml += '<div class="loading-spinner"></div>';
        loadingHtml += '<p>در حال ارسال آزمون...</p>';
        loadingHtml += '</div>';
        
        $('body').append('<div class="azmonyar-overlay">' + loadingHtml + '</div>');
    }
    
    /**
     * مخفی کردن لودینگ ارسال
     */
    function hideSubmissionLoading() {
        $('.azmonyar-overlay').fadeOut(300, function() {
            $(this).remove();
        });
    }
    
    /**
     * نمایش نتایج آزمون
     */
    function showExamResults(results) {
        // مخفی کردن آزمون
        $('.azmonyar-exam-container').fadeOut(300);
        
        // نمایش نتایج
        var resultsHtml = '<div class="azmonyar-results-container fade-in">';
        resultsHtml += '<div class="results-header">';
        resultsHtml += '<h2>نتیجه آزمون</h2>';
        resultsHtml += '</div>';
        
        resultsHtml += '<div class="results-summary">';
        resultsHtml += '<div class="result-item">';
        resultsHtml += '<span class="result-label">نمره کل:</span>';
        resultsHtml += '<span class="result-value score">' + results.score + ' از ' + results.total_questions + '</span>';
        resultsHtml += '</div>';
        
        resultsHtml += '<div class="result-item">';
        resultsHtml += '<span class="result-label">درصد موفقیت:</span>';
        resultsHtml += '<span class="result-value percentage">' + results.percentage.toFixed(1) + '%</span>';
        resultsHtml += '</div>';
        
        resultsHtml += '<div class="result-item">';
        resultsHtml += '<span class="result-label">پاسخ صحیح:</span>';
        resultsHtml += '<span class="result-value correct">' + results.correct_answers + '</span>';
        resultsHtml += '</div>';
        
        resultsHtml += '<div class="result-item">';
        resultsHtml += '<span class="result-label">پاسخ غلط:</span>';
        resultsHtml += '<span class="result-value wrong">' + results.wrong_answers + '</span>';
        resultsHtml += '</div>';
        
        resultsHtml += '<div class="result-item">';
        resultsHtml += '<span class="result-label">زمان صرف شده:</span>';
        resultsHtml += '<span class="result-value time">' + formatTime(results.time_taken) + '</span>';
        resultsHtml += '</div>';
        resultsHtml += '</div>';
        
        // نمایش وضعیت قبولی/عدم قبولی
        var passingScore = $('.azmonyar-exam-container').data('exam').passing_score || 50;
        var isPassed = results.percentage >= passingScore;
        
        resultsHtml += '<div class="result-status ' + (isPassed ? 'passed' : 'failed') + '">';
        resultsHtml += '<h3>' + (isPassed ? 'تبریک! شما قبول شدید' : 'متأسفانه قبول نشدید') + '</h3>';
        resultsHtml += '<p>حداقل نمره قبولی: ' + passingScore + '%</p>';
        resultsHtml += '</div>';
        
        resultsHtml += '<div class="results-actions">';
        resultsHtml += '<a href="' + window.location.href.split('?')[0] + '" class="button button-primary">بازگشت</a>';
        if (results.certificate_url) {
            resultsHtml += '<a href="' + results.certificate_url + '" class="button button-secondary" target="_blank">دریافت گواهی</a>';
        }
        resultsHtml += '</div>';
        
        resultsHtml += '</div>';
        
        $('.azmonyar-exam-wrapper').append(resultsHtml);
    }
    
    /**
     * فرمت زمان
     */
    function formatTime(seconds) {
        var hours = Math.floor(seconds / 3600);
        var minutes = Math.floor((seconds % 3600) / 60);
        var secs = seconds % 60;
        
        if (hours > 0) {
            return hours + ':' + String(minutes).padStart(2, '0') + ':' + String(secs).padStart(2, '0');
        } else {
            return minutes + ':' + String(secs).padStart(2, '0');
        }
    }
    
    /**
     * ثبت شروع آزمون
     */
    function recordExamStart() {
        $.ajax({
            url: azmonyar_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'azmonyar_start_exam',
                nonce: azmonyar_ajax.nonce,
                exam_id: $('.azmonyar-exam-container').data('exam-id')
            }
        });
    }
    
    /**
     * اقدامات امنیتی
     */
    function initSecurityMeasures() {
        // غیرفعال کردن کلیک راست
        $(document).on('contextmenu', '.azmonyar-exam-container', function(e) {
            e.preventDefault();
            return false;
        });
        
        // غیرفعال کردن انتخاب متن
        $('.azmonyar-exam-container').css({
            '-webkit-user-select': 'none',
            '-moz-user-select': 'none',
            '-ms-user-select': 'none',
            'user-select': 'none'
        });
        
        // غیرفعال کردن کلیدهای میانبر
        $(document).on('keydown', function(e) {
            // F12, Ctrl+Shift+I, Ctrl+U
            if (e.keyCode === 123 || 
                (e.ctrlKey && e.shiftKey && e.keyCode === 73) ||
                (e.ctrlKey && e.keyCode === 85)) {
                e.preventDefault();
                return false;
            }
            
            // Ctrl+A (انتخاب همه)
            if (e.ctrlKey && e.keyCode === 65) {
                e.preventDefault();
                return false;
            }
            
            // Ctrl+C, Ctrl+V (کپی و پیست)
            if (e.ctrlKey && (e.keyCode === 67 || e.keyCode === 86)) {
                e.preventDefault();
                return false;
            }
        });
        
        // تشخیص تغییر تب
        var hidden = 'hidden';
        var visibilityChange = 'visibilitychange';
        
        if (typeof document.hidden !== 'undefined') {
            hidden = 'hidden';
            visibilityChange = 'visibilitychange';
        } else if (typeof document.msHidden !== 'undefined') {
            hidden = 'msHidden';
            visibilityChange = 'msvisibilitychange';
        } else if (typeof document.webkitHidden !== 'undefined') {
            hidden = 'webkitHidden';
            visibilityChange = 'webkitvisibilitychange';
        }
        
        document.addEventListener(visibilityChange, function() {
            if (document[hidden] && examStarted && !examCompleted) {
                // ثبت تغییر تب
                recordTabSwitch();
            }
        });
        
        // تشخیص تغییر اندازه پنجره (احتمال باز کردن Developer Tools)
        var devtools = {open: false, orientation: null};
        var threshold = 160;
        
        setInterval(function() {
            if (window.outerHeight - window.innerHeight > threshold || 
                window.outerWidth - window.innerWidth > threshold) {
                if (!devtools.open) {
                    devtools.open = true;
                    recordSuspiciousActivity('devtools_opened');
                }
            } else {
                devtools.open = false;
            }
        }, 500);
    }
    
    /**
     * ثبت تغییر تب
     */
    function recordTabSwitch() {
        $.ajax({
            url: azmonyar_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'azmonyar_record_tab_switch',
                nonce: azmonyar_ajax.nonce,
                exam_id: $('.azmonyar-exam-container').data('exam-id')
            }
        });
        
        showNotification('هشدار: تغییر تب تشخیص داده شد!', 'warning');
    }
    
    /**
     * ثبت فعالیت مشکوک
     */
    function recordSuspiciousActivity(activity) {
        $.ajax({
            url: azmonyar_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'azmonyar_record_suspicious_activity',
                nonce: azmonyar_ajax.nonce,
                exam_id: $('.azmonyar-exam-container').data('exam-id'),
                activity: activity
            }
        });
    }
    
    /**
     * نمایش اعلان
     */
    function showNotification(message, type) {
        var notificationClass = 'azmonyar-notification';
        
        switch (type) {
            case 'success':
                notificationClass += ' notification-success';
                break;
            case 'error':
                notificationClass += ' notification-error';
                break;
            case 'warning':
                notificationClass += ' notification-warning';
                break;
            default:
                notificationClass += ' notification-info';
                break;
        }
        
        var notificationHtml = '<div class="' + notificationClass + '">';
        notificationHtml += '<p>' + message + '</p>';
        notificationHtml += '<button class="notification-close">&times;</button>';
        notificationHtml += '</div>';
        
        // حذف اعلان‌های قبلی
        $('.azmonyar-notification').fadeOut(300, function() {
            $(this).remove();
        });
        
        // افزودن اعلان جدید
        $('body').prepend(notificationHtml);
        
        // بستن اعلان
        $('.notification-close').on('click', function() {
            $(this).closest('.azmonyar-notification').fadeOut(300, function() {
                $(this).remove();
            });
        });
        
        // حذف خودکار پس از 5 ثانیه
        setTimeout(function() {
            $('.azmonyar-notification').fadeOut(300, function() {
                $(this).remove();
            });
        }, 5000);
    }
    
    /**
     * راه‌اندازی اصلی
     */
    function initialize() {
        // بررسی وجود آزمون
        if (!$('.azmonyar-exam-container').length) {
            return;
        }
        
        // راه‌اندازی عملکردها
        initAnswerHandling();
        initQuestionNavigation();
        initExamSubmission();
        initSecurityMeasures();
        
        // شروع آزمون
        initExam();
        
        // نمایش سوال اول
        if (totalQuestions > 0) {
            showQuestion(1);
        }
        
        console.log('Azmonyar Frontend Scripts Loaded Successfully');
    }
    
    // شروع اسکریپت
    initialize();
    
    /**
     * توابع سراسری
     */
    window.AzmonyarExam = {
        showNotification: showNotification,
        submitExam: function() { submitExam(false); },
        getCurrentQuestion: function() { return currentQuestion; },
        getTotalQuestions: function() { return totalQuestions; },
        getTimeRemaining: function() { return timeRemaining; },
        getUserAnswers: function() { return userAnswers; }
    };
});