jQuery(document).ready(function ($) {
    console.log('AAG: Admin script loaded v2.0.7');

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

    // Features Sub-tab switching
    $(document).on('click', '.aag-feature-nav-item', function () {
        const featureId = $(this).data('feature');
        console.log('AAG: Switching to feature:', featureId);

        $('.aag-feature-nav-item').removeClass('active');
        $(this).addClass('active');

        $('.aag-feature-content').removeClass('active');
        $('#feature-' + featureId).addClass('active');
    });

    // Methods Sub-tab switching
    $(document).on('click', '.aag-method-nav-item', function () {
        const methodId = $(this).data('method');
        console.log('AAG: Switching to method:', methodId);

        $('.aag-method-nav-item').removeClass('active');
        $(this).addClass('active');

        $('.aag-method-content').removeClass('active');
        $('#method-' + methodId).addClass('active');
    });

    // --- Image SEO Section Functions ---

    function renderImageScanResults(images) {
        const $tbody = $('#aag-image-seo-table-body');
        $tbody.empty();

        if (images.length === 0) {
            $('#aag-image-scan-results').hide();
            $('#aag-image-empty-state').show().find('h3').text('No Images Found');
            $('#aag-image-empty-state').find('p').text('All images in your media library already have ALT text!');
            return;
        }

        images.forEach(function (img) {
            $tbody.append(`
                <tr id="aag-image-row-${img.id}">
                    <td><img src="${img.url}" style="width: 50px; height: 50px; object-fit: cover; border-radius: 4px;" /></td>
                    <td>
                        <strong>${img.title}</strong><br/>
                        <span class="description" style="font-size: 11px;">ID: ${img.id}</span>
                    </td>
                    <td class="aag-image-status">
                        <span class="aag-status-pending">Missing ALT</span>
                    </td>
                    <td>
                        <button class="button button-small aag-generate-alt-btn" data-id="${img.id}">
                            Analyze & Generate
                        </button>
                    </td>
                </tr>
            `);
        });

        $('#aag-image-empty-state').hide();
        $('#aag-image-scan-results').fadeIn();
    }

    // Scan Images Button (Using delegation for robustness)
    $(document).on('click', '#aag-scan-images-btn', function (e) {
        e.preventDefault();
        console.log('AAG: Scan Media Library button clicked');

        const $btn = $(this);
        const originalText = $btn.html();

        console.log('AAG: Starting AJAX scan request...');
        $btn.prop('disabled', true).html('<span class="dashicons dashicons-update aag-spin" style="margin-top: 4px;"></span> Scanning...');

        $.ajax({
            url: aagAjax.ajax_url,
            type: 'POST',
            data: {
                action: 'aag_scan_images',
                nonce: aagAjax.nonce
            },
            success: function (response) {
                console.log('AAG: Scan response received:', response);
                $btn.prop('disabled', false).html(originalText);
                if (response.success) {
                    console.log('AAG: Scan successful, rendering results');
                    renderImageScanResults(response.data.images || []);
                    if (response.data.message) {
                        showMessage(response.data.message, 'info');
                    }
                } else {
                    console.error('AAG: Scan failed server-side:', response.data);
                    showMessage('Scan failed: ' + response.data, 'error');
                }
            },
            error: function (xhr, status, error) {
                console.error('AAG: Scan AJAX error:', status, error);
                console.error('AAG: Response text:', xhr.responseText);
                $btn.prop('disabled', false).html(originalText);
                showMessage('Connection error during scan. Check console for details.', 'error');
            }
        });
    });

    // Single Image Process
    $(document).on('click', '.aag-generate-alt-btn', function (e) {
        e.preventDefault();
        const $btn = $(this);
        const attachmentId = $btn.data('id');
        const $row = $(`#aag-image-row-${attachmentId}`);
        const $statusCell = $row.find('.aag-image-status');

        $btn.prop('disabled', true).text('Generating...');
        $statusCell.html('<span class="dashicons dashicons-update aag-spin"></span> Processing...');

        $.ajax({
            url: aagAjax.ajax_url,
            type: 'POST',
            data: {
                action: 'aag_generate_image_seo',
                nonce: aagAjax.nonce,
                attachment_id: attachmentId
            },
            success: function (response) {
                if (response.success) {
                    $statusCell.html('<span class="aag-status-completed" style="background:#d1fae5; color:#065f46; padding: 2px 5px; border-radius: 4px; font-size: 11px;">✅ Updated</span>');
                    $btn.remove();

                    // Show generated info
                    const meta = `
                        <div class="aag-image-meta-info" style="font-size:11px; color:#6b7280; margin-top:5px; line-height: 1.3;">
                            <strong>Title:</strong> ${response.data.title}<br/>
                            <strong>Alt:</strong> ${response.data.alt}<br/>
                            <strong>Caption:</strong> ${response.data.caption}
                        </div>
                    `;
                    $statusCell.append(meta);

                    // Also update the row's title display if it exists
                    $row.find('strong').first().text(response.data.title);
                } else {
                    $btn.prop('disabled', false).text('Try Again');
                    $statusCell.html('<span class="aag-status-failed">Failed</span>');
                    showMessage('Error: ' + response.data, 'error');
                }
            },
            error: function () {
                $btn.prop('disabled', false).text('Try Again');
                $statusCell.html('<span class="aag-status-failed">Error</span>');
                showMessage('Connection error.', 'error');
            }
        });
    });

    // Bulk Process
    $('#aag-bulk-process-alt-btn').on('click', async function (e) {
        e.preventDefault();
        const $btn = $(this);
        const $allButtons = $('.aag-generate-alt-btn:not(:disabled)');

        if ($allButtons.length === 0) {
            showMessage('No images left to process.', 'info');
            return;
        }

        if (!confirm(`Are you sure you want to process ${$allButtons.length} images? This will use Gemini API credits.`)) {
            return;
        }

        $btn.prop('disabled', true).html('<span class="dashicons dashicons-update aag-spin" style="margin-top: 4px;"></span> Processing Bulk...');

        // Process sequentially to avoid API quota errors
        for (let i = 0; i < $allButtons.length; i++) {
            const $singleBtn = $($allButtons[i]);
            await new Promise(resolve => {
                $singleBtn.click();

                // We need a way to know when it finishes. 
                const checkInterval = setInterval(() => {
                    const id = $singleBtn.data('id');
                    const $row = $(`#aag-image-row-${id}`);
                    if ($row.find('.aag-status-completed').length > 0 || $row.find('.aag-status-failed').length > 0) {
                        clearInterval(checkInterval);
                        resolve();
                    }
                }, 500);
            });

            // Small pause
            await new Promise(r => setTimeout(r, 1000));
        }

        $btn.prop('disabled', false).html('<span class="dashicons dashicons-images-alt2" style="margin-top: 4px;"></span> Process All Visible');
        showMessage('Bulk processing complete!', 'success');
    });

    // --- Content Refresher (Update OLD articles) Section ---

    // Scan for old articles
    $(document).on('click', '#aag-scan-old-articles-btn', function (e) {
        e.preventDefault();
        console.log('AAG: Scan Old Articles button clicked');
        const $btn = $(this);
        const originalHtml = $btn.html();

        $btn.prop('disabled', true).html('<span class="dashicons dashicons-update aag-spin" style="margin-top: 4px;"></span> Scanning...');
        $('#aag-refresher-results').hide();
        $('#aag-refresher-suggestions-box').hide();

        $.ajax({
            url: aagAjax.ajax_url,
            type: 'POST',
            data: {
                action: 'aag_scan_old_articles',
                nonce: aagAjax.nonce
            },
            success: function (response) {
                $btn.prop('disabled', false).html(originalHtml);
                if (response.success) {
                    const articles = response.data.articles || [];
                    const $tbody = $('#aag-refresher-table-body');
                    $tbody.empty();

                    if (articles.length === 0) {
                        $('#aag-refresher-empty-state h3').text('No Old Articles Found');
                        $('#aag-refresher-empty-state p').text('All your generated articles are fresh (less than 30 days old).');
                        $('#aag-refresher-empty-state').show();
                    } else {
                        articles.forEach(function (article) {
                            $tbody.append(`
                                <tr>
                                    <td><strong>${article.title}</strong></td>
                                    <td>${article.post_date}</td>
                                    <td>
                                        <button class="button button-small aag-get-suggestions-btn" data-id="${article.post_id}" data-title="${article.title}">
                                            Get Suggestions
                                        </button>
                                    </td>
                                </tr>
                            `);
                        });
                        $('#aag-refresher-empty-state').hide();
                        $('#aag-refresher-results').fadeIn();
                    }
                } else {
                    showMessage(response.data, 'error');
                }
            },
            error: function () {
                $btn.prop('disabled', false).html(originalHtml);
                showMessage('Connection error during scan.', 'error');
            }
        });
    });

    // Get suggestions for a specific article
    $(document).on('click', '.aag-get-suggestions-btn', function () {
        const $btn = $(this);
        const postId = $btn.data('id');
        const title = $btn.data('title');

        $btn.prop('disabled', true).text('Analyzing...');
        $('#aag-refresher-suggestions-box').hide();

        $.ajax({
            url: aagAjax.ajax_url,
            type: 'POST',
            data: {
                action: 'aag_get_refresher_suggestions',
                nonce: aagAjax.nonce,
                post_id: postId
            },
            success: function (response) {
                $btn.prop('disabled', false).text('Get Suggestions');
                if (response.success) {
                    const suggestions = response.data.suggestions || [];
                    $('#aag-refresher-target-title').text(`Update Suggestions for: ${title}`);
                    const $content = $('#aag-refresher-suggestions-content');
                    $content.empty();

                    if (suggestions.length === 0) {
                        $content.append('<p>No suggestions found. The article seems well-optimized.</p>');
                    } else {
                        suggestions.forEach(function (s) {
                            $content.append(`
                                <div class="aag-suggestion-item" style="margin-bottom: 20px; padding: 20px; background: #f8fafc; border-left: 4px solid #3b82f6; border-radius: 8px; box-shadow: 0 1px 2px rgba(0,0,0,0.05); display: flex; justify-content: space-between; align-items: flex-start;">
                                    <div style="flex-grow: 1; padding-right: 20px;">
                                        <h4 style="margin-top: 0; color: #1e3a8a; font-size: 16px;">${s.title}</h4>
                                        <p style="margin-bottom: 0; color: #4b5563; line-height: 1.5;">${s.description}</p>
                                    </div>
                                    <button class="button button-secondary aag-apply-suggestion-btn" 
                                            data-id="${postId}" 
                                            data-title="${s.title}" 
                                            data-desc="${s.description}">
                                        Apply This Update
                                    </button>
                                </div>
                            `);
                        });
                    }
                    $('#aag-refresher-suggestions-box').fadeIn();
                    $('html, body').animate({
                        scrollTop: $("#aag-refresher-suggestions-box").offset().top - 100
                    }, 500);
                } else {
                    showMessage(response.data, 'error');
                }
            },
            error: function () {
                $btn.prop('disabled', false).text('Get Suggestions');
                showMessage('Connection error.', 'error');
            }
        });
    });

    // Apply a specific suggestion
    $(document).on('click', '.aag-apply-suggestion-btn', function () {
        const $btn = $(this);
        const postId = $btn.data('id');
        const title = $btn.data('title');
        const desc = $btn.data('desc');

        $btn.prop('disabled', true).text('Applying...');

        $.ajax({
            url: aagAjax.ajax_url,
            type: 'POST',
            data: {
                action: 'aag_apply_refresher_suggestion',
                nonce: aagAjax.nonce,
                post_id: postId,
                suggestion_title: title,
                suggestion_description: desc
            },
            success: function (response) {
                if (response.success) {
                    $btn.removeClass('button-secondary').addClass('button-disabled').text('✅ Applied').prop('disabled', true);
                    showMessage(response.data, 'success');
                } else {
                    $btn.prop('disabled', false).text('Apply This Update');
                    showMessage(response.data, 'error');
                }
            },
            error: function () {
                $btn.prop('disabled', false).text('Apply This Update');
                showMessage('Connection error.', 'error');
            }
        });
    });

    // --- Content Gap Analyzer Section ---

    // Add another URL field
    $('#aag-add-url-btn').on('click', function () {
        const count = $('.aag-competitor-url').length;
        if (count >= 5) {
            alert('Maximum 5 competitor URLs allowed.');
            return;
        }
        $('#aag-competitor-urls-container').append(`
            <input type="url" class="aag-competitor-url" placeholder="https://competitor${count + 1}.com/ranking-page/"
                style="width: 100%; max-width: 500px; margin-bottom: 5px;" />
        `);
    });

    // Analyze gaps
    $('#aag-analyze-gap-btn').on('click', function () {
        console.log('AAG: Analyze Content Gaps button clicked');
        const keyword = $('#aag-gap-keyword').val().trim();
        const urls = [];
        $('.aag-competitor-url').each(function () {
            const val = $(this).val().trim();
            if (val) urls.push(val);
        });

        console.log('AAG: Keyword:', keyword);
        console.log('AAG: URLs:', urls);

        if (!keyword) {
            alert('Please enter a target keyword.');
            return;
        }

        const $btn = $(this);
        const originalHtml = $btn.html();

        $btn.prop('disabled', true).html('<span class="dashicons dashicons-update aag-spin" style="margin-top: 4px;"></span> Analyzing Gaps...');
        $('#aag-gap-results').hide();

        console.log('AAG: Sending AJAX request to:', aagAjax.ajax_url);

        $.ajax({
            url: aagAjax.ajax_url,
            type: 'POST',
            data: {
                action: 'aag_analyze_content_gap',
                nonce: aagAjax.nonce,
                keyword: keyword,
                urls: urls
            },
            success: function (response) {
                $btn.prop('disabled', false).html(originalHtml);
                if (response.success) {
                    const suggestions = response.data.suggestions || [];
                    const $tbody = $('#aag-gap-results-body');
                    $tbody.empty();

                    if (suggestions.length === 0) {
                        $tbody.append('<tr><td colspan="4">No gaps identified. Try broader competitors.</td></tr>');
                    } else {
                        suggestions.forEach(function (s) {
                            $tbody.append(`
                                <tr>
                                    <td><strong>${s.title}</strong></td>
                                    <td style="font-size: 13px; color: #4b5563;">${s.reason}</td>
                                    <td><span class="aag-badge ${s.priority.toLowerCase()}">${s.priority}</span></td>
                                    <td>
                                        <button class="button button-small aag-add-gap-to-queue" data-title="${s.title}">
                                            Add to Queue
                                        </button>
                                    </td>
                                </tr>
                            `);
                        });
                    }
                    $('#aag-gap-results').fadeIn();
                } else {
                    showMessage(response.data, 'error');
                }
            },
            error: function () {
                $btn.prop('disabled', false).html(originalHtml);
                showMessage('Connection error during analysis.', 'error');
            }
        });
    });

    // Add suggestion to queue
    $(document).on('click', '.aag-add-gap-to-queue', function () {
        const $btn = $(this);
        const title = $btn.data('title');

        $btn.prop('disabled', true).text('Adding...');

        $.ajax({
            url: aagAjax.ajax_url,
            type: 'POST',
            data: {
                action: 'aag_add_gap_suggestion',
                nonce: aagAjax.nonce,
                title: title
            },
            success: function (response) {
                if (response.success) {
                    $btn.removeClass('button-primary').addClass('button-disabled').text('✅ Added');
                    showMessage('Added: ' + title, 'success');
                    // Refresh queue to show the new item
                    refreshQueue();
                } else {
                    $btn.prop('disabled', false).text('Add to Queue');
                    showMessage('Failed to add: ' + response.data, 'error');
                }
            },
            error: function () {
                $btn.prop('disabled', false).text('Add to Queue');
                showMessage('Connection error.', 'error');
            }
        });
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
        const subTab = $(this).data('sub');
        console.log('AAG: Navigation to tab:', targetTab, 'Sub-tab:', subTab);
        $('.aag-tab-btn[data-tab="' + targetTab + '"]').click();

        if (targetTab === 'methods' && subTab) {
            $('.aag-method-nav-item[data-method="' + subTab + '"]').click();
        } else if (targetTab === 'features' && subTab) {
            $('.aag-feature-nav-item[data-feature="' + subTab + '"]').click();
        }
    });

    $('#settings_submit_btn').on('click', function () {
        console.log('AAG: Save button clicked');
    });

    // License Modal Logic
    const $licenseModal = $('#aag-license-modal');
    const $modalTrigger = $('#aag-license-modal-trigger');
    const $modalClose = $('#aag-modal-close-btn');

    $modalTrigger.on('click', function (e) {
        e.preventDefault();
        console.log('AAG: Opening license modal');
        $licenseModal.fadeIn(300).css('display', 'flex');
    });

    $modalClose.on('click', function () {
        $licenseModal.fadeOut(300);
    });

    $(window).on('click', function (e) {
        if ($(e.target).is($licenseModal)) {
            $licenseModal.fadeOut(300);
        }
    });

    $(document).on('keydown', function (e) {
        if (e.key === 'Escape') {
            if ($licenseModal.is(':visible')) {
                $licenseModal.fadeOut(300);
            }
            if ($('#aag-coming-soon-modal').is(':visible')) {
                $('#aag-coming-soon-modal').fadeOut(300);
            }
        }
    });

    // Coming Soon Modal Logic
    const $comingSoonModal = $('#aag-coming-soon-modal');
    const $comingSoonTrigger = $('#aag-coming-soon-trigger');
    const $comingSoonClose = $comingSoonModal.find('.aag-modal-close');

    $comingSoonTrigger.on('click', function (e) {
        e.preventDefault();
        console.log('AAG: Opening coming soon modal');
        $comingSoonModal.fadeIn(300).css('display', 'flex');
    });

    $comingSoonClose.on('click', function () {
        $comingSoonModal.fadeOut(300);
    });

    $(window).on('click', function (e) {
        if ($(e.target).is($comingSoonModal)) {
            $comingSoonModal.fadeOut(300);
        }
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

        const data = {
            action: 'aag_save_method1',
            nonce: aagAjax.nonce,
            titles: titles
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

    // Save Traffic Settings
    $('#aag-traffic-form').on('submit', function (e) {
        e.preventDefault();

        const $submitBtn = $(this).find('button[type="submit"]');
        const originalText = $submitBtn.html();

        $submitBtn.prop('disabled', true).html('<span class="dashicons dashicons-update-alt aag-spin"></span> Saving...');

        const data = {
            action: 'aag_save_traffic',
            nonce: aagAjax.nonce,
            telegram_bot_token: $('#telegram_bot_token').val().trim(),
            telegram_chat_id: $('#telegram_chat_id').val().trim(),
            discord_webhook_url: $('#discord_webhook_url').val().trim(),
            ayrshare_api_key: $('#ayrshare_api_key').val().trim()
        };

        showMessage('Saving Traffic Settings...', 'info');

        $.ajax({
            url: aagAjax.ajax_url,
            type: 'POST',
            data: data,
            success: function (response) {
                $submitBtn.prop('disabled', false).html(originalText);
                if (response.success) {
                    showMessage(response.data, 'success');
                } else {
                    showMessage('Error: ' + response.data, 'error');
                }
            },
            error: function () {
                $submitBtn.prop('disabled', false).html(originalText);
                showMessage('Error saving traffic settings. Please try again.', 'error');
            }
        });
    });

    // Save Notification Settings
    $('#aag-notifications-form').on('submit', function (e) {
        e.preventDefault();

        const $submitBtn = $(this).find('button[type="submit"]');
        const originalText = $submitBtn.html();

        $submitBtn.prop('disabled', true).html('<span class="dashicons dashicons-update-alt aag-spin"></span> Saving...');

        const data = {
            action: 'aag_save_notifications',
            nonce: aagAjax.nonce,
            onesignal_app_id: $('#onesignal_app_id').val().trim(),
            onesignal_rest_api_key: $('#onesignal_rest_api_key').val().trim(),
            webpushr_key: $('#webpushr_key').val().trim(),
            webpushr_token: $('#webpushr_token').val().trim()
        };

        showMessage('Saving Notification Settings...', 'info');

        $.ajax({
            url: aagAjax.ajax_url,
            type: 'POST',
            data: data,
            success: function (response) {
                $submitBtn.prop('disabled', false).html(originalText);
                if (response.success) {
                    showMessage(response.data, 'success');
                } else {
                    showMessage('Error: ' + response.data, 'error');
                }
            },
            error: function () {
                $submitBtn.prop('disabled', false).html(originalText);
                showMessage('Error saving notification settings. Please try again.', 'error');
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
                    refreshQueue();
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
        const includeYoutube = $('input[name="include_youtube"]').is(':checked');
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
            include_youtube: includeYoutube,
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

    // Test Freepik Connection
    $('#aag-test-freepik-btn').on('click', function (e) {
        e.preventDefault();
        const $btn = $(this);
        const $result = $('#freepik-test-result');
        const apiKey = $('#freepik_api_key').val().trim();

        if (!apiKey) {
            $result.text('Please enter an API key first.').css('color', 'red');
            return;
        }

        $btn.prop('disabled', true).text('Testing...');
        $result.text(' Connecting to Freepik...').css('color', '#666');

        $.ajax({
            url: aagAjax.ajax_url,
            type: 'POST',
            data: {
                action: 'aag_test_freepik',
                nonce: aagAjax.nonce,
                api_key: apiKey
            },
            success: function (response) {
                $btn.prop('disabled', false).text('Test Connection');
                if (response.success) {
                    $result.text(' ✅ ' + response.data).css('color', 'green');
                } else {
                    $result.text(' ❌ ' + response.data).css('color', 'red');
                }
            },
            error: function () {
                $btn.prop('disabled', false).text('Test Connection');
                $result.text(' ❌ Request failed.').css('color', 'red');
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

        const btn = $(this);
        btn.prop('disabled', true).text('Clearing...');

        $.post(aagAjax.ajax_url, data, function (response) {
            btn.prop('disabled', false).text('Clear All Queue');
            if (response.success) {
                showMessage(response.data, 'success');
                refreshQueue();
            } else {
                showMessage(response.data || 'Error clearing queue', 'error');
            }
        }).fail(function () {
            btn.prop('disabled', false).text('Clear All Queue');
            showMessage('Connection error', 'error');
        });
    });

    // Delete single queue item
    $(document).on('click', '.aag-delete-item-btn', function (e) {
        e.preventDefault();

        if (!confirm('Are you sure you want to delete this article?')) {
            return;
        }

        const btn = $(this);
        const id = btn.data('id');

        // Initial visual feedback
        btn.prop('disabled', true);
        const originalHtml = btn.html();
        btn.html('<span class="dashicons dashicons-update" style="animation: spin 2s linear infinite;"></span>');

        const data = {
            action: 'aag_delete_queue_item',
            nonce: aagAjax.nonce,
            id: id
        };

        $.post(aagAjax.ajax_url, data, function (response) {
            if (response.success) {
                // Remove row immediately for snappy feel, then refresh to be sure
                btn.closest('tr').fadeOut(300, function () {
                    $(this).remove();
                    // Update counts locally for snappiness
                    const currentCount = parseInt($('#pending-count').first().text()) || 0;
                    if (currentCount > 0) {
                        $('#pending-count, #aag-stats-queue-count').text(currentCount - 1);
                    }
                });
                showMessage(response.data, 'success');
                // Also refresh to be sure we are in sync
                refreshQueue();
            } else {
                showMessage(response.data || 'Error deleting item', 'error');
                btn.prop('disabled', false).html(originalHtml);
            }
        }).fail(function () {
            showMessage('Connection error', 'error');
            btn.prop('disabled', false).html(originalHtml);
        });
    });

    // License Activation
    $('#aag-license-form').on('submit', function (e) {
        e.preventDefault();
        const $form = $(this);
        const $btn = $form.find('button[type="submit"]');
        const key = $('#license_key').val().trim();
        const originalText = $btn.html();

        $btn.prop('disabled', true).html('Activating...');

        $.post(aagAjax.ajax_url, {
            action: 'aag_activate_license',
            nonce: aagAjax.nonce,
            license_key: key
        }, function (response) {
            $btn.prop('disabled', false).html(originalText);
            if (response.success) {
                showMessage(response.data, 'success');
                setTimeout(function () {
                    location.reload();
                }, 1500);
            } else {
                showMessage(response.data, 'error');
            }
        });
    });

    // License Deactivation
    $('#deactivate-license-btn').on('click', function (e) {
        e.preventDefault();
        if (!confirm('Are you sure you want to deactivate your license? Premium features will be locked.')) {
            return;
        }

        const $btn = $(this);
        $btn.prop('disabled', true).html('Deactivating...');

        $.post(aagAjax.ajax_url, {
            action: 'aag_deactivate_license',
            nonce: aagAjax.nonce
        }, function (response) {
            if (response.success) {
                showMessage(response.data, 'success');
                setTimeout(function () {
                    location.reload();
                }, 1500);
            } else {
                $btn.prop('disabled', false).html('Deactivate License');
                showMessage(response.data, 'error');
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

                // Update top statistics cards
                $('#aag-stats-queue-count').text(response.data.pending_count);
                if (response.data.usage) {
                    $('#aag-stats-today-count').text(response.data.usage.today);
                    $('#aag-stats-month-count').text(response.data.usage.month);
                    $('#aag-stats-remaining-count').text(response.data.usage.remaining);
                }
            } else {
                console.error('AAG: Failed to get queue status:', response.data);
            }
        });
    }

    function updateQueueTable(items) {
        const tbody = $('#queue-table tbody');
        tbody.empty();

        if (items.length === 0) {
            tbody.append('<tr><td colspan="8" style="text-align:center;">No items in queue</td></tr>');
            return;
        }

        items.forEach(function (item) {
            const postLink = item.post_id ?
                '<a href="post.php?post=' + item.post_id + '&action=edit" target="_blank">Edit Post</a>' :
                '-';

            const createdDate = new Date(item.created_at).toLocaleString();
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

            let actionHtml = '-';
            if (item.status === 'pending') {
                actionHtml = '<button class="button button-small aag-delete-item-btn" data-id="' + item.id + '" title="Delete Article"><span class="dashicons dashicons-trash" style="line-height: 1.3;"></span></button>';
            }

            tbody.append(
                '<tr>' +
                '<td>' + item.id + '</td>' +
                '<td>' + escapeHtml(item.title) + '</td>' +
                '<td>' + (item.keyword ? escapeHtml(item.keyword) : '-') + '</td>' +
                '<td><span class="aag-status-' + item.status + '">' +
                statusLabel +
                '</span></td>' +
                '<td>' + scheduledInfo + '</td>' +
                '<td>' + postLink + '</td>' +
                '<td>' + actionHtml + '</td>' +
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

    // Toggle UPI Claim Form
    $('#toggle-upi-claim').on('click', function (e) {
        e.preventDefault();
        $('#upi-claim-form-container').slideToggle();
        $(this).hide();
    });

    // Handle UPI Claim Submission
    $('#aag-upi-claim-form').on('submit', function (e) {
        e.preventDefault();
        const $form = $(this);
        const $btn = $form.find('button[type="submit"]');
        const originalText = $btn.html();

        const email = $form.find('input[name="upi_email"]').val().trim();
        const utr = $form.find('input[name="upi_utr"]').val().trim();

        if (utr.length < 10) {
            showMessage('Error: Please enter a valid 12-digit UTR number.', 'error');
            return;
        }

        $btn.prop('disabled', true).html('Submitting...');

        $.post(aagAjax.ajax_url, {
            action: 'aag_submit_upi_claim',
            nonce: aagAjax.nonce,
            email: email,
            utr: utr
        }, function (response) {
            $btn.prop('disabled', false).html(originalText);
            if (response.success) {
                showMessage(response.data, 'success');
                $form.closest('.aag-upi-claim-section').html('<h3>✅ Claim Submitted</h3><p>' + response.data + '</p>');
            } else {
                showMessage(response.data, 'error');
            }
        });
    });

    // Initialize queue refresh on page load
    setTimeout(function () {
        refreshQueue();
    }, 500);

    // Handle Linking Settings Submission
    $('#aag-linking-settings-form').on('submit', function (e) {
        e.preventDefault();
        const $form = $(this);
        const $btn = $form.find('button[type="submit"]');
        const $spinner = $form.find('.spinner');

        $btn.prop('disabled', true);
        $spinner.addClass('is-active');

        const formData = new FormData(this);
        formData.append('action', 'aag_save_linking');
        formData.append('nonce', aagAjax.nonce);

        $.ajax({
            url: aagAjax.ajax_url,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function (response) {
                $btn.prop('disabled', false);
                $spinner.removeClass('is-active');
                if (response.success) {
                    showMessage(response.data, 'success');
                } else {
                    showMessage(response.data, 'error');
                }
            },
            error: function () {
                $btn.prop('disabled', false);
                $spinner.removeClass('is-active');
                showMessage('An error occurred while saving.', 'error');
            }
        });
    });

    // --- API Key Visibility Toggle ---
    $(document).on('click', '.aag-eye-toggle', function (e) {
        e.preventDefault();
        e.stopPropagation();
        console.log('AAG: Eye toggle clicked for:', $(this).data('target'));
        const targetId = $(this).data('target');
        const $input = $('#' + targetId);
        const $icon = $(this);

        console.log('AAG: Current input type:', $input.attr('type'));

        if ($input.attr('type') === 'password') {
            $input.attr('type', 'text');
            $icon.removeClass('dashicons-visibility').addClass('dashicons-hidden');
            console.log('AAG: Switched to text');
        } else {
            $input.attr('type', 'password');
            $icon.removeClass('dashicons-hidden').addClass('dashicons-visibility');
            console.log('AAG: Switched to password');
        }
    });

    // Add debug info
    console.log('AAG: Script initialization complete');
    console.log('AAG: aagAjax object:', aagAjax);
    console.log('AAG: jQuery version:', jQuery.fn.jquery);
});

