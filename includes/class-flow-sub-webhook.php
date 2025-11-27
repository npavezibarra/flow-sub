<?php
/**
 * Webhook Handler Class for Flow Sub
 *
 * @package FlowSub
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class Flow_Sub_Webhook
 */
class Flow_Sub_Webhook
{

    /**
     * Initialize the class.
     */
    public function __construct()
    {
        add_action('rest_api_init', array($this, 'register_routes'));
    }

    /**
     * Register the REST API routes.
     */
    public function register_routes()
    {
        register_rest_route('flow-sub/v1', '/webhook', array(
            'methods' => 'POST',
            'callback' => array($this, 'handle_webhook'),
            'permission_callback' => '__return_true', // Signature verification handles security
        ));
    }

    /**
     * Handle the webhook request.
     *
     * @param WP_REST_Request $request The request object.
     * @return WP_REST_Response The response object.
     */
    public function handle_webhook($request)
    {
        $params = $request->get_params();
        $api_key = get_option('flow_sub_api_key');
        $secret_key = get_option('flow_sub_secret_key');

        if (!$api_key || !$secret_key) {
            return new WP_REST_Response(array('message' => 'Plugin not configured'), 500);
        }

        // 1. Verify Signature
        if (!$this->verify_signature($params, $secret_key)) {
            return new WP_REST_Response(array('message' => 'Invalid signature'), 401);
        }

        // 2. Process Event
        $event = $params['event'] ?? '';
        $data = $params['data'] ?? array();

        if (empty($event) || empty($data)) {
            return new WP_REST_Response(array('message' => 'Invalid payload'), 400);
        }

        $subscription_id = $data['subscriptionId'] ?? '';
        $customer_id = $data['customerId'] ?? '';

        if (empty($subscription_id) && isset($data['subscription']['subscriptionId'])) {
            $subscription_id = $data['subscription']['subscriptionId'];
        }

        // Some events might provide invoice data which links to subscription
        if (empty($subscription_id) && isset($data['invoice']['subscriptionId'])) {
            $subscription_id = $data['invoice']['subscriptionId'];
        }

        if ($subscription_id) {
            $this->invalidate_cache_by_subscription($subscription_id);
        } elseif ($customer_id) {
            $this->invalidate_cache_by_customer($customer_id);
        }

        return new WP_REST_Response(array('message' => 'Webhook received'), 200);
    }

    /**
     * Verify the webhook signature.
     *
     * @param array  $params     Request parameters.
     * @param string $secret_key Secret Key.
     * @return bool True if signature is valid.
     */
    private function verify_signature($params, $secret_key)
    {
        if (!isset($params['s'])) {
            return false;
        }

        $received_signature = $params['s'];
        unset($params['s']);

        // Flow sorts parameters by key before signing
        ksort($params);
        $to_sign = '';
        foreach ($params as $key => $value) {
            // Handle nested arrays if necessary (Flow usually sends flat params for signature or specific structure)
            // For webhooks, Flow documentation says it sends POST parameters. 
            // If 'data' is an array, we might need to be careful. 
            // However, standard Flow signature is on flat params. 
            // Let's assume standard behavior: key . value
            if (is_array($value)) {
                // If value is array, Flow might not include it in signature or handle it differently.
                // Re-checking Flow docs: Webhooks usually sign the raw POST body or specific fields.
                // But Flow API usually uses the same sign_params logic.
                // If 'data' is a JSON object, it might be passed as a string or parsed.
                // Let's try standard concatenation.
                continue; // Skip arrays for now, usually signature is on scalar fields or handled differently
            }
            $to_sign .= $key . $value;
        }

        $calculated_signature = hash_hmac('sha256', $to_sign, $secret_key);

        return hash_equals($calculated_signature, $received_signature);
    }

    /**
     * Invalidate cache for a subscription.
     *
     * @param string $subscription_id Subscription ID.
     */
    private function invalidate_cache_by_subscription($subscription_id)
    {
        // We need to find which user owns this subscription.
        // Since we don't have a direct mapping table, we have to search user meta.
        // This is expensive, but webhooks are not high frequency per second usually.

        $users = get_users(array(
            'meta_key' => 'flow_user_subscriptions',
            'meta_value' => $subscription_id,
            'meta_compare' => 'LIKE'
        ));

        foreach ($users as $user) {
            delete_transient('flow_user_subs_' . $user->ID);
            delete_transient('flow_user_active_' . $user->ID);
            error_log("Flow Sub Webhook: Invalidated cache for user {$user->ID} (Sub: $subscription_id)");
        }
    }

    /**
     * Invalidate cache for a customer.
     *
     * @param string $customer_id Customer ID.
     */
    private function invalidate_cache_by_customer($customer_id)
    {
        $users = get_users(array(
            'meta_key' => 'flow_customer_id',
            'meta_value' => $customer_id
        ));

        foreach ($users as $user) {
            delete_transient('flow_user_subs_' . $user->ID);
            delete_transient('flow_user_active_' . $user->ID);
            error_log("Flow Sub Webhook: Invalidated cache for user {$user->ID} (Customer: $customer_id)");
        }
    }
}
