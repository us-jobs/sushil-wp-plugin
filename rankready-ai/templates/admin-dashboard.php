<div class="wrap aag-container">
    <h1>RankReady AI - Human Like Content Generator</h1>
    <div class="aag-header-action">
        <a href="#" class="aag-upgrade-trigger-btn <?php echo $is_premium ? 'is-active-premium' : ''; ?>"
            id="aag-license-modal-trigger">
            <?php if ($is_premium): ?>
                <span class="dashicons dashicons-star-filled"></span> Premium Active
            <?php else: ?>
                <span class="dashicons dashicons-admin-network"></span> Upgrade to Premium
            <?php endif; ?>
        </a>
    </div>
    <?php $is_free_tier = !$is_premium && !$trial_active; ?>

    <!-- Trial/License Status Banner -->
    <div class="aag-license-banner">
        <?php if ($is_premium): ?>
            <div class="notice notice-success">
                <p><strong>🌟 Premium Active!</strong> You have unlimited access to article generation.</p>
            </div>
        <?php elseif ($trial_active): ?>
            <div class="notice notice-info">
                <p><strong>🎉 Free Trial Active!</strong> You have <?php echo esc_html($trial_days_left); ?> days left.
                    Articles today: <?php echo esc_html($usage_stats['today']); ?>/2</p>
            </div>
        <?php else: ?>
            <div class="notice notice-warning">
                <div class="notice notice-warning">
                    <p><strong>⚠️ Trial Expired - Free Tier Active.</strong> You can generate 1 article per week (max 1000
                        words). <a href="#" class="aag-nav-btn" data-target="license">Upgrade to Premium</a> for unlimited
                        access.</p>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- Usage Stats -->
    <div class="aag-usage-stats">
        <div class="stat-box">
            <h3><?php echo esc_html($usage_stats['today']); ?></h3>
            <p>Today</p>
        </div>
        <div class="stat-box">
            <h3><?php echo esc_html($usage_stats['month']); ?></h3>
            <p>This Month</p>
        </div>
        <div class="stat-box">
            <h3><?php echo esc_html($usage_stats['remaining']); ?></h3>
            <p>Remaining Today</p>
        </div>
        <div class="stat-box">
            <h3><?php echo esc_html($pending_count); ?></h3>
            <p>In Queue</p>
        </div>
    </div>

    <div class="aag-tabs">
        <button class="aag-tab-btn active" data-tab="settings">Schedule Settings</button>
        <button class="aag-tab-btn" data-tab="requirements">Article Requirements</button>
        <button class="aag-tab-btn" data-tab="method1">Method 1: Title List</button>
        <button class="aag-tab-btn" data-tab="method2">Method 2: Keyword Based</button>
        <button class="aag-tab-btn" data-tab="linking">Internal Linking</button>
        <button class="aag-tab-btn" data-tab="queue">Article Status</button>
    </div>

    <!-- Settings Tab -->
    <div class="aag-tab-content active" id="settings-tab">

        <form id="aag-settings-form">
            <input type="hidden" name="gen_method_saved"
                value="<?php echo esc_attr(get_option('aag_gen_method', 'method1')); ?>">



            <table class="form-table">
                <!-- Generation Method -->
                <tr>
                    <th><label for="gen_method">Generation Source <span class="aag-tooltip-container"><span
                                    class="aag-help-icon dashicons dashicons-editor-help"></span><span
                                    class="aag-tooltip-text">Choose how articles are generated: from a pre-defined list
                                    of titles or dynamically from a keyword.</span></span></label></th>
                    <td>
                        <select id="gen_method" name="gen_method">
                            <option value="method1" <?php selected(get_option('aag_gen_method'), 'method1'); ?>>Method
                                1: Title List</option>
                            <option value="method2" <?php selected(get_option('aag_gen_method'), 'method2'); ?>>Method
                                2: Keyword Based</option>
                        </select>
                        <div style="margin-top: 10px;">
                            <button type="button" class="button aag-nav-btn" data-target="method1">Go to Methods 1
                                Settings</button>
                            <button type="button" class="button aag-nav-btn" data-target="method2">Go to Methods 2
                                Settings</button>
                        </div>
                    </td>
                </tr>


                <tr>
                    <th><label for="post_status">Post Status <span class="aag-tooltip-container"><span
                                    class="aag-help-icon dashicons dashicons-editor-help"></span><span
                                    class="aag-tooltip-text">Select whether generated posts should be Drafts, Published,
                                    or Pending Review.</span></span></label></th>
                    <td>
                        <select id="post_status" name="post_status">
                            <option value="draft" <?php selected($post_status, 'draft'); ?>>Draft</option>
                            <option value="publish" <?php selected($post_status, 'publish'); ?>>Publish</option>
                            <option value="pending" <?php selected($post_status, 'pending'); ?>>Pending Review</option>
                        </select>
                    </td>
                </tr>
                <!-- Frequency Dropdown -->
                <tr>
                    <th><label for="schedule_frequency">Schedule Frequency <span class="aag-tooltip-container"><span
                                    class="aag-help-icon dashicons dashicons-editor-help"></span><span
                                    class="aag-tooltip-text">Choose how often you want the plugin to generate
                                    articles.</span></span></label></th>
                    <td>
                        <?php
                        $frequency = get_option('aag_schedule_frequency', 'daily');
                        if ($is_free_tier)
                            $frequency = 'weekly';
                        ?>
                        <select id="schedule_frequency" name="schedule_frequency">
                            <option value="daily" <?php selected($frequency, 'daily'); ?> <?php echo $is_free_tier ? 'disabled' : ''; ?>>Every Day <?php echo $is_free_tier ? '(Upgrade)' : ''; ?></option>
                            <option value="weekly" <?php selected($frequency, 'weekly'); ?>>Every Week</option>
                            <option value="monthly" <?php selected($frequency, 'monthly'); ?> <?php echo $is_free_tier ? 'disabled' : ''; ?>>Every Month <?php echo $is_free_tier ? '(Upgrade)' : ''; ?></option>
                            <option value="custom" <?php selected($frequency, 'custom'); ?> <?php echo $is_free_tier ? 'disabled' : ''; ?>>Custom Interval (Minutes)
                                <?php echo $is_free_tier ? '(Upgrade)' : ''; ?>
                            </option>
                        </select>
                    </td>
                </tr>

                <!-- Custom Interval (Hidden unless custom is selected) -->
                <tr id="custom_interval_row" <?php if ($frequency !== 'custom')
                    echo 'style="display:none;"'; ?>>
                    <th><label for="schedule_interval">Custom Interval (minutes) <span
                                class="aag-tooltip-container"><span
                                    class="aag-help-icon dashicons dashicons-editor-help"></span><span
                                    class="aag-tooltip-text">Enter the interval in minutes between each run (e.g., 60
                                    for every hour).</span></span></label></th>
                    <td>
                        <input type="number" id="schedule_interval" name="schedule_interval"
                            value="<?php echo esc_attr($schedule_interval); ?>" min="15" max="10080">
                        <p class="description">Time between each article generation (minutes)</p>
                    </td>
                </tr>

                <!-- Articles Per Run -->
                <tr>
                    <th><label for="articles_per_run">Articles Per Run <span class="aag-tooltip-container"><span
                                    class="aag-help-icon dashicons dashicons-editor-help"></span><span
                                    class="aag-tooltip-text">How many articles should be generated each time the
                                    schedule executes.</span></span></label></th>
                    <td>
                        <?php $articles_per_run = get_option('aag_articles_per_run', '1'); ?>
                        <input type="number" id="articles_per_run" name="articles_per_run"
                            value="<?php echo esc_attr($articles_per_run); ?>" min="1" max="10">
                        <p class="description">How many articles to generate each time the schedule runs</p>
                    </td>
                </tr>

                <!-- Time Selection -->
                <tr id="time_selection_row">
                    <th><label>Schedule Time <span class="aag-tooltip-container"><span
                                    class="aag-help-icon dashicons dashicons-editor-help"></span><span
                                    class="aag-tooltip-text">Select the specific time of day for the schedule to run
                                    (daily/weekly/monthly).</span></span></label></th>
                    <td>
                        <?php
                        $saved_time = get_option('aag_schedule_time', '10:00 AM');
                        list($time_part, $ampm_part) = explode(' ', $saved_time);
                        list($hour_part, $minute_part) = explode(':', $time_part);
                        ?>
                        <select name="schedule_hour" class="tiny-text">
                            <?php for ($i = 1; $i <= 12; $i++): ?>
                                <option value="<?php echo sprintf('%02d', $i); ?>" <?php selected($hour_part, sprintf('%02d', $i)); ?>>
                                    <?php echo sprintf('%02d', $i); ?>
                                </option>
                            <?php endfor; ?>
                        </select>
                        :
                        <select name="schedule_minute" class="tiny-text">
                            <option value="00" <?php selected($minute_part, '00'); ?>>00</option>
                            <option value="15" <?php selected($minute_part, '15'); ?>>15</option>
                            <option value="30" <?php selected($minute_part, '30'); ?>>30</option>
                            <option value="45" <?php selected($minute_part, '45'); ?>>45</option>
                        </select>
                        <select name="schedule_ampm" class="tiny-text">
                            <option value="AM" <?php selected($ampm_part, 'AM'); ?>>AM</option>
                            <option value="PM" <?php selected($ampm_part, 'PM'); ?>>PM</option>
                        </select>
                        <p class="description">Select the local time to run (for Daily, Weekly, Monthly)</p>
                    </td>
                </tr>

                <!-- Timezone -->
                <tr>
                    <th><label for="schedule_timezone">Timezone <span class="aag-tooltip-container"><span
                                    class="aag-help-icon dashicons dashicons-editor-help"></span><span
                                    class="aag-tooltip-text">Select your local timezone to ensure the schedule runs at
                                    the correct time.</span></span></label></th>
                    <td>
                        <div style="margin-bottom: 5px;">
                            <label>
                                <input type="checkbox" id="aag_auto_timezone" name="aag_auto_timezone" value="1" <?php checked(get_option('aag_auto_timezone'), '1'); ?>>
                                Your current timezone is <span id="aag_detected_tz_display">Detecting...</span>
                            </label>
                        </div>
                        <?php
                        $saved_timezone = get_option('aag_schedule_timezone', wp_timezone_string());
                        $timezones = timezone_identifiers_list();
                        ?>
                        <select id="schedule_timezone" name="schedule_timezone">
                            <?php foreach ($timezones as $tz): ?>
                                <option value="<?php echo esc_attr($tz); ?>" <?php selected($saved_timezone, $tz); ?>>
                                    <?php echo esc_html($tz); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                </tr>

                <!-- Generate Immediately -->
                <tr>
                    <th></th>
                    <td>
                        <label>
                            <input type="checkbox" id="generate_immediate" name="generate_immediate" value="1">
                            Generate 1 article immediately on save
                        </label>
                    </td>
                </tr>
            </table>
            <p class="submit">
                <button type="submit" id="settings_submit_btn" class="button button-primary">Save and Schedule</button>
            </p>
        </form>
    </div>

    <!-- Method 1 Tab -->
    <div class="aag-tab-content" id="method1-tab">
        <h2>Method 1: Title List Source</h2>
        <form id="aag-method1-form">
            <p>
                <label for="title_list"><strong>Article Titles Source (one per line)</strong></label>
                <textarea id="title_list" name="title_list" rows="10" class="large-text"
                    placeholder="Enter article titles, one per line..."><?php echo esc_textarea(get_option('aag_method1_titles', '')); ?></textarea>
                <span class="description">Scheduler will pick titles from this list top-to-bottom.</span>
            </p>
            <p>
                <label for="title_keywords"><strong>Keywords to Include (Optional)</strong></label>
                <input type="text" id="title_keywords" name="title_keywords" class="regular-text"
                    value="<?php echo esc_attr(get_option('aag_method1_keywords', '')); ?>"
                    placeholder="e.g., SEO, WordPress, digital marketing">
                <span class="description">Common keywords for these articles</span>
            </p>
            <p class="submit">
                <button type="submit" class="button button-primary">Save Title List</button>
            </p>
        </form>
    </div>

    <!-- Method 2 Tab -->
    <div class="aag-tab-content" id="method2-tab">
        <h2>Method 2: Keyword Based Source</h2>
        <form id="aag-method2-form">
            <table class="form-table">
                <tr>
                    <th><label for="keyword">Target Keyword</label></th>
                    <td>
                        <input type="text" id="keyword" name="keyword" class="regular-text"
                            value="<?php echo esc_attr(get_option('aag_method2_keyword', '')); ?>"
                            placeholder="e.g., artificial intelligence">
                        <p class="description">Plugin will dynamically generate trending titles based on this keyword.
                        </p>
                    </td>
                </tr>
            </table>
            <p class="submit">
                <button type="submit" class="button button-primary">Save Keyword Source</button>
            </p>
        </form>
    </div>

    <!-- Article Requirements Tab -->
    <div class="aag-tab-content" id="requirements-tab">
        <h2>Article Requirements</h2>
        <form id="aag-requirements-form">
            <table class="form-table">
                <!-- Word Count -->
                <tr>
                    <th><label for="target_word_count">Target Word Count</label></th>
                    <td>
                        <?php
                        $word_count = get_option('aag_word_count', '1500');
                        if ($is_free_tier)
                            $word_count = '1000';
                        $is_trial = $usage_stats['trial_active'] ?? true; // fallback
                        ?>
                        <select id="target_word_count" name="target_word_count">
                            <option value="1000" <?php selected($word_count, '1000'); ?>>1000 Words</option>
                            <option value="1500" <?php selected($word_count, '1500'); ?> <?php echo $is_free_tier ? 'disabled' : ''; ?>>1500 Words <?php echo $is_free_tier ? '(Upgrade)' : ''; ?></option>
                            <option value="2500" <?php selected($word_count, '2500'); ?> <?php echo (!$is_premium) ? 'disabled' : ''; ?>>2500 Words <?php echo (!$is_premium) ? '(Upgrade)' : ''; ?>
                            </option>
                            <option value="3000" <?php selected($word_count, '3000'); ?> <?php echo (!$is_premium) ? 'disabled' : ''; ?>>3000 Words <?php echo (!$is_premium) ? '(Upgrade)' : ''; ?>
                            </option>
                        </select>
                        <?php if (!$is_premium): ?>
                            <p class="description">During trial, only 1500 words option is available. <a href="#"
                                    class="aag-nav-btn" data-target="license">Upgrade to Unlock</a></p>
                        <?php endif; ?>
                    </td>
                </tr>
                <tr>
                    <th><label for="article_tone">Article Tone</label></th>
                    <td>
                        <?php
                        $article_tone = get_option('aag_article_tone', 'neutral');
                        $article_tone_auto = get_option('aag_article_tone_auto', '0');
                        ?>
                        <select id="article_tone" name="article_tone">
                            <option value="neutral" <?php selected($article_tone, 'neutral'); ?>>Neutral</option>
                            <option value="friendly" <?php selected($article_tone, 'friendly'); ?>>Friendly</option>
                            <option value="professional" <?php selected($article_tone, 'professional'); ?>>Professional
                            </option>
                            <option value="persuasive" <?php selected($article_tone, 'persuasive'); ?>>Persuasive
                            </option>
                            <option value="technical" <?php selected($article_tone, 'technical'); ?>>Technical</option>
                        </select>
                        <label style="margin-left:10px;">
                            <input type="checkbox" id="article_tone_auto" name="article_tone_auto" value="1" <?php checked($article_tone_auto, '1'); ?>>
                            Let AI decide most suitable article tone
                        </label>
                        <p class="description">Controls the tone of voice used in generated articles.</p>
                    </td>
                </tr>
                <!-- Tables -->
                <tr>
                    <th>Formatting Options</th>
                    <td>
                        <fieldset>
                            <label>
                                <input type="checkbox" name="include_table" value="1" <?php checked(get_option('aag_include_table', '1'), '1'); ?>>
                                Include Table
                            </label>
                            <br>
                            <label>
                                <input type="checkbox" name="include_lists" value="1" <?php checked(get_option('aag_include_lists', '1'), '1'); ?>>
                                Include Lists (Bulleted/Numbered)
                            </label>
                            <br>
                            <label>
                                <input type="checkbox" name="include_faq" value="1" <?php checked(get_option('aag_include_faq', '0'), '1'); ?>>
                                Include FAQ Section
                            </label>
                        </fieldset>
                    </td>
                </tr>
                <!-- API Keys -->
                <tr>
                    <th><label for="gemini_api_key">Gemini API Key <span class="aag-tooltip-container"><span
                                    class="aag-help-icon dashicons dashicons-editor-help"></span><span
                                    class="aag-tooltip-text">Enter your
                                    Google Gemini API key here to enable content
                                    generation.</span></span></label></th>
                    <td>
                        <input type="text" id="gemini_api_key" name="gemini_api_key"
                            value="<?php echo esc_attr($gemini_api_key); ?>" class="regular-text">
                        <p class="description">Get your API key from <a href="https://makersuite.google.com/app/apikey"
                                target="_blank">Google AI Studio</a></p>
                    </td>
                </tr>
                <tr>
                    <th><label for="freepik_api_key">Freepik API Key (Optional) <span
                                class="aag-tooltip-container"><span
                                    class="aag-help-icon dashicons dashicons-editor-help"></span><span
                                    class="aag-tooltip-text">Enter your Freepik API key if you want to automatically add
                                    featured images.</span></span></label></th>
                    <td>
                        <input type="text" id="freepik_api_key" name="freepik_api_key"
                            value="<?php echo esc_attr($freepik_api_key); ?>" class="regular-text">
                        <button type="button" id="aag-test-freepik-btn" class="button">Test Connection</button>
                        <span id="freepik-test-result"></span>
                        <p class="description">Get your API key from <a href="https://www.freepik.com/api"
                                target="_blank">Freepik API</a> (Leave empty to skip images)</p>
                    </td>
                </tr>
            </table>
            <p class="submit">
                <button type="submit" id="requirements_submit_btn" class="button button-primary">Save
                    Requirements</button>
            </p>
        </form>
    </div>

    <!-- Internal Linking Tab -->
    <div class="aag-tab-content" id="linking-tab">
        <div class="aag-card">
            <h2>AI Internal Link Builder</h2>
            <p>This feature helps you maintain a healthy internal link structure by suggesting relevant posts from your
                site as you write new content.</p>

            <div class="aag-feature-box">
                <div class="feature-icon"><span class="dashicons dashicons-admin-links"></span></div>
                <div class="feature-text">
                    <h3>How it works</h3>
                    <ul>
                        <li>Open any post in the WordPress editor.</li>
                        <li>Look for the <strong>RankReady AI Linking</strong> sidebar icon on the top right.</li>
                        <li>Click "Get Relevant Links" to see suggestions based on your current text.</li>
                        <li>Easily insert suggested links into your content with one click.</li>
                    </ul>
                </div>
            </div>

            <div class="notice notice-info inline" style="margin-top: 20px;">
                <p>This feature is automatically enabled for all posts. You just need to have your Gemini API key
                    configured in the <strong>Article Requirements</strong> tab.</p>
            </div>
        </div>
    </div>

    <!-- Article Status Tab -->
    <div class="aag-tab-content" id="queue-tab">
        <h2>Article Status</h2>
        <div class="aag-queue-controls">
            <button id="process-queue-btn" class="button button-primary">Process Next Article</button>
            <button id="refresh-queue-btn" class="button">Refresh Queue</button>
            <button id="clear-queue-btn" class="button button-link-delete">Clear All Queue</button>
            <span style="margin-left: 20px;">
                Pending: <strong id="pending-count"><?php echo esc_html($pending_count); ?></strong>
            </span>
        </div>

        <table class="wp-list-table widefat fixed striped" id="queue-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Title</th>
                    <th>Keyword</th>
                    <th>Keywords to Include</th>
                    <th>Status</th>
                    <th>Created</th>
                    <th>Post Link</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($queue_items)): ?>
                    <tr>
                    <tr>
                        <td colspan="8" style="text-align:center;">No items in queue</td>
                    </tr>
                    </tr>
                <?php else: ?>
                    <?php foreach ($queue_items as $item): ?>
                        <tr>
                            <td><?php echo esc_html($item->id); ?></td>
                            <td><?php echo esc_html($item->title); ?></td>
                            <td><?php echo esc_html($item->keyword ?: '-'); ?></td>
                            <td><?php echo esc_html($item->keywords_to_include ?: '-'); ?></td>
                            <td><span
                                    class="aag-status-<?php echo esc_attr($item->status); ?>"><?php echo esc_html(ucfirst($item->status)); ?></span>
                            </td>
                            <td>
                                <?php
                                if ($item->status === 'pending' && !empty($item->scheduled_at)) {
                                    echo esc_html(date_i18n('Y-m-d H:i', strtotime($item->scheduled_at))) . ' · ' . esc_html(intval($articles_per_run)) . ' per run';
                                } else {
                                    echo $item->created_at ? esc_html(date_i18n('Y-m-d H:i', strtotime($item->created_at))) : '-';
                                }
                                ?>
                            </td>
                            <td>
                                <?php if ($item->post_id): ?>
                                    <a href="<?php echo get_edit_post_link($item->post_id); ?>" target="_blank">Edit Post</a>
                                <?php else: ?>
                                    -
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($item->status === 'pending'): ?>
                                    <button class="button button-small aag-delete-item-btn"
                                        data-id="<?php echo esc_attr($item->id); ?>" title="Delete Article">
                                        <span class="dashicons dashicons-trash" style="line-height: 1.3;"></span>
                                    </button>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <div id="aag-message" class="notice" style="display:none;"></div>
</div>

<!-- License Modal -->
<div class="aag-modal-overlay" id="aag-license-modal">
    <div class="aag-modal-container">
        <div class="aag-modal-close" id="aag-modal-close-btn">
            <span class="dashicons dashicons-no-alt"></span>
        </div>
        <div class="aag-modal-content">
            <h2>License & Upgrade</h2>

            <?php if ($is_premium): ?>
                <div class="aag-premium-box active">
                    <h3>✅ Premium Active</h3>
                    <p>Thank you for supporting RankReady AI! You have access to all features.</p>
                    <hr>
                    <p>License Key:
                        <code>
                                    <?php
                                    if (!empty($license_key) && strlen($license_key) > 8) {
                                        $masked_key = substr($license_key, 0, 4) . str_repeat('X', strlen($license_key) - 8) . substr($license_key, -4);
                                        echo esc_html($masked_key);
                                    } else {
                                        echo '********';
                                    }
                                    ?>
                                </code>
                    </p>
                    <p class="description" style="margin-top: 5px;">
                        This license key was sent to your email ID. <br>
                        To retrieve a lost license key, please send an email from your registered email ID to support.
                    </p>
                    <p style="margin-top: 20px;">
                        <button id="deactivate-license-btn" class="button button-secondary">Deactivate License</button>
                    </p>
                </div>
            <?php else: ?>
                <div class="aag-pricing-container">
                    <div class="aag-pricing-box" style="box-shadow: none; border: none; padding: 0;">
                        <h3>Upgrade to Premium</h3>
                        <ul>
                            <li>Unlimited Article Generation</li>
                            <li>Remove Daily Limits</li>
                            <li>Longer Articles (2500+ words)</li>
                            <li>Priority Support</li>
                        </ul>
                        <div class="aag-price-tag highlighted">$29 / one-time</div>

                        <!-- Payment Options Grid -->
                        <div class="aag-payment-grid">
                            <!-- PayPal Button -->
                            <div class="aag-payment-method">
                                <a href="https://www.paypal.com/cgi-bin/webscr?cmd=_xclick&business=product.utube@gmail.com&item_name=Auto+SEO+Article+Generator+Premium&amount=29.00&currency_code=USD"
                                    target="_blank" class="button button-primary button-hero aag-btn-paypal">
                                    <span class="aag-btn-icon">
                                        <img src="<?php echo esc_url(AAG_PLUGIN_URL . 'assets/icons/paypal.svg'); ?>"
                                            width="20" height="20" alt="PayPal">
                                    </span>
                                    PayPal Checkout
                                </a>
                            </div>

                            <!-- UPI Button (Mobile Only) / QR (Desktop) -->
                            <div class="aag-payment-method upi-method">
                                <a href="upi://pay?pa=sushilmohan98-1@okhdfcbank&pn=Sushil%20Mohan&am=2400&cu=INR&tn=Auto%20Article%20Generator"
                                    class="button button-secondary button-hero upi-pay-btn aag-btn-gpay">
                                    <span class="aag-btn-icon">
                                        <img src="<?php echo esc_url(AAG_PLUGIN_URL . 'assets/icons/gpay.svg'); ?>"
                                            width="20" height="20" alt="Google Pay">
                                    </span>
                                    Pay via Google Pay
                                </a>
                                <div class="upi-qr-code">
                                    <img src="<?php echo esc_url('https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=upi://pay?pa=sushilmohan98-1@okhdfcbank%26pn=Sushil%20Mohan%26am=2400%26cu=INR%26tn=Auto%20Article%20Generator'); ?>"
                                        alt="UPI QR Code">
                                </div>
                                <p class="description">Indian users (GPay, Paytm, etc.)</p>
                            </div>
                        </div>

                        <div class="aag-upi-claim-section" style="margin-top: 30px; display: none;"
                            id="upi-claim-form-container">
                            <h4>Step 2: Submit your Transaction Details</h4>
                            <p>After paying, enter your Transaction ID (UTR) below to receive your key.</p>
                            <form id="aag-upi-claim-form">
                                <div style="display:grid; gap:10px; max-width:400px;">
                                    <input type="text" name="upi_email" placeholder="Your Email Address" required>
                                    <input type="text" name="upi_utr" placeholder="Transaction ID / UTR (12 digits)"
                                        required>
                                    <button type="submit" class="button button-primary">Submit for Verification</button>
                                </div>
                            </form>
                        </div>

                        <p style="margin-top: 20px; text-align: center;">
                            <a href="#" id="toggle-upi-claim">Already paid via UPI? Submit Transaction ID</a>
                        </p>

                        <p style="text-align: center; color: #666; font-size: 13px; margin-top: 15px;">
                            Need Help? Contact support: <strong>product.utube@gmail.com</strong>
                        </p>

                        <hr style="margin: 30px 0;">

                        <h4>Already purchased? Activate License</h4>
                        <p>Enter the license key you received via email.</p>
                        <form id="aag-license-form">
                            <div style="display:flex; gap:10px; max-width:400px;">
                                <input type="text" id="license_key" name="license_key" class="regular-text"
                                    placeholder="Enter License Key (e.g. PREMIUM-XXX)" required>
                                <button type="submit" class="button button-primary">Activate</button>
                            </div>
                        </form>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>