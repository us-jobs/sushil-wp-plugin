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
            <h3 id="aag-stats-today-count"><?php echo esc_html($usage_stats['today']); ?></h3>
            <p>Today</p>
        </div>
        <div class="stat-box">
            <h3 id="aag-stats-month-count"><?php echo esc_html($usage_stats['month']); ?></h3>
            <p>This Month</p>
        </div>
        <div class="stat-box">
            <h3 id="aag-stats-remaining-count"><?php echo esc_html($usage_stats['remaining']); ?></h3>
            <p>Remaining Today</p>
        </div>
        <div class="stat-box">
            <h3 id="aag-stats-queue-count"><?php echo esc_html($pending_count); ?></h3>
            <p>In Queue</p>
        </div>
    </div>

    <div class="aag-tabs">
        <button class="aag-tab-btn active" data-tab="settings">Schedule Settings</button>
        <button class="aag-tab-btn" data-tab="requirements">Article Requirements</button>
        <button class="aag-tab-btn" data-tab="method1">Title List</button>
        <button class="aag-tab-btn" data-tab="method2">Keyword Based</button>
        <button class="aag-tab-btn" data-tab="queue">Article Status</button>
        <button class="aag-tab-btn" data-tab="traffic">Get Traffic</button>
        <button class="aag-tab-btn" data-tab="notifications">Notifications</button>
        <button class="aag-tab-btn aag-features-tab-btn" data-tab="features">SEO</button>
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
                            <option value="method1" <?php selected(get_option('aag_gen_method'), 'method1'); ?>>Title
                                List</option>
                            <option value="method2" <?php selected(get_option('aag_gen_method'), 'method2'); ?>>Keyword
                                Based</option>
                        </select>
                        <div style="margin-top: 10px;">
                            <button type="button" class="button aag-nav-btn" data-target="method1">Go to Title List
                                Settings</button>
                            <button type="button" class="button aag-nav-btn" data-target="method2">Go to Keyword Based
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

    <div class="aag-tab-content" id="method1-tab">
        <h2>Title List Source <span
                style="font-weight: normal; font-size: 0.7em; color: #666; margin-left: 10px;">(Article Titles Source
                (one per line))</span></h2>
        <form id="aag-method1-form">
            <p>
                <textarea id="title_list" name="title_list" rows="10" class="large-text"
                    placeholder="Enter article titles, one per line..."><?php echo esc_textarea(get_option('aag_method1_titles', '')); ?></textarea>
                <span class="description">Scheduler will pick titles from this list top-to-bottom.</span>
            </p>
            <p class="submit">
                <button type="submit" class="button button-primary">Save Title List</button>
            </p>
        </form>
    </div>

    <div class="aag-tab-content" id="method2-tab">
        <h2>Keyword Based Source</h2>
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
                            <br>
                            <label>
                                <input type="checkbox" name="include_youtube" value="1" <?php checked(get_option('aag_include_youtube', '0'), '1'); ?>>
                                Include Relevant YouTube Video
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
                        <div class="aag-api-key-wrapper">
                            <input type="password" id="gemini_api_key" name="gemini_api_key"
                                value="<?php echo esc_attr($gemini_api_key); ?>" class="regular-text">
                            <span class="dashicons dashicons-visibility aag-eye-toggle"
                                data-target="gemini_api_key"></span>
                        </div>
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
                        <div class="aag-api-key-wrapper">
                            <input type="password" id="freepik_api_key" name="freepik_api_key"
                                value="<?php echo esc_attr($freepik_api_key); ?>" class="regular-text">
                            <span class="dashicons dashicons-visibility aag-eye-toggle"
                                data-target="freepik_api_key"></span>
                        </div>
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

    <!-- Notifications Tab -->
    <div class="aag-tab-content" id="notifications-tab">
        <h2>Notifications & Alerts</h2>
        <p>Configure push notifications to alert your subscribers when new articles are published.</p>

        <form id="aag-notifications-form">
            <table class="form-table">
                <!-- OneSignal Settings -->
                <tr>
                    <th colspan="2" style="padding-left: 0;">
                        <h3 style="margin-bottom: 0; display: flex; align-items: center;">
                            <span class="dashicons dashicons-megaphone"
                                style="color: #E54B4D; font-size: 1.3em; margin-right: 10px; vertical-align: middle;"></span>
                            OneSignal Settings
                        </h3>
                        <p class="description">Send push notifications via OneSignal.</p>
                    </th>
                </tr>
                <tr>
                    <th><label for="onesignal_app_id">OneSignal App ID</label></th>
                    <td>
                        <div class="aag-api-key-wrapper">
                            <input type="password" id="onesignal_app_id" name="onesignal_app_id"
                                value="<?php echo esc_attr(get_option('aag_onesignal_app_id', '')); ?>"
                                class="regular-text" placeholder="xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx">
                            <span class="dashicons dashicons-visibility aag-eye-toggle"
                                data-target="onesignal_app_id"></span>
                        </div>
                    </td>
                </tr>
                <tr>
                    <th><label for="onesignal_rest_api_key">REST API Key</label></th>
                    <td>
                        <div class="aag-api-key-wrapper">
                            <input type="password" id="onesignal_rest_api_key" name="onesignal_rest_api_key"
                                value="<?php echo esc_attr(get_option('aag_onesignal_rest_api_key', '')); ?>"
                                class="regular-text">
                            <span class="dashicons dashicons-visibility aag-eye-toggle"
                                data-target="onesignal_rest_api_key"></span>
                        </div>
                        <p class="description">Found in OneSignal Dashboard -> Settings -> Keys & IDs.</p>
                    </td>
                </tr>

                <!-- Webpushr Settings -->
                <tr>
                    <th colspan="2" style="padding-left: 0; padding-top: 30px;">
                        <h3 style="margin-bottom: 0; display: flex; align-items: center;">
                            <span class="aag-settings-icon">
                                <span class="dashicons dashicons-cloud" style="color: #007bff; font-size: 20px;"></span>
                            </span>
                            Webpushr Settings
                        </h3>
                        <p class="description">Send push notifications via Webpushr.</p>
                    </th>
                </tr>
                <tr>
                    <th><label for="webpushr_key">Webpushr Key</label></th>
                    <td>
                        <div class="aag-api-key-wrapper">
                            <input type="password" id="webpushr_key" name="webpushr_key"
                                value="<?php echo esc_attr(get_option('aag_webpushr_key', '')); ?>"
                                class="regular-text">
                            <span class="dashicons dashicons-visibility aag-eye-toggle"
                                data-target="webpushr_key"></span>
                        </div>
                    </td>
                </tr>
                <tr>
                    <th><label for="webpushr_token">Auth Token</label></th>
                    <td>
                        <div class="aag-api-key-wrapper">
                            <input type="password" id="webpushr_token" name="webpushr_token"
                                value="<?php echo esc_attr(get_option('aag_webpushr_token', '')); ?>"
                                class="regular-text">
                            <span class="dashicons dashicons-visibility aag-eye-toggle"
                                data-target="webpushr_token"></span>
                        </div>
                        <p class="description">Found in Webpushr Dashboard -> API Access.</p>
                    </td>
                </tr>
            </table>

            <p class="submit">
                <button type="submit" class="button button-primary">Save Notification Settings</button>
            </p>
        </form>
    </div>

    <!-- SEO (Features) Tab -->
    <div class="aag-tab-content" id="features-tab">
        <div class="aag-features-layout">
            <!-- Sidebar Navigation for Features -->
            <div class="aag-features-sidebar">
                <div class="aag-feature-nav-item active" data-feature="internal-link">
                    <span class="dashicons dashicons-admin-links"></span>
                    <span>Internal Link Builder</span>
                </div>
                <div class="aag-feature-nav-item" data-feature="alt-text">
                    <span class="dashicons dashicons-format-image"></span>
                    <span>AI Auto-ALT Text</span>
                </div>
                <div class="aag-feature-nav-item" data-feature="gap-analyzer">
                    <span class="dashicons dashicons-chart-area"></span>
                    <span>Content Gap Analyzer</span>
                </div>
                <div class="aag-feature-nav-item" data-feature="refresher">
                    <span class="dashicons dashicons-update"></span>
                    <span>Update OLD articles</span>
                </div>
            </div>

            <!-- Content Area for Features -->
            <div class="aag-features-main">
                <!-- Internal Link Builder -->
                <div class="aag-feature-content active" id="feature-internal-link">
                    <div class="aag-card">
                        <h2>AI Internal Link Builder</h2>
                        <p>This feature helps you maintain a healthy internal link structure by suggesting relevant
                            posts from your site as you write new content.</p>

                        <div class="aag-feature-box">
                            <div class="feature-icon"><span class="dashicons dashicons-admin-links"></span></div>
                            <div class="feature-text">
                                <h3>How it works</h3>
                                <ul>
                                    <li>Open any post in the WordPress editor.</li>
                                    <li>Look for the <strong>RankReady AI Linking</strong> sidebar icon on the top
                                        right.</li>
                                    <li>Click "Get Relevant Links" to see suggestions based on your current text.</li>
                                    <li>Easily insert suggested links into your content with one click.</li>
                                </ul>
                            </div>
                        </div>

                        <div class="notice notice-info inline" style="margin-top: 20px;">
                            <p>This feature is automatically enabled for all posts. You just need to have your Gemini
                                API key configured in the <strong>Article Requirements</strong> tab.</p>
                        </div>
                    </div>
                </div>

                <!-- AI Auto-ALT Text & Captions -->
                <div class="aag-feature-content" id="feature-alt-text">
                    <div class="aag-card">
                        <div
                            style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                            <div>
                                <h2>AI Auto-ALT Text & Captions</h2>
                                <p>Automatically generate SEO-friendly descriptive ALT text and captions for your images
                                    using AI.</p>
                            </div>
                            <button id="aag-scan-images-btn" class="button button-primary">
                                <span class="dashicons dashicons-search" style="margin-top: 4px;"></span> Scan Media
                                Library
                            </button>
                        </div>

                        <div id="aag-image-seo-container">
                            <div class="aag-placeholder-feature" id="aag-image-empty-state">
                                <div class="dashicons dashicons-format-image"
                                    style="font-size: 64px; height: 64px; width: 64px; color: #ccc;"></div>
                                <h3>No Images Scanned</h3>
                                <p>Click the button above to find images in your media library missing ALT text.</p>
                            </div>

                            <div id="aag-image-scan-results" style="display: none;">
                                <div
                                    style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                                    <h3 style="margin: 0;">Images Missing ALT Text</h3>
                                    <button id="aag-bulk-process-alt-btn" class="button">
                                        <span class="dashicons dashicons-images-alt2" style="margin-top: 4px;"></span>
                                        Process All Visible
                                    </button>
                                </div>
                                <table class="wp-list-table widefat fixed striped">
                                    <thead>
                                        <tr>
                                            <th style="width: 80px;">Image</th>
                                            <th>File Name / Title</th>
                                            <th>Status</th>
                                            <th style="width: 150px;">Action</th>
                                        </tr>
                                    </thead>
                                    <tbody id="aag-image-seo-table-body">
                                        <!-- Dynamic content -->
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Competitor Content Gap Analyzer -->
                <div class="aag-feature-content" id="feature-gap-analyzer">
                    <div class="aag-card">
                        <h2>Competitor Content Gap Analyzer</h2>
                        <p>Enter a target keyword and your competitor URLs to find missing content opportunities.</p>
                        <div
                            style="background: #f0f9ff; border-left: 4px solid #0ea5e9; padding: 10px 15px; margin-bottom: 20px; font-size: 13px; color: #0369a1;">
                            <strong>Note:</strong> Enter competitors of the site where this plugin is installed to get
                            the best results.
                        </div>

                        <div class="aag-gap-analyzer-form" style="margin-top: 20px;">
                            <div class="aag-form-group" style="margin-bottom: 15px;">
                                <label style="display: block; font-weight: 600; margin-bottom: 5px;">Target Keyword
                                    <span style="color: red;">*</span></label>
                                <input type="text" id="aag-gap-keyword" placeholder="e.g. Best SEO Plugin for WordPress"
                                    style="width: 100%; max-width: 500px;" />
                            </div>

                            <div class="aag-form-group" style="margin-bottom: 15px;">
                                <label style="display: block; font-weight: 600; margin-bottom: 5px;">Competitor URLs
                                    (Max
                                    5)</label>
                                <div id="aag-competitor-urls-container">
                                    <input type="url" class="aag-competitor-url"
                                        placeholder="https://competitor1.com/ranking-page/"
                                        style="width: 100%; max-width: 500px; margin-bottom: 5px;" />
                                    <input type="url" class="aag-competitor-url"
                                        placeholder="https://competitor2.com/ranking-page/"
                                        style="width: 100%; max-width: 500px; margin-bottom: 5px;" />
                                </div>
                                <button type="button" id="aag-add-url-btn" class="button button-small"
                                    style="margin-top: 5px;">
                                    <span class="dashicons dashicons-plus" style="margin-top: 4px;"></span> Add Another
                                    URL
                                </button>
                            </div>

                            <button type="button" id="aag-analyze-gap-btn" class="button button-primary">
                                <span class="dashicons dashicons-search" style="margin-top: 4px;"></span> Analyze
                                Content Gaps
                            </button>
                        </div>

                        <!-- Results Section -->
                        <div id="aag-gap-results"
                            style="display: none; margin-top: 30px; border-top: 1px solid #eee; padding-top: 20px;">
                            <h3 style="margin-bottom: 15px;">Missing Content Opportunities</h3>
                            <div class="aag-table-responsive">
                                <table class="wp-list-table widefat fixed striped">
                                    <thead>
                                        <tr>
                                            <th>Proposed Article Title</th>
                                            <th>Gap Identified / Reason</th>
                                            <th style="width: 100px;">Priority <span
                                                    class="dashicons dashicons-editor-help"
                                                    title="Priority is determined by AI based on search intent relevance and competitor content gaps. High priority indicates a significant opportunity to rank."
                                                    style="font-size: 16px; cursor: help; vertical-align: text-bottom;"></span>
                                            </th>
                                            <th style="width: 150px;">Action</th>
                                        </tr>
                                    </thead>
                                    <tbody id="aag-gap-results-body">
                                        <!-- Dynamic content -->
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Update OLD Articles (Content Refresher) -->
                <div class="aag-feature-content" id="feature-refresher">
                    <div class="aag-card">
                        <div
                            style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                            <div>
                                <h2>Update OLD articles</h2>
                                <p>Scan for articles generated by the plugin that are older than 30 days and get AI
                                    suggestions to keep them fresh.</p>
                            </div>
                            <button id="aag-scan-old-articles-btn" class="button button-primary">
                                <span class="dashicons dashicons-search" style="margin-top: 4px;"></span> Scan for Old
                                Articles
                            </button>
                        </div>

                        <div id="aag-refresher-container">
                            <div class="aag-placeholder-feature" id="aag-refresher-empty-state">
                                <div class="dashicons dashicons-update"
                                    style="font-size: 64px; height: 64px; width: 64px; color: #ccc;"></div>
                                <h3>No Articles Scanned</h3>
                                <p>Click the button above to find articles that might need an update.</p>
                            </div>

                            <div id="aag-refresher-results" style="display: none;">
                                <table class="wp-list-table widefat fixed striped">
                                    <thead>
                                        <tr>
                                            <th>Article Title</th>
                                            <th>Published Date</th>
                                            <th style="width: 150px;">Action</th>
                                        </tr>
                                    </thead>
                                    <tbody id="aag-refresher-table-body">
                                        <!-- Dynamic content -->
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <!-- Suggestions Modal/Area (Internal) -->
                        <div id="aag-refresher-suggestions-box"
                            style="display: none; margin-top: 30px; border-top: 2px solid #eee; padding-top: 20px;">
                            <h3 id="aag-refresher-target-title">Update Suggestions</h3>
                            <div id="aag-refresher-suggestions-content">
                                <!-- AI Suggestions will appear here -->
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!-- Get Traffic Tab -->
    <div class="aag-tab-content" id="traffic-tab">
        <h2>Get Social Traffic (Auto-Posting)</h2>
        <p>Automatically share your generated articles to Telegram and Discord as soon as they are published.</p>

        <form id="aag-traffic-form">
            <table class="form-table">
                <tr>
                    <th colspan="2" style="padding-left: 0;">
                        <h3 style="margin-bottom: 0; display: flex; align-items: center;">
                            <span class="aag-settings-icon">
                                <svg width="30px" height="30px" viewBox="0 0 32 32" fill="none"
                                    xmlns="http://www.w3.org/2000/svg">
                                    <circle cx="16" cy="16" r="14" fill="url(#paint0_linear_87_7225)" />
                                    <path
                                        d="M22.9866 10.2088C23.1112 9.40332 22.3454 8.76755 21.6292 9.082L7.36482 15.3448C6.85123 15.5703 6.8888 16.3483 7.42147 16.5179L10.3631 17.4547C10.9246 17.6335 11.5325 17.541 12.0228 17.2023L18.655 12.6203C18.855 12.4821 19.073 12.7665 18.9021 12.9426L14.1281 17.8646C13.665 18.3421 13.7569 19.1512 14.314 19.5005L19.659 22.8523C20.2585 23.2282 21.0297 22.8506 21.1418 22.1261L22.9866 10.2088Z"
                                        fill="white" />
                                    <defs>
                                        <linearGradient id="paint0_linear_87_7225" x1="16" y1="2" x2="16" y2="30"
                                            gradientUnits="userSpaceOnUse">
                                            <stop stop-color="#37BBFE" />
                                            <stop offset="1" stop-color="#007DBB" />
                                        </linearGradient>
                                    </defs>
                                </svg>
                            </span>
                            Telegram Settings
                        </h3>
                        <p class="description">Post articles to your Telegram Channel or Group.</p>
                    </th>
                </tr>
                <tr>
                    <th><label for="telegram_bot_token">Telegram Bot Token</label></th>
                    <td>
                        <div class="aag-api-key-wrapper">
                            <input type="password" id="telegram_bot_token" name="telegram_bot_token"
                                value="<?php echo esc_attr(get_option('aag_telegram_bot_token', '')); ?>"
                                class="regular-text" placeholder="123456789:ABCDefgh...">
                            <span class="dashicons dashicons-visibility aag-eye-toggle"
                                data-target="telegram_bot_token"></span>
                        </div>
                        <p class="description">Create a bot via <a href="https://t.me/botfather"
                                target="_blank">@BotFather</a> to get your token.</p>
                    </td>
                </tr>
                <tr>
                    <th><label for="telegram_chat_id">Telegram Chat ID</label></th>
                    <td>
                        <input type="text" id="telegram_chat_id" name="telegram_chat_id"
                            value="<?php echo esc_attr(get_option('aag_telegram_chat_id', '')); ?>" class="regular-text"
                            placeholder="@mychannel or -100123456789">
                        <p class="description">Username of your channel (with @) or numerical Chat ID.</p>
                    </td>
                </tr>

                <tr>
                    <th colspan="2" style="padding-left: 0; padding-top: 30px;">
                        <h3 style="margin-bottom: 0; display: flex; align-items: center;">
                            <span class="aag-settings-icon">
                                <svg width="24" height="24" viewBox="0 0 24 24" fill="none"
                                    xmlns="http://www.w3.org/2000/svg">
                                    <path
                                        d="M19.73 4.87A18.23 18.23 0 0 0 15.08 3.5a.05.05 0 0 0-.05.02c-.2.35-.42.81-.57 1.17a16.83 16.83 0 0 0-4.88 0c-.15-.36-.38-.82-.58-1.17a.05.05 0 0 0-.05-.02c-1.55.13-3.11.59-4.65 1.37a.07.07 0 0 0-.03.03C1.65 8.78.81 12.63 1.15 16.44a.07.07 0 0 0 .03.05 18.25 18.25 0 0 0 5.49 2.76.05.05 0 0 0 .06-.02c.42-.58.79-1.2 1.09-1.85a.05.05 0 0 0-.03-.07 11.97 11.97 0 0 1-1.71-.82.05.05 0 0 1-.01-.08c.14-.11.28-.22.41-.33a.05.05 0 0 1 .05-.01c3.55 1.63 7.4 1.63 10.9 0a.05.05 0 0 1 .05 0c.13.11.27.22.41.33a.05.05 0 0 1-.01.08c-.54.33-1.12.6-1.71.82a.05.05 0 0 0-.03.07c.3.65.67 1.27 1.09 1.85a.05.05 0 0 0 .06.02 18.2 18.2 0 0 0 5.49-2.76.07.07 0 0 0 .03-.05c.4-4.38-.69-8.2-2.9-11.54a.07.07 0 0 0-.03-.03zM8.53 13.91c-1.07 0-1.95-.98-1.95-2.19s.86-2.19 1.95-2.19 1.95.98 1.95 2.19-.88 2.19-1.95 2.19zm6.94 0c-1.07 0-1.95-.98-1.95-2.19s.86-2.19 1.95-2.19 1.95.98 1.95 2.19-.88 2.19-1.95 2.19z"
                                        fill="#5865F2" />
                                </svg>
                            </span>
                            Discord Settings
                        </h3>
                        <p class="description">Post articles to your Discord Server via Webhook.</p>
                    </th>
                </tr>
                <tr>
                    <th><label for="discord_webhook_url">Discord Webhook URL</label></th>
                    <td>
                        <div class="aag-api-key-wrapper">
                            <input type="password" id="discord_webhook_url" name="discord_webhook_url"
                                value="<?php echo esc_attr(get_option('aag_discord_webhook_url', '')); ?>"
                                class="large-text" placeholder="https://discord.com/api/webhooks/...">
                            <span class="dashicons dashicons-visibility aag-eye-toggle"
                                data-target="discord_webhook_url"></span>
                        </div>
                        <p class="description">Go to Channel Settings -> Integrations -> Webhooks to create one.</p>
                    </td>
                </tr>
                <tr>
                    <th colspan="2" style="padding-left: 0; padding-top: 30px;">
                        <h3 style="margin-bottom: 0; display: flex; align-items: center;">
                            <svg width="24" height="24" viewBox="0 0 100 100" fill="none"
                                xmlns="http://www.w3.org/2000/svg" style="margin-right: 10px;">
                                <circle cx="50" cy="50" r="50" fill="#FF5722" />
                                <path d="M30 50L45 65L70 35" stroke="white" stroke-width="8" stroke-linecap="round"
                                    stroke-linejoin="round" />
                            </svg>
                            Premium Sharing (FB, IG, X)
                        </h3>
                        <p class="description">Use <a href="https://www.ayrshare.com/?ref=rankready"
                                target="_blank">Ayrshare</a> to post to Facebook, Instagram, X (Twitter), LinkedIn, and
                            more without API approval headaches.</p>
                    </th>
                </tr>
                <tr>
                    <th><label for="ayrshare_api_key">Ayrshare API Key</label></th>
                    <td>
                        <div class="aag-api-key-wrapper">
                            <input type="password" id="ayrshare_api_key" name="ayrshare_api_key"
                                value="<?php echo esc_attr(get_option('aag_ayrshare_api_key', '')); ?>"
                                class="large-text" placeholder="API Key from Ayrshare Dashboard">
                            <span class="dashicons dashicons-visibility aag-eye-toggle"
                                data-target="ayrshare_api_key"></span>
                        </div>
                        <p class="description">Get your API Key from the Ayrshare Dashboard -> API Key section.</p>
                    </td>
                </tr>
            </table>

            <p class="submit">
                <button type="submit" class="button button-primary">Save Traffic Settings</button>
            </p>
        </form>
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
                    <th>Status</th>
                    <th>Created</th>
                    <th>Post Link</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($queue_items)): ?>
                    <tr>
                        <td colspan="7" style="text-align:center;">No items in queue</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($queue_items as $item): ?>
                        <tr>
                            <td><?php echo esc_html($item->id); ?></td>
                            <td><?php echo esc_html($item->title); ?></td>
                            <td><?php echo esc_html($item->keyword ?: '-'); ?></td>
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
            <h2>License & Upgrade
                <div class="aag-price-tag highlighted">$29 / one-time</div>
            </h2>

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
                            <li>✅ Unlimited Article Generation</li>
                            <li>✅ Remove Daily Limits</li>
                            <li>✅ Longer Articles (2500+ words)</li>
                            <li>✅ Priority Support</li>
                        </ul>
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
                                <div class="aag-api-key-wrapper" style="flex-grow: 1;">
                                    <input type="password" id="license_key" name="license_key" class="regular-text"
                                        style="width: 100%; max-width: none;"
                                        placeholder="Enter License Key (e.g. PREMIUM-XXX)" required>
                                    <span class="dashicons dashicons-visibility aag-eye-toggle"
                                        data-target="license_key"></span>
                                </div>
                                <button type="submit" class="button button-primary">Activate</button>
                            </div>
                        </form>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>