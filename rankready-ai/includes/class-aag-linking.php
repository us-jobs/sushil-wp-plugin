<?php

if (!defined('ABSPATH')) {
    exit;
}

class AAG_Linking
{
    private $generator;

    public function __construct($generator)
    {
        $this->generator = $generator;

        // Hooks
        add_action('wp_ajax_aag_get_internal_links', array($this, 'get_internal_link_suggestions'));
        add_action('enqueue_block_editor_assets', array($this, 'enqueue_editor_assets'));
    }

    /**
     * Enqueue assets for the Gutenberg editor.
     */
    public function enqueue_editor_assets()
    {
        wp_enqueue_script(
            'aag-linking-script',
            AAG_PLUGIN_URL . 'assets/js/linking.js',
            array('wp-plugins', 'wp-edit-post', 'wp-element', 'wp-components', 'wp-data', 'jquery'),
            time(),
            true
        );

        wp_localize_script('aag-linking-script', 'aagLinking', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('aag_nonce')
        ));
    }

    /**
     * AJAX handler to get internal link suggestions.
     */
    public function get_internal_link_suggestions()
    {
        check_ajax_referer('aag_nonce', 'nonce');

        if (!current_user_can('edit_posts')) {
            wp_send_json_error('Unauthorized');
        }

        $content = isset($_POST['content']) ? wp_kses_post($_POST['content']) : '';

        if (empty($content)) {
            wp_send_json_error('No content provided');
        }

        // Get existing posts
        $existing_posts = $this->get_existing_posts();

        if (empty($existing_posts)) {
            wp_send_json_error('No existing posts found for linking.');
        }

        // Call Gemini
        $suggestions = $this->fetch_suggestions_from_ai($content, $existing_posts);

        if (is_wp_error($suggestions)) {
            wp_send_json_error($suggestions->get_error_message());
        }

        wp_send_json_success($suggestions);
    }

    /**
     * Fetch suggestions from Gemini AI.
     */
    private function fetch_suggestions_from_ai($content, $posts)
    {
        $api_key = get_option('aag_gemini_api_key');
        if (empty($api_key)) {
            return new WP_Error('missing_key', 'Gemini API key not configured.');
        }

        $post_list = "";
        foreach ($posts as $post) {
            $post_list .= "- Title: \"{$post['title']}\", URL: {$post['url']}\n";
        }

        $prompt = "You are an SEO expert. Below is the content of a new blog post.\n\n" .
            "CONENT:\n{$content}\n\n" .
            "Here is a list of existing blog posts on the same website:\n{$post_list}\n\n" .
            "Based on the content of the new post, suggest 3-5 existing posts that would be highly relevant to link to. " .
            "For each suggestion, provide the title and the URL exactly as provided. " .
            "Return only a valid JSON array of objects with 'title' and 'url' keys. No other text.";

        $response = $this->generator->call_gemini_api($prompt, $api_key, 0);

        if (is_wp_error($response)) {
            return $response;
        }

        // Clean up response if it has block quotes
        $json_text = preg_replace('/^```json\s*|\s*```$/i', '', trim($response));
        $data = json_decode($json_text, true);

        if (!is_array($data)) {
            error_log('AAG Linking: Invalid JSON from AI: ' . $response);
            return new WP_Error('invalid_ai_response', 'AI returned an invalid response format.');
        }

        return $data;
    }

    /**
     * Get 50 most recent posts for linking.
     */
    private function get_existing_posts()
    {
        $posts = get_posts(array(
            'post_type' => 'post',
            'post_status' => 'publish',
            'numberposts' => 50,
            'orderby' => 'date',
            'order' => 'DESC'
        ));

        $result = array();
        foreach ($posts as $post) {
            $result[] = array(
                'title' => $post->post_title,
                'url' => get_permalink($post->ID)
            );
        }

        return $result;
    }
}
