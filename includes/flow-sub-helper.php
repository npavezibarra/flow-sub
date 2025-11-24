<?php
/**
 * Helper functions for Flow Sub
 *
 * @package FlowSub
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Helper function to check if the current user has an active Flow subscription.
 * Uses Transients to cache the result for performance.
 *
 * @param int $user_id The ID of the user to check.
 * @return bool True if the user has a Flow subscription with status '1' (Active), False otherwise.
 */
function flow_sub_is_user_active($user_id)
{
    if (!$user_id) {
        return false;
    }

    // 1. Check Transient Cache
    $transient_key = 'flow_user_active_' . $user_id;
    $cached_status = get_transient($transient_key);

    if (false !== $cached_status) {
        return (bool) $cached_status;
    }

    // 2. Get User Subscriptions from Meta
    $subscriptions = get_user_meta($user_id, 'flow_user_subscriptions', true);

    if (empty($subscriptions) || !is_array($subscriptions)) {
        // No subscriptions found, cache as false
        set_transient($transient_key, 0, HOUR_IN_SECONDS);
        return false;
    }

    // 3. Initialize API
    $api_key = get_option('flow_sub_api_key');
    $secret_key = get_option('flow_sub_secret_key');

    if (!$api_key || !$secret_key) {
        return false; // Cannot check without keys
    }

    if (!class_exists('Flow_Sub_API')) {
        require_once FLOW_SUB_PATH . 'includes/class-flow-sub-api.php';
    }

    $api = new Flow_Sub_API($api_key, $secret_key);
    $is_active = false;

    // 4. Check each subscription status via API
    foreach ($subscriptions as $subscription_id) {
        $response = $api->get_subscription($subscription_id);

        if (is_wp_error($response)) {
            continue; // Skip on error
        }

        // Check if status is 1 (Active)
        if (isset($response['status']) && 1 === (int) $response['status']) {
            $is_active = true;
            break; // Found an active one, no need to check others
        }
    }

    // 5. Cache the result
    // Store 1 for true, 0 for false
    set_transient($transient_key, $is_active ? 1 : 0, HOUR_IN_SECONDS);

    return $is_active;
}
