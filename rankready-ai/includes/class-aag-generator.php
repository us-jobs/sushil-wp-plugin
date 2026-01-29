<?php

if (!defined('ABSPATH')) {
    exit;
}

class AAG_Generator
{
    public $table_name;
    public $usage_table;
    private $license;
    private $telemetry;

    public function __construct($license, $telemetry = null)
    {
        $this->license = $license;
        $this->telemetry = $telemetry;
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'aag_queue';
        $this->usage_table = $wpdb->prefix . 'aag_usage';

        // Hooks
        add_action('wp_ajax_aag_process_queue', array($this, 'process_queue_item'));
        add_action('wp_ajax_aag_get_queue_status', array($this, 'get_queue_status'));
        add_action('wp_ajax_aag_clear_queue', array($this, 'clear_queue'));
        add_action('wp_ajax_aag_delete_queue_item', array($this, 'delete_queue_item'));
        add_action('aag_scheduled_generation', array($this, 'run_scheduled_generation'));
        add_filter('cron_schedules', array($this, 'add_custom_intervals'));

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

    public function process_queue_item_internal()
    {
        // Check limits for non-premium users
        if (!$this->license->is_premium()) {
            if ($this->is_trial_active()) {
                // Trial Active: 2 per day, max 1500 words
                if (!$this->check_daily_limit()) {
                    return new WP_Error('limit_reached', 'Daily limit reached (2 articles per day during trial).');
                }
            } else {
                // Trial Expired (Free Tier): 1 per week, max 1000 words
                if (!$this->check_weekly_limit()) {
                    return new WP_Error('limit_reached', 'Free tier limit reached (1 article per week). Upgrade to Premium for unlimited access.');
                }
            }
        }

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

        // Get Requirements & Enforce Limits
        $word_count = get_option('aag_word_count', '1500');

        if (!$this->license->is_premium()) {
            if ($this->is_trial_active()) {
                // Trial: Max 1500
                if ($word_count > 1500) {
                    $word_count = '1500';
                }
            } else {
                // Free Tier: Max 1000
                $word_count = '1000';
            }
        }

        $include_table = get_option('aag_include_table', '1');
        $include_lists = get_option('aag_include_lists', '1');
        $include_faq = get_option('aag_include_faq', '0');
        $article_tone = get_option('aag_article_tone', 'neutral');
        $article_tone_auto = get_option('aag_article_tone_auto', '0');

        $prompt .= " IMPORTANT: Do NOT include the article title as an <h1> heading at the beginning. Start directly with the introduction.";
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
        $prompt .= " IMPORTANT: Do NOT use Markdown symbols like **bold** or *italic*. Use <strong> and <em> HTML tags instead.";
        $prompt .= " IMPORTANT: Do NOT include the article title as an <h1> heading at the beginning. Start directly with the introduction.";

        $content = $this->call_gemini_api($prompt, $gemini_api_key, 500);

        // Strip markdown code blocks if present
        $content = preg_replace('/^```html\s*/i', '', $content);
        $content = preg_replace('/^```\s*/', '', $content);
        $content = preg_replace('/```$/', '', $content);

        // Safety fallback: Convert any remaining Markdown to HTML
        $content = $this->convert_markdown_to_html($content);

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
            // Use the item title as the fallback filename/title for the image
            $image_id = $this->get_freepik_image($search_query, $freepik_api_key, $item->title);

            if (!is_wp_error($image_id) && $image_id) {
                set_post_thumbnail($post_id, $image_id);

                // Proactively generate SEO metadata for the new featured image
                $this->generate_image_seo_metadata($image_id);
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
            if (!$this->license->is_premium() && !$this->is_trial_active()) {
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

        // Telemetry tracking
        if ($this->telemetry) {
            $this->telemetry->track_activation();
        }
    }

    public function deactivate()
    {
        wp_clear_scheduled_hook('aag_scheduled_generation');

        // Telemetry tracking
        if ($this->telemetry) {
            $this->telemetry->track_deactivation();
        }
    }

    // Check trial status (7 days, 2 articles per day)
    public function is_trial_active()
    {
        $trial_start = get_option('aag_trial_start_date');
        if (!$trial_start) {
            return true; // Assume trial is active if not set
        }

        $trial_end = strtotime($trial_start . ' +7 days');
        return time() < $trial_end;
    }

    public function get_trial_days_left()
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
    public function check_daily_limit()
    {
        if ($this->license->is_premium()) {
            return true;
        }

        global $wpdb;

        $today = current_time('Y-m-d');
        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT articles_generated FROM {$this->usage_table} WHERE date = %s",
            $today
        ));

        return ($count === null || $count < 2);
    }

    // Check weekly limit for free tier (1 article per week)
    public function check_weekly_limit()
    {
        if ($this->license->is_premium()) {
            return true;
        }

        global $wpdb;

        // Sum articles generated in the last 7 days
        $count = $wpdb->get_var("SELECT SUM(articles_generated) FROM {$this->usage_table} WHERE date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)");

        return ($count === null || $count < 1);
    }

    // Track article generation
    public function track_usage()
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
    public function get_usage_stats()
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

        $is_premium = $this->license->is_premium();
        $is_trial = $this->is_trial_active();

        // Remaining
        if ($is_premium) {
            $stats['remaining'] = 'Unlimited';
            $stats['limit'] = 'Unlimited';
        } elseif ($is_trial) {
            $stats['remaining'] = max(0, 2 - $stats['today']);
            $stats['limit'] = '2 per day (Trial)';
        } else {
            // Free Tier
            $weekly_usage = $wpdb->get_var("SELECT SUM(articles_generated) FROM {$this->usage_table} WHERE date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)");
            $stats['remaining'] = max(0, 1 - ($weekly_usage ?: 0));
            $stats['limit'] = '1 per week (Free)';
        }

        // Trial days left
        $stats['trial_days_left'] = $this->get_trial_days_left();
        $stats['is_premium'] = $is_premium;

        return $stats;
    }

    public static function aag_debug_log($message)
    {
        $log_file = plugin_dir_path(dirname(__FILE__)) . 'aag_debug.log';
        $timestamp = current_time('mysql');
        $entry = "[{$timestamp}] {$message}\n";
        file_put_contents($log_file, $entry, FILE_APPEND);
    }

    public function enqueue_method1_titles_to_queue($limit = 0, $consume = false)
    {
        global $wpdb;

        $titles_raw = get_option('aag_method1_titles', '');
        $titles_array = array_filter(array_map('trim', explode("\n", $titles_raw)));

        if (empty($titles_array)) {
            self::aag_debug_log("Method 1: No titles found for enqueue.");
            return 0;
        }

        $added = 0;

        $count_to_process = ($limit > 0) ? min($limit, count($titles_array)) : count($titles_array);
        $titles_to_process = array_slice($titles_array, 0, $count_to_process);

        foreach ($titles_to_process as $title) {
            if ($title === '') {
                continue;
            }

            if (stripos($title, 'titles from this list') !== false || stripos($title, 'Enter article titles') !== false) {
                continue;
            }

            $exists = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$this->table_name} WHERE title = %s AND status = 'pending'",
                $title
            ));

            if ($exists) {
                continue;
            }

            $wpdb->insert($this->table_name, array(
                'title' => $title,
                'status' => 'pending',
                'created_at' => current_time('mysql')
            ));

            $added++;
        }

        if ($consume) {
            $remaining_titles = array_slice($titles_array, $count_to_process);
            update_option('aag_method1_titles', implode("\n", $remaining_titles));
        }

        self::aag_debug_log("Method 1: Enqueued {$added} titles into queue.");
        return $added;
    }

    public function populate_queue_if_needed($force = false)
    {
        global $wpdb;
        $method = get_option('aag_gen_method', 'method2');
        $buffer = 5; // Maintain at least 5 items in queue

        // Count pending items FOR THE CURRENT METHOD
        if ($method === 'method1') {
            $count = $wpdb->get_var("SELECT COUNT(*) FROM {$this->table_name} WHERE status = 'pending' AND (keyword IS NULL OR keyword = '')");
        } else {
            $count = $wpdb->get_var("SELECT COUNT(*) FROM {$this->table_name} WHERE status = 'pending' AND (keyword IS NOT NULL AND keyword != '')");
        }

        self::aag_debug_log("Populate Queue Called. Method: $method, Force: " . ($force ? 'Yes' : 'No') . ", Current Count for Method: $count");

        if (!$force && $count >= $buffer) {
            self::aag_debug_log("Queue healthy for $method, returning.");
            return; // Queue is healthy
        }

        if ($method === 'method1') {
            $needed = $force ? 1 : ($buffer - $count);
            if ($needed > 0) {
                $this->enqueue_method1_titles_to_queue($needed, true);
            }
        } elseif ($method === 'method2') {
            $main_keyword = get_option('aag_method2_keyword', '');
            self::aag_debug_log("Method 2: Keyword is '$main_keyword'");

            if (empty($main_keyword)) {
                self::aag_debug_log("Method 2: Keyword empty, returning.");
                return;
            }

            // Generate titles from keyword
            $this->generate_titles_from_keyword($main_keyword, $force ? 1 : 5);
        }
    }

    private function fetch_trending_news($keyword)
    {
        $rss_url = 'https://news.google.com/rss/search?q=' . urlencode($keyword) . '&hl=en-US&gl=US&ceid=US:en';
        self::aag_debug_log("Fetching Trending News from: $rss_url");

        $response = wp_remote_get($rss_url);

        if (is_wp_error($response)) {
            self::aag_debug_log("Trending News Fetch Error: " . $response->get_error_message());
            return array();
        }

        $body = wp_remote_retrieve_body($response);
        if (empty($body)) {
            self::aag_debug_log("Trending News Empty Body");
            return array();
        }

        try {
            $xml = simplexml_load_string($body);
            if ($xml === false || !isset($xml->channel->item)) {
                self::aag_debug_log("Trending News XML Parse Error or No Items");
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

            self::aag_debug_log("Trending News Found: " . count($headlines) . " headlines.");
            return $headlines;
        } catch (Exception $e) {
            self::aag_debug_log("Trending News Exception: " . $e->getMessage());
            return array();
        }
    }

    private function generate_titles_from_keyword($keyword, $count = 5)
    {
        global $wpdb;
        $api_key = get_option('aag_gemini_api_key');
        if (!$api_key) {
            self::aag_debug_log("Generate Titles: No API Key found.");
            return;
        }

        // Try to get trending news
        $trending_headlines = $this->fetch_trending_news($keyword);

        self::aag_debug_log("Structuring prompt for $keyword");

        if (!empty($trending_headlines)) {
            $headlines_text = implode("\n- ", $trending_headlines);
            $prompt = "Here are some currently trending news headlines regarding '$keyword':\n- $headlines_text\n\nBased on these trends, generate a list of $count engaging, SEO-friendly blog post titles about '$keyword'. The titles should be catchy and relevant to current interests. Return ONLY the titles, one per line. Do not include any numbering or bullet points.";
        } else {
            $prompt = "Generate list of $count engaging, SEO-friendly blog post titles about '$keyword'. Return ONLY the titles, one per line. Do not include any numbering or bullet points.";
        }

        self::aag_debug_log("Prompting Gemini...");

        $response = $this->call_gemini_api($prompt, $api_key, 10);
        if (is_wp_error($response)) {
            self::aag_debug_log("Gemini API Error: " . $response->get_error_message());
            return;
        }

        self::aag_debug_log("Gemini Response received. Length: " . strlen($response));

        $titles = array_filter(array_map('trim', explode("\n", $response)));
        self::aag_debug_log("Parsed " . count($titles) . " titles.");

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
                self::aag_debug_log("Title exists/skipped: $title");
            }
        }
    }

    public function update_schedule()
    {
        wp_clear_scheduled_hook('aag_scheduled_generation');

        $frequency = get_option('aag_schedule_frequency', 'daily');

        if ($frequency === 'custom') {
            $interval = get_option('aag_schedule_interval', 60);
            if ($interval < 15)
                $interval = 15;

            // Check if custom interval schedule exists, if not add it
            // Note: Filters added in __construct will handle 'cron_schedules'
            // But we need to make sure the hook fires.
            // In WP, 'cron_schedules' is filtered when fetching schedules.

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
        // Always add it if custom frequency is selected OR just generally available
        // To be safe, we can check if dynamic interval is set
        $interval = get_option('aag_schedule_interval', 60);
        if ($interval < 15)
            $interval = 15;

        // Dynamic name based on interval
        $schedules["custom_{$interval}_minutes"] = array(
            'interval' => $interval * 60,
            'display' => "Every $interval Minutes"
        );

        return $schedules;
    }

    public function get_queue_items_with_schedule()
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
        $usage_stats = $this->get_usage_stats();

        wp_send_json_success(array(
            'items' => $items,
            'pending_count' => $pending_count,
            'usage' => $usage_stats
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

    public function delete_queue_item()
    {
        check_ajax_referer('aag_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }

        $id = isset($_POST['id']) ? intval($_POST['id']) : 0;

        if ($id <= 0) {
            wp_send_json_error('Invalid ID');
        }

        global $wpdb;
        $deleted = $wpdb->delete($this->table_name, array('id' => $id));

        if ($deleted) {
            wp_send_json_success('Item deleted successfully');
        } else {
            wp_send_json_error('Failed to delete item or item not found');
        }
    }

    public function call_gemini_api($prompt, $api_key, $min_length = 100)
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

        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);
        $result = json_decode($response_body, true);

        // Debug logging
        error_log('AAG: Gemini Response (Code: ' . $response_code . '): ' . print_r($result, true));

        // Check for Quota Exceeded (HTTP 429)
        if ($response_code === 429) {
            return new WP_Error('quota_exceeded', 'Gemini API Daily Quota Exceeded. Please try again tomorrow or use a different API key.');
        }

        // Check for API errors
        if (isset($result['error'])) {
            $error_msg = isset($result['error']['message']) ? $result['error']['message'] : 'Unknown Gemini API Error';
            $error_status = isset($result['error']['status']) ? $result['error']['status'] : '';

            if ($error_status === 'RESOURCE_EXHAUSTED') {
                return new WP_Error('quota_exceeded', 'Gemini API Daily Quota Exceeded. Please try again tomorrow or use a different API key.');
            }

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

    public function get_freepik_image($search_query, $api_key, $item_title = '')
    {
        $this->aag_debug_log("Freepik: Searching for '$search_query'");

        $url = "https://api.freepik.com/v1/resources?term=" . urlencode($search_query) . "&limit=1";

        $response = wp_remote_get($url, array(
            'headers' => array(
                'Accept' => 'application/json',
                'x-freepik-api-key' => $api_key
            ),
            'timeout' => 30
        ));

        if (is_wp_error($response)) {
            $this->aag_debug_log("Freepik: API Request Failed: " . $response->get_error_message());
            return $response;
        }

        $response_code = wp_remote_retrieve_response_code($response);
        $body_raw = wp_remote_retrieve_body($response);
        $body = json_decode($body_raw, true);

        if ($response_code !== 200) {
            $this->aag_debug_log("Freepik: API error (Code: $response_code): " . $body_raw);
            return new WP_Error('freepik_error', 'Freepik API error: ' . $response_code);
        }

        // Try both paths as they might vary by API version/resource type
        $image_url = null;
        if (!empty($body['data'][0]['image']['source']['url'])) {
            $image_url = $body['data'][0]['image']['source']['url'];
        } elseif (!empty($body['data'][0]['image']['url'])) {
            $image_url = $body['data'][0]['image']['url'];
        }

        if (!$image_url) {
            $this->aag_debug_log("Freepik: No image URL found in response: " . substr($body_raw, 0, 500));
            return new WP_Error('no_image', 'No images found from Freepik');
        }

        $this->aag_debug_log("Freepik: Image URL found: $image_url");

        // Download image to WordPress media library
        require_once(ABSPATH . 'wp-admin/includes/media.php');
        require_once(ABSPATH . 'wp-admin/includes/file.php');
        require_once(ABSPATH . 'wp-admin/includes/image.php');

        $tmp = download_url($image_url);

        if (is_wp_error($tmp)) {
            $this->aag_debug_log("Freepik: Download Failed: " . $tmp->get_error_message());
            return $tmp;
        }

        $filename = !empty($item_title) ? sanitize_title($item_title) : 'freepik-' . time();
        $file_array = array(
            'name' => $filename . '.jpg',
            'tmp_name' => $tmp
        );

        $image_id = media_handle_sideload($file_array, 0);

        if (is_wp_error($image_id)) {
            $this->aag_debug_log("Freepik: Sideload Failed: " . $image_id->get_error_message());
            @unlink($file_array['tmp_name']);
            return $image_id;
        }

        // Update the attachment title to match the item title if provided
        if (!empty($item_title)) {
            wp_update_post(array(
                'ID' => $image_id,
                'post_title' => $item_title
            ));
        }

        $this->aag_debug_log("Freepik: Successfully attached image ID $image_id");
        return $image_id;
    }

    /**
     * Generate and apply SEO metadata for an image.
     */
    public function generate_image_seo_metadata($attachment_id)
    {
        $api_key = get_option('aag_gemini_api_key');
        if (empty($api_key)) {
            return;
        }

        $file_path = get_attached_file($attachment_id);
        if (!$file_path || !file_exists($file_path)) {
            return;
        }

        $prompt = "Analyze this image and provide:
1. A concise, SEO-friendly Title (max 60 characters).
2. A concise, SEO-friendly ALT text (max 125 characters).
3. A descriptive caption (max 200 characters).

Return ONLY a valid JSON object with keys 'title', 'alt' and 'caption'. No other text.";

        $response = $this->call_gemini_vision_api($prompt, $file_path, $api_key);

        if (is_wp_error($response)) {
            error_log('AAG SEO Gen Error: ' . $response->get_error_message());
            return;
        }

        $json_text = preg_replace('/^```json\s*|\s*```$/i', '', trim($response));
        $data = json_decode($json_text, true);

        if (is_array($data)) {
            $title = sanitize_text_field($data['title'] ?? '');
            $alt = sanitize_text_field($data['alt'] ?? '');
            $caption = sanitize_text_field($data['caption'] ?? '');

            $update_data = array('ID' => $attachment_id);
            if (!empty($title)) {
                $update_data['post_title'] = $title;
            }
            if (!empty($caption)) {
                $update_data['post_excerpt'] = $caption;
            }

            if (count($update_data) > 1) {
                wp_update_post($update_data);
            }

            if (!empty($alt)) {
                update_post_meta($attachment_id, '_wp_attachment_image_alt', $alt);
            }
        }
    }

    /**
     * Call Gemini Vision API to analyze images.
     */
    public function call_gemini_vision_api($prompt, $image_path, $api_key)
    {
        if (!file_exists($image_path)) {
            return new WP_Error('file_not_found', 'Image file not found: ' . $image_path);
        }

        $image_data = base64_encode(file_get_contents($image_path));
        $mime_type = wp_check_filetype($image_path)['type'] ?: 'image/jpeg';

        $url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent?key={$api_key}";

        $body = json_encode(array(
            'contents' => array(
                array(
                    'parts' => array(
                        array('text' => $prompt),
                        array(
                            'inline_data' => array(
                                'mime_type' => $mime_type,
                                'data' => $image_data
                            )
                        )
                    )
                )
            ),
            'generationConfig' => array(
                'temperature' => 0.4,
                'topK' => 1,
                'topP' => 1,
                'maxOutputTokens' => 1024
            )
        ));

        $response = wp_remote_post($url, array(
            'headers' => array('Content-Type' => 'application/json'),
            'body' => $body,
            'timeout' => 60
        ));

        if (is_wp_error($response)) {
            return $response;
        }

        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);
        $result = json_decode($response_body, true);

        if ($response_code !== 200) {
            $error_msg = isset($result['error']['message']) ? $result['error']['message'] : 'Unknown Gemini API Error';
            return new WP_Error('api_error', 'Gemini API Error: ' . $error_msg);
        }

        if (isset($result['candidates'][0]['content']['parts'][0]['text'])) {
            return trim($result['candidates'][0]['content']['parts'][0]['text']);
        }

    }

    /**
     * Converts common Markdown formatting to HTML.
     * Used as a fallback if the AI returns Markdown instead of pure HTML.
     */
    private function convert_markdown_to_html($content)
    {
        // Bold: **text** or __text__ -> <strong>text</strong>
        $content = preg_replace('/\*\*(.*?)\*\*/', '<strong>$1</strong>', $content);
        $content = preg_replace('/__(.*?)__/', '<strong>$1</strong>', $content);

        // Italic: *text* or _text_ -> <em>text</em>
        $content = preg_replace('/\*(.*?)\*/', '<em>$1</em>', $content);
        $content = preg_replace('/_(.*?)_/', '<em>$1</em>', $content);

        // Headings: ### Title -> <h3>Title</h3>
        $content = preg_replace('/^### (.*?)$/m', '<h3>$1</h3>', $content);
        $content = preg_replace('/^## (.*?)$/m', '<h2>$1</h2>', $content);
        $content = preg_replace('/^# (.*?)$/m', '<h1>$1</h1>', $content);

        // Unordered Lists: * Item or - Item -> <li>Item</li>
        // (Note: This is a simple conversion, real list tagging is complex without a parser)
        // Only convert if it's at the start of a line and followed by space
        $content = preg_replace('/^[*-] (.*?)$/m', '<li>$1</li>', $content);

        return $content;
    }
}
