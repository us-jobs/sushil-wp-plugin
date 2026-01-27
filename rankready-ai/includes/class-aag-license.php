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
        add_action('wp_ajax_aag_submit_upi_claim', array($this, 'submit_upi_claim_ajax'));
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
        $url = add_query_arg(array(
            'action' => 'activate',
            'key' => $key,
            'domain' => $_SERVER['HTTP_HOST']
        ), $this->api_url);

        $response = wp_remote_get($url);

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

    /**
     * Handle UPI Claim Submission
     */
    public function submit_upi_claim_ajax()
    {
        check_ajax_referer('aag_nonce', 'nonce');

        $email = sanitize_email($_POST['email']);
        $utr = sanitize_text_field($_POST['utr']);
        $domain = $_SERVER['HTTP_HOST'];

        if (empty($email) || empty($utr)) {
            wp_send_json_error('Please provide both email and Transaction ID (UTR).');
        }

        // Send to Remote Server for notification
        $remote_url = 'https://thefashionmart.org/aag-server/upi_handler.php';
        $response = wp_remote_post($remote_url, array(
            'body' => array(
                'email' => $email,
                'utr' => $utr,
                'domain' => $domain
            )
        ));

        if (is_wp_error($response)) {
            wp_send_json_error('Connection failed. Please try again later.');
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if ($data && isset($data['success']) && $data['success']) {
            wp_send_json_success('Request submitted! SUSHIL will verify and send your key to ' . $email . ' within 24 hours.');
        } else {
            $msg = isset($data['message']) ? $data['message'] : 'Failed to submit request.';
            wp_send_json_error($msg);
        }
    }
}
