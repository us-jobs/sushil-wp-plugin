<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class AAG_Content_Gap
 * Handles competitor content gap analysis using AI.
 */
class AAG_Content_Gap
{
    private $generator;

    /**
     * Constructor.
     * 
     * @param AAG_Generator $generator
     */
    public function __construct($generator)
    {
        $this->generator = $generator;
        $this->init_hooks();
    }

    /**
     * Register AJAX hooks.
     */
    public function init_hooks()
    {
        add_action('wp_ajax_aag_analyze_content_gap', array($this, 'ajax_analyze_content_gap'));
        add_action('wp_ajax_aag_add_gap_suggestion', array($this, 'ajax_add_gap_suggestion'));
    }

    /**
     * AJAX handler to analyze content gap.
     */
    public function ajax_analyze_content_gap()
    {
        error_log('AAG: ajax_analyze_content_gap called');
        check_ajax_referer('aag_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            error_log('AAG: Unauthorized access to content gap');
            wp_send_json_error('Unauthorized');
        }

        $keyword = isset($_POST['keyword']) ? sanitize_text_field($_POST['keyword']) : '';
        $urls = isset($_POST['urls']) ? $_POST['urls'] : array();

        error_log('AAG: Keyword: ' . $keyword);
        error_log('AAG: URLs: ' . print_r($urls, true));

        if (empty($keyword)) {
            wp_send_json_error('Keyword is required.');
        }

        $api_key = get_option('aag_gemini_api_key', '');
        if (empty($api_key)) {
            error_log('AAG: Gemini API key missing');
            wp_send_json_error('Gemini API key is missing. Please configure it in Article Requirements.');
        }

        // Clean and validate URLs
        $valid_urls = array();
        if (is_array($urls)) {
            foreach ($urls as $url) {
                $url = esc_url_raw($url);
                if (!empty($url)) {
                    $valid_urls[] = $url;
                }
            }
        }

        $prompt = $this->build_analysis_prompt($keyword, $valid_urls);
        error_log('AAG: Calling Gemini for content gap...');
        $response = $this->generator->call_gemini_api($prompt, $api_key, 0);

        if (is_wp_error($response)) {
            error_log('AAG: Gemini API error: ' . $response->get_error_message());
            wp_send_json_error('AI Analysis failed: ' . $response->get_error_message());
        }

        error_log('AAG: Gemini response received: ' . substr($response, 0, 100) . '...');

        // Parse JSON from AI response
        $clean_response = preg_replace('/```json\s*|\s*```/', '', $response);
        $data = json_decode($clean_response, true);

        if (!$data) {
            error_log('AAG: Failed to parse JSON. Raw response: ' . $response);
            wp_send_json_error('Failed to parse AI response.');
        }

        if (isset($data['error'])) {
            error_log('AAG: Niche mismatch: ' . $data['error']);
            wp_send_json_error($data['error']);
        }

        if (!isset($data['suggestions'])) {
            wp_send_json_error('No suggestions found in AI response.');
        }

        error_log('AAG: Analysis successful. Returning ' . count($data['suggestions']) . ' suggestions');
        wp_send_json_success(array('suggestions' => $data['suggestions']));
    }

    /**
     * AJAX handler to add a suggestion to the queue.
     */
    public function ajax_add_gap_suggestion()
    {
        check_ajax_referer('aag_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }

        $title = isset($_POST['title']) ? sanitize_text_field($_POST['title']) : '';

        if (empty($title)) {
            wp_send_json_error('Title is required.');
        }

        $current_titles = get_option('aag_method1_titles', '');
        $new_titles = empty($current_titles) ? $title : $current_titles . "\n" . $title;

        update_option('aag_method1_titles', $new_titles);

        wp_send_json_success('Added to Title List and Queue!');
    }

    /**
     * Build the prompt for Gemini.
     */
    private function build_analysis_prompt($keyword, $urls)
    {
        $site_name = get_bloginfo('name');
        $site_url = home_url();
        $url_list = !empty($urls) ? implode("\n- ", $urls) : "No specific competitor URLs provided.";

        return "You are an SEO expert. I want to rank for the keyword: '{$keyword}' for my website.
        
        Current Website: {$site_name} ({$site_url})
        Target Keyword: {$keyword}
        Competitor URLs:
        - {$url_list}
        
        TASK:
        1. FIRST, check if the Target Keyword or Competitor URLs are relevant to the niche of '{$site_name}'. 
        2. If the inputs are totally unrelated to the niche of {$site_name} (e.g., if the site is about chess and the inputs are about 'hello world' or 'cooking' or something completely different), you MUST return an error message.
        3. If relevant, identify 5 content gaps or specific sub-topics/articles I should write to beat competitors.
        
        RESPONSE FORMAT:
        You must return ONLY a JSON object.
        If NOT relevant, return:
        {
            \"error\": \"these keywords or competitor urls are not related to your blog site: {$site_name}\"
        }
        
        If relevant, return:
        {
            \"suggestions\": [
                {
                    \"title\": \"Proposed Article Title\",
                    \"reason\": \"Brief explanation of why this fills a gap\",
                    \"priority\": \"High\"
                },
                ...
            ]
        }";
    }
}
