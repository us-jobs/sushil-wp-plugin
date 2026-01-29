<?php
/**
 * Plugin Name: RankReady AI
 * Description: Automatically writes SEO-optimized articles with featured images from Freepik
 * Version: 1.1.0
 * Requires at least: 5.0
 * Tested up to: 6.9
 * Author: Sushil M.
 * License: GPL v2 or later
 * Text Domain: rankready-ai
 */

if (!defined('ABSPATH')) {
    exit;
}

// Define Constants
define('AAG_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('AAG_PLUGIN_URL', plugin_dir_url(__FILE__));

// Include Classes
require_once AAG_PLUGIN_DIR . 'includes/class-aag-license.php';
require_once AAG_PLUGIN_DIR . 'includes/class-aag-telemetry.php';
require_once AAG_PLUGIN_DIR . 'includes/class-aag-generator.php';
require_once AAG_PLUGIN_DIR . 'includes/class-aag-settings.php';
require_once AAG_PLUGIN_DIR . 'includes/class-aag-linking.php';
require_once AAG_PLUGIN_DIR . 'includes/class-aag-image-seo.php';
require_once AAG_PLUGIN_DIR . 'includes/class-aag-content-gap.php';
require_once AAG_PLUGIN_DIR . 'includes/class-aag-refresher.php';

// Initialize Plugin
function aag_init()
{
    $license = new AAG_License();
    $telemetry = new AAG_Telemetry();
    $generator = new AAG_Generator($license, $telemetry);
    $image_seo = new AAG_Image_SEO($generator);
    $content_gap = new AAG_Content_Gap($generator);
    $refresher = new AAG_Refresher($generator);
    $settings = new AAG_Settings($generator, $license, $refresher);

    return $generator;
}

$aag_generator = aag_init();

// Register Activation and Deactivation Hooks
register_activation_hook(__FILE__, array($aag_generator, 'activate'));
register_deactivation_hook(__FILE__, array($aag_generator, 'deactivate'));
