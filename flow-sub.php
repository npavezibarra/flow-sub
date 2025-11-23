<?php
/**
 * Plugin Name: Flow Sub
 * Plugin URI:  https://example.com/flow-sub
 * Description: A custom WordPress plugin for subscription management.
 * Version:     1.0.0
 * Author:      Your Name
 * Author URI:  https://example.com
 * Text Domain: flow-sub
 * Domain Path: /languages
 */

if (!defined('ABSPATH')) {
	exit; // Exit if accessed directly.
}

// Define plugin constants.
define('FLOW_SUB_VERSION', '1.0.0');
define('FLOW_SUB_PATH', plugin_dir_path(__FILE__));
define('FLOW_SUB_URL', plugin_dir_url(__FILE__));

/**
 * Initialize the plugin.
 */
function flow_sub_init()
{
	// Include the admin class.
	require_once FLOW_SUB_PATH . 'includes/class-flow-sub-admin.php';

	// Include the shortcode class.
	require_once FLOW_SUB_PATH . 'includes/class-flow-sub-shortcode.php';
	new Flow_Sub_Shortcode();

	// Instantiate the admin class.
	if (is_admin()) {
		new Flow_Sub_Admin();
	}
}
add_action('plugins_loaded', 'flow_sub_init');
