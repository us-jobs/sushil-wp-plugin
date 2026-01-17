<?php
/**
 * Plugin Name: Auto SEO Article Generator
 * Plugin URI: https://example.com/auto-article-generator
 * Description: Automatically generate articles using Gemini AI with featured images from Freepik
 * Version: 1.0.0
 * Author: Your Name
 * License: GPL v2 or later
 * Text Domain: auto-article-generator
 */

if (!defined('ABSPATH')) {
    exit;
}

class Auto_Article_Generator
{
    private $table_name;
    private $usage_table;

    public function __construct()
    {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'aag_queue';
        $this->usage_table = $wpdb->prefix . 'aag_usage';

        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
        add_action('wp_ajax_aag_save_settings', array($this, 'save_settings'));
        add_action('wp_ajax_aag_save_method1', array($this, 'save_method1'));
        add_action('wp_ajax_aag_save_method2', array($this, 'save_method2'));
        add_action('wp_ajax_aag_save_requirements', array($this, 'save_requirements'));
        add_action('wp_ajax_aag_process_queue', array($this, 'process_queue_item'));
        add_action('wp_ajax_aag_get_queue_status', array($this, 'get_queue_status'));
        add_action('wp_ajax_aag_clear_queue', array($this, 'clear_queue'));
        add_action('aag_scheduled_generation', array($this, 'run_scheduled_generation'));
        add_filter('cron_schedules', array($this, 'add_custom_intervals'));

        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));

        // Initialize schedule on plugin load
        add_action('init', array($this, 'maybe_reschedule'));
    }

    public function maybe_reschedule()
    {
        if (!wp_next_scheduled('aag_scheduled_generation')) {
            $this->update_schedule();
        }
    }

    public function process_queue_item()
    {
        check_ajax_referer('aag_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }

        $result = $this->process_queue_item_internal();

        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }

        wp_send_json_success($result);
    }

    private function process_queue_item_internal()
    {
        // Check trial status and daily limit
        if (!$this->is_trial_active()) {
            return new WP_Error('trial_expired', 'Your trial has expired. Please upgrade to continue.');
        }

        /*
        if (!$this->check_daily_limit()) {
            return new WP_Error('limit_reached', 'Daily limit reached (2 articles per day during trial).');
        }
        */

        global $wpdb;

        // Get next pending item
        $item = $wpdb->get_row("SELECT * FROM {$this->table_name} WHERE status = 'pending' ORDER BY created_at ASC LIMIT 1");

        if (!$item) {
            return new WP_Error('empty_queue', 'No pending articles in queue');
        }

        // Update status to processing
        $wpdb->update($this->table_name, array('status' => 'processing'), array('id' => $item->id));

        $gemini_api_key = get_option('aag_gemini_api_key');
        $freepik_api_key = get_option('aag_freepik_api_key');
        $post_status = get_option('aag_post_status', 'draft');

        if (empty($gemini_api_key)) {
            $wpdb->update($this->table_name, array(
                'status' => 'failed',
                'error_message' => 'Gemini API key not configured',
                'processed_at' => current_time('mysql')
            ), array('id' => $item->id));

            return new WP_Error('missing_key', 'Gemini API key not configured. Please add it in Settings.');
        }

        // Generate article content
        $prompt = "Write a comprehensive, SEO-optimized blog article with the title: '{$item->title}'.";

        if (!empty($item->keywords_to_include)) {
            $prompt .= " Make sure to naturally incorporate these keywords throughout the article: {$item->keywords_to_include}.";
        }

        // Get Requirements
        $word_count = get_option('aag_word_count', '1500');
        // Enforce trial limit
        if ($this->is_trial_active() && $word_count > 1500) {
            $word_count = '1500';
        }

        $include_table = get_option('aag_include_table', '1');
        $include_lists = get_option('aag_include_lists', '1');
        $include_faq = get_option('aag_include_faq', '0');
        $article_tone = get_option('aag_article_tone', 'neutral');
        $article_tone_auto = get_option('aag_article_tone_auto', '0');

        $prompt .= " Include an introduction, multiple detailed sections with subheadings, and a conclusion.";
        $prompt .= " The article should be approximately {$word_count} words in length.";

        if ($include_table === '1') {
            $prompt .= " Include a relevant data table where appropriate.";
        }

        if ($include_lists === '1') {
            $prompt .= " Use bulleted or numbered lists to organize information.";
        }

        if ($include_faq === '1') {
            $prompt .= " Add a Frequently Asked Questions (FAQ) section at the end.";
        }

        if ($article_tone_auto === '1') {
            $prompt .= " Choose the most suitable tone (neutral, friendly, professional, persuasive, or technical) based on the topic and target reader, and keep the tone consistent throughout.";
        } else {
            if ($article_tone === 'friendly') {
                $prompt .= " Use a friendly, conversational tone.";
            } elseif ($article_tone === 'professional') {
                $prompt .= " Use a professional, authoritative tone.";
            } elseif ($article_tone === 'persuasive') {
                $prompt .= " Use a persuasive, conversion-focused tone.";
            } elseif ($article_tone === 'technical') {
                $prompt .= " Use a technical, detail-oriented tone.";
            } else {
                $prompt .= " Use a clear, neutral tone.";
            }
        }

        $prompt .= " Speak directly to the reader using \"you\" language throughout the article.";
        $prompt .= " Make it informative and engaging. Use proper HTML formatting with <h2>, <h3>, <p>, <ul>, <li>, <table>, and <strong> tags where appropriate.";

        $content = $this->call_gemini_api($prompt, $gemini_api_key, 500);

        // Strip markdown code blocks if present
        $content = preg_replace('/^```html\s*/i', '', $content);
        $content = preg_replace('/^```\s*/', '', $content);
        $content = preg_replace('/```$/', '', $content);

        if (is_wp_error($content)) {
            $wpdb->update($this->table_name, array(
                'status' => 'failed',
                'error_message' => $content->get_error_message(),
                'processed_at' => current_time('mysql')
            ), array('id' => $item->id));

            return $content;
        }

        // Create post
        $post_data = array(
            'post_title' => $item->title,
            'post_content' => $content,
            'post_status' => $post_status,
            'post_type' => 'post'
        );

        $post_id = wp_insert_post($post_data);

        if (is_wp_error($post_id) || $post_id === 0) {
            $error_msg = is_wp_error($post_id) ? $post_id->get_error_message() : 'Failed to create post (returned 0)';
            $wpdb->update($this->table_name, array(
                'status' => 'failed',
                'error_message' => $error_msg,
                'processed_at' => current_time('mysql')
            ), array('id' => $item->id));

            return new WP_Error('post_error', 'Failed to create WordPress post: ' . $error_msg);
        }

        // Get and set featured image from Freepik (if API key provided)
        if (!empty($freepik_api_key)) {
            $search_query = $item->keyword ?: $item->title;
            $image_id = $this->get_freepik_image($search_query, $freepik_api_key);

            if (!is_wp_error($image_id) && $image_id) {
                set_post_thumbnail($post_id, $image_id);
            }
        }

        // Update queue item
        $wpdb->update($this->table_name, array(
            'status' => 'completed',
            'post_id' => $post_id,
            'processed_at' => current_time('mysql')
        ), array('id' => $item->id));

        // Track usage
        $this->track_usage();

        return array(
            'message' => 'Article created successfully!',
            'post_id' => $post_id,
            'edit_link' => get_edit_post_link($post_id, '')
        );
    }

    public function run_scheduled_generation()
    {
        // Auto-populate queue if needed based on selected method
        $this->populate_queue_if_needed();

        $articles_per_run = get_option('aag_articles_per_run', 1);

        for ($i = 0; $i < $articles_per_run; $i++) {
            $result = $this->process_queue_item_internal();

            // Respect trial limits
            if (!$this->is_trial_active() /* || !$this->check_daily_limit() */) {
                break;
            }

            // Small pause between multiple generations to avoid API overwhelming
            if ($i < $articles_per_run - 1) {
                sleep(2);
            }
        }
    }

    public function activate()
    {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();

        // Queue table
        $sql1 = "CREATE TABLE IF NOT EXISTS {$this->table_name} (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            title varchar(255) NOT NULL,
            keyword varchar(255) DEFAULT NULL,
            keywords_to_include text DEFAULT NULL,
            status varchar(20) DEFAULT 'pending',
            post_id bigint(20) DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            processed_at datetime DEFAULT NULL,
            error_message text DEFAULT NULL,
            PRIMARY KEY (id)
        ) $charset_collate;";

        // Usage tracking table
        $sql2 = "CREATE TABLE IF NOT EXISTS {$this->usage_table} (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            date date NOT NULL,
            articles_generated int(11) DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY date (date)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql1);
        dbDelta($sql2);

        // Set trial start date
        if (!get_option('aag_trial_start_date')) {
            update_option('aag_trial_start_date', current_time('mysql'));
        }

        // Initialize default options
        if (!get_option('aag_gen_method')) {
            update_option('aag_gen_method', 'method1');
        }
        if (!get_option('aag_schedule_frequency')) {
            update_option('aag_schedule_frequency', 'daily');
        }
        if (!get_option('aag_schedule_time')) {
            update_option('aag_schedule_time', '10:00 AM');
        }
        if (!get_option('aag_schedule_timezone')) {
            update_option('aag_schedule_timezone', wp_timezone_string());
        }
    }

    public function deactivate()
    {
        wp_clear_scheduled_hook('aag_scheduled_generation');
    }

    // Check trial status (7 days, 2 articles per day)
    private function is_trial_active()
    {
        $trial_start = get_option('aag_trial_start_date');
        if (!$trial_start) {
            return true; // Assume trial is active if not set
        }

        $trial_end = strtotime($trial_start . ' +7 days');
        return time() < $trial_end;
    }

    private function get_trial_days_left()
    {
        $trial_start = get_option('aag_trial_start_date');
        if (!$trial_start) {
            return 7; // Default trial period
        }

        $trial_end = strtotime($trial_start . ' +7 days');
        $days_left = ceil(($trial_end - time()) / 86400);
        return max(0, $days_left);
    }

    // Check daily limit for trial (2 articles per day)
    private function check_daily_limit()
    {
        global $wpdb;

        $today = current_time('Y-m-d');
        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT articles_generated FROM {$this->usage_table} WHERE date = %s",
            $today
        ));

        return ($count === null || $count < 2);
    }

    // Track article generation
    private function track_usage()
    {
        global $wpdb;

        $today = current_time('Y-m-d');

        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$this->usage_table} WHERE date = %s",
            $today
        ));

        if ($exists) {
            $wpdb->query($wpdb->prepare(
                "UPDATE {$this->usage_table} SET articles_generated = articles_generated + 1 WHERE date = %s",
                $today
            ));
        } else {
            $wpdb->insert($this->usage_table, array(
                'date' => $today,
                'articles_generated' => 1
            ));
        }
    }

    // Get usage stats
    private function get_usage_stats()
    {
        global $wpdb;

        $stats = array(
            'today' => 0,
            'month' => 0,
            'limit' => '2 per day (Trial)',
            'remaining' => 0,
            'trial_days_left' => 0
        );

        // Today's usage
        $today = current_time('Y-m-d');
        $today_count = $wpdb->get_var($wpdb->prepare(
            "SELECT articles_generated FROM {$this->usage_table} WHERE date = %s",
            $today
        ));

        if (!is_null($today_count)) {
            $stats['today'] = intval($today_count);
        }

        // Monthly usage
        $first_day = date('Y-m-01');
        $month_count = $wpdb->get_var($wpdb->prepare(
            "SELECT SUM(articles_generated) FROM {$this->usage_table} WHERE date >= %s",
            $first_day
        ));
        $stats['month'] = $month_count ?: 0;

        // Remaining for today
        $stats['remaining'] = max(0, 2 - $stats['today']);

        // Trial days left
        $stats['trial_days_left'] = $this->get_trial_days_left();

        return $stats;
    }

    public function add_admin_menu()
    {
        add_menu_page(
            'Auto SEO Article Generator',
            'Article Generator',
            'manage_options',
            'auto-article-generator',
            array($this, 'admin_page'),
            'dashicons-edit-large',
            30
        );
    }

    public function enqueue_admin_assets($hook)
    {
        if (strpos($hook, 'auto-article-generator') === false) {
            return;
        }

        wp_enqueue_style('aag-admin-style', plugin_dir_url(__FILE__) . 'admin-style.css', array(), '1.0.0');
        wp_enqueue_script('aag-admin-script', plugin_dir_url(__FILE__) . 'admin-script.js', array('jquery'), time(), true);

        wp_localize_script('aag-admin-script', 'aagAjax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('aag_nonce')
        ));
    }

    public function admin_page()
    {
        $gemini_api_key = get_option('aag_gemini_api_key', '');
        $freepik_api_key = get_option('aag_freepik_api_key', '');
        $schedule_interval = get_option('aag_schedule_interval', '60');
        $post_status = get_option('aag_post_status', 'draft');
        $schedule_enabled = get_option('aag_schedule_enabled', '0');
        $article_tone = get_option('aag_article_tone', 'neutral');

        global $wpdb;
        $articles_per_run = get_option('aag_articles_per_run', 1);
        $queue_items = $this->get_queue_items_with_schedule();
        $pending_count = $wpdb->get_var("SELECT COUNT(*) FROM {$this->table_name} WHERE status = 'pending'");

        $usage_stats = $this->get_usage_stats();
        $trial_active = $this->is_trial_active();
        $trial_days_left = $this->get_trial_days_left();
        ?>
        <div class="wrap aag-container">
            <h1>Auto SEO Article Generator</h1>

            <!-- Trial Status Banner -->
            <div class="aag-license-banner">
                <?php if ($trial_active): ?>
                    <div class="notice notice-info">
                        <p><strong>🎉 Free Trial Active!</strong> You have <?php echo $trial_days_left; ?> days left.
                            Articles today: <?php echo $usage_stats['today']; ?>/2</p>
                    </div>
                <?php else: ?>
                    <div class="notice notice-warning">
                        <p><strong>⚠️ Trial Expired</strong> - Your 7-day trial has ended. Upgrade to continue generating articles.
                        </p>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Usage Stats -->
            <div class="aag-usage-stats">
                <div class="stat-box">
                    <h3><?php echo $usage_stats['today']; ?></h3>
                    <p>Today</p>
                </div>
                <div class="stat-box">
                    <h3><?php echo $usage_stats['month']; ?></h3>
                    <p>This Month</p>
                </div>
                <div class="stat-box">
                    <h3><?php echo $usage_stats['remaining']; ?></h3>
                    <p>Remaining Today</p>
                </div>
                <div class="stat-box">
                    <h3><?php echo $pending_count; ?></h3>
                    <p>In Queue</p>
                </div>
            </div>

            <div class="aag-tabs">
                <button class="aag-tab-btn active" data-tab="settings">Schedule Settings</button>
                <button class="aag-tab-btn" data-tab="requirements">Article Requirements</button>
                <button class="aag-tab-btn" data-tab="method1">Method 1: Title List</button>
                <button class="aag-tab-btn" data-tab="method2">Method 2: Keyword Based</button>
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
                                ?>
                                <select id="schedule_frequency" name="schedule_frequency">
                                    <option value="daily" <?php selected($frequency, 'daily'); ?>>Every Day</option>
                                    <option value="weekly" <?php selected($frequency, 'weekly'); ?>>Every Week</option>
                                    <option value="monthly" <?php selected($frequency, 'monthly'); ?>>Every Month</option>
                                    <option value="custom" <?php selected($frequency, 'custom'); ?>>Custom Interval (Minutes)
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
                                $is_trial = $this->is_trial_active();
                                ?>
                                <select id="target_word_count" name="target_word_count">
                                    <option value="1500" <?php selected($word_count, '1500'); ?>>1500 Words</option>
                                    <option value="2500" <?php selected($word_count, '2500'); ?>         <?php echo $is_trial ? 'disabled' : ''; ?>>2500 Words <?php echo $is_trial ? '(Pro Only)' : ''; ?></option>
                                    <option value="3000" <?php selected($word_count, '3000'); ?>         <?php echo $is_trial ? 'disabled' : ''; ?>>3000 Words <?php echo $is_trial ? '(Pro Only)' : ''; ?></option>
                                </select>
                                <?php if ($is_trial): ?>
                                    <p class="description">During trial, only 1500 words option is available.</p>
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

            <!-- Article Status Tab -->
            <div class="aag-tab-content" id="queue-tab">
                <h2>Article Status</h2>
                <div class="aag-queue-controls">
                    <button id="process-queue-btn" class="button button-primary">Process Next Article</button>
                    <button id="refresh-queue-btn" class="button">Refresh Queue</button>
                    <button id="clear-queue-btn" class="button button-link-delete">Clear All Queue</button>
                    <span style="margin-left: 20px;">
                        Pending: <strong id="pending-count"><?php echo $pending_count; ?></strong>
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
                                    <td><?php echo $item->id; ?></td>
                                    <td><?php echo esc_html($item->title); ?></td>
                                    <td><?php echo esc_html($item->keyword ?: '-'); ?></td>
                                    <td><?php echo esc_html($item->keywords_to_include ?: '-'); ?></td>
                                    <td><span
                                            class="aag-status-<?php echo $item->status; ?>"><?php echo ucfirst($item->status); ?></span>
                                    </td>
                                    <td>
                                        <?php
                                        if ($item->status === 'pending' && !empty($item->scheduled_at)) {
                                            echo date_i18n('Y-m-d H:i', strtotime($item->scheduled_at)) . ' · ' . intval($articles_per_run) . ' per run';
                                        } else {
                                            echo $item->created_at ? date_i18n('Y-m-d H:i', strtotime($item->created_at)) : '-';
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
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <div id="aag-message" class="notice" style="display:none;"></div>
        </div>
        <?php
    }

    public function save_settings()
    {
        // Log the request
        error_log('AAG: save_settings called');
        error_log('AAG: POST data: ' . print_r($_POST, true));

        // Check if nonce exists
        if (!isset($_POST['nonce'])) {
            error_log('AAG: No nonce provided');
            wp_send_json_error('Security check failed: No nonce provided');
        }

        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'aag_nonce')) {
            error_log('AAG: Nonce verification failed');
            wp_send_json_error('Security check failed: Invalid nonce');
        }

        // Check user capability
        if (!current_user_can('manage_options')) {
            error_log('AAG: User does not have manage_options capability');
            wp_send_json_error('Unauthorized');
        }

        // Save all settings first
        update_option('aag_post_status', sanitize_text_field($_POST['post_status']));

        $frequency = sanitize_text_field($_POST['schedule_frequency']);
        update_option('aag_schedule_frequency', $frequency);

        $articles_per_run = intval($_POST['articles_per_run']);
        update_option('aag_articles_per_run', $articles_per_run);

        $hour = sanitize_text_field($_POST['schedule_hour']);
        $minute = sanitize_text_field($_POST['schedule_minute']);
        $ampm = sanitize_text_field($_POST['schedule_ampm']);
        $time_string = "$hour:$minute $ampm";
        update_option('aag_schedule_time', $time_string);

        $timezone = sanitize_text_field($_POST['schedule_timezone']);
        update_option('aag_schedule_timezone', $timezone);

        $auto_timezone = isset($_POST['aag_auto_timezone']) ? '1' : '0';
        update_option('aag_auto_timezone', $auto_timezone);

        if ($frequency === 'custom') {
            $interval = intval($_POST['schedule_interval']);
            if ($interval < 15)
                $interval = 15;
            update_option('aag_schedule_interval', $interval);
        }

        // Save generation method
        if (isset($_POST['gen_method'])) {
            $gen_method = sanitize_text_field($_POST['gen_method']);
            update_option('aag_gen_method', $gen_method);
        } else {
            $gen_method = get_option('aag_gen_method', 'method1');
        }

        // Update schedule
        $this->update_schedule();

        // Ensure queue is populated for Method 1 based on full title list
        if ($gen_method === 'method1') {
            $this->enqueue_method1_titles_to_queue();
        }

        // Save Method 1 Data
        if (isset($_POST['method1_titles'])) {
            update_option('aag_method1_titles', sanitize_textarea_field($_POST['method1_titles']));
        }
        if (isset($_POST['method1_keywords'])) {
            update_option('aag_method1_keywords', sanitize_text_field($_POST['method1_keywords']));
        }

        // Save Method 2 Data
        if (isset($_POST['method2_keyword'])) {
            update_option('aag_method2_keyword', sanitize_text_field($_POST['method2_keyword']));
        }

        // Save API Keys (if provided)
        if (isset($_POST['gemini_api_key'])) {
            update_option('aag_gemini_api_key', sanitize_text_field($_POST['gemini_api_key']));
        }
        if (isset($_POST['freepik_api_key'])) {
            update_option('aag_freepik_api_key', sanitize_text_field($_POST['freepik_api_key']));
        }


        // Handle Immediate Generation
        if (isset($_POST['generate_immediate']) && $_POST['generate_immediate'] == '1') {

            set_time_limit(300); // 5 minutes

            // Check trial and limits
            if (!$this->is_trial_active()) {
                wp_send_json_error('Your trial has expired. Please upgrade to continue.');
                return;
            }

            /*
            if (!$this->check_daily_limit()) {
                wp_send_json_error('Daily limit reached (2 articles per day during trial).');
                return;
            }
            */

            // Get generation method from POST data if available, or fallback to saved option
            $gen_method = isset($_POST['gen_method']) ? sanitize_text_field($_POST['gen_method']) : get_option('aag_gen_method', 'method1');

            // Ensure the option is updated for populate_queue_if_needed to use
            update_option('aag_gen_method', $gen_method);

            // Validate source data exists
            if ($gen_method === 'method1') {
                $titles = get_option('aag_method1_titles', '');
                // Check if titles are empty
                if (empty(trim($titles))) {
                    wp_send_json_error('Please add titles in Method 1 settings before generating articles.');
                    return;
                }
            } elseif ($gen_method === 'method2') {
                $keyword = get_option('aag_method2_keyword', '');
                // Check if keyword is empty for Method 2
                if (empty(trim($keyword))) {
                    wp_send_json_error('Please add a keyword in Method 2 settings before generating articles.');
                    return;
                }
            }

            // Check API key
            $api_key = get_option('aag_gemini_api_key', '');
            if (empty(trim($api_key))) {
                wp_send_json_error('Please enter your Gemini API key before generating articles.');
                return;
            }

            // Populate queue based on selected method
            if ($gen_method === 'method1') {
                $this->enqueue_method1_titles_to_queue();
            } else {
                $this->populate_queue_if_needed(true); // Force populate even if queue has items
            }

            // Check if queue has pending items
            global $wpdb;
            $queue_count = $wpdb->get_var("SELECT COUNT(*) FROM {$this->table_name} WHERE status = 'pending'");

            if ($queue_count == 0) {
                wp_send_json_error('No articles in queue. Please check your generation method settings.');
                return;
            }

            // Process one item immediately
            $result = $this->process_queue_item_internal();

            if (is_wp_error($result)) {
                wp_send_json_error($result->get_error_message());
                return;
            }

            // Success response
            wp_send_json_success(array(
                'message' => 'Settings saved! Article generated successfully!',
                'generated' => true,
                'post_id' => isset($result['post_id']) ? $result['post_id'] : null,
                'edit_link' => isset($result['edit_link']) ? $result['edit_link'] : ''
            ));
            return;
        }

        // Normal save without generation
        wp_send_json_success('Settings saved successfully!');
    }

    public function save_method1()
    {
        check_ajax_referer('aag_nonce', 'nonce');
        if (!current_user_can('manage_options'))
            wp_send_json_error('Unauthorized');

        update_option('aag_method1_titles', sanitize_textarea_field($_POST['titles']));
        update_option('aag_method1_keywords', sanitize_text_field($_POST['keywords']));

        $added = $this->enqueue_method1_titles_to_queue();

        if ($added > 0) {
            wp_send_json_success('Method 1 (Title List) saved successfully! ' . $added . ' new titles added to the queue.');
        }

        wp_send_json_success('Method 1 (Title List) saved successfully! No new titles were added to the queue.');
    }

    public function save_method2()
    {
        check_ajax_referer('aag_nonce', 'nonce');
        if (!current_user_can('manage_options'))
            wp_send_json_error('Unauthorized');

        update_option('aag_method2_keyword', sanitize_text_field($_POST['keyword']));

        wp_send_json_success('Method 2 (Keyword Source) saved successfully!');
    }

    public function save_requirements()
    {
        check_ajax_referer('aag_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }

        update_option('aag_gemini_api_key', sanitize_text_field($_POST['gemini_api_key']));
        update_option('aag_freepik_api_key', sanitize_text_field($_POST['freepik_api_key']));

        update_option('aag_word_count', sanitize_text_field($_POST['target_word_count']));
        update_option('aag_include_table', isset($_POST['include_table']) ? '1' : '0');
        update_option('aag_include_lists', isset($_POST['include_lists']) ? '1' : '0');
        update_option('aag_include_faq', isset($_POST['include_faq']) ? '1' : '0');
        if (isset($_POST['article_tone'])) {
            update_option('aag_article_tone', sanitize_text_field($_POST['article_tone']));
        }
        update_option('aag_article_tone_auto', isset($_POST['article_tone_auto']) ? '1' : '0');

        wp_send_json_success('Article Requirements saved successfully!');
    }

    private function aag_debug_log($message)
    {
        $log_file = plugin_dir_path(__FILE__) . 'aag_debug.log';
        $timestamp = current_time('mysql');
        $entry = "[{$timestamp}] {$message}\n";
        file_put_contents($log_file, $entry, FILE_APPEND);
    }

    private function enqueue_method1_titles_to_queue()
    {
        global $wpdb;

        $titles_raw = get_option('aag_method1_titles', '');
        $titles_array = array_filter(array_map('trim', explode("\n", $titles_raw)));

        if (empty($titles_array)) {
            $this->aag_debug_log("Method 1: No titles found for enqueue.");
            return 0;
        }

        $keywords = get_option('aag_method1_keywords', '');
        $added = 0;

        foreach ($titles_array as $title) {
            if ($title === '') {
                continue;
            }

            if (stripos($title, 'titles from this list') !== false || stripos($title, 'Enter article titles') !== false) {
                continue;
            }

            $exists = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$this->table_name} WHERE title = %s",
                $title
            ));

            if ($exists) {
                continue;
            }

            $wpdb->insert($this->table_name, array(
                'title' => $title,
                'keywords_to_include' => $keywords,
                'status' => 'pending',
                'created_at' => current_time('mysql')
            ));

            $added++;
        }

        $this->aag_debug_log("Method 1: Enqueued {$added} titles into queue.");
        return $added;
    }

    private function populate_queue_if_needed($force = false)
    {
        global $wpdb;
        $count = $wpdb->get_var("SELECT COUNT(*) FROM {$this->table_name} WHERE status = 'pending'");
        $buffer = 5; // Maintain at least 5 items in queue

        $this->aag_debug_log("Populate Queue Called. Force: " . ($force ? 'Yes' : 'No') . ", Current Count: $count");

        if (!$force && $count >= $buffer) {
            $this->aag_debug_log("Queue healthy, returning.");
            return; // Queue is healthy
        }

        $method = get_option('aag_gen_method', 'method2');
        $this->aag_debug_log("Generation Method: $method");

        if ($method === 'method1') {
            $titles_raw = get_option('aag_method1_titles', '');
            $titles_array = array_filter(array_map('trim', explode("\n", $titles_raw)));

            if (empty($titles_array)) {
                $this->aag_debug_log("Method 1: No titles found.");
                return; // No titles to add
            }

            $needed = $force ? 1 : max(1, $buffer - $count); // If forcing, just add 1
            $keywords = get_option('aag_method1_keywords', '');

            // Take top N titles
            $to_add = array_slice($titles_array, 0, $needed);

            $this->aag_debug_log("Method 1: Adding " . count($to_add) . " titles.");

            foreach ($to_add as $title) {
                if (empty($title))
                    continue;

                // Filter out placeholder text if accidentally saved
                if (stripos($title, 'titles from this list') !== false || stripos($title, 'Enter article titles') !== false) {
                    continue;
                }

                // Check if title already exists in queue
                $exists = $wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(*) FROM {$this->table_name} WHERE title = %s AND status = 'pending'",
                    $title
                ));

                if (!$exists) {
                    $wpdb->insert($this->table_name, array(
                        'title' => $title,
                        'keywords_to_include' => $keywords,
                        'status' => 'pending',
                        'created_at' => current_time('mysql')
                    ));
                }
            }

            // Update the option with remaining titles
            $remaining_titles = array_slice($titles_array, $needed);
            update_option('aag_method1_titles', implode("\n", $remaining_titles));

        } elseif ($method === 'method2') {
            $main_keyword = get_option('aag_method2_keyword', '');
            $this->aag_debug_log("Method 2: Keyword is '$main_keyword'");

            if (empty($main_keyword)) {
                $this->aag_debug_log("Method 2: Keyword empty, returning.");
                return;
            }

            // Generate titles from keyword
            $this->generate_titles_from_keyword($main_keyword, $force ? 1 : 5);
        }
    }

    private function fetch_trending_news($keyword)
    {
        $rss_url = 'https://news.google.com/rss/search?q=' . urlencode($keyword) . '&hl=en-US&gl=US&ceid=US:en';
        $this->aag_debug_log("Fetching Trending News from: $rss_url");

        $response = wp_remote_get($rss_url);

        if (is_wp_error($response)) {
            $this->aag_debug_log("Trending News Fetch Error: " . $response->get_error_message());
            return array();
        }

        $body = wp_remote_retrieve_body($response);
        if (empty($body)) {
            $this->aag_debug_log("Trending News Empty Body");
            return array();
        }

        try {
            $xml = simplexml_load_string($body);
            if ($xml === false || !isset($xml->channel->item)) {
                $this->aag_debug_log("Trending News XML Parse Error or No Items");
                return array();
            }

            $headlines = array();
            $count = 0;
            foreach ($xml->channel->item as $item) {
                if ($count >= 5)
                    break;
                $headlines[] = (string) $item->title;
                $count++;
            }

            $this->aag_debug_log("Trending News Found: " . count($headlines) . " headlines.");
            return $headlines;
        } catch (Exception $e) {
            $this->aag_debug_log("Trending News Exception: " . $e->getMessage());
            return array();
        }
    }

    private function generate_titles_from_keyword($keyword, $count = 5)
    {
        global $wpdb;
        $api_key = get_option('aag_gemini_api_key');
        if (!$api_key) {
            $this->aag_debug_log("Generate Titles: No API Key found.");
            return;
        }

        // Try to get trending news
        $trending_headlines = $this->fetch_trending_news($keyword);

        $this->aag_debug_log("Structuring prompt for $keyword");

        if (!empty($trending_headlines)) {
            $headlines_text = implode("\n- ", $trending_headlines);
            $prompt = "Here are some currently trending news headlines regarding '$keyword':\n- $headlines_text\n\nBased on these trends, generate a list of $count engaging, SEO-friendly blog post titles about '$keyword'. The titles should be catchy and relevant to current interests. Return ONLY the titles, one per line. Do not include any numbering or bullet points.";
        } else {
            $prompt = "Generate list of $count engaging, SEO-friendly blog post titles about '$keyword'. Return ONLY the titles, one per line. Do not include any numbering or bullet points.";
        }

        $this->aag_debug_log("Prompting Gemini...");

        $response = $this->call_gemini_api($prompt, $api_key, 10);
        if (is_wp_error($response)) {
            $this->aag_debug_log("Gemini API Error: " . $response->get_error_message());
            return;
        }

        $this->aag_debug_log("Gemini Response received. Length: " . strlen($response));

        $titles = array_filter(array_map('trim', explode("\n", $response)));
        $this->aag_debug_log("Parsed " . count($titles) . " titles.");

        foreach ($titles as $title) {
            // Remove numbering if present (e.g. "1. Title")
            $title = preg_replace('/^\d+\.\s*/', '', $title);
            if (empty($title))
                continue;

            // Check if title already exists in queue
            $exists = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$this->table_name} WHERE title = %s AND status = 'pending'",
                $title
            ));

            if (!$exists) {
                $wpdb->insert($this->table_name, array(
                    'title' => $title,
                    'keyword' => $keyword,
                    'status' => 'pending',
                    'created_at' => current_time('mysql')
                ));
            } else {
                $this->aag_debug_log("Title exists/skipped: $title");
            }
        }
    }

    private function update_schedule()
    {
        wp_clear_scheduled_hook('aag_scheduled_generation');

        $frequency = get_option('aag_schedule_frequency', 'daily');

        if ($frequency === 'custom') {
            $interval = get_option('aag_schedule_interval', 60);
            if ($interval < 15)
                $interval = 15;

            // Check if custom interval schedule exists, if not add it
            add_filter('cron_schedules', function ($schedules) use ($interval) {
                if (!isset($schedules["custom_{$interval}_minutes"])) {
                    $schedules["custom_{$interval}_minutes"] = array(
                        'interval' => $interval * 60,
                        'display' => sprintf(__('Every %d Minutes', 'auto-article-generator'), $interval)
                    );
                }
                return $schedules;
            });

            // Schedule the event
            if (!wp_next_scheduled('aag_scheduled_generation')) {
                wp_schedule_event(time(), "custom_{$interval}_minutes", 'aag_scheduled_generation');
            }

            return;
        }

        // For daily, weekly, monthly schedules
        $timezone_string = get_option('aag_schedule_timezone', wp_timezone_string());
        $time_string = get_option('aag_schedule_time', '10:00 AM');

        try {
            $tz = new DateTimeZone($timezone_string);
            $now = new DateTime('now', $tz);
            $next_run = new DateTime($time_string, $tz);

            // If the time has already passed today, move to next occurrence
            if ($next_run <= $now) {
                if ($frequency === 'daily') {
                    $next_run->modify('+1 day');
                } elseif ($frequency === 'weekly') {
                    $next_run->modify('+7 days');
                } elseif ($frequency === 'monthly') {
                    $next_run->modify('+1 month');
                }
            }

            // Convert to UTC timestamp for WP-Cron
            $timestamp = $next_run->getTimestamp();

            // Map frequency to WP-Cron recurrence
            $recurrence = 'daily';
            if ($frequency === 'weekly') {
                $recurrence = 'weekly';
            } elseif ($frequency === 'monthly') {
                // For monthly, we need a custom interval
                if (!wp_next_scheduled('aag_scheduled_generation')) {
                    wp_schedule_single_event($timestamp, 'aag_scheduled_generation');
                }
                return;
            }

            if (!wp_next_scheduled('aag_scheduled_generation')) {
                wp_schedule_event($timestamp, $recurrence, 'aag_scheduled_generation');
            }

        } catch (Exception $e) {
            error_log('AAG Schedule Error: ' . $e->getMessage());
        }
    }

    // Custom interval for dynamic minutes
    public function add_custom_intervals($schedules)
    {
        $frequency = get_option('aag_schedule_frequency', 'custom');
        if ($frequency === 'custom') {
            $interval = get_option('aag_schedule_interval', 60);
            if ($interval < 15)
                $interval = 15;

            $schedules['custom_minutes_' . $interval] = array(
                'interval' => $interval * 60,
                'display' => "Every $interval Minutes"
            );
        }
        return $schedules;
    }

    private function get_queue_items_with_schedule()
    {
        global $wpdb;

        $items = $wpdb->get_results("SELECT * FROM {$this->table_name} ORDER BY created_at ASC LIMIT 50");

        $schedule_enabled = get_option('aag_schedule_enabled', '0');
        if ($schedule_enabled !== '1') {
            foreach ($items as $item) {
                $item->scheduled_at = null;
            }
            return $items;
        }

        $frequency = get_option('aag_schedule_frequency', 'daily');
        $articles_per_run = max(1, intval(get_option('aag_articles_per_run', 1)));
        $timezone_string = get_option('aag_schedule_timezone', wp_timezone_string());
        $time_string = get_option('aag_schedule_time', '10:00 AM');

        try {
            $tz = new DateTimeZone($timezone_string);
            $now = new DateTime('now', $tz);

            if ($frequency === 'custom') {
                $interval_minutes = intval(get_option('aag_schedule_interval', 60));
                if ($interval_minutes < 15) {
                    $interval_minutes = 15;
                }
                $next_run = clone $now;
                $next_run->modify('+' . $interval_minutes . ' minutes');
                $interval = new DateInterval('PT' . $interval_minutes . 'M');
            } else {
                $next_run = new DateTime($time_string, $tz);
                if ($next_run <= $now) {
                    if ($frequency === 'daily') {
                        $next_run->modify('+1 day');
                    } elseif ($frequency === 'weekly') {
                        $next_run->modify('+7 days');
                    } elseif ($frequency === 'monthly') {
                        $next_run->modify('+1 month');
                    } else {
                        $next_run->modify('+1 day');
                    }
                }

                if ($frequency === 'daily') {
                    $interval = new DateInterval('P1D');
                } elseif ($frequency === 'weekly') {
                    $interval = new DateInterval('P7D');
                } elseif ($frequency === 'monthly') {
                    $interval = new DateInterval('P1M');
                } else {
                    $interval = new DateInterval('P1D');
                }
            }

            $current_run = clone $next_run;
            $slot_in_run = 0;

            foreach ($items as $item) {
                if ($item->status !== 'pending') {
                    $item->scheduled_at = null;
                    continue;
                }

                if ($slot_in_run >= $articles_per_run) {
                    $current_run->add($interval);
                    $slot_in_run = 0;
                }

                $item->scheduled_at = $current_run->format('Y-m-d H:i:s');
                $slot_in_run++;
            }
        } catch (Exception $e) {
            foreach ($items as $item) {
                $item->scheduled_at = null;
            }
        }

        return $items;
    }

    public function get_queue_status()
    {
        check_ajax_referer('aag_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }

        global $wpdb;
        $items = $this->get_queue_items_with_schedule();
        $pending_count = $wpdb->get_var("SELECT COUNT(*) FROM {$this->table_name} WHERE status = 'pending'");

        wp_send_json_success(array(
            'items' => $items,
            'pending_count' => $pending_count
        ));
    }

    public function clear_queue()
    {
        check_ajax_referer('aag_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }

        global $wpdb;
        $wpdb->query("DELETE FROM {$this->table_name}");

        wp_send_json_success('Queue cleared successfully');
    }

    private function call_gemini_api($prompt, $api_key, $min_length = 100)
    {
        // Use the stable 1.5-flash model
        $url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent?key={$api_key}";

        $body = json_encode(array(
            'contents' => array(
                array(
                    'parts' => array(
                        array('text' => $prompt)
                    )
                )
            ),
            'generationConfig' => array(
                'temperature' => 0.7,
                'topK' => 1,
                'topP' => 1,
                'maxOutputTokens' => 8192 // Increased token limit
            )
        ));

        $response = wp_remote_post($url, array(
            'headers' => array('Content-Type' => 'application/json'),
            'body' => $body,
            'timeout' => 120
        ));

        if (is_wp_error($response)) {
            return $response;
        }

        $response_body = wp_remote_retrieve_body($response);
        $result = json_decode($response_body, true);

        // Debug logging
        error_log('AAG: Gemini Response: ' . print_r($result, true));

        // Check for API errors
        if (isset($result['error'])) {
            $error_msg = isset($result['error']['message']) ? $result['error']['message'] : 'Unknown Gemini API Error';
            return new WP_Error('api_error', 'Gemini API Error: ' . $error_msg);
        }

        // Check for content candidates
        if (isset($result['candidates'][0]['content']['parts'][0]['text'])) {
            $content = $result['candidates'][0]['content']['parts'][0]['text'];

            if (!is_null($min_length)) {
                $trimmed = trim($content);
                if ($min_length > 0 && (empty($trimmed) || strlen($trimmed) < $min_length)) {
                    error_log('AAG: Gemini returned too short content: ' . $content);
                    return new WP_Error('empty_content', 'Gemini returned empty or too short content.');
                }
            }
            return $content;
        }

        // Check for safety blocking or other finish reasons
        if (isset($result['candidates'][0]['finishReason'])) {
            $reason = $result['candidates'][0]['finishReason'];
            return new WP_Error('api_block', 'Gemini API blocked content generation. Reason: ' . $reason);
        }

        return new WP_Error('api_error', 'Failed to generate content from Gemini API. Unexpected response format.');
    }

    private function get_freepik_image($search_query, $api_key)
    {
        $url = "https://api.freepik.com/v1/resources?term=" . urlencode($search_query) . "&limit=1";

        $response = wp_remote_get($url, array(
            'headers' => array(
                'Accept' => 'application/json',
                'x-freepik-api-key' => $api_key
            ),
            'timeout' => 30
        ));

        if (is_wp_error($response)) {
            return $response;
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);

        if (empty($body['data']) || !isset($body['data'][0]['image']['url'])) {
            return new WP_Error('no_image', 'No images found from Freepik');
        }

        $image_url = $body['data'][0]['image']['url'];

        // Download image to WordPress media library
        require_once(ABSPATH . 'wp-admin/includes/media.php');
        require_once(ABSPATH . 'wp-admin/includes/file.php');
        require_once(ABSPATH . 'wp-admin/includes/image.php');

        $tmp = download_url($image_url);

        if (is_wp_error($tmp)) {
            return $tmp;
        }

        $file_array = array(
            'name' => basename($image_url) . '.jpg',
            'tmp_name' => $tmp
        );

        $image_id = media_handle_sideload($file_array, 0);

        if (is_wp_error($image_id)) {
            @unlink($file_array['tmp_name']);
            return $image_id;
        }

        return $image_id;
    }
}

// Initialize the plugin
new Auto_Article_Generator();
