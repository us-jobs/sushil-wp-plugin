<?php
/**
 * Plugin Name: RankReady AI
 * Description: Automatically writes SEO-optimized articles with featured images from Freepik
 * Version: 1.1.0
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
require_once AAG_PLUGIN_DIR . 'includes/class-aag-generator.php';
require_once AAG_PLUGIN_DIR . 'includes/class-aag-settings.php';

// Initialize Plugin
function aag_init()
{
    $license = new AAG_License();
    $generator = new AAG_Generator($license);
    $settings = new AAG_Settings($generator, $license);

    return $generator;
}

$aag_generator = aag_init();

// Register Activation and Deactivation Hooks
register_activation_hook(__FILE__, array($aag_generator, 'activate'));
register_deactivation_hook(__FILE__, array($aag_generator, 'deactivate'));
