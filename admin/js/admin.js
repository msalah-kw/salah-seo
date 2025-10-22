/**
 * Admin JavaScript for Salah SEO Plugin
 */

jQuery(document).ready(function($) {
    
    // Internal Links Management
    var linkCounter = 0;
    
    // Add new internal link row
    $('#add-internal-link').on('click', function(e) {
        e.preventDefault();
        
        var container = $('#internal-links-container');
        linkCounter++;
        
        var newRow = $('<div class="internal-link-row" data-id="' + linkCounter + '">' +
            '<input type="text" name="salah_seo_settings[internal_links_new][' + linkCounter + '][keyword]" placeholder="Keyword (e.g., فيب)" class="regular-text keyword-field" />' +
            '<input type="text" name="salah_seo_settings[internal_links_new][' + linkCounter + '][url]" placeholder="URL (e.g., https://example.com)" class="regular-text url-field" />' +
            '<button type="button" class="button remove-link">Remove</button>' +
            '</div>');
        
        container.append(newRow);
        
        // Focus on the keyword field
        newRow.find('.keyword-field').focus();
    });
    
    // Remove internal link row
    $(document).on('click', '.remove-link', function(e) {
        e.preventDefault();
        
        var row = $(this).closest('.internal-link-row');
        
        // Add fade out animation
        row.fadeOut(300, function() {
            $(this).remove();
        });
    });
    
    // Validate URLs in real-time
    $(document).on('blur', '.url-field', function() {
        var url = $(this).val().trim();
        var field = $(this);
        
        if (url && !isValidUrl(url)) {
            field.addClass('error');
            field.attr('title', 'Please enter a valid URL starting with http:// or https://');
            
            // Show error message
            if (!field.next('.url-error').length) {
                field.after('<span class="url-error" style="color: #dc3232; font-size: 12px; display: block; margin-top: 5px;">Invalid URL format</span>');
            }
        } else {
            field.removeClass('error');
            field.removeAttr('title');
            field.next('.url-error').remove();
        }
    });
    
    // Validate keywords
    $(document).on('blur', '.keyword-field', function() {
        var keyword = $(this).val().trim();
        var field = $(this);
        
        if (keyword && keyword.length < 2) {
            field.addClass('error');
            field.attr('title', 'Keyword must be at least 2 characters long');
        } else {
            field.removeClass('error');
            field.removeAttr('title');
        }
    });
    
    // Form validation before submit
    $('form').on('submit', function(e) {
        var hasErrors = false;
        
        // Check all URL fields
        $('.url-field').each(function() {
            var url = $(this).val().trim();
            if (url && !isValidUrl(url)) {
                $(this).addClass('error');
                hasErrors = true;
            }
        });
        
        // Check all keyword fields
        $('.keyword-field').each(function() {
            var keyword = $(this).val().trim();
            var url = $(this).siblings('.url-field').val().trim();
            
            if (url && (!keyword || keyword.length < 2)) {
                $(this).addClass('error');
                hasErrors = true;
            }
        });
        
        if (hasErrors) {
            e.preventDefault();
            
            // Show error message
            if (!$('.salah-seo-form-error').length) {
                $('h1').after('<div class="notice notice-error salah-seo-form-error"><p>Please fix the highlighted errors before saving.</p></div>');
            }
            
            // Scroll to first error
            var firstError = $('.error').first();
            if (firstError.length) {
                $('html, body').animate({
                    scrollTop: firstError.offset().top - 100
                }, 500);
            }
        }
    });
    
    // Auto-save functionality (optional)
    var autoSaveTimeout;
    $('input, textarea, select').on('change input', function() {
        clearTimeout(autoSaveTimeout);
        
        // Show unsaved changes indicator
        if (!$('.unsaved-changes').length) {
            $('.submit').append('<span class="unsaved-changes" style="color: #dc3232; margin-left: 10px; font-size: 12px;">Unsaved changes</span>');
        }
    });
    
    // Remove unsaved changes indicator on form submit
    $('form').on('submit', function() {
        $('.unsaved-changes').remove();
    });
    
    // Confirm before leaving page with unsaved changes
    window.addEventListener('beforeunload', function(e) {
        if ($('.unsaved-changes').length) {
            e.preventDefault();
            e.returnValue = 'You have unsaved changes. Are you sure you want to leave?';
            return e.returnValue;
        }
    });
    
    // Character counter for textareas
    $('textarea').each(function() {
        var textarea = $(this);
        var maxLength = textarea.attr('maxlength');
        
        if (maxLength) {
            var counter = $('<div class="char-counter" style="font-size: 12px; color: #666; margin-top: 5px;"></div>');
            textarea.after(counter);
            
            function updateCounter() {
                var remaining = maxLength - textarea.val().length;
                counter.text(remaining + ' characters remaining');
                
                if (remaining < 20) {
                    counter.css('color', '#dc3232');
                } else if (remaining < 50) {
                    counter.css('color', '#ffb900');
                } else {
                    counter.css('color', '#666');
                }
            }
            
            textarea.on('input', updateCounter);
            updateCounter();
        }
    });
    
    // Collapsible sections
    $('.form-table').each(function() {
        var table = $(this);
        var heading = table.prev('h2, h3');
        
        if (heading.length) {
            heading.css('cursor', 'pointer');
            heading.on('click', function() {
                table.slideToggle(300);
                
                var icon = heading.find('.toggle-icon');
                if (!icon.length) {
                    heading.append('<span class="toggle-icon" style="float: right;">▼</span>');
                    icon = heading.find('.toggle-icon');
                }
                
                if (table.is(':visible')) {
                    icon.text('▼');
                } else {
                    icon.text('▶');
                }
            });
        }
    });
    
    // Tooltips for help text
    $('[title]').each(function() {
        var element = $(this);
        var title = element.attr('title');
        
        element.removeAttr('title');
        
        element.on('mouseenter', function(e) {
            var tooltip = $('<div class="salah-seo-tooltip">' + title + '</div>');
            tooltip.css({
                position: 'absolute',
                background: '#333',
                color: '#fff',
                padding: '8px 12px',
                borderRadius: '4px',
                fontSize: '12px',
                zIndex: 9999,
                maxWidth: '300px',
                wordWrap: 'break-word'
            });
            
            $('body').append(tooltip);
            
            var offset = element.offset();
            tooltip.css({
                top: offset.top - tooltip.outerHeight() - 10,
                left: offset.left + (element.outerWidth() / 2) - (tooltip.outerWidth() / 2)
            });
        });
        
        element.on('mouseleave', function() {
            $('.salah-seo-tooltip').remove();
        });
    });
    
    // Helper functions
    function isValidUrl(string) {
        try {
            var url = new URL(string);
            return url.protocol === 'http:' || url.protocol === 'https:';
        } catch (_) {
            return false;
        }
    }
    
    // Initialize existing rows counter
    linkCounter = $('.internal-link-row').length;
    
    // Add smooth transitions
    $('.form-table tr').hover(
        function() {
            $(this).css('background-color', '#f9f9f9');
        },
        function() {
            $(this).css('background-color', '');
        }
    );
    
    // Success message auto-hide
    setTimeout(function() {
        $('.notice.is-dismissible').fadeOut(500);
    }, 5000);
    
    // Bulk Operations Handler
    var bulkProcessing = false;
    var bulkInterval;
    
    // Check if salahSeoAjax is defined, if not create fallback
    if (typeof salahSeoAjax === 'undefined') {
        window.salahSeoAjax = {
            ajaxurl: ajaxurl || '/wp-admin/admin-ajax.php',
            nonce: $('#salah_seo_bulk_nonce').val() || '',
            strings: {
                starting: 'بدء العملية...',
                processing: 'جاري المعالجة...',
                completed: 'اكتملت العملية!',
                error: 'حدث خطأ'
            }
        };
    }
    
    // Check if bulk operations section exists
    if ($('#salah-seo-bulk-start').length === 0) {
        console.log('Bulk start button not found on this page');
        return;
    }
    
    console.log('Bulk start button found, attaching event handler');
    
    $('#salah-seo-bulk-start').on('click', function() {
        console.log('Bulk start button clicked');
        
        if (bulkProcessing) {
            console.log('Already processing, returning');
            return;
        }
        
        var startBtn = $(this);
        var stopBtn = $('#salah-seo-bulk-stop');
        var progressContainer = $('#salah-seo-bulk-progress');
        var resultsContainer = $('#salah-seo-bulk-results');
        var progressBar = $('.progress-bar');
        var progressCurrent = $('#progress-current');
        var progressStatus = $('#progress-status');
        var progressTotal = $('#progress-total');
        var progressLog = $('#progress-log');
        
        console.log('salahSeoAjax object:', salahSeoAjax);
        
        // Reset UI
        resultsContainer.hide();
        progressLog.empty();
        
        // Start bulk operation
        console.log('Starting AJAX request to:', salahSeoAjax.ajaxurl);
        $.ajax({
            url: salahSeoAjax.ajaxurl,
            type: 'POST',
            data: {
                action: 'salah_seo_bulk_start',
                nonce: salahSeoAjax.nonce
            },
            success: function(response) {
                console.log('AJAX success response:', response);
                if (response.success) {
                    console.log('Operation started successfully');
                    bulkProcessing = true;
                    startBtn.prop('disabled', true).html('<span class="dashicons dashicons-update-alt" style="animation: spin 1s linear infinite; margin-right: 5px;"></span>جاري المعالجة...');
                    stopBtn.show();
                    progressContainer.show();
                    
                    progressTotal.text(response.data.total);
                    progressStatus.text('بدء المعالجة...');
                    
                    addLogEntry('بدء العملية: ' + response.data.message, 'info');
                    
                    // Start processing batches (increased interval for stability)
                    bulkInterval = setInterval(processBatch, 3000);
                } else {
                    console.log('Operation failed:', response.data);
                    alert('خطأ في بدء العملية: ' + (response.data ? response.data.message : 'خطأ غير معروف'));
                }
            },
            error: function(xhr, status, error) {
                console.log('AJAX error:', xhr, status, error);
                console.log('Response text:', xhr.responseText);
                alert('حدث خطأ في الاتصال: ' + error);
            }
        });
    });
    
    $('#salah-seo-bulk-stop').on('click', function() {
        if (bulkProcessing) {
            clearInterval(bulkInterval);
            bulkProcessing = false;
            
            $('#salah-seo-bulk-start').prop('disabled', false).html('<span class="dashicons dashicons-performance" style="margin-right: 5px;"></span>بدء التحسين الجماعي');
            $(this).hide();
            
            addLogEntry('تم إيقاف العملية بواسطة المستخدم', 'warning');
            $('#progress-status').text('تم الإيقاف');
        }
    });
    
    var retryCount = 0;
    var maxRetries = 3;
    
    function processBatch() {
        if (!bulkProcessing) return;
        
        $.ajax({
            url: salahSeoAjax.ajaxurl,
            type: 'POST',
            timeout: 30000, // 30 seconds timeout
            data: {
                action: 'salah_seo_bulk_process',
                nonce: salahSeoAjax.nonce
            },
            success: function(response) {
                if (response.success) {
                    retryCount = 0; // Reset retry count on success
                    var data = response.data;
                    var progress = data.progress;
                    
                    // Update progress bar
                    $('.progress-bar').css('width', data.percentage + '%').text(data.percentage + '%');
                    $('#progress-current').text(progress.processed);
                    $('#progress-status').text('معالجة المنتج ' + progress.processed + ' من ' + progress.total);
                    
                    // Add batch results to log
                    if (data.batch_results && data.batch_results.length > 0) {
                        data.batch_results.forEach(function(result) {
                            var statusClass = result.status === 'optimized' ? 'success' : 
                                            result.status === 'error' ? 'error' : 'info';
                            var logMessage = result.title + ': ' + result.message;
                            if (result.details) {
                                logMessage += ' (' + result.details + ')';
                            }
                            addLogEntry(logMessage, statusClass);
                        });
                    }
                    
                    // Check if complete
                    if (data.is_complete) {
                        clearInterval(bulkInterval);
                        bulkProcessing = false;
                        
                        $('#salah-seo-bulk-start').prop('disabled', false).html('<span class="dashicons dashicons-performance" style="margin-right: 5px;"></span>بدء التحسين الجماعي');
                        $('#salah-seo-bulk-stop').hide();
                        $('#progress-status').text('اكتملت العملية!');
                        
                        // Show results summary
                        showResultsSummary(progress);
                        
                        addLogEntry('اكتملت العملية بنجاح!', 'success');
                    }
                } else {
                    if (retryCount < maxRetries) {
                        retryCount++;
                        addLogEntry('خطأ في المعالجة، إعادة المحاولة ' + retryCount + '/' + maxRetries, 'warning');
                        setTimeout(processBatch, 5000); // Retry after 5 seconds
                    } else {
                        clearInterval(bulkInterval);
                        bulkProcessing = false;
                        addLogEntry('خطأ: ' + response.data.message, 'error');
                        $('#progress-status').text('حدث خطأ');
                        $('#salah-seo-bulk-start').prop('disabled', false).html('<span class="dashicons dashicons-performance" style="margin-right: 5px;"></span>بدء التحسين الجماعي');
                        $('#salah-seo-bulk-stop').hide();
                    }
                }
            },
            error: function(xhr, status, error) {
                console.log('AJAX error in processBatch:', xhr, status, error);
                
                if (retryCount < maxRetries) {
                    retryCount++;
                    addLogEntry('خطأ في الاتصال، إعادة المحاولة ' + retryCount + '/' + maxRetries + ' (' + error + ')', 'warning');
                    setTimeout(processBatch, 5000); // Retry after 5 seconds
                } else {
                    clearInterval(bulkInterval);
                    bulkProcessing = false;
                    addLogEntry('خطأ في الاتصال: ' + error, 'error');
                    $('#progress-status').text('خطأ في الاتصال');
                    $('#salah-seo-bulk-start').prop('disabled', false).html('<span class="dashicons dashicons-performance" style="margin-right: 5px;"></span>بدء التحسين الجماعي');
                    $('#salah-seo-bulk-stop').hide();
                }
            }
        });
    }
    
    function addLogEntry(message, type) {
        var logContainer = $('#progress-log');
        var timestamp = new Date().toLocaleTimeString('ar');
        var colorClass = type === 'success' ? 'color: #46b450' : 
                        type === 'error' ? 'color: #dc3232' : 
                        type === 'warning' ? 'color: #ffb900' : 'color: #666';
        
        var logEntry = $('<div style="margin-bottom: 5px; padding: 5px; border-bottom: 1px solid #eee; font-size: 12px;">' +
            '<span style="color: #999;">[' + timestamp + ']</span> ' +
            '<span style="' + colorClass + ';">' + message + '</span>' +
            '</div>');
        
        logContainer.append(logEntry);
        logContainer.scrollTop(logContainer[0].scrollHeight);
    }
    
    function showResultsSummary(progress) {
        var resultsContainer = $('#salah-seo-bulk-results');
        var summaryContainer = $('#results-summary');
        
        var summaryHtml = '<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 15px; text-align: center;">' +
            '<div><strong style="display: block; font-size: 18px; color: #0073aa;">' + progress.total + '</strong><span>إجمالي المنتجات</span></div>' +
            '<div><strong style="display: block; font-size: 18px; color: #46b450;">' + progress.optimized + '</strong><span>تم تحسينها</span></div>' +
            '<div><strong style="display: block; font-size: 18px; color: #666;">' + progress.skipped + '</strong><span>تم تجاهلها</span></div>' +
            '<div><strong style="display: block; font-size: 18px; color: #dc3232;">' + progress.errors + '</strong><span>أخطاء</span></div>' +
            '</div>';
        
        if (progress.optimized > 0) {
            summaryHtml += '<div style="margin-top: 15px; padding: 10px; background: #d4edda; border: 1px solid #c3e6cb; border-radius: 4px; color: #155724;">' +
                '<strong>تم بنجاح!</strong> تم تحسين ' + progress.optimized + ' منتج من أصل ' + progress.total + ' منتج.' +
                '</div>';
        }
        
        summaryContainer.html(summaryHtml);
        resultsContainer.show();
    }
});
