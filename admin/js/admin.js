/**
 * Admin JavaScript for Salah SEO Plugin
 */
(function($) {
    $(function() {
        if (typeof window.salahSeoLabels === 'undefined') {
            window.salahSeoLabels = {
                keyword: 'Keyword',
                url: 'URL',
                repeats: 'Max repeats',
                delete: 'Delete rule',
                emptyState: 'No rules yet. Click "Add rule" to get started.',
                validationError: 'Please provide a keyword and a valid URL for each rule.',
                unsaved: 'Unsaved changes',
                unsavedWarning: 'You have unsaved changes. Are you sure you want to leave?',
                bulkStart: 'Start bulk optimization',
                stoppedByUser: 'Operation stopped by user',
                processingProduct: 'Processing product %1$s of %2$s',
                totalProducts: 'Total products',
                optimized: 'Optimized',
                skipped: 'Skipped',
                errors: 'Errors',
                confirmRemove: 'This will remove all internal links. Continue?',
                noItems: 'No items to process.',
                optionPrefix: 'salah_seo_settings'
            };
        }

        var settingsForm = $('form[action="options.php"]');
        var linkWrapper = $('#salah-seo-links-wrapper');
        var currentBulkInterval = null;
        var bulkProcessing = false;
        var linkProcessing = false;
        var processedLinks = 0;

        // ---------------------------
        // Helpers
        // ---------------------------
        function getNextLinkIndex() {
            var maxIndex = -1;
            linkWrapper.find('.salah-seo-link-row').each(function() {
                var value = parseInt($(this).data('index'), 10);
                if (!isNaN(value)) {
                    maxIndex = Math.max(maxIndex, value);
                }
            });
            return maxIndex + 1;
        }

        function createLinkRow(index) {
            return $(
                '<div class="salah-seo-link-row bg-slate-50 border border-slate-200 rounded-xl p-4 grid gap-4 md:grid-cols-[1fr_1fr_auto]" data-index="' + index + '">' +
                    '<div>' +
                        '<label class="text-xs font-semibold text-slate-500 mb-1 block">' + window.salahSeoLabels.keyword + '</label>' +
                        '<input type="text" name="' + window.salahSeoLabels.optionPrefix + '[internal_link_rules][' + index + '][keyword]" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:ring-emerald-500 focus:border-emerald-500 salah-seo-keyword" required />' +
                    '</div>' +
                    '<div>' +
                        '<label class="text-xs font-semibold text-slate-500 mb-1 block">' + window.salahSeoLabels.url + '</label>' +
                        '<input type="url" name="' + window.salahSeoLabels.optionPrefix + '[internal_link_rules][' + index + '][url]" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:ring-emerald-500 focus:border-emerald-500 salah-seo-url" required />' +
                    '</div>' +
                    '<div class="flex gap-3 items-end">' +
                        '<div class="flex-1">' +
                            '<label class="text-xs font-semibold text-slate-500 mb-1 block">' + window.salahSeoLabels.repeats + '</label>' +
                            '<input type="number" min="1" value="1" name="' + window.salahSeoLabels.optionPrefix + '[internal_link_rules][' + index + '][repeats]" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:ring-emerald-500 focus:border-emerald-500" />' +
                        '</div>' +
                        '<button type="button" class="salah-seo-remove-link inline-flex items-center justify-center h-10 w-10 rounded-full bg-red-100 text-red-600 hover:bg-red-600 hover:text-white transition" aria-label="' + window.salahSeoLabels.delete + '">' +
                            '<span class="dashicons dashicons-trash"></span>' +
                        '</button>' +
                    '</div>' +
                '</div>'
            );
        }

        function ensureLinksEmptyState() {
            if (linkWrapper.find('.salah-seo-link-row').length === 0) {
                if (!linkWrapper.find('.salah-seo-links-empty').length) {
                    linkWrapper.append('<div class="salah-seo-links-empty text-sm text-slate-500 bg-slate-50 border border-dashed border-slate-200 rounded-xl p-5 text-center">' + window.salahSeoLabels.emptyState + '</div>');
                }
            } else {
                linkWrapper.find('.salah-seo-links-empty').remove();
            }
        }

        function isValidUrl(value) {
            try {
                var url = new URL(value);
                return url.protocol === 'http:' || url.protocol === 'https:';
            } catch (e) {
                return false;
            }
        }

        function addLogEntry(container, message, type) {
            var classes = {
                success: 'text-emerald-600',
                error: 'text-red-600',
                warning: 'text-amber-500',
                info: 'text-slate-500'
            };
            var timestamp = new Date().toLocaleTimeString('ar');
            var entry = $('<div class="py-1 border-b border-slate-200 last:border-none text-[12px] flex justify-between gap-2"></div>');
            entry.append('<span class="text-slate-400">[' + timestamp + ']</span>');
            entry.append('<span class="flex-1 ' + (classes[type] || classes.info) + '">' + message + '</span>');
            container.append(entry);
            container.scrollTop(container[0].scrollHeight);
        }

        function resetBulkUi() {
            $('#progress-log').empty();
            $('#salah-seo-bulk-progress').removeClass('hidden');
            $('.progress-bar').css('width', '0%').text('0%');
            $('#progress-current').text('0');
            $('#progress-total').text(salahSeoAjax.totalProducts || 0);
            $('#progress-status').text(salahSeoAjax.strings.starting);
            $('#salah-seo-bulk-results').addClass('hidden');
        }

        function showBulkResults(progress) {
            var summaryHtml = [
                '<div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-center">',
                    '<div><span class="block text-xs text-slate-500">' + window.salahSeoLabels.totalProducts + '</span><strong class="text-lg text-slate-800">' + progress.total + '</strong></div>',
                    '<div><span class="block text-xs text-slate-500">' + window.salahSeoLabels.optimized + '</span><strong class="text-lg text-emerald-600">' + progress.optimized + '</strong></div>',
                    '<div><span class="block text-xs text-slate-500">' + window.salahSeoLabels.skipped + '</span><strong class="text-lg text-slate-600">' + progress.skipped + '</strong></div>',
                    '<div><span class="block text-xs text-slate-500">' + window.salahSeoLabels.errors + '</span><strong class="text-lg text-red-600">' + progress.errors + '</strong></div>',
                '</div>'
            ].join('');

            $('#results-summary').html(summaryHtml);
            $('#salah-seo-bulk-results').removeClass('hidden');
        }

        function toggleButtonsDuringLinks(disabled) {
            $('#salah-seo-links-apply, #salah-seo-links-remove').prop('disabled', disabled);
        }

        function updateLinksProgress(total) {
            $('#links-total').text(total);
            $('#links-current').text(processedLinks);
            var percentage = total > 0 ? Math.min(100, Math.round((processedLinks / total) * 100)) : 100;
            $('#salah-seo-links-bar').css('width', percentage + '%').text(percentage + '%');
        }

        function getNonce(fallbackSelector, localizedValue) {
            if (localizedValue) {
                return localizedValue;
            }
            var element = $(fallbackSelector);
            return element.length ? element.val() : '';
        }

        ensureLinksEmptyState();

        // ---------------------------
        // Internal link rules actions
        // ---------------------------
        $('#salah-seo-add-link').on('click', function(e) {
            e.preventDefault();
            var index = getNextLinkIndex();
            var row = createLinkRow(index);
            linkWrapper.append(row.hide());
            row.fadeIn(200);
            ensureLinksEmptyState();
        });

        linkWrapper.on('click', '.salah-seo-remove-link', function(e) {
            e.preventDefault();
            var row = $(this).closest('.salah-seo-link-row');
            row.fadeOut(200, function() {
                row.remove();
                ensureLinksEmptyState();
            });
        });

        // Validate before submit
        settingsForm.on('submit', function(e) {
            var hasErrors = false;
            linkWrapper.find('.salah-seo-link-row').each(function() {
                var keyword = $(this).find('.salah-seo-keyword');
                var url = $(this).find('.salah-seo-url');
                keyword.removeClass('ring-2 ring-red-500');
                url.removeClass('ring-2 ring-red-500');

                if (!keyword.val().trim()) {
                    keyword.addClass('ring-2 ring-red-500');
                    hasErrors = true;
                }
                if (!isValidUrl(url.val().trim())) {
                    url.addClass('ring-2 ring-red-500');
                    hasErrors = true;
                }
            });

            if (hasErrors) {
                e.preventDefault();
            var notice = $('<div class="notice notice-error salah-seo-form-error"><p>' + window.salahSeoLabels.validationError + '</p></div>');
            $('.salah-seo-form-error').remove();
                $('.wrap h1').after(notice);
                $('html, body').animate({ scrollTop: notice.offset().top - 100 }, 300);
            }
        });

        // Unsaved changes indicator
        settingsForm.find('input, textarea, select').on('change input', function() {
            if (!$('.unsaved-changes').length) {
                $('.wrap .submit').append('<span class="unsaved-changes text-xs text-red-500 ml-2">' + window.salahSeoLabels.unsaved + '</span>');
            }
        });

        settingsForm.on('submit', function() {
            $('.unsaved-changes').remove();
        });

        window.addEventListener('beforeunload', function(e) {
            if ($('.unsaved-changes').length) {
                e.preventDefault();
                e.returnValue = window.salahSeoLabels.unsavedWarning;
                return e.returnValue;
            }
        });

        // ---------------------------
        // Bulk optimization
        // ---------------------------
        if (typeof window.salahSeoAjax === 'undefined') {
            window.salahSeoAjax = {
                ajaxurl: ajaxurl || '/wp-admin/admin-ajax.php',
                nonce: getNonce('#salah_seo_bulk_nonce'),
                strings: {
                    starting: 'بدء العملية...',
                    processing: 'جاري المعالجة...',
                    completed: 'اكتملت العملية!',
                    error: 'حدث خطأ'
                }
            };
        }

        $('#salah-seo-bulk-start').on('click', function() {
            if (bulkProcessing) {
                return;
            }

            var startBtn = $(this);
            var stopBtn = $('#salah-seo-bulk-stop');
            resetBulkUi();

            $.ajax({
                url: salahSeoAjax.ajaxurl,
                type: 'POST',
                dataType: 'json',
                data: {
                    action: 'salah_seo_bulk_start',
                    nonce: salahSeoAjax.nonce
                },
                beforeSend: function() {
                    bulkProcessing = true;
                    startBtn.prop('disabled', true).html('<span class="dashicons dashicons-update-alt animate-spin"></span>' + salahSeoAjax.strings.processing);
                    stopBtn.removeClass('hidden');
                }
            }).done(function(response) {
                if (!response || !response.success) {
                    bulkProcessing = false;
                    startBtn.prop('disabled', false).html('<span class="dashicons dashicons-performance"></span>' + window.salahSeoLabels.bulkStart);
                    stopBtn.addClass('hidden');
                    alert(response && response.data ? response.data.message : salahSeoAjax.strings.error);
                    return;
                }

                $('#progress-total').text(response.data.total);
                $('#progress-status').text(salahSeoAjax.strings.processing);
                addLogEntry($('#progress-log'), response.data.message, 'info');

                currentBulkInterval = setInterval(function() {
                    processBulkBatch(startBtn, stopBtn);
                }, 3000);
            }).fail(function() {
                bulkProcessing = false;
                startBtn.prop('disabled', false).html('<span class="dashicons dashicons-performance"></span>' + window.salahSeoLabels.bulkStart);
                stopBtn.addClass('hidden');
                alert(salahSeoAjax.strings.error);
            });
        });

        $('#salah-seo-bulk-stop').on('click', function() {
            if (!bulkProcessing) {
                return;
            }
            clearInterval(currentBulkInterval);
            bulkProcessing = false;
            $('#salah-seo-bulk-start').prop('disabled', false).html('<span class="dashicons dashicons-performance"></span>' + window.salahSeoLabels.bulkStart);
            $(this).addClass('hidden');
            addLogEntry($('#progress-log'), window.salahSeoLabels.stoppedByUser, 'warning');
            $('#progress-status').text(window.salahSeoLabels.stoppedByUser);
        });

        function processBulkBatch(startBtn, stopBtn) {
            if (!bulkProcessing) {
                clearInterval(currentBulkInterval);
                return;
            }

            $.ajax({
                url: salahSeoAjax.ajaxurl,
                type: 'POST',
                dataType: 'json',
                data: {
                    action: 'salah_seo_bulk_process',
                    nonce: salahSeoAjax.nonce
                }
            }).done(function(response) {
                if (!response || !response.success) {
                    addLogEntry($('#progress-log'), response && response.data ? response.data.message : salahSeoAjax.strings.error, 'error');
                    return;
                }

                var data = response.data;
                var percentage = data.percentage;
                $('.progress-bar').css('width', percentage + '%').text(percentage + '%');
                $('#progress-current').text(data.progress.processed);
                $('#progress-status').text(window.salahSeoLabels.processingProduct.replace('%1$s', data.progress.processed).replace('%2$s', data.progress.total));

                if (data.batch_results) {
                    data.batch_results.forEach(function(result) {
                        addLogEntry($('#progress-log'), result.title + ': ' + result.message, result.status === 'optimized' ? 'success' : result.status === 'error' ? 'error' : 'info');
                    });
                }

                if (data.is_complete) {
                    clearInterval(currentBulkInterval);
                    bulkProcessing = false;
                    startBtn.prop('disabled', false).html('<span class="dashicons dashicons-performance"></span>' + window.salahSeoLabels.bulkStart);
                    stopBtn.addClass('hidden');
                    $('#progress-status').text(salahSeoAjax.strings.completed);
                    showBulkResults(data.progress);
                    addLogEntry($('#progress-log'), salahSeoAjax.strings.completed, 'success');
                }
            }).fail(function(_, __, error) {
                addLogEntry($('#progress-log'), error || salahSeoAjax.strings.error, 'error');
            });
        }

        // ---------------------------
        // Internal links automation
        // ---------------------------
        function startLinksOperation(action) {
            if (linkProcessing) {
                return;
            }

            var confirmRemove = action === 'remove' ? window.confirm(window.salahSeoLabels.confirmRemove) : true;
            if (!confirmRemove) {
                return;
            }

            processedLinks = 0;
            linkProcessing = true;
            toggleButtonsDuringLinks(true);
            $('#links-log').empty();
            $('#links-status').text(salahSeoAjax.linksStrings.preparing);
            $('#salah-seo-links-progress').removeClass('hidden');
            updateLinksProgress(0);

            $.ajax({
                url: salahSeoAjax.ajaxurl,
                type: 'POST',
                dataType: 'json',
                data: {
                    action: 'salah_seo_links_prepare',
                    nonce: getNonce('#salah_seo_links_nonce', salahSeoAjax.linksNonce),
                    link_action: action
                }
            }).done(function(response) {
                if (!response || !response.success) {
                    linkProcessing = false;
                    toggleButtonsDuringLinks(false);
                    alert(response && response.data ? response.data.message : salahSeoAjax.strings.error);
                    return;
                }

                var total = response.data.total_items || 0;
                updateLinksProgress(total);

                if (total === 0) {
                    linkProcessing = false;
                    toggleButtonsDuringLinks(false);
                    $('#links-status').text(window.salahSeoLabels.noItems);
                    return;
                }

                $('#links-status').text(action === 'apply' ? salahSeoAjax.linksStrings.applying : salahSeoAjax.linksStrings.removing);
                processLinksBatch(action, total);
            }).fail(function() {
                linkProcessing = false;
                toggleButtonsDuringLinks(false);
                alert(salahSeoAjax.strings.error);
            });
        }

        function processLinksBatch(action, total) {
            $.ajax({
                url: salahSeoAjax.ajaxurl,
                type: 'POST',
                dataType: 'json',
                data: {
                    action: 'salah_seo_links_process',
                    nonce: getNonce('#salah_seo_links_nonce', salahSeoAjax.linksNonce),
                    process_action: action
                }
            }).done(function(response) {
                if (!response || !response.success) {
                    addLogEntry($('#links-log'), response && response.data ? response.data.message : salahSeoAjax.strings.error, 'error');
                    linkProcessing = false;
                    toggleButtonsDuringLinks(false);
                    return;
                }

                var data = response.data;
                var increment = data.batch_total || data.processed_count || 0;
                processedLinks += increment;
                updateLinksProgress(total);

                if (data.message) {
                    data.message.split('\n').forEach(function(line) {
                        if (line.trim().length) {
                            addLogEntry($('#links-log'), line, 'info');
                        }
                    });
                }

                if (data.done) {
                    $('#links-status').text(salahSeoAjax.linksStrings.completed);
                    linkProcessing = false;
                    toggleButtonsDuringLinks(false);
                    return;
                }

                $('#links-status').text(action === 'apply' ? salahSeoAjax.linksStrings.applying : salahSeoAjax.linksStrings.removing);
                processLinksBatch(action, total);
            }).fail(function() {
                addLogEntry($('#links-log'), salahSeoAjax.strings.error, 'error');
                linkProcessing = false;
                toggleButtonsDuringLinks(false);
            });
        }

        $('#salah-seo-links-apply').on('click', function() {
            startLinksOperation('apply');
        });

        $('#salah-seo-links-remove').on('click', function() {
            startLinksOperation('remove');
        });

        // Auto-hide dismissible notices
        setTimeout(function() {
            $('.notice.is-dismissible').fadeOut(400);
        }, 5000);
    });
})(jQuery);
