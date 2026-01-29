<?php

if (!defined('ABSPATH')) {
    exit;
}

class AAG_Telemetry
{
    private $endpoint = 'https://script.google.com/macros/s/AKfycbzA0CY794MMGcDzTYribEzGD22RH47JfQqMV20BLICjY53XGLz0IZTKpGcdilTudnd8/exec';

    public function __construct()
    {
        add_action('aag_daily_heartbeat', array($this, 'send_heartbeat'));
    }

    /**
     * Send tracking data to the telemetry endpoint
     */
    private function send_ping($action)
    {
        $data = array(
            'action' => $action,
            'url' => get_site_url(),
            'version' => '1.1.0',
            'timestamp' => current_time('mysql'),
            'php_version' => PHP_VERSION,
            'wp_version' => get_bloginfo('version'),
            'is_premium' => get_option('aag_license_key') ? 'yes' : 'no'
        );

        wp_remote_post($this->endpoint, array(
            'method' => 'POST',
            'timeout' => 15,
            'blocking' => false, // Don't slow down the user
            'headers' => array('Content-Type' => 'application/json'),
            'body' => json_encode($data)
        ));
    }

    public function track_activation()
    {
        $this->send_ping('activation');

        // Schedule heartbeat if not already scheduled
        if (!wp_next_scheduled('aag_daily_heartbeat')) {
            wp_schedule_event(time(), 'daily', 'aag_daily_heartbeat');
        }
    }

    public function track_deactivation()
    {
        $this->send_ping('deactivation');
        wp_clear_scheduled_hook('aag_daily_heartbeat');
    }

    public function send_heartbeat()
    {
        $this->send_ping('heartbeat');
    }
}
