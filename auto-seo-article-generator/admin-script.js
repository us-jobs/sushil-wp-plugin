jQuery(document).ready(function ($) {
    console.log('AAG: Admin script loaded v1.1.0');

    // Tab switching
    $('.aag-tab-btn').on('click', function () {
        const tabId = $(this).data('tab');
        console.log('AAG: Switching to tab:', tabId);

        $('.aag-tab-btn').removeClass('active');
        $(this).addClass('active');

        $('.aag-tab-content').removeClass('active');
        $('#' + tabId + '-tab').addClass('active');

        if (tabId === 'queue') {
            refreshQueue();
        }
    });

    // Schedule Frequency Toggle
    $('#schedule_frequency').on('change', function () {
        const frequency = $(this).val();
        console.log('AAG: Frequency changed to:', frequency);

        if (frequency === 'custom') {
            $('#custom_interval_row').show();
            $('#time_selection_row').hide();
        } else {
            $('#custom_interval_row').hide();
            $('#time_selection_row').show();
        }
    });

    // Timezone & Tooltip Logic
    const $timezoneSelect = $('#schedule_timezone');
    const $autoTimezoneCheckbox = $('#aag_auto_timezone');
    const $detectedTzDisplay = $('#aag_detected_tz_display');

    if ($timezoneSelect.length > 0 && $autoTimezoneCheckbox.length > 0) {
        try {
            const userTimezone = Intl.DateTimeFormat().resolvedOptions().timeZone;
            console.log('AAG: Detected timezone:', userTimezone);

            if (userTimezone) {
                $detectedTzDisplay.text(userTimezone).css('font-weight', 'bold');

                function toggleTimezoneState() {
                    if ($autoTimezoneCheckbox.is(':checked')) {
                        $timezoneSelect.val(userTimezone).css({ 'pointer-events': 'none', 'opacity': '0.6' });
                    } else {
                        $timezoneSelect.css({ 'pointer-events': 'auto', 'opacity': '1' });
                    }
                }

                $autoTimezoneCheckbox.on('change', toggleTimezoneState);
                toggleTimezoneState();

                if (!$timezoneSelect.val()) {
                    $timezoneSelect.val(userTimezone);
                }

            } else {
                console.warn('AAG: Could not detect timezone');
                $detectedTzDisplay.text('Unknown');
                $autoTimezoneCheckbox.prop('disabled', true);
            }
        } catch (e) {
            console.error('AAG: Timezone detect error:', e);
            $detectedTzDisplay.text('Error');
        }
    }

    // Dynamic Submit Button Text
    const $genImmediateCheckbox = $('#generate_immediate');
    const $submitBtn = $('#settings_submit_btn');

    function updateSubmitButtonText() {
        if ($genImmediateCheckbox.is(':checked')) {
            $submitBtn.html('Generate and Schedule');
        } else {
            $submitBtn.html('Save and Schedule');
        }
    }

    if ($genImmediateCheckbox.length > 0) {
        $genImmediateCheckbox.on('change', updateSubmitButtonText);
        updateSubmitButtonText();
    }

    // Initial state check
    $('#schedule_frequency').trigger('change');

    // Navigation Buttons (Settings -> Methods)
    $('.aag-nav-btn').on('click', function () {
        const targetTab = $(this).data('target');
        console.log('AAG: Navigation to tab:', targetTab);
        $('.aag-tab-btn[data-tab="' + targetTab + '"]').click();
    });

    $('#settings_submit_btn').on('click', function () {
        console.log('AAG: Save button clicked');
    });

    $('#aag-settings-form').on('submit', function (e) {
        e.preventDefault();
        console.log('AAG: Settings form submitted');

        const isImmediate = $('#generate_immediate').is(':checked');
        console.log('AAG: Immediate generation checked:', isImmediate);

        // Get all form values
        let genMethodVal = $('#gen_method').val();
        const geminiApiKey = $('#gemini_api_key').val().trim();
        const freepikApiKey = $('#freepik_api_key').val().trim();

        console.log('AAG: Generation method:', genMethodVal);
        console.log('AAG: Gemini API key present:', !!geminiApiKey);
        console.log('AAG: Freepik API key present:', !!freepikApiKey);

        // Validate required fields
        if (isImmediate && !geminiApiKey) {
            showMessage('Error: Gemini API Key is required for immediate generation!', 'error');
            return;
        }

        const data = {
            action: 'aag_save_settings',
            nonce: aagAjax.nonce,
            gen_method: genMethodVal || 'method1',
            schedule_interval: $('#schedule_interval').val(),
            post_status: $('#post_status').val(),
            schedule_frequency: $('#schedule_frequency').val(),
            articles_per_run: $('#articles_per_run').val(),
            schedule_hour: $('select[name="schedule_hour"]').val(),
            schedule_minute: $('select[name="schedule_minute"]').val(),
            schedule_ampm: $('select[name="schedule_ampm"]').val(),
            schedule_timezone: $('#schedule_timezone').val(),
            aag_auto_timezone: $('#aag_auto_timezone').is(':checked') ? 1 : 0,
            generate_immediate: isImmediate ? 1 : 0,
            // Method 1 Data
            method1_titles: $('#title_list').val(),
            method1_keywords: $('#title_keywords').val(),
            // Method 2 Data
            method2_keyword: $('#keyword').val(),
            // API Keys (Requirement Tab)
            gemini_api_key: geminiApiKey,
            freepik_api_key: freepikApiKey
        };

        console.log('AAG: Sending data:', data);
        console.log('AAG: AJAX URL:', aagAjax.ajax_url);
        console.log('AAG: Nonce:', aagAjax.nonce);

        // Validate nonce
        if (!aagAjax.nonce) {
            showMessage('Error: Security token missing. Please refresh the page.', 'error');
            return;
        }

        // Disable button and show loading state
        $submitBtn.prop('disabled', true);

        if (isImmediate) {
            console.log('AAG: Showing generation loader');
            $submitBtn.html('<span class="dashicons dashicons-update-alt aag-spin"></span> Generating Article...');
            showMessage('Saving settings and generating article... This may take 1-2 minutes.', 'info');
        } else {
            console.log('AAG: Showing save loader');
            $submitBtn.html('<span class="dashicons dashicons-update-alt aag-spin"></span> Saving...');
            showMessage('Saving settings...', 'info');
        }

        $.ajax({
            url: aagAjax.ajax_url,
            type: 'POST',
            data: data,
            timeout: 180000, // 3 minutes timeout for article generation
            success: function (response) {
                console.log('AAG: Response received', response);
                console.log('AAG: Response status:', response.success);

                // Reset button state
                $submitBtn.prop('disabled', false);
                updateSubmitButtonText();

                if (response.success) {
                    let msg = response.data;
                    let showRefresh = false;

                    // Check if it's an object response with more details
                    if (typeof response.data === 'object') {
                        if (response.data.generated) {
                            // Article was generated successfully
                            msg = 'Settings saved! Article generated successfully! ';

                            if (response.data.edit_link) {
                                msg += '<a href="' + response.data.edit_link + '" target="_blank">Edit Post</a> ';
                            }

                            msg += '<a href="edit.php?post_type=post" target="_blank">View All Posts</a>';

                            showRefresh = true;
                        } else if (response.data.message) {
                            msg = response.data.message;
                        }
                    }

                    showMessage(msg, 'success');

                    // Refresh queue after a delay if article was generated
                    if (showRefresh) {
                        setTimeout(function () {
                            refreshQueue();
                        }, 1000);
                    }

                } else {
                    // Error response
                    let errorMsg = 'Error: ';

                    if (typeof response.data === 'string') {
                        errorMsg += response.data;
                    } else if (response.data && typeof response.data === 'object' && response.data.message) {
                        errorMsg += response.data.message;
                    } else if (response.data) {
                        errorMsg += JSON.stringify(response.data);
                    } else {
                        errorMsg += 'Unknown error occurred';
                    }

                    console.error('AAG: Server error:', response.data);
                    showMessage(errorMsg, 'error');
                }
            },
            error: function (xhr, status, error) {
                console.error('AAG: AJAX Request failed', {
                    status: status,
                    error: error,
                    xhr: xhr,
                    responseText: xhr.responseText
                });

                $submitBtn.prop('disabled', false);
                updateSubmitButtonText();

                let errorMsg = 'Request failed. ';
                if (status === 'timeout') {
                    errorMsg += 'The request timed out. The article might still be processing in the background. Check the queue status.';
                } else if (xhr.status === 500) {
                    errorMsg += 'Server error (500). Please check your server error logs.';
                    console.error('AAG: Server response:', xhr.responseText);
                } else if (xhr.status === 403) {
                    errorMsg += 'Access denied (403). Please refresh the page and try again.';
                } else if (xhr.status === 0) {
                    errorMsg += 'Network error. Please check your internet connection.';
                } else {
                    errorMsg += 'HTTP Status: ' + xhr.status + ' - ' + error;
                }

                showMessage(errorMsg, 'error');
            }
        });
    });

    // Save Method 1: Title List
    $('#aag-method1-form').on('submit', function (e) {
        e.preventDefault();

        const $form = $(this);
        const $submitBtn = $form.find('button[type="submit"]');
        const originalText = $submitBtn.html();

        $submitBtn.prop('disabled', true).html('<span class="dashicons dashicons-update-alt aag-spin"></span> Saving...');

        const titles = $('#title_list').val().trim();
        const keywords = $('#title_keywords').val().trim();

        const data = {
            action: 'aag_save_method1',
            nonce: aagAjax.nonce,
            titles: titles,
            keywords: keywords
        };

        showMessage('Saving Title List Source...', 'info');

        $.ajax({
            url: aagAjax.ajax_url,
            type: 'POST',
            data: data,
            success: function (response) {
                console.log('AAG: Method1 response:', response);
                $submitBtn.prop('disabled', false).html(originalText);

                if (response.success) {
                    showMessage(response.data, 'success');
                    refreshQueue();
                } else {
                    showMessage('Error: ' + response.data, 'error');
                }
            },
            error: function (xhr, status, error) {
                console.error('AAG: Method1 error:', error);
                $submitBtn.prop('disabled', false).html(originalText);
                showMessage('Error saving. Please try again.', 'error');
            }
        });
    });

    // Save Method 2: Keyword Source
    $('#aag-method2-form').on('submit', function (e) {
        e.preventDefault();

        const $form = $(this);
        const $submitBtn = $form.find('button[type="submit"]');
        const originalText = $submitBtn.html();

        $submitBtn.prop('disabled', true).html('<span class="dashicons dashicons-update-alt aag-spin"></span> Saving...');

        const keyword = $('#keyword').val().trim();

        if (!keyword) {
            showMessage('Please enter a keyword', 'error');
            $submitBtn.prop('disabled', false).html(originalText);
            return;
        }

        const data = {
            action: 'aag_save_method2',
            nonce: aagAjax.nonce,
            keyword: keyword
        };

        showMessage('Saving Keyword Source...', 'info');

        $.ajax({
            url: aagAjax.ajax_url,
            type: 'POST',
            data: data,
            success: function (response) {
                console.log('AAG: Method2 response:', response);
                $submitBtn.prop('disabled', false).html(originalText);

                if (response.success) {
                    showMessage(response.data, 'success');
                } else {
                    showMessage('Error: ' + response.data, 'error');
                }
            },
            error: function (xhr, status, error) {
                showMessage('Error saving. Please try again.', 'error');
            }
        });
    });

    // Save Article Requirements
    $('#aag-requirements-form').on('submit', function (e) {
        e.preventDefault();

        const $form = $(this);
        const $submitBtn = $form.find('button[type="submit"]');
        const originalText = $submitBtn.html();

        $submitBtn.prop('disabled', true).html('<span class="dashicons dashicons-update-alt aag-spin"></span> Saving...');

        const wordCount = $('#target_word_count').val();
        const geminiApiKey = $('#gemini_api_key').val().trim();
        const freepikApiKey = $('#freepik_api_key').val().trim();
        const includeTable = $('input[name="include_table"]').is(':checked');
        const includeLists = $('input[name="include_lists"]').is(':checked');
        const includeFaq = $('input[name="include_faq"]').is(':checked');
        const articleTone = $('#article_tone').val();
        const articleToneAuto = $('#article_tone_auto').is(':checked');

        const data = {
            action: 'aag_save_requirements',
            nonce: aagAjax.nonce,
            gemini_api_key: geminiApiKey,
            freepik_api_key: freepikApiKey,
            target_word_count: wordCount,
            include_table: includeTable,
            include_lists: includeLists,
            include_faq: includeFaq,
            article_tone: articleTone,
            article_tone_auto: articleToneAuto ? 1 : 0
        };

        showMessage('Saving Article Requirements...', 'info');

        $.ajax({
            url: aagAjax.ajax_url,
            type: 'POST',
            data: data,
            success: function (response) {
                console.log('AAG: Requirements response:', response);
                $submitBtn.prop('disabled', false).html(originalText);

                if (response.success) {
                    showMessage(response.data, 'success');
                } else {
                    showMessage('Error: ' + response.data, 'error');
                }
            },
            error: function (xhr, status, error) {
                console.error('AAG: Requirements error:', error);
                $submitBtn.prop('disabled', false).html(originalText);
                showMessage('Error saving. Please try again.', 'error');
            }
        });
    });

    // Process queue
    $('#process-queue-btn').on('click', function (e) {
        e.preventDefault();

        const $btn = $(this);
        const originalText = $btn.html();

        $btn.prop('disabled', true).html('<span class="dashicons dashicons-update-alt aag-spin"></span> Processing...');

        const data = {
            action: 'aag_process_queue',
            nonce: aagAjax.nonce
        };

        showMessage('Generating article... This may take 1-2 minutes.', 'info');

        $.ajax({
            url: aagAjax.ajax_url,
            type: 'POST',
            data: data,
            timeout: 180000, // 3 minutes
            success: function (response) {
                console.log('AAG: Process queue response:', response);
                $btn.prop('disabled', false).html(originalText);

                if (response.success) {
                    const editLink = response.data.edit_link ?
                        ' <a href="' + response.data.edit_link + '" target="_blank">View Post</a>' : '';
                    showMessage(response.data.message + editLink, 'success');
                    refreshQueue();
                } else {
                    showMessage('Error: ' + response.data, 'error');
                }
            },
            error: function (xhr, status, error) {
                console.error('AAG: Process queue error:', error);
                $btn.prop('disabled', false).html(originalText);
                let errorMsg = 'Request failed. ';
                if (status === 'timeout') {
                    errorMsg += 'The request timed out. Please check the queue to see if the article was created.';
                }
                showMessage(errorMsg, 'error');
            }
        });
    });

    // Refresh queue
    $('#refresh-queue-btn').on('click', function (e) {
        e.preventDefault();
        refreshQueue();
    });

    // Clear queue
    $('#clear-queue-btn').on('click', function (e) {
        e.preventDefault();

        if (!confirm('Are you sure you want to clear all queue items? This cannot be undone.')) {
            return;
        }

        const data = {
            action: 'aag_clear_queue',
            nonce: aagAjax.nonce
        };

        $.post(aagAjax.ajax_url, data, function (response) {
            console.log('AAG: Clear queue response:', response);
            if (response.success) {
                showMessage(response.data, 'success');
                refreshQueue();
            } else {
                showMessage('Error: ' + response.data, 'error');
            }
        });
    });

    // Helper functions
    function refreshQueue() {
        console.log('AAG: Refreshing queue');
        const data = {
            action: 'aag_get_queue_status',
            nonce: aagAjax.nonce
        };

        $.post(aagAjax.ajax_url, data, function (response) {
            console.log('AAG: Queue status response:', response);
            if (response.success) {
                updateQueueTable(response.data.items);
                $('#pending-count').text(response.data.pending_count);
            } else {
                console.error('AAG: Failed to get queue status:', response.data);
            }
        });
    }

    function updateQueueTable(items) {
        const tbody = $('#queue-table tbody');
        tbody.empty();

        if (items.length === 0) {
            tbody.append('<tr><td colspan="7" style="text-align:center;">No items in queue</td></tr>');
            return;
        }

        items.forEach(function (item) {
            const postLink = item.post_id ?
                '<a href="post.php?post=' + item.post_id + '&action=edit" target="_blank">Edit Post</a>' :
                '-';

            const createdDate = new Date(item.created_at).toLocaleString();
            const keywordsToInclude = item.keywords_to_include ? escapeHtml(item.keywords_to_include) : '-';
            const methodLabel = item.keyword ? 'Method 2: Keyword Based' : 'Method 1: Title List';
            const statusLabel = item.status.charAt(0).toUpperCase() + item.status.slice(1) + ' (' + methodLabel + ')';
            const articlesPerRun = parseInt($('#articles_per_run').val(), 10) || 1;
            let scheduledInfo = '-';
            if (item.status === 'pending' && item.scheduled_at) {
                let scheduledDate = new Date(item.scheduled_at.replace(' ', 'T'));
                if (isNaN(scheduledDate.getTime())) {
                    scheduledInfo = item.scheduled_at + ' · ' + articlesPerRun + ' per run';
                } else {
                    scheduledInfo = scheduledDate.toLocaleString() + ' · ' + articlesPerRun + ' per run';
                }
            }

            tbody.append(
                '<tr>' +
                '<td>' + item.id + '</td>' +
                '<td>' + escapeHtml(item.title) + '</td>' +
                '<td>' + (item.keyword ? escapeHtml(item.keyword) : '-') + '</td>' +
                '<td>' + keywordsToInclude + '</td>' +
                '<td><span class="aag-status-' + item.status + '">' +
                statusLabel +
                '</span></td>' +
                '<td>' + scheduledInfo + '</td>' +
                '<td>' + postLink + '</td>' +
                '</tr>'
            );
        });
    }

    function showMessage(message, type) {
        console.log('AAG: Showing message:', type, message);
        const $msg = $('#aag-message');
        $msg.removeClass('notice-success notice-error notice-info')
            .addClass('notice-' + type)
            .html('<p>' + message + '</p>')
            .slideDown();

        if (type === 'success') {
            setTimeout(function () {
                $msg.slideUp();
            }, 10000);
        }

        if (type === 'error') {
            setTimeout(function () {
                $msg.slideUp();
            }, 15000);
        }
    }

    function getNextRunInfo() {
        try {
            const freq = $('#schedule_frequency').val();
            const tz = $('#schedule_timezone').val();
            const hour = $('select[name="schedule_hour"]').val();
            const minute = $('select[name="schedule_minute"]').val();
            const ampm = $('select[name="schedule_ampm"]').val();
            const articlesPerRun = parseInt($('#articles_per_run').val(), 10) || 1;

            let h = parseInt(hour, 10);
            if (ampm === 'PM' && h !== 12) h += 12;
            if (ampm === 'AM' && h === 12) h = 0;

            const now = new Date();
            const next = new Date(now);
            next.setHours(h, parseInt(minute, 10), 0, 0);
            if (next <= now) {
                if (freq === 'daily') next.setDate(next.getDate() + 1);
                else if (freq === 'weekly') next.setDate(next.getDate() + 7);
                else if (freq === 'monthly') next.setMonth(next.getMonth() + 1);
            }
            return next.toLocaleString() + ` · ${articlesPerRun} per run`;
        } catch (e) {
            return '-';
        }
    }
    function escapeHtml(text) {
        if (!text) return '';
        const map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        return text.replace(/[&<>"']/g, function (m) { return map[m]; });
    }

    // Initialize queue refresh on page load
    setTimeout(function () {
        refreshQueue();
    }, 500);

    // Add debug info
    console.log('AAG: Script initialization complete');
    console.log('AAG: aagAjax object:', aagAjax);
    console.log('AAG: jQuery version:', jQuery.fn.jquery);
});
