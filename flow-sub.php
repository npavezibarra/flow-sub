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

	// Include WooCommerce integration if WooCommerce is active.
	if (class_exists('WooCommerce')) {
		require_once FLOW_SUB_PATH . 'includes/class-flow-sub-woocommerce.php';
		new Flow_Sub_WooCommerce();
	}

	// Instantiate the admin class.
	if (is_admin()) {
		new Flow_Sub_Admin();
	}

	// Include Flow Post Setup
	require_once FLOW_SUB_PATH . 'flow-post-module/class-flow-post-setup.php';
}
add_action('plugins_loaded', 'flow_sub_init');

/**
 * Plugin Activation Hook: Adds the custom subscriber role and capabilities.
 */
function flow_post_add_roles()
{
	// 1. Define the base Subscriber role capabilities (mostly 'read')
	$subscriber_role = get_role('subscriber');
	$subscriber_caps = $subscriber_role ? $subscriber_role->capabilities : [];

	// 2. Define the capabilities needed to view Flow Posts (for Subscribers)
	$flow_subscriber_caps = [
		'read' => true, // Must be able to read
		'read_flow_post' => true, // Required to view a single Flow Post
		'edit_flow_posts' => false, // Can't edit or create posts
		'read_private_flow_posts' => true, // Can read Flow posts marked as private
		// Custom capabilities needed for commenting:
		'edit_comment' => true, // Allows commenting/editing their own comment
		'moderate_comments' => false,
	];

	// 3. Create the new role by merging subscriber caps with flow caps
	$role_caps = array_merge($subscriber_caps, $flow_subscriber_caps);

	add_role(
		'flow_subscriber',
		__('Flow Subscriber', 'flow-sub'),
		$role_caps
	);

	// 4. Grant Admin all these new capabilities as well (FULL ACCESS)
	$admin_role = get_role('administrator');
	if ($admin_role) {
		$admin_caps = [
			'edit_flow_post',
			'read_flow_post',
			'delete_flow_post',
			'edit_flow_posts',
			'edit_others_flow_posts',
			'publish_flow_posts',
			'read_private_flow_posts',
			'create_posts', // Just in case
		];
		foreach ($admin_caps as $cap) {
			$admin_role->add_cap($cap, true);
		}
	}
}
register_activation_hook(__FILE__, 'flow_post_add_roles');
// Temporary: Force role update on admin init to fix missing caps for existing installs
add_action('admin_init', 'flow_post_add_roles');

/**
 * Plugin Deactivation Hook: Removes the custom role (cleanup).
 */
function flow_post_remove_roles()
{
	remove_role('flow_subscriber');

	// Cleanup Admin capabilities
	$admin_role = get_role('administrator');
	if ($admin_role) {
		$caps_to_remove = [
			'read_flow_post',
			'edit_flow_posts',
			'read_private_flow_posts',
		];
		foreach ($caps_to_remove as $cap) {
			$admin_role->remove_cap($cap);
		}
	}
}
register_deactivation_hook(__FILE__, 'flow_post_remove_roles');

