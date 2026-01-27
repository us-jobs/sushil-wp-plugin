<?php

if (!defined('ABSPATH')) {
    exit;
}

class AAG_Settings
{
    private $generator;
    private $license;

    public function __construct($generator, $license)
    {
        $this->generator = $generator;
        $this->license = $license;

        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));

        // Ajax actions
        add_action('wp_ajax_aag_save_settings', array($this, 'save_settings'));
        add_action('wp_ajax_aag_save_method1', array($this, 'save_method1'));
        add_action('wp_ajax_aag_save_method2', array($this, 'save_method2'));
        add_action('wp_ajax_aag_save_requirements', array($this, 'save_requirements'));
        add_action('wp_ajax_aag_test_freepik', array($this, 'test_freepik'));
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

        // Updated paths for assets
        wp_enqueue_style('aag-admin-style', plugin_dir_url(dirname(__FILE__)) . 'assets/css/admin.css', array(), '1.6.0');
        wp_enqueue_script('aag-admin-script', plugin_dir_url(dirname(__FILE__)) . 'assets/js/admin.js', array('jquery'), time(), true);

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

        // Use generator methods
        $queue_items = $this->generator->get_queue_items_with_schedule();
        $pending_count = $wpdb->get_var("SELECT COUNT(*) FROM {$this->generator->table_name} WHERE status = 'pending'");

        $usage_stats = $this->generator->get_usage_stats();
        $trial_active = $this->generator->is_trial_active();
        $trial_days_left = $this->generator->get_trial_days_left();

        $is_premium = $this->license->is_premium();
        $license_key = $this->license->get_license_key();

        // Include template
        require_once plugin_dir_path(dirname(__FILE__)) . 'templates/admin-dashboard.php';
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
        $this->generator->update_schedule();

        // Ensure queue is populated for Method 1 based on full title list
        if ($gen_method === 'method1') {
            $this->generator->enqueue_method1_titles_to_queue();
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
            if (!$this->license->is_premium() && !$this->generator->is_trial_active()) {
                wp_send_json_error('Your trial has expired. Please upgrade to continue.');
                return;
            }

            /*
            if (!$this->generator->check_daily_limit()) {
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
                $this->generator->enqueue_method1_titles_to_queue();
            } else {
                $this->generator->populate_queue_if_needed(true); // Force populate even if queue has items
            }

            // Check if queue has pending items
            global $wpdb;
            $queue_count = $wpdb->get_var("SELECT COUNT(*) FROM {$this->generator->table_name} WHERE status = 'pending'");

            if ($queue_count == 0) {
                wp_send_json_error('No articles in queue. Please check your generation method settings.');
                return;
            }

            // Process one item immediately
            $result = $this->generator->process_queue_item_internal();

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

        $added = $this->generator->enqueue_method1_titles_to_queue();

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

    public function test_freepik()
    {
        check_ajax_referer('aag_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }

        $api_key = isset($_POST['api_key']) ? sanitize_text_field($_POST['api_key']) : '';
        if (empty($api_key)) {
            wp_send_json_error('Please enter an API key first.');
        }

        // Use a generic search term for testing
        $result = $this->generator->get_freepik_image('Business Technology', $api_key);

        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }

        wp_send_json_success('Connection Successful! Image found and downloaded (ID: ' . $result . '). You can check your Media Library.');
    }
}
