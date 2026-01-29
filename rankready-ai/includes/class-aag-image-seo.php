<?php

if (!defined('ABSPATH')) {
    exit;
}

class AAG_Image_SEO
{
    private $generator;

    public function __construct($generator)
    {
        $this->generator = $generator;

        // Hooks
        add_action('wp_ajax_aag_scan_images', array($this, 'ajax_scan_images'));
        add_action('wp_ajax_aag_generate_image_seo', array($this, 'ajax_generate_image_seo'));
    }

    /**
     * AJAX handler to scan for images missing ALT text.
     */
    public function ajax_scan_images()
    {
        error_log('AAG: ajax_scan_images called');
        check_ajax_referer('aag_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }

        $images = $this->get_images_missing_alt(20);

        if (empty($images)) {
            wp_send_json_success(array('images' => array(), 'message' => 'No images missing ALT text found.'));
        }

        wp_send_json_success(array('images' => $images));
    }

    /**
     * AJAX handler to generate SEO for a specific image.
     */
    public function ajax_generate_image_seo()
    {
        check_ajax_referer('aag_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }

        $attachment_id = isset($_POST['attachment_id']) ? intval($_POST['attachment_id']) : 0;

        if (!$attachment_id) {
            wp_send_json_error('Invalid Attachment ID');
        }

        $result = $this->process_image_seo($attachment_id);

        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }

        wp_send_json_success($result);
    }

    /**
     * Get images from media library that are missing ALT text.
     */
    private function get_images_missing_alt($limit = 20)
    {
        $args = array(
            'post_type' => 'attachment',
            'post_mime_type' => 'image',
            'post_status' => 'inherit',
            'posts_per_page' => $limit,
            'meta_query' => array(
                'relation' => 'OR',
                array(
                    'key' => '_wp_attachment_image_alt',
                    'compare' => 'NOT EXISTS',
                ),
                array(
                    'key' => '_wp_attachment_image_alt',
                    'value' => '',
                    'compare' => '=',
                ),
            ),
        );

        $query = new WP_Query($args);
        $images = array();

        if ($query->have_posts()) {
            foreach ($query->posts as $post) {
                $images[] = array(
                    'id' => $post->ID,
                    'title' => $post->post_title,
                    'url' => wp_get_attachment_thumb_url($post->ID),
                    'full_url' => wp_get_attachment_url($post->ID),
                );
            }
        }

        return $images;
    }

    /**
     * Process image SEO using Gemini Vision.
     */
    private function process_image_seo($attachment_id)
    {
        $api_key = get_option('aag_gemini_api_key');
        if (empty($api_key)) {
            return new WP_Error('missing_key', 'Gemini API key not configured.');
        }

        $file_path = get_attached_file($attachment_id);
        if (!$file_path || !file_exists($file_path)) {
            return new WP_Error('file_not_found', 'Image file not found on server.');
        }

        $prompt = "Analyze this image and provide:
1. A concise, SEO-friendly Title (max 60 characters).
2. A concise, SEO-friendly ALT text (max 125 characters).
3. A descriptive caption (max 200 characters).

Return ONLY a valid JSON object with keys 'title', 'alt' and 'caption'. No other text.";

        $response = $this->generator->call_gemini_vision_api($prompt, $file_path, $api_key);

        if (is_wp_error($response)) {
            return $response;
        }

        // Clean up response if it has blocks
        $json_text = preg_replace('/^```json\s*|\s*```$/i', '', trim($response));
        $data = json_decode($json_text, true);

        if (!is_array($data) || (!isset($data['alt']) && !isset($data['title']))) {
            return new WP_Error('invalid_ai_response', 'AI returned an invalid response format.');
        }

        $title = sanitize_text_field($data['title'] ?? '');
        $alt = sanitize_text_field($data['alt'] ?? '');
        $caption = sanitize_text_field($data['caption'] ?? '');

        // Update post data (Title and Caption)
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

        // Update ALT text
        if (!empty($alt)) {
            update_post_meta($attachment_id, '_wp_attachment_image_alt', $alt);
        }

        return array(
            'attachment_id' => $attachment_id,
            'title' => $title,
            'alt' => $alt,
            'caption' => $caption,
            'message' => 'SEO metadata updated successfully!'
        );
    }
}
