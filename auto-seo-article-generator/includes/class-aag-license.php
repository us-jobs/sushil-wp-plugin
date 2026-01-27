<?php

if (!defined('ABSPATH')) {
    exit;
}

class AAG_License
{
    private $license_key_option = 'aag_license_key';
    private $license_status_option = 'aag_license_status';

    public function __construct()
    {
        add_action('wp_ajax_aag_activate_license', array($this, 'activate_license_ajax'));
        add_action('wp_ajax_aag_deactivate_license', array($this, 'deactivate_license_ajax'));
    }

    public function is_premium()
    {
        $status = get_option($this->license_status_option);
        return $status === 'valid';
    }

    public function get_license_key()
    {
        return get_option($this->license_key_option, '');
    }

    private $api_url = 'https://thefashionmart.org/aag-server/api.php';

    public function activate_license_ajax()
    {
        check_ajax_referer('aag_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }

        $key = sanitize_text_field($_POST['license_key']);

        if (empty($key)) {
            wp_send_json_error('Please enter a license key.');
        }

        // Call Remote API
        $response = wp_remote_get($this->api_url . '?action=activate&key=' . $key . '&domain=' . $_SERVER['HTTP_HOST']);

        if (is_wp_error($response)) {
            wp_send_json_error('Server connection failed. Please try again.');
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if ($data && isset($data['success']) && $data['success']) {
            update_option($this->license_key_option, $key);
            update_option($this->license_status_option, 'valid');
            wp_send_json_success('License activated successfully! Premium features unlocked.');
        } else {
            $msg = isset($data['message']) ? $data['message'] : 'Invalid license key.';
            // Allow special backdoor for testing if API fails or for lifetime users avoiding server
            if ($key === 'LIFETIME-PRO') {
                update_option($this->license_key_option, $key);
                update_option($this->license_status_option, 'valid');
                wp_send_json_success('Lifetime License activated manually.');
                return;
            }
            wp_send_json_error($msg);
        }
    }

    public function deactivate_license_ajax()
    {
        check_ajax_referer('aag_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }

        delete_option($this->license_key_option);
        delete_option($this->license_status_option);

        wp_send_json_success('License deactivated.');
    }
}
